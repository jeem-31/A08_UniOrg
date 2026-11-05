<?php

namespace Tests\Unit;

use App\Models\ApplyOrg;
use App\Models\ProcessedDocument;
use App\Models\User;
use App\Policies\ProcessedDocumentPolicy;
use App\Services\DocumentIntelligence\SupportedDocumentCatalog;
use Tests\TestCase;

class ProcessedDocumentPolicyTest extends TestCase
{
    private ProcessedDocumentPolicy $policy;

    private ApplyOrg $application;

    private ProcessedDocument $analysis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = app(ProcessedDocumentPolicy::class);
        $this->application = (new ApplyOrg)->forceFill([
            'id' => 10,
            'letter_of_application' => 'applications/current.pdf',
        ]);
        $this->analysis = (new ProcessedDocument)->forceFill([
            'apply_org_id' => 10,
            'document_field' => 'letter_of_application',
            'document_slot' => SupportedDocumentCatalog::PRIMARY_SLOT,
            'file_path' => 'applications/current.pdf',
            'storage_disk' => 'protected',
            'source_version' => 'source-current',
            'source_fingerprint' => str_repeat('a', 64),
            'association_ready_at' => now(),
        ]);
    }

    public function test_osas_admin_can_access_analysis_owned_by_application_and_current_source(): void
    {
        self::assertTrue($this->policy->view($this->user('osas_admin'), $this->analysis, $this->application));
        self::assertTrue($this->policy->viewSource($this->user('osas_admin'), $this->analysis, $this->application));
        self::assertTrue($this->policy->retry($this->user('osas_admin'), $this->analysis, $this->application));
    }

    public function test_non_osas_cross_application_and_stale_source_access_are_denied(): void
    {
        self::assertFalse($this->policy->view($this->user('admin'), $this->analysis, $this->application));

        $otherApplication = (new ApplyOrg)->forceFill(['id' => 11, 'letter_of_application' => 'applications/current.pdf']);
        self::assertFalse($this->policy->view($this->user('osas_admin'), $this->analysis, $otherApplication));

        $this->application->letter_of_application = 'applications/replacement.pdf';
        self::assertFalse($this->policy->view($this->user('osas_admin'), $this->analysis, $this->application));
    }

    private function user(string $role): User
    {
        return (new User)->forceFill(['role' => $role]);
    }
}
