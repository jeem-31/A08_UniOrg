<?php

namespace Tests\Feature;

use App\Models\ApplyOrg;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Mother Org-sourced officer ID lookup (Sub Org flow). */
final class MotherOrgIdLookupEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_lists_mother_org_member_files(): void
    {
        $this->seedMotherOrgWithMembers();

        $response = $this->get(route('apply-org.mother-org-member-files', ['departmentId' => 1]));

        $response->assertOk();
        self::assertTrue($response->json('has_mother_org'));
        self::assertSame('Test Mother Org', $response->json('mother_org_name'));
        self::assertCount(1, $response->json('files'));
        self::assertSame('members.pdf', $response->json('files.0.name'));
    }

    public function test_chunk_parse_then_finish_returns_matches_without_raw_rows(): void
    {
        $this->seedMotherOrgWithMembers();
        $officers = [
            'president' => ['first_name' => 'Juan', 'middle_name' => 'P', 'last_name' => 'Dela Cruz', 'suffix' => ''],
            'secretary' => ['first_name' => 'Juan', 'middle_name' => '', 'last_name' => 'Cruz', 'suffix' => ''],
            'auditor' => ['first_name' => 'Pedro', 'middle_name' => '', 'last_name' => 'Garcia', 'suffix' => ''],
        ];

        $chunk = $this->post(route('apply-org.match-mother-org-ids'), [
            'department_id' => 1,
            'officers' => json_encode($officers),
            'request_key' => 'mother-case-1',
            'file_index' => 0,
            'page_from' => 1,
            'page_size' => 2,
        ]);

        $chunk->assertOk();
        self::assertTrue($chunk->json('success'));
        self::assertSame(3, $chunk->json('pages_total'));
        self::assertSame(2, $chunk->json('pages_done'));
        self::assertFalse($chunk->json('complete'));
        self::assertSame(3, $chunk->json('rows_total'));
        // Privacy contract: chunk responses carry counts, never raw rows.
        self::assertArrayNotHasKey('rows', $chunk->json());
        self::assertArrayNotHasKey('results', $chunk->json());

        $tail = $this->post(route('apply-org.match-mother-org-ids'), [
            'department_id' => 1,
            'officers' => json_encode($officers),
            'request_key' => 'mother-case-1',
            'file_index' => 0,
            'page_from' => 3,
            'page_size' => 2,
        ]);

        $tail->assertOk();
        self::assertTrue($tail->json('complete'));
        self::assertSame(4, $tail->json('rows_total'));

        $finish = $this->post(route('apply-org.match-mother-org-ids'), [
            'department_id' => 1,
            'officers' => json_encode($officers),
            'request_key' => 'mother-case-1',
            'finish' => true,
        ]);

        $finish->assertOk();
        self::assertTrue($finish->json('success'));
        self::assertSame(4, $finish->json('rows_received'));
        self::assertSame('2021-0001', $finish->json('results.president.match.student_id'));
        self::assertNull($finish->json('results.secretary.match'));
        self::assertCount(2, $finish->json('results.secretary.candidates'));
        self::assertNull($finish->json('results.auditor.match'));
        self::assertArrayNotHasKey('rows', $finish->json());
        // Server accumulation is cleared after finish.
        self::assertNull(Cache::get('mother-id-lookup-mother-case-1'));
    }

    public function test_unknown_department_returns_safe_envelopes(): void
    {
        $meta = $this->get(route('apply-org.mother-org-member-files', ['departmentId' => 999]));

        $meta->assertOk();
        self::assertFalse($meta->json('has_mother_org'));
        self::assertSame([], $meta->json('files'));

        $chunk = $this->post(route('apply-org.match-mother-org-ids'), [
            'department_id' => 999,
            'officers' => json_encode([]),
            'request_key' => 'mother-case-unknown',
            'file_index' => 0,
            'page_from' => 1,
            'page_size' => 2,
        ]);

        $chunk->assertOk();
        self::assertFalse($chunk->json('success'));
    }

    private function seedMotherOrgWithMembers(): void
    {
        if (DB::table('departments')->count() < 1) {
            DB::table('departments')->insert([
                ['department_name' => 'College A', 'department_acronym' => 'CA'],
            ]);
        }

        Storage::fake('public');
        Storage::disk('public')->put('mother/members.pdf', self::membersPdf([
            ['LIST OF MEMBERS - MOTHER ORG', '1 | DELA CRUZ, JUAN P. | 2021-0001 | BSIT | 2A', '2 | SANTOS, MARIA C. | 2021-0002 | BSIT | 2A'],
            ['3 | CRUZ, JUAN D. | 2022-0456 | BSCS | 1B'],
            ['4 | REYES, ANA | 2021-0003 | BSBA | 3C'],
        ]));

        Organization::factory()->create([
            'name' => 'Test Mother Org',
            'type' => 'Mother Org',
            'status' => 'Approved',
            'department_id' => 1,
        ]);

        ApplyOrg::factory()->approved()->create([
            'organization_name' => 'Test Mother Org Application',
            'organization_acronym' => 'TMO',
            'organization_type' => 'Mother Org',
            'department_id' => 1,
            'list_of_members' => json_encode([
                ['path' => 'mother/members.pdf', 'disk' => 'public', 'name' => 'members.pdf'],
            ]),
        ]);
    }

    /**
     * Minimal multi-page text PDF (one BT/ET block per line) for fixtures.
     *
     * @param list<list<string>> $pages Lines per page.
     */
    private static function membersPdf(array $pages): string
    {
        $next = 3;
        $kids = [];
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>'];
        $fontNum = null;
        $pageDefs = [];
        foreach ($pages as $lines) {
            $content = '';
            $y = 700;
            foreach ($lines as $line) {
                $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
                $content .= "BT /F1 12 Tf 50 {$y} Td ({$escaped}) Tj ET\n";
                $y -= 20;
            }
            $pageNum = $next++;
            $contentNum = $next++;
            if ($fontNum === null) {
                $fontNum = $next++;
            }
            $objects[$contentNum] = '<< /Length '.strlen($content).' >> stream'."\n".$content.'endstream';
            $pageDefs[$pageNum] = $contentNum;
            $kids[] = "{$pageNum} 0 R";
        }
        foreach ($pageDefs as $pageNum => $contentNum) {
            $objects[$pageNum] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents {$contentNum} 0 R /Resources << /Font << /F1 {$fontNum} 0 R >> >> >>";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        $objects[$fontNum] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj {$body} endobj\n";
        }
        $xrefPos = strlen($pdf);
        $count = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i] ?? 0)."\n";
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }
}
