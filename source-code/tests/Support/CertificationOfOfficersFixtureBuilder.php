<?php

namespace Tests\Support;

/**
 * Deterministic, fictional-only Rev. 1 PDFs and parser-facing page data.
 * This is test support only; it has no Laravel or database dependency.
 */
final class CertificationOfOfficersFixtureBuilder
{
    private const CERTIFIERS = [
        'senior_adviser' => ['first_name' => 'Aiden', 'middle_name' => 'P.', 'last_name' => 'North', 'suffix' => 'MA'],
        'junior_adviser' => ['first_name' => 'Blair', 'middle_name' => 'Q.', 'last_name' => 'South', 'suffix' => 'PhD'],
        'dean' => ['first_name' => 'Casey', 'middle_name' => 'R.', 'last_name' => 'West', 'suffix' => 'EdD'],
    ];

    /** @var list<array{0:string,1:string,2:string,3:string}> */
    private const ROLES = [
        ['PRESIDENT', 'president', 'DELA ASTER, AVERY SKY M.', '1A'],
        ['VICE PRESIDENT', 'vice_president', 'BIRCH, BAILEY NOEL', '2B'],
        ['SECRETARY', 'secretary', 'CEDAR, CAMERON', '3C'],
        ['TREASURER', 'treasurer', 'EMBER, ELLIS Q.', '1E'],
        ['AUDITOR', 'auditor', 'FABLE, FINLEY R.', '2F'],
        ['1ST YEAR REPRESENTATIVE', 'rep_1st_year', 'GROVE, GABRIEL S.', '3G'],
        ['2ND YEAR REPRESENTATIVE', 'rep_2nd_year', 'HARBOR, HARPER T.', '4H'],
        ['3RD YEAR REPRESENTATIVE', 'rep_3rd_year', 'IVORY, INDIGO U.', '1I'],
        ['4TH YEAR REPRESENTATIVE', 'rep_4th_year', 'JUNIPER, JORDAN V.', '2J'],
        ['BUSINESS MANAGER', 'business_manager', 'KITE, KENDALL W.', '3K'],
        ['PUBLIC INFORMATION OFFICER', 'pio', 'MEADOW, MORGAN Y.', '1M'],
        ['EVENT COORDINATOR', 'event_coordinator', 'ORCHID, OAKLEY A.', '3O'],
        ['COMELEC', 'comelec', 'QUARTZ, QUINN C.', '1Q'],
    ];

    /**
     * Complete normal-form fixture: one valid certificate page per supported
     * destination slot, in physical PDF order. All names are fictional.
     */
    public static function validMultiPageCase(string $requestToken = 'fixture-32440-current'): array
    {
        $pages = [];
        $officers = [];
        foreach (self::ROLES as [$role, $slot, $studentName, $yearSection]) {
            $page = self::page($role, $slot, $studentName, $yearSection);
            $page['index'] = count($pages);
            $pages[] = $page;
            $officers[$slot] = self::officerDestination($studentName, $yearSection);
        }

        return [
            'pdf' => self::pdf($pages),
            'pages' => $pages,
            'expectedEnvelope' => [
                'success' => true,
                'outcome' => 'completed',
                'request_token' => $requestToken,
                'data' => array_merge(self::CERTIFIERS, ['officers' => $officers, 'college' => 'College of Imaginary Arts']),
                'warnings' => [],
            ],
            'domPayload' => ['request_token' => $requestToken, 'officers' => $officers],
        ];
    }

    /** @return list<string> */
    public static function documentedSlots(): array
    {
        return array_column(self::ROLES, 1);
    }

    /** @return list<array{0:string,1:string,2:string,3:string}> */
    public static function roleFixtures(): array
    {
        return self::ROLES;
    }

    /**
     * A recognized AcroForm field deliberately conflicts with selectable text.
     * The extractor still reports form values as preferred_values, but the parser
     * now prefers the visible text content over form field defaults.
     */
    public static function formFieldPrecedenceCase(): array
    {
        $fields = [
            'student_name' => 'FIELD, UMA P.',
            'year_section' => '4Z',
            'position' => 'PRESIDENT',
        ];
        $textStudentName = 'TEXT, TAY Z.';
        $textYearSection = '1A';
        $page = self::page('PRESIDENT', 'president', $textStudentName, $textYearSection, $fields);

        return [
            'pdf' => self::pdf([$page]),
            'page' => $page,
            'expectedPreferredValues' => $fields,
            'expectedOfficer' => self::officerDestination($textStudentName, $textYearSection),
        ];
    }

    /**
     * Reader/parser recovery fixtures: unreadable bytes, a readable wrong
     * template, and a valid page followed by a wrong-template page.
     */
    public static function recoveryCases(): array
    {
        $nonTemplate = [
            'index' => 0,
            'text_lines' => ['SYNTHETIC NON-TEMPLATE PAGE', 'No Rev. 1 anchors are present.'],
            'text_values' => [],
            'fields' => [],
            'certifiers' => [],
        ];
        $validFirstPage = self::page(...self::ROLES[0]);

        return [
            'malformed-local-bytes' => [
                'pdf' => "%PDF-1.4\n% fictional malformed fixture only\nnot-a-pdf-object\n",
                'pages' => [],
                'expectedOutcome' => 'manual_required',
                'expectedDataSource' => 'none',
            ],
            'readable-non-template-page' => [
                'pdf' => self::pdf([$nonTemplate]),
                'pages' => [$nonTemplate],
                'expectedOutcome' => 'manual_required',
                'expectedDataSource' => 'none',
            ],
            'valid-then-non-template-page' => [
                'pdf' => self::pdf([$validFirstPage, $nonTemplate]),
                'pages' => [$validFirstPage, $nonTemplate],
                'expectedOutcome' => 'partial',
                'expectedDataSource' => 'fixed',
            ],
        ];
    }

    /**
     * AI merge case: one valid Rev. 1 page (President) followed by a freeform
     * page that carries an officer name but no fixed-layout anchors. The fixed
     * parser stops at President; Gemini is expected to recover the Treasurer.
     */
    public static function aiMergeCase(): array
    {
        $validFirstPage = self::page(...self::ROLES[0]);
        $freeformTreasurer = [
            'index' => 1,
            'text_lines' => [
                'CERTIFICATION OF OFFICERS',
                'Fictional Orbit Society - SY 2026-2027.',
                'This certifies that EMBER, ELLIS Q., a student taking up Bachelor of Synthetic Studies, 1E, is the elected TREASURER of the Fictional Orbit Society.',
                'Signed by the Organization Adviser.',
            ],
            'text_values' => [],
            'fields' => [],
            'certifiers' => [],
        ];

        return [
            'pdf' => self::pdf([$validFirstPage, $freeformTreasurer]),
            'pages' => [$validFirstPage, $freeformTreasurer],
            'expectedOutcome' => 'partial',
            'expectedAiOfficer' => self::officerDestination('EMBER, ELLIS Q.', '1E'),
        ];
    }

    /**
     * AI-only case: a single freeform page with an officer name and no
     * fixed-layout anchors at all. Only Gemini can recover the officer.
     */
    public static function aiOnlyCase(): array
    {
        $freeformTreasurer = [
            'index' => 0,
            'text_lines' => [
                'CERTIFICATION OF OFFICERS',
                'Fictional Orbit Society - SY 2026-2027.',
                'Official list of officers: EMBER, ELLIS Q., 1E.',
                'Noted by the Organization Adviser.',
            ],
            'text_values' => [],
            'fields' => [],
            'certifiers' => [],
        ];

        return [
            'pdf' => self::pdf([$freeformTreasurer]),
            'pages' => [$freeformTreasurer],
            'expectedOutcome' => 'manual_required',
            'expectedAiOfficer' => self::officerDestination('EMBER, ELLIS Q.', '1E'),
        ];
    }

    private static function page(string $role, string $slot, string $studentName, string $yearSection, array $fields = []): array
    {
        $certifiers = self::certifierLines();
        $textValues = ['student_name' => $studentName, 'year_section' => $yearSection, 'position' => $role];

        return [
            'index' => 0,
            'slot' => $slot,
            'text_values' => $textValues,
            'fields' => $fields,
            'certifiers' => $certifiers,
            'text_lines' => [
                'CERTIFICATION',
                "This certifies that $studentName, a student taking up Bachelor of Synthetic Studies, $yearSection from the College of Imaginary Arts and the elected/appointed $role of the Fictional Orbit Society.",
                "{$certifiers['senior_adviser']}",
                'Organization Adviser',
                "{$certifiers['junior_adviser']}",
                'Organization Adviser',
                "{$certifiers['dean']}",
                'Dean/Assoc. Dean, College of Imaginary Arts',
                'Noted:',
                'NOTED-BY FICTIONAL PERSON',
                'Director/Chairperson',
                'LSPU-OSAS-SF-006',
                'Rev. 1',
            ],
        ];
    }

    private static function certifierLines(): array
    {
        return [
            'senior_adviser' => 'AIDEN P. NORTH, MA',
            'junior_adviser' => 'BLAIR Q. SOUTH, PhD',
            'dean' => 'CASEY R. WEST, EdD',
        ];
    }

    private static function officerDestination(string $studentName, string $yearSection): array
    {
        [$lastName, $remainder] = array_map('trim', explode(',', $studentName, 2));
        $nameParts = preg_split('/\s+/', $remainder) ?: [];
        $middleName = count($nameParts) > 1 ? array_pop($nameParts) : '';
        preg_match('/^(\d+)\s*([A-Za-z]+)$/', $yearSection, $yearSectionParts);

        // The parser applies NameParser::toTitleCase to first, middle, and last names.
        return [
            'first_name' => \App\Helpers\NameParser::toTitleCase(implode(' ', $nameParts)),
            'middle_name' => \App\Helpers\NameParser::toTitleCase($middleName),
            'last_name' => \App\Helpers\NameParser::toTitleCase($lastName),
            'suffix' => '',
            'year_level' => $yearSectionParts[1] ?? '',
            'section' => strtoupper($yearSectionParts[2] ?? ''),
            'course' => 'Bachelor of Synthetic Studies',
        ];
    }

    /** @param list<array<string,mixed>> $pages */
    private static function pdf(array $pages): string
    {
        $objects = [];
        $fontId = 4;
        $nextId = 5;
        $pageReferences = [];
        $fieldReferences = [];

        foreach ($pages as $index => $page) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $fields = [];
            foreach ($page['fields'] as $name => $value) {
                $fieldId = $nextId++;
                $fields[] = "$fieldId 0 R";
                $fieldReferences[] = "$fieldId 0 R";
                $objects[$fieldId] = '<< /Type /Annot /Subtype /Widget /FT /Tx /T ('.self::escapePdf($name)
                    .') /V ('.self::escapePdf($value).") /P $pageId 0 R /Rect [0 0 0 0] >>";
            }
            $annotations = $fields === [] ? '' : ' /Annots ['.implode(' ', $fields).']';
            $stream = self::textStream($page['text_lines']);
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 $fontId 0 R >> >> /Contents $contentId 0 R$annotations >>";
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n$stream\nendstream";
            $pageReferences[] = "$pageId 0 R";
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R /AcroForm 3 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageReferences).'] /Count '.count($pages).' >>';
        $objects[3] = '<< /Fields ['.implode(' ', $fieldReferences).'] >>';
        $objects[$fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$object\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 $size\n0000000000 65535 f \n";
        for ($id = 1; $id < $size; $id++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$id])."\n";
        }

        return $pdf."trailer\n<< /Size $size /Root 1 0 R >>\nstartxref\n$xrefOffset\n%%EOF\n";
    }

    /** @param list<string> $lines */
    private static function textStream(array $lines): string
    {
        $lines = array_map(fn (string $line): string => self::escapePdf($line), $lines);

        return "BT\n/F1 10 Tf\n14 TL\n50 740 Td\n(".implode(") Tj\nT*\n(", $lines).") Tj\nET";
    }

    private static function escapePdf(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
