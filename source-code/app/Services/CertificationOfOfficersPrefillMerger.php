<?php

namespace App\Services;

/**
 * Merges the deterministic fixed-layout Certification of Officers prefill
 * payload with a Gemini AI extraction under AI-first agreement semantics.
 *
 * Gemini is the primary parser for non-standard layouts, but the
 * deterministic parser remains authoritative per slot: when both sources
 * carry the same person the deterministic value wins (its exact normalized
 * year/section formatting is kept), and when they conflict the deterministic
 * value still wins. AI fills only slots the deterministic path left empty,
 * and AI entries are rejected when their name cannot be traced back to the
 * extracted source text (anti-hallucination guard).
 */
class CertificationOfOfficersPrefillMerger
{
    /**
     * Merge fixed-layout parser data with Gemini AI data.
     *
     * @param  array{dean:?array,senior_adviser:?array,junior_adviser:?array,officers:array}  $fixed
     * @param  array{dean:?array,senior_adviser:?array,junior_adviser:?array,officers:array}  $ai
     * @param  string  $sourceText  Raw page text the AI was asked to parse.
     * @return array{data:array,source:string} source: fixed|ai|merged
     */
    public function merge(array $fixed, array $ai, string $sourceText): array
    {
        $aiUsed = false;

        $pickPerson = function (?array $fixedValue, ?array $aiValue) use ($sourceText, &$aiUsed): ?array {
            if ($this->hasName($fixedValue)) {
                return $fixedValue;
            }
            if ($this->hasName($aiValue) && $this->plausiblyInSource($aiValue, $sourceText)) {
                $aiUsed = true;

                return $aiValue;
            }

            return null;
        };

        $data = [
            'dean'           => $pickPerson($fixed['dean'] ?? null, $ai['dean'] ?? null),
            'senior_adviser' => $pickPerson($fixed['senior_adviser'] ?? null, $ai['senior_adviser'] ?? null),
            'junior_adviser' => $pickPerson($fixed['junior_adviser'] ?? null, $ai['junior_adviser'] ?? null),
            'officers'       => [],
            // The college comes from the deterministic layout only.
            'college'        => $fixed['college'] ?? null,
        ];

        $fixedOfficers = $fixed['officers'] ?? [];
        $aiOfficers = $ai['officers'] ?? [];
        $fixedUsed = $this->hasAnyName($fixed);

        $slots = array_values(array_unique(array_merge(array_keys($fixedOfficers), array_keys($aiOfficers))));
        foreach ($slots as $slot) {
            $fixedValue = $fixedOfficers[$slot] ?? null;
            $aiValue = $aiOfficers[$slot] ?? null;

            if ($this->hasOfficerName($fixedValue)) {
                $data['officers'][$slot] = $fixedValue;
                continue;
            }
            if ($this->hasOfficerName($aiValue) && $this->plausiblyInSource($aiValue, $sourceText)) {
                $aiUsed = true;
                $data['officers'][$slot] = $aiValue;
            }
        }

        if (! $aiUsed) {
            $source = 'fixed';
        } elseif ($fixedUsed) {
            $source = 'merged';
        } else {
            $source = 'ai';
        }

        return ['data' => $data, 'source' => $source];
    }

    /**
     * A person entry counts as filled when it carries at least one name part.
     */
    private function hasName(?array $person): bool
    {
        if (empty($person)) {
            return false;
        }
        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            if (trim((string) ($person[$field] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Officer entries additionally carry year_level/section, but a slot is
     * only worth filling when it carries a name.
     */
    private function hasOfficerName(?array $officer): bool
    {
        return $this->hasName($officer);
    }

    /**
     * Did the fixed payload contribute any name anywhere?
     */
    private function hasAnyName(array $data): bool
    {
        foreach (['dean', 'senior_adviser', 'junior_adviser'] as $key) {
            if ($this->hasName($data[$key] ?? null)) {
                return true;
            }
        }
        foreach ($data['officers'] ?? [] as $officer) {
            if ($this->hasOfficerName($officer)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Anti-hallucination guard: every last-name token must appear in the
     * source text, and at least one first/middle token must appear whenever
     * the AI supplied any. Comparison is case/punctuation-insensitive and
     * order-independent because source names use LAST, FIRST M. ordering.
     */
    private function plausiblyInSource(array $person, string $sourceText): bool
    {
        $lastTokens = $this->tokens($person['last_name'] ?? '');
        $firstMiddleTokens = $this->tokens(
            trim(($person['first_name'] ?? '').' '.($person['middle_name'] ?? ''))
        );

        if ($lastTokens === []) {
            return false;
        }

        $sourceTokens = array_fill_keys($this->tokens($sourceText), true);

        foreach ($lastTokens as $token) {
            if (! isset($sourceTokens[$token])) {
                return false;
            }
        }
        if ($firstMiddleTokens !== [] && $this->anyInSource($firstMiddleTokens, $sourceTokens) === false) {
            return false;
        }

        return true;
    }

    private function anyInSource(array $tokens, array $sourceTokens): bool
    {
        foreach ($tokens as $token) {
            if (isset($sourceTokens[$token])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Uppercased, punctuation-stripped whitespace tokens.
     */
    private function tokens(string $value): array
    {
        $value = mb_strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/i', ' ', $value);

        return array_values(array_filter(preg_split('/\s+/', trim($value)), fn ($token) => $token !== ''));
    }
}
