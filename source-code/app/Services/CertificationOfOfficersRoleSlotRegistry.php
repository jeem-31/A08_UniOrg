<?php

namespace App\Services;

/** Exact, ordered source-role destinations for Certification of Officers only. */
final class CertificationOfOfficersRoleSlotRegistry
{
    /**
     * Upper bound for dynamically captured "other position" slots. Every
     * unsupported role on the Certification is captured instead of dropped,
     * so the applicant keeps full control of the final officer list.
     */
    private const MAX_CUSTOM_SLOTS = 20;

    private const ROLE_SLOTS = [
        'PRESIDENT' => ['president'],
        'VICE PRESIDENT' => ['vice_president'],
        'SECRETARY' => ['secretary'],
        'TREASURER' => ['treasurer'],
        'AUDITOR' => ['auditor'],
        '1ST YEAR REPRESENTATIVE' => ['rep_1st_year'],
        '2ND YEAR REPRESENTATIVE' => ['rep_2nd_year'],
        '3RD YEAR REPRESENTATIVE' => ['rep_3rd_year'],
        '4TH YEAR REPRESENTATIVE' => ['rep_4th_year'],
        'BUSINESS MANAGER' => ['business_manager'],
        'PUBLIC INFORMATION OFFICER' => ['pio'],
        'EVENT COORDINATOR' => ['event_coordinator'],
        'COMELEC' => ['comelec'],
    ];

    /**
     * Exact legacy template labels evidenced by project data. Aliases resolve
     * to canonical source roles and never add browser/form destinations.
     */
    private const ROLE_ALIASES = [
        'FIRST YEAR REPRESENTATIVE' => '1ST YEAR REPRESENTATIVE',
        'SECOND YEAR REPRESENTATIVE' => '2ND YEAR REPRESENTATIVE',
        'THIRD YEAR REPRESENTATIVE' => '3RD YEAR REPRESENTATIVE',
        'FOURTH YEAR REPRESENTATIVE' => '4TH YEAR REPRESENTATIVE',
    ];

    private const REPRESENTATIVE_SUFFIXES = [
        '1' => 'ST', '2' => 'ND', '3' => 'RD', '4' => 'TH',
    ];

    private const SUPERSCRIPT_ORDINAL_LETTERS = [
        'ˢ' => 'S', 'ᵗ' => 'T', 'ᵀ' => 'T', 'ⁿ' => 'N',
        'ᵈ' => 'D', 'ʳ' => 'R', 'ʰ' => 'H',
    ];

    private const SLOT_LABELS = [
        'president' => 'President', 'vice_president' => 'Vice President',
        'secretary' => 'Secretary',
        'treasurer' => 'Treasurer', 'auditor' => 'Auditor',
        'rep_1st_year' => '1st Year Representative', 'rep_2nd_year' => '2nd Year Representative',
        'rep_3rd_year' => '3rd Year Representative', 'rep_4th_year' => '4th Year Representative',
        'business_manager' => 'Business Manager',
        'pio' => 'Public Information Officer',
        'event_coordinator' => 'Event Coordinator',
        'comelec' => 'COMELEC',
    ];

    /** @return array{officers:array<string,array<string,int|string>>,warnings:list<string>} */
    public function allocate(array $records): array
    {
        $officers = [];
        $warnings = [];
        $customCount = 0;
        foreach ($records as $record) {
            $sourceRole = $this->normalizeRole((string) ($record['position'] ?? ''));
            $role = self::ROLE_ALIASES[$sourceRole] ?? $sourceRole;
            $slots = self::ROLE_SLOTS[$role] ?? null;
            $page = ((int) ($record['page_index'] ?? 0)) + 1;
            if ($slots === null) {
                if ($customCount >= self::MAX_CUSTOM_SLOTS) {
                    $warnings[] = "Certification page {$page} exceeds the supported capacity for additional positions; enter \"{$sourceRole}\" manually.";

                    continue;
                }
                $customCount++;
                $officers["custom_{$customCount}"] = $this->destinationFields($record, self::humanizeRole($sourceRole));
                $warnings[] = "Certification page {$page} captured \"{$sourceRole}\" as an additional position; review it before submitting.";

                continue;
            }
            $availableSlots = array_filter($slots, static fn (string $candidate): bool => ! isset($officers[$candidate]));
            $slot = current($availableSlots);
            if ($slot === false) {
                // Every documented slot for this role is already taken. Keep the
                // extra holder as a custom slot (roles no longer have "*2"
                // variants) so two people holding the same position both survive
                // into the form without the "*2" destination suffix.
                if ($customCount >= self::MAX_CUSTOM_SLOTS) {
                    $warnings[] = "Certification page {$page} exceeds the supported capacity for {$sourceRole}; enter it manually.";

                    continue;
                }
                $customCount++;
                $officers["custom_{$customCount}"] = $this->destinationFields($record, self::humanizeRole($sourceRole));
                $warnings[] = "Certification page {$page} captured \"{$sourceRole}\" as an additional position; review it before submitting.";

                continue;
            }
            $officers[$slot] = $this->destinationFields($record);
        }

        return compact('officers', 'warnings');
    }

    /** @return array{dean:?array<string,string>,senior_adviser:?array<string,string>,junior_adviser:?array<string,string>} */
    public function certifierDestinations(array $advisers, array $deans): array
    {
        $uniqueAdvisers = $this->uniqueCertifiers($advisers, 'organization_adviser');
        $uniqueDeans = $this->uniqueCertifiers($deans, 'dean_assoc_dean');

        return [
            'dean' => $uniqueDeans[0] ?? null,
            'senior_adviser' => $uniqueAdvisers[0] ?? null,
            'junior_adviser' => $uniqueAdvisers[1] ?? null,
        ];
    }

    /** @return list<array{key:string,label:string,is_adviser:bool}> */
    public static function browserPositions(): array
    {
        $positions = [
            ['key' => 'senior_adviser', 'label' => 'Senior Adviser', 'is_adviser' => true],
            ['key' => 'junior_adviser', 'label' => 'Junior Adviser', 'is_adviser' => true],
        ];
        foreach (self::ROLE_SLOTS as $slots) {
            foreach ($slots as $slot) {
                $positions[] = ['key' => $slot, 'label' => self::SLOT_LABELS[$slot], 'is_adviser' => false];
            }
        }

        return $positions;
    }

    /** @return list<string> */
    public static function documentedSlots(): array
    {
        return array_merge(...array_values(self::ROLE_SLOTS));
    }

    /** Dynamic "other position" slot captured from an unsupported Certification role. */
    public static function isCustomSlot(string $slot): bool
    {
        return str_starts_with($slot, 'custom_');
    }

    public static function canonicalRepresentativeRole(string $role): ?string
    {
        return self::representativeRole($role, true);
    }

    public static function canonicalRepresentativeOrdinal(string $role): ?string
    {
        return self::representativeRole($role, false);
    }

    private static function representativeRole(string $role, bool $requiresFullRole): ?string
    {
        $normalized = strtr(self::normalizeRoleText($role), self::SUPERSCRIPT_ORDINAL_LETTERS);
        $tail = $requiresFullRole ? '\\s+YEAR\\s+REPRESENTATIVE' : '';
        if (preg_match('/^([1-4])\\s*(ST|ND|RD|TH)'.$tail.'$/u', $normalized, $matches) !== 1) {
            return null;
        }

        $digit = $matches[1];
        $suffix = self::REPRESENTATIVE_SUFFIXES[$digit];
        if ($matches[2] !== $suffix) {
            return null;
        }

        return $digit.$suffix.' YEAR REPRESENTATIVE';
    }

    private function normalizeRole(string $role): string
    {
        return self::canonicalRepresentativeRole($role) ?? self::normalizeRoleText($role);
    }

    private static function normalizeRoleText(string $role): string
    {
        return strtoupper(trim((string) preg_replace('/\s+/u', ' ', $role)));
    }

    /** @return array<string,string> */
    private function destinationFields(array $record, ?string $positionName = null): array
    {
        $fields = ['first_name', 'middle_name', 'last_name', 'suffix', 'year_level', 'section', 'course'];
        $destination = array_combine($fields, array_map(
            static fn (string $field): string => (string) ($record[$field] ?? ''),
            $fields,
        )) ?: [];
        if ($positionName !== null) {
            $destination['position_name'] = $positionName;
        }

        return $destination;
    }

    /**
     * Convert an uppercased source role into a display-friendly label.
     * Applicants can still edit the label before submitting.
     */
    private static function humanizeRole(string $role): string
    {
        return ucwords(strtolower(trim($role)));
    }

    /** @return list<array<string,string>> */
    private function uniqueCertifiers(array $records, string $role): array
    {
        $unique = [];
        $seen = [];
        foreach ($records as $record) {
            if (($record['role'] ?? null) !== $role) {
                continue;
            }
            $identity = $role.'|'.implode('|', array_map(
                fn (string $field): string => $this->identityPart((string) ($record[$field] ?? '')),
                ['first_name', 'middle_name', 'last_name', 'suffix'],
            ));
            if (isset($seen[$identity])) {
                continue;
            }
            $seen[$identity] = true;
            $fields = ['first_name', 'middle_name', 'last_name', 'suffix'];
            $unique[] = array_combine($fields, array_map(
                static fn (string $field): string => (string) ($record[$field] ?? ''),
                $fields,
            )) ?: [];
        }

        return $unique;
    }

    private function identityPart(string $value): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', $value));

        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    }
}
