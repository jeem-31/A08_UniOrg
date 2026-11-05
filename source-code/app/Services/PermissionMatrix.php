<?php

namespace App\Services;

class PermissionMatrix
{
    /**
     * Static permission mappings between positions and modules
     * 
     * Access levels: 'full', 'read', 'none'
     */
    /**
     * NOTE: Keys here MUST exactly match the values stored in organization_user.role,
     * which come from App\Http\Controllers\ApplyOrgController::sanitizeRole().
     * That function normalizes raw input positions to these canonical strings:
     * "President", "Vice President", "Secretary", "Treasurer", "Auditor", "PIO",
     * "Business Manager", "Adviser", "Dean", "Event Coordinator",
     * "1st Year Representative", "2nd Year Representative", "3rd Year Representative", "4th Year Representative".
     *
     * Older/alternate spellings (underscore variants, "Vice_president", "Yr Rep", etc.) are kept
     * as aliases below so existing data using those forms keeps working. The resolver
     * also handles case/abbreviation variations (e.g. "1st Yr Rep" -> "1st Year Representative").
     */
    private const PERMISSIONS = [
        'President' => [
            'announcements' => 'full',
            'events' => 'full',
            'documents' => 'full',
            'complaints' => 'full',
            'payments' => 'full',
            'elections' => 'full',
            'library' => 'full',
            'edushare' => 'full',
            'chatbot' => 'full',
        ],
        'Vice President' => [
            'announcements' => 'full',
            'events' => 'full',
            'documents' => 'full',
            'complaints' => 'full',
            'payments' => 'full',
            'elections' => 'full',
            'library' => 'full',
            'edushare' => 'full',
            'chatbot' => 'full',
        ],
        'Adviser' => [
            'announcements' => 'full',
            'events' => 'full',
            'documents' => 'full',
            'complaints' => 'full',
            'payments' => 'full',
            'elections' => 'full',
            'library' => 'full',
            'edushare' => 'full',
            'chatbot' => 'full',
        ],
        'Junior Adviser' => [
            'announcements' => 'full',
            'events' => 'full',
            'documents' => 'full',
            'complaints' => 'full',
            'payments' => 'full',
            'elections' => 'full',
            'library' => 'full',
            'edushare' => 'full',
            'chatbot' => 'full',
        ],
        'Senior Adviser' => [
            'announcements' => 'full',
            'events' => 'full',
            'documents' => 'full',
            'complaints' => 'full',
            'payments' => 'full',
            'elections' => 'full',
            'library' => 'full',
            'edushare' => 'full',
            'chatbot' => 'full',
        ],
        // COMELEC is a dedicated election-management position. It must not
        // inherit access from the system-level `admin` account role.
        'COMELEC' => [
            'announcements' => 'none',
            'events' => 'none',
            'documents' => 'none',
            'complaints' => 'none',
            'payments' => 'none',
            'elections' => 'full',
            'library' => 'none',
            'edushare' => 'none',
            'chatbot' => 'none',
        ],
        'Secretary' => [
            'announcements' => 'full',
            'events' => 'full',
            'documents' => 'full',
            'complaints' => 'full',
            'payments' => 'none',
            'elections' => 'none',
            'library' => 'full',
            'edushare' => 'full',
            'chatbot' => 'full',
        ],
        'Treasurer' => [
            'announcements' => 'none',
            'events' => 'none',
            'documents' => 'none',
            'complaints' => 'none',
            'payments' => 'full',
            'elections' => 'none',
            'library' => 'none',
            'edushare' => 'none',
            'chatbot' => 'none',
        ],
        'Auditor' => [
            'announcements' => 'none',
            'events' => 'none',
            'documents' => 'none',
            'complaints' => 'none',
            'payments' => 'read',
            'elections' => 'none',
            'library' => 'none',
            'edushare' => 'none',
            'chatbot' => 'none',
        ],
        // NEW OFFICERS - Simplified focused access
        // Year-level representatives all share the same permission profile
        '1st Year Representative' => [
            'announcements' => 'none',
            'events' => 'none',
            'documents' => 'none',
            'complaints' => 'full',
            'payments' => 'none',
            'elections' => 'none',
            'library' => 'full',
            'edushare' => 'full',
            'chatbot' => 'none',
        ],
        '2nd Year Representative' => [
            'announcements' => 'none',
            'events' => 'none',
            'documents' => 'none',
            'complaints' => 'full',
            'payments' => 'none',
            'elections' => 'none',
            'library' => 'full',
            'edushare' => 'full',
            'chatbot' => 'none',
        ],
        '3rd Year Representative' => [
            'announcements' => 'none',
            'events' => 'none',
            'documents' => 'none',
            'complaints' => 'full',
            'payments' => 'none',
            'elections' => 'none',
            'library' => 'full',
            'edushare' => 'full',
            'chatbot' => 'none',
        ],
        '4th Year Representative' => [
            'announcements' => 'none',
            'events' => 'none',
            'documents' => 'none',
            'complaints' => 'full',
            'payments' => 'none',
            'elections' => 'none',
            'library' => 'full',
            'edushare' => 'full',
            'chatbot' => 'none',
        ],
        'Business Manager' => [
            'announcements' => 'none',
            'events' => 'none',
            'documents' => 'full',
            'complaints' => 'none',
            'payments' => 'full',
            'elections' => 'none',
            'library' => 'none',
            'edushare' => 'none',
            'chatbot' => 'none',
        ],
        'PIO' => [
            'announcements' => 'full',
            'events' => 'full',
            'documents' => 'none',
            'complaints' => 'none',
            'payments' => 'none',
            'elections' => 'none',
            'library' => 'full',
            'edushare' => 'full',
            'chatbot' => 'full',
        ],
        'Event Coordinator' => [
            'announcements' => 'none',
            'events' => 'full',
            'documents' => 'none',
            'complaints' => 'none',
            'payments' => 'none',
            'elections' => 'none',
            'library' => 'none',
            'edushare' => 'none',
            'chatbot' => 'none',
        ],
    ];

    /**
     * Aliases for legacy/alternate role spellings that may already exist in the
     * database (e.g. underscore variants from older data), mapped to the
     * canonical key above.
     */
    private const ALIASES = [
        'Vice_president'   => 'Vice President',
        'Vice_President'   => 'Vice President',
        'Junior_adviser'   => 'Junior Adviser',
        'Junior_Adviser'   => 'Junior Adviser',
        'Business_manager' => 'Business Manager',
        'Business_Manager' => 'Business Manager',
        'Businness Manager' => 'Business Manager', // legacy typo safety net (fixed at the source in organization_user.role)
        'Businness_manager' => 'Business Manager',
        'Event_coordinator' => 'Event Coordinator',
        'Event_Coordinator' => 'Event Coordinator',
        // Legacy yr Representative (lowercase yr) -> canonical Year
        '1st yr Representative' => '1st Year Representative',
        '2nd yr Representative' => '2nd Year Representative',
        '3rd yr Representative' => '3rd Year Representative',
        '4th yr Representative' => '4th Year Representative',
        // Underscore variants
        '1st_year_rep'     => '1st Year Representative',
        '2nd_year_rep'     => '2nd Year Representative',
        '3rd_year_rep'     => '3rd Year Representative',
        '4th_year_rep'     => '4th Year Representative',
        // Abbreviated display in DB: "1st Yr Rep" -> canonical
        '1st Yr Rep' => '1st Year Representative',
        '2nd Yr Rep' => '2nd Year Representative',
        '3rd Yr Rep' => '3rd Year Representative',
        '4th Yr Rep' => '4th Year Representative',
        '1st Yr Representative' => '1st Year Representative',
        '2nd Yr Representative' => '2nd Year Representative',
        '3rd Yr Representative' => '3rd Year Representative',
        '4th Yr Representative' => '4th Year Representative',
        '1st Year Rep' => '1st Year Representative',
        '2nd Year Rep' => '2nd Year Representative',
        '3rd Year Rep' => '3rd Year Representative',
        '4th Year Rep' => '4th Year Representative',
        'Representative'  => '1st Year Representative', // generic fallback
        'Public Information Officer' => 'PIO',
        'Public Information Officer 2' => 'PIO',
        'Public_Information_Officer' => 'PIO',
    ];

    /**
     * Normalize a position string for case/abbreviation-insensitive lookup.
     * Expands "Yr" -> "Year" and "Rep" -> "Representative" so "1st Yr Rep",
     * "1st yr rep", "1st Year Rep" all collapse to "1st year representative".
     */
    private function normalizeForLookup(string $position): string
    {
        $s = trim((string) preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $position)));
        $s = strtolower($s);
        // Expand abbreviations: "yr" -> "year", "rep" -> "representative"
        $s = preg_replace('/\byr\.?\b/', 'year', $s);
        $s = preg_replace('/\brep\.?\b/', 'representative', $s);
        $s = trim((string) preg_replace('/\s+/', ' ', $s));
        return $s;
    }

    /**
     * Resolve a raw role/position string to its canonical PERMISSIONS key.
     *
     * OCR-extracted positions ("Public Information Officer ") may carry stray
     * whitespace, so the lookup input is trimmed and collapsed before matching.
     * The " 2" slot suffix is intentionally left untouched here.
     * Also handles "Yr Rep" abbreviations and case variations so DB values like
     * "1st Yr Rep" map to "1st Year Representative".
     */
    private function resolveKey(string $position): string
    {
        $position = trim((string) preg_replace('/\s+/', ' ', $position));

        if (isset(self::PERMISSIONS[$position])) {
            return $position;
        }

        if (isset(self::ALIASES[$position])) {
            return self::ALIASES[$position];
        }

        // Normalized fallback: case/abbrev-insensitive (e.g. "1st Yr Rep" -> "1st Year Representative")
        $normalized = $this->normalizeForLookup($position);
        static $normalizedMap = null;
        if ($normalizedMap === null) {
            $normalizedMap = [];
            foreach (self::PERMISSIONS as $key => $_) {
                $normalizedMap[$this->normalizeForLookup($key)] = $key;
            }
            foreach (self::ALIASES as $alias => $canonical) {
                $normalizedMap[$this->normalizeForLookup($alias)] = $canonical;
            }
        }

        return $normalizedMap[$normalized] ?? $position;
    }

    /**
     * Resolve a raw role/position string to its canonical PERMISSIONS key.
     *
     * Public wrapper so the settings page can group every holder of the same
     * position under one row, regardless of legacy spellings ("Business_manager",
     * "Vice_president") or the " 2" slot suffix ("Business Manager 2").
     */
    public function canonicalize(string $position): string
    {
        return $this->resolveKey($position);
    }

    /**
     * Get access level for a position-module combination
     *
     * @param string $position The organizational position (President, Vice_president, etc.)
     * @param string $module The module name (announcements, events, documents, etc.)
     * @return string Access level: 'full', 'read', or 'none'
     */
    public function getAccessLevel(string $position, string $module): string
    {
        $key = $this->resolveKey($position);
        return self::PERMISSIONS[$key][$module] ?? 'none';
    }

    /**
     * Get all accessible modules for a position
     * Returns only modules where access is not 'none'
     *
     * @param string $position The organizational position
     * @return array Associative array of module => access_level
     */
    public function getModulesForPosition(string $position): array
    {
        $modules = [];
        $key = $this->resolveKey($position);

        foreach (self::PERMISSIONS[$key] ?? [] as $module => $access) {
            if ($access !== 'none') {
                $modules[$module] = $access;
            }
        }
        
        return $modules;
    }

    /**
     * Get all available modules in the system
     *
     * @return array List of all module names
     */
    public function getAllModules(): array
    {
        return ['announcements', 'events', 'documents', 'complaints', 'payments', 'elections', 'library', 'edushare', 'chatbot'];
    }
}
