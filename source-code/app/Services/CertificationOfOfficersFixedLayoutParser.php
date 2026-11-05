<?php

namespace App\Services;

use App\Helpers\NameParser;

/**
 * Parses only the confirmed LSPU-OSAS-SF-006 Rev. 1 certificate layout.
 *
 * This deliberately returns source-ordered records rather than form slots;
 * role allocation and certifier de-duplication are a separate concern.
 */
final class CertificationOfOfficersFixedLayoutParser
{
    private const FORM_NUMBER_ANCHOR = 'LSPU-OSAS-SF-006';

    private const REVISION_ANCHOR = 'REV. 1';

    private const TITLE_ANCHOR = 'CERTIFICATION';

    private const CERTIFICATION_SENTENCE_PREFIX = 'THIS CERTIFIES THAT';

    /**
     * @param  list<array{index?:int,text?:string,preferred_values?:array<string,string>}>  $pages
     * @return array{outcome:'completed'|'partial'|'manual_required',data:array{officers:list<array<string,int|string>>,certifiers:array{advisers:list<array<string,int|string>>,deans:list<array<string,int|string>>}},warnings:list<string>}
     */
    public function parse(array $pages): array
    {
        $officers = [];
        $advisers = [];
        $deans = [];
        $warnings = [];
        $college = null;

        if ($pages === []) {
            return $this->result('manual_required', [], [
                'dean' => null,
                'senior_adviser' => null,
                'junior_adviser' => null,
            ], [
                'No readable Certification of Officers pages were supplied.',
            ]);
        }

        foreach ($pages as $offset => $page) {
            $pageIndex = is_int($page['index'] ?? null) ? $page['index'] : $offset;
            $parsed = $this->parsePage($page, $pageIndex);
            array_push($warnings, ...$parsed['warnings']);

            if ($parsed['college'] !== null && $college === null) {
                $college = $parsed['college'];
            }

            if ($parsed['officer'] === null) {
                continue;
            }

            $officers[] = $parsed['officer'];
            array_push($advisers, ...$parsed['advisers']);
            array_push($deans, ...$parsed['deans']);
        }

        $registry = new CertificationOfOfficersRoleSlotRegistry;
        $allocation = $registry->allocate($officers);
        $certifiers = $registry->certifierDestinations($advisers, $deans);
        array_push($warnings, ...$allocation['warnings']);

        $outcome = $allocation['officers'] === []
            ? ($officers === [] ? 'manual_required' : 'partial')
            : ($warnings === [] ? 'completed' : 'partial');

        return $this->result($outcome, $allocation['officers'], $certifiers, $warnings, $college);
    }

    /**
     * @param  array{index?:int,text?:string,preferred_values?:array<string,string>}  $page
     * @return array{officer:array<string,int|string>|null,advisers:list<array<string,int|string>>,deans:list<array<string,int|string>>,warnings:list<string>}
     */
    private function parsePage(array $page, int $pageIndex): array
    {
        $text = isset($page['text']) && is_string($page['text']) ? $page['text'] : '';
        if ($this->normalizeWhitespace($text) === '') {
            return $this->unparseablePage($pageIndex, 'is unreadable or contains no selectable text.');
        }

        $lines = $this->lines($text);
        $upperLines = array_map(static fn (string $line): string => strtoupper($line), $lines);
        if (! $this->hasTemplateAnchors($lines)
            || ! in_array(self::TITLE_ANCHOR, $upperLines, true)
            || ! in_array('NOTED:', $upperLines, true)) {
            return $this->unparseablePage($pageIndex, 'does not match the supported Certification of Officers template.');
        }

        $content = implode(' ', $lines);
        // Lazy .*? tolerates both comma-delimited surnames ("DELA ASTER, AVERY
        // SKY M., a student taking up ...") and conventional order without an
        // extra surname comma ("AVERY SKY M. DELA ASTER, a student taking up ...").
        $sentencePattern = '/THIS CERTIFIES THAT\s*(?<student>.*?)\s*[,.]\s*A STUDENT TAKING UP\s+(?<course>.+?)\s*[,.]\s*(?<year_section>.+?)\s+FROM THE COLLEGE OF\s+(?<college>.+?)\s+AND THE ELECTED\/APPOINTED\s+(?<position>.+?)\s+OF THE\s+(?<organization>.+?)\./iu';
        if (preg_match($sentencePattern, $content, $sentence) !== 1) {
            return $this->unparseablePage($pageIndex, 'is malformed and cannot be safely auto-filled.');
        }

        // Text-extracted values (visible content) take priority over embedded
        // form field values, which may contain stale defaults from the template.
        $textStudentName = $this->normalizeWhitespace($sentence['student']);
        $studentName = $textStudentName !== ''
            ? $textStudentName
            : ($this->preferredValue($page, 'student_name') ?? '');
        // Final fallback: fillable PDFs may place the student name as the first
        // name-like line near the bottom cluster (after all label lines).
        if ($studentName === '') {
            $studentName = $this->extractStudentNameFromCluster($lines) ?? '';
        }
        $textYearSection = $this->normalizeWhitespace($sentence['year_section']);
        $yearSection = $textYearSection !== ''
            ? $textYearSection
            : ($this->preferredValue($page, 'year_section') ?? '');
        // The degree program ("BACHELOR OF SCIENCE IN ...") feeds the
        // officer course select; matching downstream is case-insensitive
        // so the visible-text casing is preserved as-is.
        $course = $this->normalizeWhitespace($sentence['course'] ?? '');
        $college = $this->normalizeCollege($sentence['college'] ?? '');
        $visiblePosition = $this->normalizeWhitespace($sentence['position']);
        $position = $visiblePosition;

        $adviserNames = [];
        $deanNames = [];
        foreach ($lines as $index => $line) {
            $role = strtoupper($this->normalizeWhitespace($line));
            $isOrganizationAdviser = $role === 'ORGANIZATION ADVISER';
            $isDeanAssocDean = str_starts_with($role, 'DEAN/ASSOC. DEAN');
            if (! $isOrganizationAdviser && ! $isDeanAssocDean) {
                continue;
            }

            // Try name on the line BEFORE the label (normal layout)
            $name = $lines[$index - 1] ?? '';
            if ($name === '' || str_contains($name, ':')) {
                // Fallback: try name on the line AFTER the label (fillable PDF layout)
                // but only if it doesn't look like another role label
                $after = $lines[$index + 1] ?? '';
                $afterUpper = strtoupper($this->normalizeWhitespace($after));
                $isAnotherLabel = str_contains($afterUpper, 'ORGANIZATION ADVISER')
                    || str_contains($afterUpper, 'DEAN/ASSOC')
                    || str_contains($afterUpper, 'DIRECTOR')
                    || str_contains($afterUpper, 'NOTED')
                    || str_contains($after, ':');
                $name = ($after !== '' && ! $isAnotherLabel) ? $after : '';
            }

            if ($name === '') {
                continue;
            }

            if ($isOrganizationAdviser) {
                $adviserNames[] = $name;
            } else {
                $deanNames[] = $name;
            }
        }

        // Fallback for fillable PDFs where names are extracted separately from labels.
        // In this layout the extracted text has labels without adjacent names, but the
        // names appear as a cluster at the end in the order: student, adviser1, adviser2, dean, director.
        if (count($adviserNames) !== 2 || count($deanNames) !== 1) {
            [$adviserNames, $deanNames] = $this->extractCertifierNamesFromCluster($lines);
        }

        if ($studentName === '' || $yearSection === '' || $position === ''
            || count($adviserNames) !== 2 || count($deanNames) !== 1) {
            return $this->unparseablePage($pageIndex, 'is malformed and cannot be safely auto-filled.');
        }

        $student = $this->parseStudent($studentName);
        if ($student === null) {
            return $this->unparseablePage($pageIndex, 'has a malformed comma-delimited student name.');
        }

        $warnings = [];
        $yearAndSection = $this->parseYearSection($yearSection);
        if ($yearAndSection === null) {
            $yearAndSection = ['year_level' => '', 'section' => ''];
            $warnings[] = $this->pageWarning($pageIndex, 'has a malformed year-section; enter it manually.');
        }

        $officer = [
            'page_index' => $pageIndex,
            'position' => strtoupper($this->normalizeWhitespace($position)),
            ...$student,
            ...$yearAndSection,
            'course' => $course,
        ];

        $advisers = array_map(
            fn (string $name): array => ['page_index' => $pageIndex, 'role' => 'organization_adviser', ...$this->parseCertifier($name)],
            $adviserNames,
        );
        $deans = array_map(
            fn (string $name): array => ['page_index' => $pageIndex, 'role' => 'dean_assoc_dean', ...$this->parseCertifier($name)],
            $deanNames,
        );

        return compact('officer', 'advisers', 'deans', 'warnings', 'college');
    }

    /** @return array{first_name:string,middle_name:string,last_name:string,suffix:string}|null */
    private function parseStudent(string $source): ?array
    {
        $normalized = $this->normalizeWhitespace($source);
        if ($normalized === '') {
            return null;
        }

        if (! str_contains($normalized, ',')) {
            // The exact certification sentence already proves a comma follows
            // this capture. Some valid pages use conventional name order before
            // that static delimiter instead of adding a second surname comma.
            if (preg_match('/^[\p{L}\p{M}][\p{L}\p{M}.\'’-]*(?:\s+[\p{L}\p{M}][\p{L}\p{M}.\'’-]*)+$/u', $normalized) !== 1) {
                return null;
            }

            $parsed = $this->parseNormalOrderName($normalized);
            if ($parsed['first_name'] === '' || $parsed['last_name'] === '') {
                return null;
            }

            return [
                'first_name' => $parsed['first_name'],
                'middle_name' => $parsed['middle_name'],
                'last_name' => $parsed['last_name'],
                // The sentence delimiter is not an explicit student suffix.
                'suffix' => '',
            ];
        }

        [$lastName, $givenNames] = array_map($this->normalizeWhitespace(...), explode(',', $normalized, 2));
        if ($lastName === '' || $givenNames === '') {
            return null;
        }

        // The comma-delimited surname is authoritative. A placeholder lets the
        // shared COR parser apply its right-to-left given/middle-name behavior
        // without reinterpreting an arbitrary compound surname.
        $parsed = $this->parseNormalOrderName($givenNames.' CERTIFICATION_SURNAME');

        return [
            'first_name' => $parsed['first_name'],
            'middle_name' => $parsed['middle_name'],
            'last_name' => NameParser::toTitleCase($lastName),
            // Student suffixes are never inferred from a trailing name token.
            'suffix' => '',
        ];
    }

    /** @return array{first_name:string,middle_name:string,last_name:string,suffix:string} */
    private function parseCertifier(string $source): array
    {
        [$personalName, $credentials] = array_pad(explode(',', $source, 2), 2, '');
        $parsed = $this->parseNormalOrderName($personalName);

        return [
            'first_name' => $parsed['first_name'],
            'middle_name' => $parsed['middle_name'],
            'last_name' => $parsed['last_name'],
            // Everything after the first comma is the represented credential suffix.
            'suffix' => $this->normalizeWhitespace($credentials),
        ];
    }

    /** @return array{first_name:string,middle_name:string,last_name:string,suffix:string} */
    private function parseNormalOrderName(string $source): array
    {
        $normalized = $this->normalizeWhitespace($source);
        $parsed = NameParser::parse($normalized);
        $parts = $this->nameParts($normalized);

        // NameParser's right-to-left rule needs this conventional two-token
        // boundary so a First Last name does not lose its first-name value.
        if (count($parts) === 2 && $parsed['suffix'] === '') {
            return [
                'first_name' => NameParser::toTitleCase($parts[0]),
                'middle_name' => '',
                'last_name' => NameParser::toTitleCase($parts[1]),
                'suffix' => '',
            ];
        }

        return [
            'first_name' => NameParser::toTitleCase($parsed['first_name']),
            'middle_name' => NameParser::toTitleCase($parsed['middle_name']),
            'last_name' => NameParser::toTitleCase($parsed['last_name']),
            'suffix' => $parsed['suffix'],
        ];
    }

    /** @return array{year_level:string,section:string}|null */
    private function parseYearSection(string $source): ?array
    {
        if (preg_match('/^(\d+)\s*([A-Za-z]+)$/', $this->normalizeWhitespace($source), $matches) !== 1) {
            return null;
        }

        return ['year_level' => $matches[1], 'section' => strtoupper($matches[2])];
    }

    /**
     * Document-level template pre-gate: the loose text fallback must only run
     * on plausible LSPU-OSAS-SF-006 Rev. 1 content, never on arbitrary
     * documents (e.g. a Plan of Activities mentioning coordinators).
     */
    public static function isTemplateText(string $text): bool
    {
        $upper = mb_strtoupper($text);

        return str_contains($upper, self::FORM_NUMBER_ANCHOR)
            && str_contains($upper, self::CERTIFICATION_SENTENCE_PREFIX);
    }

    /** @param list<string> $lines */
    private function hasTemplateAnchors(array $lines): bool
    {
        $content = strtoupper(implode("\n", $lines));
        foreach ([self::FORM_NUMBER_ANCHOR, self::REVISION_ANCHOR, self::TITLE_ANCHOR, self::CERTIFICATION_SENTENCE_PREFIX, 'DIRECTOR/CHAIRPERSON'] as $anchor) {
            if (! str_contains($content, $anchor)) {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $lines @param list<string> $labels */
    private function valueForLabels(array $lines, array $labels): ?string
    {
        foreach ($labels as $label) {
            $values = $this->valuesForLabel($lines, $label);
            if ($values !== []) {
                return $values[0];
            }
        }

        return null;
    }

    /** @param list<string> $lines @return list<string> */
    private function valuesForLabel(array $lines, string $label): array
    {
        $values = [];
        $pattern = '/^'.preg_quote($label, '/').'\s*:?\s*(.*)$/iu';

        foreach ($lines as $index => $line) {
            if (preg_match($pattern, $line, $matches) !== 1) {
                continue;
            }

            $value = $this->normalizeWhitespace($matches[1]);
            if ($value === '') {
                $value = $this->adjacentSignatureValue($lines, $index);
            }
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    /** @param list<string> $lines */
    private function adjacentSignatureValue(array $lines, int $labelIndex): string
    {
        foreach ([$labelIndex + 1, $labelIndex - 1] as $index) {
            if (! isset($lines[$index]) || str_contains($lines[$index], ':')) {
                continue;
            }

            return $lines[$index];
        }

        return '';
    }

    /** @param array<string,mixed> $page */
    private function preferredPosition(array $page, string $visiblePosition): string
    {
        $preferred = $this->preferredValue($page, 'position');
        if ($preferred === null) {
            return $visiblePosition;
        }

        $incompleteRepresentative = CertificationOfOfficersRoleSlotRegistry::canonicalRepresentativeOrdinal($preferred);
        $completeVisibleRepresentative = CertificationOfOfficersRoleSlotRegistry::canonicalRepresentativeRole($visiblePosition);
        if ($incompleteRepresentative !== null && $completeVisibleRepresentative !== null) {
            return $visiblePosition;
        }

        return $preferred;
    }

    /** @param array<string,mixed> $page */
    private function preferredValue(array $page, string $field): ?string
    {
        $values = $page['preferred_values'] ?? [];
        if (! is_array($values) || ! isset($values[$field]) || ! is_string($values[$field])) {
            return null;
        }

        $value = $this->normalizeWhitespace($values[$field]);

        return $value === '' ? null : $value;
    }

    /** @return list<string> */
    private function lines(string $text): array
    {
        return array_values(array_filter(
            array_map($this->normalizeWhitespace(...), preg_split('/\R/u', $text) ?: []),
            static fn (string $line): bool => $line !== '',
        ));
    }

    /** @return list<string> */
    private function nameParts(string $name): array
    {
        $normalized = $this->normalizeWhitespace($name);

        return $normalized === '' ? [] : (preg_split('/\s+/', $normalized) ?: []);
    }

    private function normalizeWhitespace(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    /** @return array{officer:null,college:null,advisers:list<array<string,int|string>>,deans:list<array<string,int|string>>,warnings:list<string>} */
    private function unparseablePage(int $pageIndex, string $reason): array
    {
        return ['officer' => null, 'college' => null, 'advisers' => [], 'deans' => [], 'warnings' => [$this->pageWarning($pageIndex, $reason)]];
    }

    private function pageWarning(int $pageIndex, string $reason): string
    {
        return 'Certification page '.($pageIndex + 1).' '.$reason;
    }

    /**
     * @param  array<string,array<string,string>>  $officers
     * @param  array{dean:?array<string,string>,senior_adviser:?array<string,string>,junior_adviser:?array<string,string>}  $certifiers
     * @param  list<string>  $warnings
     * @return array{outcome:'completed'|'partial'|'manual_required',data:array{dean:?array<string,string>,senior_adviser:?array<string,string>,junior_adviser:?array<string,string>,officers:array<string,array<string,string>>,college:?string},warnings:list<string>}
     */
    private function result(string $outcome, array $officers, array $certifiers, array $warnings, ?string $college = null): array
    {
        return [
            'outcome' => $outcome,
            'data' => [...$certifiers, 'officers' => $officers, 'college' => $college],
            'warnings' => $warnings,
        ];
    }

    /**
     * Rebuilds the full college name from the sentence's "College of ..." segment.
     */
    private function normalizeCollege(string $college): ?string
    {
        $segment = $this->normalizeWhitespace($college);
        if ($segment === '') {
            return null;
        }

        return 'College of '.ucwords(mb_strtolower($segment));
    }

    /**
     * Extracts the student name from a fillable-PDF cluster at the bottom of the page.
     *
     * In fillable PDFs the smalot extractor renders form field values as a block
     * of lines AFTER all the template text — typically:
     *   STUDENT_NAME
     *   ADVISER_1
     *   ADVISER_2
     *   DEAN, DIT
     *   DIRECTOR, credentials
     *
     * The student name is the first entry that looks like a surname-first name
     * (contains a comma) OR is in ALL-CAPS.
     */
    private function extractStudentNameFromCluster(array $lines): ?string
    {
        // Use the last DIRECTOR/CHAIRPERSON or NOTED: line as the start anchor
        $clusterStart = null;
        foreach ($lines as $index => $line) {
            $upper = strtoupper($this->normalizeWhitespace($line));
            if (str_contains($upper, 'DIRECTOR/CHAIRPERSON') || str_contains($upper, 'NOTED:')) {
                $clusterStart = $index + 1; // keep updating to get the LAST match
            }
        }

        if ($clusterStart === null) {
            return null;
        }

        // The first name-like line after the anchor is the student name
        for ($i = $clusterStart; $i < count($lines); $i++) {
            $line = $this->normalizeWhitespace($lines[$i]);
            if ($line === '') {
                continue;
            }
            // Must look like a name: letters, spaces, commas, dots, hyphens
            if (preg_match('/^[\p{L}\p{M}\s,.\'-]+$/u', $line) && strlen($line) > 3) {
                return $line;
            }
        }

        return null;
    }

    /**
     * Extracts adviser and dean names from the fillable-PDF cluster at the bottom.
     *
     * After the student name in the cluster come: adviser1, adviser2, dean (with DIT/etc suffix).
     * The director line is last and is excluded.
     *
     * @return array{list<string>, list<string>} [$adviserNames, $deanNames]
     */
    private function extractCertifierNamesFromCluster(array $lines): array
    {
        // Find the last occurrence of DIRECTOR/CHAIRPERSON or NOTED: — names follow after
        $clusterStart = null;
        foreach ($lines as $index => $line) {
            $upper = strtoupper($this->normalizeWhitespace($line));
            if (str_contains($upper, 'DIRECTOR/CHAIRPERSON') || str_contains($upper, 'NOTED:')) {
                $clusterStart = $index + 1; // keep updating to get the LAST match
            }
        }

        if ($clusterStart === null) {
            return [[], []];
        }

        $nameLines = [];
        for ($i = $clusterStart; $i < count($lines); $i++) {
            $line = $this->normalizeWhitespace($lines[$i]);
            if ($line === '') {
                continue;
            }
            // Skip role label lines that are part of the template
            $upper = strtoupper($line);
            $isRoleLabel = str_contains($upper, 'ORGANIZATION ADVISER')
                || str_contains($upper, 'DEAN/ASSOC')
                || str_contains($upper, 'DIRECTOR/CHAIRPERSON')
                || str_contains($upper, 'OFFICE OF STUDENT')
                || str_contains($upper, 'LSPU-OSAS');
            if ($isRoleLabel) {
                continue;
            }
            if (preg_match('/^[\p{L}\p{M}\s,.\'-]+$/u', $line) && strlen($line) > 3) {
                $nameLines[] = $line;
            }
        }

        // Expected order: student, adviser1, adviser2, dean, director
        // Skip student (index 0) and director (last)
        if (count($nameLines) < 4) {
            return [[], []];
        }

        // Skip first (student), take next two as advisers, then one as dean
        // Last entry is director — skip it
        $adviser1 = $nameLines[1] ?? null;
        $adviser2 = $nameLines[2] ?? null;
        $dean     = $nameLines[3] ?? null;

        $adviserNames = array_filter([$adviser1, $adviser2]);
        $deanNames    = array_filter([$dean]);

        return [array_values($adviserNames), array_values($deanNames)];
    }
}
