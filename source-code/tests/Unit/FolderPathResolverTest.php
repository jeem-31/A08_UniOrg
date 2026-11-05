<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\DocumentLibrary\AuthorizedFolderRepository;
use App\Services\DocumentLibrary\FolderPathResolver;
use App\Services\DocumentLibrary\LibraryQuery;
use App\Services\DocumentLibrary\LibrarySort;
use App\Services\DocumentLibrary\LibraryUrlBuilder;
use App\Services\DocumentLibrary\OrganizationFolderIdentity;
use App\Services\DocumentLibrary\OrganizationFolderToken;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** **Validates: Requirements 2.5, 2.9, 3.1, 3.2, 3.3, 3.4, 3.5, 3.8, 9.7** */
final class FolderPathResolverTest extends TestCase
{
    private string $organizationsTable;
    private string $applicationsTable;
    private string $entriesTable;
    private string $analysesTable;

    protected function setUp(): void
    {
        parent::setUp();
        $suffix = bin2hex(random_bytes(4));
        $this->organizationsTable = "tfl_{$suffix}_orgs";
        $this->applicationsTable = "tfl_{$suffix}_apps";
        $this->entriesTable = "tfl_{$suffix}_entries";
        $this->analysesTable = "tfl_{$suffix}_analyses";
        DB::beginTransaction();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_folder_tokens_are_opaque_authenticated_and_reject_tampering(): void
    {
        $tokens = app(OrganizationFolderToken::class);
        $identity = OrganizationFolderIdentity::linked(42);
        $token = $tokens->issue($identity);

        self::assertSame($identity->key(), $tokens->verify($token));
        self::assertStringNotContainsString('linked:42', $token);

        $offset = intdiv(strlen($token), 2);
        $tampered = substr_replace($token, $token[$offset] === 'a' ? 'b' : 'a', $offset, 1);
        self::assertNull($tokens->verify($tampered));
        self::assertNull($tokens->verify('not-a-signed-folder-token'));
    }

    public function test_linked_year_path_uses_trusted_labels_and_preserves_canonical_query_in_parent_links(): void
    {
        $applicationId = $this->linkedEntry('Trusted Organization', 'TRUST');
        $this->currentAnalysis($applicationId, '2024-2025');
        $query = new LibraryQuery(
            keyword: 'annual report',
            departments: ['CCS'],
            sort: LibrarySort::FilenameAscending,
            page: 3,
        );
        $token = app(OrganizationFolderToken::class)->issue(OrganizationFolderIdentity::linked(1));

        $resolution = $this->resolver()->resolve(
            $token,
            '2024-2025',
            $query,
            $this->osasUser(),
        );

        self::assertTrue($resolution->found());
        self::assertSame('year', $resolution->level);
        self::assertSame('2024-2025', $resolution->heading());
        self::assertSame(
            ['Document Library', 'Trusted Organization', '2024-2025'],
            array_map(static fn ($crumb): string => $crumb->label, $resolution->breadcrumbs),
        );
        self::assertSame($resolution->breadcrumbs[1]->url, $resolution->parentUrl);
        $this->assertCanonicalQuery($resolution->parentUrl, expectFolder: true);
        $this->assertCanonicalQuery($resolution->rootUrl, expectFolder: false);
    }

    public function test_normalized_legacy_identity_resolves_only_authorized_indexed_applications(): void
    {
        $applicationId = $this->legacyEntry("  Straße\u{00A0}Scholars ", ' Σ Club ');
        $this->currentAnalysis($applicationId, null, processingStatus: 'failed');
        $identity = OrganizationFolderIdentity::legacy('STRASSE scholars', 'σ club');
        $token = app(OrganizationFolderToken::class)->issue($identity);

        $organization = $this->resolver()->resolve(
            $token,
            null,
            new LibraryQuery(keyword: 'keep me', page: 2),
            $this->osasUser(),
        );
        self::assertTrue($organization->found());
        self::assertSame('organization', $organization->level);
        self::assertSame('Straße Scholars', $organization->heading());
        self::assertSame($organization->rootUrl, $organization->parentUrl);

        $unspecified = $this->resolver()->resolve(
            $token,
            'unspecified',
            new LibraryQuery(keyword: 'keep me', page: 2),
            $this->osasUser(),
        );
        self::assertTrue($unspecified->found());
        self::assertSame('Unspecified Year', $unspecified->heading());
    }

    public function test_invalid_unauthorized_and_missing_paths_share_safe_root_recovery(): void
    {
        $this->linkedEntry('Hidden Organization', 'HID');
        $validToken = app(OrganizationFolderToken::class)->issue(OrganizationFolderIdentity::linked(1));
        $query = new LibraryQuery(keyword: 'retained', page: 4);
        $resolver = $this->resolver();

        $invalidToken = $resolver->resolve('invalid', null, $query, $this->osasUser());
        $wrongRole = $resolver->resolve($validToken, null, $query, new User(['role' => 'student']));
        $missingYear = $resolver->resolve($validToken, '2024-2026', $query, $this->osasUser());
        $orphanYear = $resolver->resolve(null, '2024-2025', $query, $this->osasUser());

        foreach ([$invalidToken, $wrongRole, $missingYear, $orphanYear] as $failure) {
            self::assertFalse($failure->found());
            self::assertSame('not_found', $failure->level);
            self::assertSame($invalidToken->error, $failure->error);
            self::assertStringNotContainsString('Hidden Organization', $failure->error);
            parse_str((string) parse_url($failure->rootUrl, PHP_URL_QUERY), $parameters);
            self::assertSame('retained', $parameters['q']);
            self::assertSame('4', (string) $parameters['page']);
            self::assertArrayNotHasKey('folder', $parameters);
        }
    }

    public function test_stale_or_mismatched_analysis_cannot_authorize_a_year_folder(): void
    {
        $applicationId = $this->linkedEntry('Current Source Organization', 'CSO');
        $this->currentAnalysis($applicationId, '2024-2025', outputVersion: 'stale-version');
        $token = app(OrganizationFolderToken::class)->issue(OrganizationFolderIdentity::linked(1));
        $resolver = $this->resolver();

        self::assertFalse($resolver->resolve(
            $token, '2024-2025', new LibraryQuery, $this->osasUser(),
        )->found());
        self::assertTrue($resolver->resolve(
            $token, 'unspecified', new LibraryQuery, $this->osasUser(),
        )->found());
    }

    public function test_application_accreditation_year_authorizes_its_year_folder_without_an_extracted_year(): void
    {
        $applicationId = $this->linkedEntry('Year Authorized Organization', 'YAO');
        DB::table($this->applicationsTable)
            ->where('id', $applicationId)
            ->update(['accreditation_year' => '2025-2026']);
        $token = app(OrganizationFolderToken::class)->issue(OrganizationFolderIdentity::linked(1));
        $resolver = $this->resolver();

        self::assertTrue($resolver->resolve(
            $token, '2025-2026', new LibraryQuery, $this->osasUser(),
        )->found());
        self::assertSame('2025-2026', $resolver->resolve(
            $token, '2025-2026', new LibraryQuery, $this->osasUser(),
        )->heading());
    }

    private function linkedEntry(string $name, string $acronym): int
    {
        $organizationId = DB::table($this->organizationsTable)->insertGetId([
            'name' => $name,
        ]);

        return $this->entry($name, $acronym, $organizationId);
    }

    private function legacyEntry(string $name, string $acronym): int
    {
        return $this->entry($name, $acronym, null);
    }

    private function entry(string $name, string $acronym, ?int $organizationId): int
    {
        $applicationId = DB::table($this->applicationsTable)->insertGetId([
            'organization_id' => $organizationId,
            'organization_name' => $name,
            'organization_acronym' => $acronym,
            'status' => 'Approved',
        ]);
        DB::table($this->entriesTable)->insert([
            'apply_org_id' => $applicationId,
            'document_field' => 'application_form',
            'document_slot' => 'primary',
            'storage_disk' => 'private',
            'file_path' => 'applications/current.pdf',
            'source_version' => 'current-version',
            'source_fingerprint' => str_repeat('a', 64),
        ]);

        return $applicationId;
    }

    private function currentAnalysis(
        int $applicationId,
        ?string $year,
        string $outputVersion = 'current-version',
        string $processingStatus = 'completed',
    ): void {
        DB::table($this->analysesTable)->insert([
            'apply_org_id' => $applicationId,
            'document_field' => 'application_form',
            'document_slot' => 'primary',
            'storage_disk' => 'private',
            'file_path' => 'applications/current.pdf',
            'source_version' => 'current-version',
            'source_fingerprint' => str_repeat('a', 64),
            'output_source_version' => $outputVersion,
            'processing_status' => $processingStatus,
            'key_information' => json_encode(['school_year' => $year], JSON_THROW_ON_ERROR),
        ]);
    }

    private function osasUser(): User
    {
        return new User(['role' => 'osas_admin']);
    }

    private function assertCanonicalQuery(string $url, bool $expectFolder): void
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $parameters);
        self::assertSame('annual report', $parameters['q']);
        self::assertSame(['CCS'], $parameters['department']);
        self::assertSame('filename_asc', $parameters['sort']);
        self::assertSame('3', (string) $parameters['page']);
        self::assertSame($expectFolder, array_key_exists('folder', $parameters));
        self::assertArrayNotHasKey('year_folder', $parameters);
    }

    private function resolver(): FolderPathResolver
    {
        $tokens = app(OrganizationFolderToken::class);
        $folders = new AuthorizedFolderRepository(
            $tokens,
            $this->entriesTable,
            $this->applicationsTable,
            $this->organizationsTable,
            $this->analysesTable,
        );

        return new FolderPathResolver($tokens, $folders, app(LibraryUrlBuilder::class));
    }

    private function createSchema(): void
    {
        Schema::create($this->organizationsTable, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        Schema::create($this->applicationsTable, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('organization_acronym')->nullable();
            $table->string('status')->nullable();
            $table->string('accreditation_year')->nullable();
        });
        Schema::create($this->entriesTable, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('apply_org_id');
            $table->string('document_field');
            $table->string('document_slot');
            $table->string('storage_disk');
            $table->string('file_path');
            $table->string('source_version')->nullable();
            $table->string('source_fingerprint')->nullable();
        });
        Schema::create($this->analysesTable, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('apply_org_id');
            $table->string('document_field');
            $table->string('document_slot');
            $table->string('storage_disk');
            $table->string('file_path');
            $table->string('source_version')->nullable();
            $table->string('source_fingerprint')->nullable();
            $table->string('output_source_version')->nullable();
            $table->string('processing_status');
            $table->json('key_information')->nullable();
        });
    }
}
