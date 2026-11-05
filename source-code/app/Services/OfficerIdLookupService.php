<?php

namespace App\Services;

/**
 * Matches officer names (from the Certification of Officers prefill) against
 * name + student-ID rows parsed from List of Members text.
 *
 * Pure deterministic logic: no AI calls, no database writes. The caller
 * supplies the already-extracted member text (OcrService) and the officer
 * names; this service returns a single confident match and/or a short list
 * of candidates per officer slot.
 */
class OfficerIdLookupService
{
    /**
     * Same student ID shape used by StudentListProcessor: 4 digits, dash,
     * 4 digits. The \s? pairs tolerate spacing artifacts like "0123- 1560"
     * and "0123 - 1793". The optional 5th leading digit tolerates roster
     * typos like "00124-1574" (normalized back to 4 digits when it starts
     * with "00").
     */
    private const STUDENT_ID_PATTERN = '/\b\d{4,5}\s?-\s?\d{4}\b/';

    /** Minimum score for an automatic (single) match. */
    private const AUTO_MATCH_THRESHOLD = 0.85;

    /** Minimum score for a row to appear as a dropdown candidate. */
    private const CANDIDATE_THRESHOLD = 0.6;

    /** Maximum candidates returned per officer slot. */
    private const MAX_CANDIDATES = 5;

    /**
     * @param array<string,array{first_name?:mixed,middle_name?:mixed,last_name?:mixed,suffix?:mixed}> $officers
     * @return array{rows: int, results: array<string,array{match:?array{student_id:string,member_name:string,raw_line:string,confidence:float},candidates:list<array{student_id:string,member_name:string,raw_line:string,confidence:float}>}>}
     */
    public function match(string $membersText, array $officers): array
    {
        $rows = $this->parseMemberRows($membersText);

        return ['rows' => count($rows), 'results' => $this->matchRows($rows, $officers)];
    }

    /**
     * Match officer names against already-parsed member rows. Used by the
     * paginated flow, where rows accumulate across several parse requests
     * before a single match call.
     *
     * @param list<array{student_id:string,member_name:string,raw_line:string}> $rows
     * @param array<string,array{first_name?:mixed,middle_name?:mixed,last_name?:mixed,suffix?:mixed}> $officers
     * @return array<string,array{match:?array{student_id:string,member_name:string,raw_line:string,confidence:float},candidates:list<array{student_id:string,member_name:string,raw_line:string,confidence:float}>}>
     */
    public function matchRows(array $rows, array $officers): array
    {
        $rows = array_values(array_filter($rows, static fn ($row): bool => is_array($row)
            && isset($row['student_id'], $row['member_name'])
            && is_string($row['student_id']) && is_string($row['member_name'])));

        // Deduplicate identical rows accumulated across overlapping chunks.
        $seen = [];
        $unique = [];
        foreach ($rows as $row) {
            $key = $row['student_id'].'|'.$row['member_name'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = [
                'student_id' => $row['student_id'],
                'member_name' => $row['member_name'],
                'raw_line' => isset($row['raw_line']) && is_string($row['raw_line'])
                    ? mb_substr($row['raw_line'], 0, 160)
                    : '',
            ];
        }

        $results = [];
        foreach ($officers as $slot => $officer) {
            if (! is_array($officer)) {
                continue;
            }
            $results[(string) $slot] = $this->matchOfficer($officer, $unique);
        }

        return $results;
    }

    /**
     * Parse every line carrying a student ID into a name + ID row.
     *
     * Two roster layouts are supported:
     * 1. Same-line rows: [No.] Name ... ID ... (columns in any order). The
     *    name is taken from the text before the ID, falling back to the
     *    text after it (for "ID-first" layouts).
     * 2. SF-005 two-column blocks: name line(s) ("LAST, First ...   LAST,
     *    First ...") followed by an ID-only line ("0124-1111  0124-2222"),
     *    optionally followed by a course line. Names and IDs are paired by
     *    column order; on count mismatch the first pairs still attach
     *    left-to-right (truncation cuts line ends, never line starts).
     *
     * @return list<array{student_id:string,member_name:string,raw_line:string}>
     */
    public function parseMemberRows(string $text): array
    {
        $rows = [];
        $seen = [];

        $addRow = function (string $id, string $name, string $raw) use (&$rows, &$seen): void {
            if (! $this->looksLikeName($name)) {
                return;
            }
            $key = $id.'|'.$name;
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;

            $rows[] = [
                'student_id' => $id,
                'member_name' => $name,
                'raw_line' => mb_substr($raw, 0, 160),
            ];
        };

        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $total = count($lines);
        $consumed = [];

        for ($i = 0; $i < $total; $i++) {
            if (isset($consumed[$i])) {
                continue;
            }
            $line = trim((string) $lines[$i]);
            if ($line === '') {
                continue;
            }

            $ids = $this->extractStudentIds($line);

            // SF-005 block: ID-only line paired with preceding name line(s).
            if ($ids !== [] && $this->isIdOnlyLine($line, $ids)) {
                $collected = $this->collectNameLines($lines, $i, $consumed, count($ids));
                $chunks = $collected['chunks'];
                if ($chunks !== [] && count($chunks) < count($ids)) {
                    // Crammed columns with single-space gaps: rescue via
                    // surname-led comma splitting ("A, B C, D E" -> two names).
                    $split = $this->commaSplitChunks($chunks, count($ids));
                    if ($split !== null) {
                        $chunks = $split;
                    }
                }
                // Greedy left-to-right: attach the leading pairs even when the
                // ID line was truncated (truncation cuts line ends, never
                // starts). Fewer name chunks than IDs stays skipped — with
                // no column position to anchor to, placing those IDs would
                // be a coin flip.
                if (count($chunks) >= count($ids)) {
                    $emitted = count($rows);
                    for ($k = 0; $k < count($ids); $k++) {
                        $addRow($ids[$k]['value'], $chunks[$k], $chunks[$k].' | '.$ids[$k]['value']);
                    }
                    if (count($rows) > $emitted) {
                        foreach ($collected['indexes'] as $idx) {
                            $consumed[$idx] = true;
                        }
                    }
                }
                continue;
            }

            if ($ids === []) {
                continue;
            }

            // Same-line fallback: name before the ID, else after it.
            $first = $ids[0];
            $pos = mb_strpos($line, $first['raw']);
            $before = $pos === false ? '' : trim(mb_substr($line, 0, $pos));
            $after = $pos === false ? '' : trim(mb_substr($line, $pos + mb_strlen($first['raw'])));

            $name = $this->cleanNameChunk($before);
            if (! $this->looksLikeName($name)) {
                $name = $this->cleanNameChunk($after);
            }
            $addRow($first['value'], $name, $line);
        }

        return $rows;
    }

    /**
     * Extract normalized student IDs from a line, skipping academic-year
     * ranges (e.g. "2025-2026" from "1st Sem. / AY 2025-2026") and
     * unfixable digit shapes.
     *
     * @return list<array{raw:string,value:string}>
     */
    private function extractStudentIds(string $line): array
    {
        if (! preg_match_all(self::STUDENT_ID_PATTERN, $line, $m)) {
            return [];
        }

        $out = [];
        foreach ($m[0] as $raw) {
            $value = $this->normalizeStudentId($raw);
            if ($value === null || $this->looksLikeYearRange($value)) {
                continue;
            }
            $out[] = ['raw' => $raw, 'value' => $value];
        }

        return $out;
    }

    /**
     * Normalize one raw ID match: collapse inner whitespace, repair the
     * "00"-prefixed 5-digit typo ("00124-1574" -> "0124-1574"). Anything
     * else that is not exactly 4-4 digits is rejected.
     */
    private function normalizeStudentId(string $raw): ?string
    {
        $compact = (string) preg_replace('/\s+/', '', $raw);
        $parts = explode('-', $compact, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$left, $right] = $parts;
        if (strlen($left) === 5) {
            if (! str_starts_with($left, '00')) {
                return null;
            }
            $left = substr($left, 1);
        }
        if (strlen($left) !== 4 || strlen($right) !== 4) {
            return null;
        }

        return $left.'-'.$right;
    }

    /** Academic-year ranges ("2025-2026") are never student IDs. */
    private function looksLikeYearRange(string $id): bool
    {
        [$left, $right] = array_pad(explode('-', $id, 2), 2, '0');
        $l = (int) $left;
        $r = (int) $right;

        return $l >= 1900 && $l <= 2100 && $r >= 1900 && $r <= 2100;
    }

    /**
     * True when the line holds nothing but the given ID matches. Truncated
     * ID fragments from native text extraction ("0124-2034    0124-20")
     * are stripped too — they are not valid IDs, but their presence must
     * not demote the line out of the block path.
     */
    private function isIdOnlyLine(string $line, array $ids): bool
    {
        $remainder = $line;
        foreach ($ids as $id) {
            $remainder = str_replace($id['raw'], '', $remainder);
        }
        $remainder = (string) preg_replace('/\b\d{4,5}\s?-\s?\d{1,3}\b/', '', $remainder);

        return trim($remainder) === '';
    }

    /** Roster header/form boilerplate that must never pose as a name line. */
    private const ROSTER_HEADER_HINTS = [
        'LIST OF MEMBERS',
        'NAME OF ORGANIZATION',
        'SAMPLE FORMAT',
        'SIGNATURE OVER PRINTED NAME',
        'REPUBLIC OF THE PHILIPPINES',
        'LAGUNA STATE POLYTECHNIC',
        'OFFICE OF STUDENT',
        'LSPU-OSAS',
        'ORGANIZATION ADVISER',
        'RECOMMENDING APPROVAL',
    ];

    /**
     * Walk backwards from an ID-only line collecting name chunks across
     * consecutive content lines (stacked single-column names, two-column
     * rows). Stops at page breaks, lines carrying IDs, course lines, and
     * roster headers. Garbage/OCR-noise lines are skipped over. Walk depth
     * is capped so a missing name line can never pair across blocks.
     *
     * @return array{indexes:list<int>,chunks:list<string>} indexes top-down
     */
    private function collectNameLines(array $lines, int $idIndex, array $consumed, int $need): array
    {
        $found = [];
        for ($j = $idIndex - 1; $j >= 0 && count($found) < 4; $j--) {
            if (isset($consumed[$j])) {
                continue;
            }
            $candidate = trim((string) $lines[$j]);
            if ($candidate === '') {
                continue;
            }
            if (str_contains($candidate, '--- PAGE BREAK ---')) {
                break;
            }
            if ($this->extractStudentIds($candidate) !== []) {
                break;
            }
            $upper = mb_strtoupper($candidate);
            foreach (self::ROSTER_HEADER_HINTS as $hint) {
                if (str_contains($upper, $hint)) {
                    break 2;
                }
            }
            if ($this->looksLikeCourseLine($candidate)) {
                break;
            }
            if (! $this->looksLikeName($this->cleanNameChunk($candidate))) {
                // OCR noise ("Arm a", "\\\"): skip over it.
                continue;
            }
            $found[] = $j;
            $probe = [];
            foreach ($found as $idx) {
                foreach ($this->splitNameColumns(trim((string) $lines[$idx])) as $chunk) {
                    $probe[] = $chunk;
                }
            }
            if (count($probe) >= $need) {
                break;
            }
        }

        $indexes = array_reverse($found);
        $chunks = [];
        foreach ($indexes as $idx) {
            foreach ($this->splitNameColumns(trim((string) $lines[$idx])) as $chunk) {
                $chunks[] = $chunk;
            }
        }

        return ['indexes' => $indexes, 'chunks' => $chunks];
    }

    /** Course/section lines ("BS Computer Science 2A") are never names. */
    private function looksLikeCourseLine(string $line): bool
    {
        $upper = mb_strtoupper($line);
        if (preg_match('/\b(BACHELOR|COMPUTER SCIENCE|INFORMATION TECHNOLOGY|BUSINESS ADMINISTRATION)\b/', $upper) === 1) {
            return true;
        }

        return preg_match('/\b(BSCS|BSIT|BSBA|BSED|BEED|BSCRIM|BSHM|BSA)\b/', $upper) === 1
            && preg_match('/\b\d[A-Z]\b/', $upper) === 1;
    }

    /**
     * Split crammed name columns with single-space gaps
     * ("Miranda, Irene Austria Sombrano, Mrincess Allisha Cruz") into
     * surname-led pairs. The first (need-1) persons take surname+given
     * pairs; the last person takes the remainder.
     *
     * @param list<string> $chunks
     * @return list<string>|null null when the comma math does not work out
     */
    private function commaSplitChunks(array $chunks, int $need): ?array
    {
        $segments = [];
        foreach ($chunks as $chunk) {
            foreach (explode(',', $chunk) as $segment) {
                $segment = trim($segment);
                if ($segment !== '') {
                    $segments[] = $segment;
                }
            }
        }
        if (count($segments) < 2 * $need - 1) {
            return null;
        }

        $out = [];
        for ($k = 0; $k < $need - 1; $k++) {
            $out[] = $segments[2 * $k].', '.$segments[2 * $k + 1];
        }
        $out[] = implode(', ', array_slice($segments, 2 * ($need - 1)));

        return $out;
    }

    /** Split a two-column name line on wide whitespace gaps. */
    private function splitNameColumns(string $line): array
    {
        $chunks = preg_split('/\s{2,}/u', $line) ?: [];
        $out = [];
        foreach ($chunks as $chunk) {
            $chunk = trim((string) preg_replace('/\s+/u', ' ', $chunk));
            if ($chunk !== '') {
                $out[] = $chunk;
            }
        }

        return $out;
    }

    /**
     * @param array{first_name?:mixed,middle_name?:mixed,last_name?:mixed,suffix?:mixed} $officer
     * @param list<array{student_id:string,member_name:string,raw_line:string}> $rows
     * @return array{match:?array{student_id:string,member_name:string,raw_line:string,confidence:float},candidates:list<array{student_id:string,member_name:string,raw_line:string,confidence:float}>}
     */
    private function matchOfficer(array $officer, array $rows): array
    {
        $officerParts = [
            'last' => $this->stripSuffix($this->norm((string) ($officer['last_name'] ?? ''))),
            'first' => $this->norm((string) ($officer['first_name'] ?? '')),
            'middle' => $this->norm((string) ($officer['middle_name'] ?? '')),
        ];

        if ($officerParts['last'] === '' || $officerParts['first'] === '') {
            return ['match' => null, 'candidates' => []];
        }

        $scored = [];
        foreach ($rows as $row) {
            $score = $this->score($officerParts, $this->splitName($row['member_name']));
            if ($score >= self::CANDIDATE_THRESHOLD) {
                $scored[] = [
                    'student_id' => $row['student_id'],
                    'member_name' => $row['member_name'],
                    'raw_line' => $row['raw_line'],
                    'confidence' => round($score, 2),
                ];
            }
        }

        usort($scored, static fn ($a, $b) => $b['confidence'] <=> $a['confidence']
            ?: strcmp($a['student_id'], $b['student_id']));
        $candidates = array_slice($scored, 0, self::MAX_CANDIDATES);

        // Auto-match only when exactly one row clears the high bar; anything
        // ambiguous stays a user-picked dropdown.
        $strong = array_values(array_filter(
            $candidates,
            static fn ($c) => $c['confidence'] >= self::AUTO_MATCH_THRESHOLD
        ));
        $match = count($strong) === 1 ? $strong[0] : null;

        return ['match' => $match, 'candidates' => $candidates];
    }

    /**
     * Split a roster name into last/first/middle parts. Handles both
     * "LAST, FIRST M." and "FIRST M. LAST" orders, including compound
     * surnames like "DELA CRUZ".
     *
     * @return array{last:string,first:string,middle:string}
     */
    private function splitName(string $name, string $hintLast = '', string $hintFirst = '', string $hintMiddle = ''): array
    {
        $clean = mb_strtoupper(trim($name));
        $clean = (string) preg_replace('/[."“”]/u', ' ', $clean);
        $clean = (string) preg_replace('/\b(JR|SR|II|III|IV|V|VI)\b/u', ' ', $clean);
        $clean = trim((string) preg_replace('/\s+/u', ' ', $clean));

        if (str_contains($clean, ',')) {
            [$last, $rest] = array_pad(explode(',', $clean, 2), 2, '');
            $restTokens = preg_split('/\s+/', trim($rest)) ?: [];
            $first = $restTokens[0] ?? '';
            $middle = implode(' ', array_slice($restTokens, 1));
        } else {
            $tokens = preg_split('/\s+/', $clean) ?: [];
            if (count($tokens) === 1) {
                return ['last' => $tokens[0], 'first' => $hintFirst, 'middle' => $hintMiddle];
            }
            $last = (string) array_pop($tokens);
            $first = (string) array_shift($tokens);
            $middle = implode(' ', $tokens);
        }

        // Certification data already separates the parts, so prefer it when
        // the roster text is missing a side (e.g. first-name-only rows).
        return [
            'last' => $last !== '' ? $last : $hintLast,
            'first' => $first !== '' ? $first : $hintFirst,
            'middle' => $middle !== '' ? $middle : $hintMiddle,
        ];
    }

    /**
     * Score an officer against one roster row (0..1). Last name must match
     * (full, final token for compound surnames, or junk-token-stripped for
     * OCR noise like "ODEVILAS L"); first name must match exactly, by
     * prefix, or by initial ("M" vs "MARIA" — candidate-only, it can never
     * reach the auto-match bar); middle must be compatible, never
     * contradictory.
     *
     * @param array{last:string,first:string,middle:string} $officer
     * @param array{last:string,first:string,middle:string} $row
     */
    private function score(array $officer, array $row): float
    {
        $lastExact = $officer['last'] !== '' && $officer['last'] === $row['last'];
        $lastToken = ! $lastExact && $this->lastToken($officer['last']) === $this->lastToken($row['last']);
        $lastStripped = ! $lastExact && ! $lastToken && $officer['last'] !== '' && $row['last'] !== ''
            && $this->stripJunkTokens($officer['last']) === $this->stripJunkTokens($row['last']);
        if (! $lastExact && ! $lastToken && ! $lastStripped) {
            return 0.0;
        }

        $firstExact = $officer['first'] !== '' && $officer['first'] === $row['first'];
        $firstPrefix = ! $firstExact && $this->isPrefixName($officer['first'], $row['first']);
        $firstInitial = ! $firstExact && ! $firstPrefix && $this->isInitialName($officer['first'], $row['first']);
        if (! $firstExact && ! $firstPrefix && ! $firstInitial) {
            return 0.0;
        }

        $middle = $this->middleScore($officer['middle'], $row['middle']);

        // Initial-tier caps at 0.45 + 0.20 + 0.15 = 0.80: visible as a
        // candidate, mathematically unable to auto-match (bar is 0.85).
        $score = ($lastExact ? 0.45 : 0.35)
            + ($firstExact ? 0.40 : ($firstPrefix ? 0.25 : 0.20))
            + $middle;

        return min(1.0, max(0.0, $score));
    }

    /**
     * Middle-name compatibility: +0.15 when both sides agree (or agree by
     * initial), +0.10 when one side is missing, -0.25 on contradiction.
     * A single-letter side is an initial: it agrees when the letter matches
     * ANY token initial on the other side, since compound given names push
     * the real initial to a later token ("F" vs "JAY F").
     */
    private function middleScore(string $officerMiddle, string $rowMiddle): float
    {
        $a = $this->norm($officerMiddle);
        $b = $this->norm($rowMiddle);

        if ($a === '' || $b === '') {
            return 0.10;
        }
        if ($a === $b || $this->initial($a) === $this->initial($b)) {
            return 0.15;
        }
        if ((mb_strlen($a) === 1 && $this->initialInTokens($a, $b))
            || (mb_strlen($b) === 1 && $this->initialInTokens($b, $a))) {
            return 0.15;
        }

        return -0.25;
    }

    /** True when $letter equals the initial of any whitespace token in $text. */
    private function initialInTokens(string $letter, string $text): bool
    {
        foreach (preg_split('/\s+/', trim($text)) ?: [] as $token) {
            if ($token !== '' && mb_substr($token, 0, 1) === $letter) {
                return true;
            }
        }

        return false;
    }

    /** Final token of a (possibly compound) surname: "DELA CRUZ" -> "CRUZ". */
    private function lastToken(string $last): string
    {
        $tokens = preg_split('/\s+/', trim($last)) ?: [];
        return end($tokens) ?: '';
    }

    /**
     * Surname with single-letter OCR-junk tokens removed ("ODEVILAS L" ->
     * "ODEVILAS"). Multi-letter tokens are untouched, so real compound
     * surnames are unaffected.
     */
    private function stripJunkTokens(string $last): string
    {
        $tokens = preg_split('/\s+/', trim($last)) ?: [];
        if (count($tokens) <= 1) {
            return trim($last);
        }
        $kept = array_values(array_filter(
            $tokens,
            static fn ($t): bool => mb_strlen((string) $t) > 1
        ));

        return $kept === [] ? trim($last) : implode(' ', $kept);
    }

    /** Prefix tolerance for OCR/nickname variance: min 3 shared leading letters. */
    private function isPrefixName(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }
        $short = mb_strlen($a) <= mb_strlen($b) ? $a : $b;
        $long = $short === $a ? $b : $a;
        return mb_strlen($short) >= 3 && str_starts_with($long, $short);
    }

    /**
     * Single-letter first-name compatibility ("M" vs "MARIA", either
     * side). Surfaces a candidate dropdown; the 0.20 tier can never clear
     * the 0.85 auto-match bar.
     */
    private function isInitialName(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }
        if (mb_strlen($a) === 1) {
            return mb_substr($b, 0, 1) === $a;
        }
        if (mb_strlen($b) === 1) {
            return mb_substr($a, 0, 1) === $b;
        }

        return false;
    }

    private function initial(string $name): string
    {
        return mb_substr($name, 0, 1);
    }

    /** Uppercase letters-only form used for every comparison. */
    private function norm(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $value = (string) preg_replace('/[^A-ZÑ ]/u', ' ', $value);
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /** Remove generational suffixes accidentally stored in surname fields. */
    private function stripSuffix(string $value): string
    {
        return trim((string) preg_replace('/\b(JR|SR|II|III|IV|V|VI)\b/u', ' ', $value));
    }

    /**
     * Strip roster decorations ("1.", "1)", leading pipes/columns) and, for
     * pipe/tab-separated rows, keep the most name-like segment.
     */
    private function cleanNameChunk(string $chunk): string
    {
        $chunk = trim($chunk);
        if ($chunk === '') {
            return '';
        }

        $segments = preg_split('/[|\t]/u', $chunk) ?: [$chunk];
        $best = '';
        $bestScore = -1;
        foreach ($segments as $segment) {
            $segment = trim((string) preg_replace('/^[\d.\)\-#:•\s]+/u', '', $segment));
            $segment = trim((string) preg_replace('/\s+/u', ' ', $segment));
            $letters = preg_match_all('/[A-Za-zÑñ]/u', $segment);
            $hasSpace = (int) (str_contains($segment, ' ') || str_contains($segment, ','));
            $score = $letters + ($hasSpace * 5);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $segment;
            }
        }

        return $best;
    }

    /** A plausible person name: at least two letter-tokens of length 2+. */
    private function looksLikeName(string $value): bool
    {
        if ($value === '' || mb_strlen($value) > 120) {
            return false;
        }
        // Skip section/course codes and pure-number leftovers.
        if (preg_match('/^[A-Z]{2,}\s+\d[A-Z]*$/i', $value) === 1) {
            return false;
        }
        $tokens = preg_split('/[\s,]+/u', $value) ?: [];
        $wordy = 0;
        foreach ($tokens as $token) {
            if (preg_match('/^[A-Za-zÑñ]{2,}$/u', $token) === 1) {
                $wordy++;
            }
        }

        return $wordy >= 2;
    }
}
