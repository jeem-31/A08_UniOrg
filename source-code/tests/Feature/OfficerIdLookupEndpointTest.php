<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Officer student-ID lookup from the List of Members: paginated parse + match. */
final class OfficerIdLookupEndpointTest extends TestCase
{
    public function test_parse_member_rows_paginates_every_page_without_missing_rows(): void
    {
        $pdf = self::membersPdf([
            ['LIST OF MEMBERS - ACME ORG', '1 | DELA CRUZ, JUAN P. | 2021-0001 | BSIT | 2A', '2 | SANTOS, MARIA C. | 2021-0002 | BSIT | 2A'],
            ['3 | CRUZ, JUAN D. | 2022-0456 | BSCS | 1B'],
            ['4 | REYES, ANA | 2021-0003 | BSBA | 3C'],
        ]);
        $file = static fn () => UploadedFile::fake()->createWithContent('members.pdf', $pdf);

        $first = $this->post(route('apply-org.parse-member-rows'), [
            'request_key' => 'lookup-pages-1',
            'page_from' => 1,
            'page_size' => 2,
            'member_file' => $file(),
        ]);

        $first->assertOk();
        self::assertTrue($first->json('success'));
        self::assertSame('lookup-pages-1', $first->json('request_key'));
        self::assertSame(3, $first->json('pages_total'));
        self::assertSame(2, $first->json('pages_done'));
        self::assertFalse($first->json('complete'));
        self::assertSame(
            ['2021-0001', '2021-0002', '2022-0456'],
            array_column($first->json('rows'), 'student_id')
        );

        $second = $this->post(route('apply-org.parse-member-rows'), [
            'request_key' => 'lookup-pages-2',
            'page_from' => 3,
            'page_size' => 2,
            'member_file' => $file(),
        ]);

        $second->assertOk();
        self::assertTrue($second->json('complete'));
        self::assertSame(3, $second->json('pages_done'));
        self::assertSame(['2021-0003'], array_column($second->json('rows'), 'student_id'));

        // Past-the-end ranges finish immediately with no rows.
        $pastEnd = $this->post(route('apply-org.parse-member-rows'), [
            'request_key' => 'lookup-pages-3',
            'page_from' => 9,
            'page_size' => 2,
            'member_file' => $file(),
        ]);

        $pastEnd->assertOk();
        self::assertTrue($pastEnd->json('complete'));
        self::assertSame([], $pastEnd->json('rows'));
    }

    public function test_match_officer_ids_from_accumulated_rows(): void
    {
        $rows = [
            ['student_id' => '2021-0001', 'member_name' => 'DELA CRUZ, JUAN P.', 'raw_line' => '1 | DELA CRUZ, JUAN P. | 2021-0001 | BSIT | 2A'],
            ['student_id' => '2021-0002', 'member_name' => 'SANTOS, MARIA C.', 'raw_line' => '2 | SANTOS, MARIA C. | 2021-0002 | BSIT | 2A'],
            ['student_id' => '2022-0456', 'member_name' => 'CRUZ, JUAN D.', 'raw_line' => '3 | CRUZ, JUAN D. | 2022-0456 | BSCS | 1B'],
            ['student_id' => '2021-0003', 'member_name' => 'REYES, ANA', 'raw_line' => '4 | REYES, ANA | 2021-0003 | BSBA | 3C'],
        ];

        $response = $this->post(route('apply-org.match-officer-ids'), [
            'request_key' => 'lookup-match-1',
            'officers' => json_encode([
                'president' => ['first_name' => 'Juan', 'middle_name' => 'P', 'last_name' => 'Dela Cruz', 'suffix' => ''],
                'secretary' => ['first_name' => 'Juan', 'middle_name' => '', 'last_name' => 'Cruz', 'suffix' => ''],
                'treasurer' => ['first_name' => 'Maria', 'middle_name' => 'C', 'last_name' => 'Santos', 'suffix' => ''],
                'auditor' => ['first_name' => 'Pedro', 'middle_name' => '', 'last_name' => 'Garcia', 'suffix' => ''],
                'pio' => ['first_name' => 'Ana', 'middle_name' => '', 'last_name' => 'Reyes', 'suffix' => ''],
            ]),
            'rows' => json_encode($rows),
        ]);

        $response->assertOk();
        self::assertTrue($response->json('success'));
        self::assertSame('lookup-match-1', $response->json('request_key'));
        self::assertSame(4, $response->json('rows_received'));

        // Exact single match auto-fills.
        self::assertSame('2021-0001', $response->json('results.president.match.student_id'));
        self::assertSame('2021-0002', $response->json('results.treasurer.match.student_id'));
        self::assertSame('2021-0003', $response->json('results.pio.match.student_id'));

        // Ambiguous: two strong rows, so no auto match — user picks from candidates.
        self::assertNull($response->json('results.secretary.match'));
        $candidates = $response->json('results.secretary.candidates');
        self::assertCount(2, $candidates);
        self::assertSame(
            ['2022-0456', '2021-0001'],
            array_column($candidates, 'student_id')
        );

        // Unknown officer: no match, no candidates.
        self::assertNull($response->json('results.auditor.match'));
        self::assertSame([], $response->json('results.auditor.candidates'));
    }

    public function test_parse_member_rows_pairs_sf005_two_column_blocks(): void
    {
        // Mirrors the real LSPU-OSAS-SF-005 layout: a name line, an ID-only
        // line, then a course line — two members per visual row, plus a
        // single-column tail, an academic-year header, and a 5-digit typo.
        $pdf = self::membersPdf([
            [
                'LIST OF MEMBERS OF THE ORGANIZATION',
                '1st Sem. / AY 2025-2026',
                'Famulercano, Carlos Jim Balingit       Leron, Karl Austin Quinto',
                '0124-1466  0124-1453',
                'BS Computer Science 2A          BS Computer Science 2A',
            ],
            [
                'Sadiasa, Avegail Kalim                         Villacorta, Aaron Cruzin',
                '0124-1983  0124-1448',
                '',
                'Sto. Domingo, Eulyn Vincee Ann',
                '0124-2059',
                '',
                'Asistido, Mark Ariel           Cueto, John Lei Ugat',
                '0124-1530     00124-1574',
            ],
        ]);
        $file = static fn () => UploadedFile::fake()->createWithContent('members-sf005.pdf', $pdf);

        $response = $this->post(route('apply-org.parse-member-rows'), [
            'request_key' => 'lookup-sf005-1',
            'page_from' => 1,
            'page_size' => 30,
            'member_file' => $file(),
        ]);

        $response->assertOk();
        self::assertTrue($response->json('success'));
        self::assertTrue($response->json('complete'));

        $rows = $response->json('rows');
        $byId = [];
        foreach ($rows as $row) {
            $byId[$row['student_id']] = $row['member_name'];
        }

        // Column-order pairing, both columns and the single-column tail.
        self::assertSame('Famulercano, Carlos Jim Balingit', $byId['0124-1466'] ?? null);
        self::assertSame('Leron, Karl Austin Quinto', $byId['0124-1453'] ?? null);
        self::assertSame('Sadiasa, Avegail Kalim', $byId['0124-1983'] ?? null);
        self::assertSame('Villacorta, Aaron Cruzin', $byId['0124-1448'] ?? null);
        self::assertSame('Sto. Domingo, Eulyn Vincee Ann', $byId['0124-2059'] ?? null);
        self::assertSame('Asistido, Mark Ariel', $byId['0124-1530'] ?? null);
        // 5-digit "00"-prefixed typo is repaired.
        self::assertSame('Cueto, John Lei Ugat', $byId['0124-1574'] ?? null);
        // Academic-year header never becomes a row.
        self::assertArrayNotHasKey('2025-2026', $byId);
        self::assertCount(7, $rows);

        // The parsed rows feed straight into officer matching.
        $match = $this->post(route('apply-org.match-officer-ids'), [
            'request_key' => 'lookup-sf005-2',
            'officers' => json_encode([
                'president' => ['first_name' => 'Carlos', 'middle_name' => 'Jim', 'last_name' => 'Famulercano', 'suffix' => ''],
            ]),
            'rows' => json_encode($rows),
        ]);

        $match->assertOk();
        self::assertSame('0124-1466', $match->json('results.president.match.student_id'));
    }

    public function test_parse_member_rows_recovers_noisy_sf005_blocks(): void
    {
        // Real-world noise: truncated ID line, stacked single-column names,
        // garbage line between name and ID, and crammed single-space columns.
        $pdf = self::membersPdf([
            [
                'Abillonar, Rhea Carlizette Tardecilla             Concibido, Melrose Pascion',
                '0124-2034    0124-20',
                'BS Computer Science 2B              BS Computer Science 2B',
            ],
            [
                'Odevilas l, Abigail Bolar',
                'Sumague, Jhamel Rose Ramirez',
                '0125-0174 	0125-0233',
                '',
                'Altobar, Loraine Rivera',
                'Arm 	a',
                '0125-0123',
            ],
            [
                'Miranda, Irene Austria Sombrano, Mrincess Allisha Cruz',
                '0124-2039       0124-1945',
            ],
        ]);
        $file = static fn () => UploadedFile::fake()->createWithContent('members-noisy.pdf', $pdf);

        $response = $this->post(route('apply-org.parse-member-rows'), [
            'request_key' => 'lookup-noisy-1',
            'page_from' => 1,
            'page_size' => 30,
            'member_file' => $file(),
        ]);

        $response->assertOk();
        self::assertTrue($response->json('complete'));

        $byId = [];
        foreach ($response->json('rows') as $row) {
            $byId[$row['student_id']] = $row['member_name'];
        }

        // Truncated line: leading pair still attaches left-to-right.
        self::assertSame('Abillonar, Rhea Carlizette Tardecilla', $byId['0124-2034'] ?? null);
        // Stacked names accumulate across lines.
        self::assertSame('Odevilas l, Abigail Bolar', $byId['0125-0174'] ?? null);
        self::assertSame('Sumague, Jhamel Rose Ramirez', $byId['0125-0233'] ?? null);
        // Garbage line is skipped over.
        self::assertSame('Altobar, Loraine Rivera', $byId['0125-0123'] ?? null);
        // Crammed columns split on surname-led commas.
        self::assertSame('Miranda, Irene Austria Sombrano', $byId['0124-2039'] ?? null);
        self::assertSame('Mrincess Allisha Cruz', $byId['0124-1945'] ?? null);

        // OCR-junk surname still matches its officer.
        $match = $this->post(route('apply-org.match-officer-ids'), [
            'request_key' => 'lookup-noisy-2',
            'officers' => json_encode([
                'president' => ['first_name' => 'Abigail', 'middle_name' => 'Bolar', 'last_name' => 'Odevilas', 'suffix' => ''],
            ]),
            'rows' => json_encode($response->json('rows')),
        ]);

        $match->assertOk();
        self::assertSame('0125-0174', $match->json('results.president.match.student_id'));
    }

    public function test_first_initial_match_surfaces_candidate_but_never_auto_matches(): void
    {
        $rows = [
            ['student_id' => '2021-0001', 'member_name' => 'DELA CRUZ, JUAN P.', 'raw_line' => 'DELA CRUZ, JUAN P. | 2021-0001'],
        ];

        $response = $this->post(route('apply-org.match-officer-ids'), [
            'request_key' => 'lookup-initial-1',
            'officers' => json_encode([
                // Perfect last + middle, initial-only first: 0.45 + 0.20 + 0.15 = 0.80.
                'president' => ['first_name' => 'J', 'middle_name' => 'P', 'last_name' => 'Dela Cruz', 'suffix' => ''],
            ]),
            'rows' => json_encode($rows),
        ]);

        $response->assertOk();
        self::assertNull($response->json('results.president.match'));
        $candidates = $response->json('results.president.candidates');
        self::assertCount(1, $candidates);
        self::assertSame('2021-0001', $candidates[0]['student_id']);
        self::assertSame(0.80, $candidates[0]['confidence']);
    }

    public function test_spaced_dash_ids_and_compound_middle_names_resolve(): void
    {
        $pdf = self::membersPdf([
            [
                'Uy, Imac James S.',
                '0123 - 1793',
                'BS Information Technology 3C',
            ],
            [
                'Balmes, Amaru Jay F.       Almario, Cel Rick D.',
                '0124-0398  0122-1123',
            ],
        ]);
        $file = static fn () => UploadedFile::fake()->createWithContent('members-spaced.pdf', $pdf);

        $parse = $this->post(route('apply-org.parse-member-rows'), [
            'request_key' => 'lookup-spaced-1',
            'page_from' => 1,
            'page_size' => 30,
            'member_file' => $file(),
        ]);

        $parse->assertOk();
        $byId = [];
        foreach ($parse->json('rows') as $row) {
            $byId[$row['student_id']] = $row['member_name'];
        }
        // Space before the dash still parses.
        self::assertSame('Uy, Imac James S.', $byId['0123-1793'] ?? null);
        self::assertCount(3, $parse->json('rows'));

        $match = $this->post(route('apply-org.match-officer-ids'), [
            'request_key' => 'lookup-spaced-2',
            'officers' => json_encode([
                // Compound given name: cert middle initial matches a later
                // roster middle token ("F" in "JAY F").
                'auditor' => ['first_name' => 'Amaru Jay', 'middle_name' => 'F', 'last_name' => 'Balmes', 'suffix' => ''],
                'rep_4th_year' => ['first_name' => 'Cel Rick', 'middle_name' => 'D', 'last_name' => 'Almario', 'suffix' => ''],
                'event_coordinator' => ['first_name' => 'Imac James', 'middle_name' => 'S', 'last_name' => 'Uy', 'suffix' => ''],
            ]),
            'rows' => json_encode($parse->json('rows')),
        ]);

        $match->assertOk();
        self::assertSame('0124-0398', $match->json('results.auditor.match.student_id'));
        self::assertSame('0122-1123', $match->json('results.rep_4th_year.match.student_id'));
        self::assertSame('0123-1793', $match->json('results.event_coordinator.match.student_id'));
    }

    public function test_invalid_payloads_return_safe_error_envelopes(): void
    {
        $badOfficers = $this->postJson(route('apply-org.match-officer-ids'), [
            'request_key' => 'lookup-bad-officers',
            'officers' => 'not-json',
            'rows' => json_encode([]),
        ]);

        $badOfficers->assertStatus(422);
        self::assertFalse($badOfficers->json('success'));
        self::assertSame('lookup-bad-officers', $badOfficers->json('request_key'));

        $badRows = $this->postJson(route('apply-org.match-officer-ids'), [
            'request_key' => 'lookup-bad-rows',
            'officers' => json_encode([]),
            'rows' => 'not-json',
        ]);

        $badRows->assertStatus(422);
        self::assertFalse($badRows->json('success'));
        self::assertSame('lookup-bad-rows', $badRows->json('request_key'));
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
