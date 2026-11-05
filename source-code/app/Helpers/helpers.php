<?php

use App\Helpers\StorageHelper;
use App\Models\OrganizationSettings;

if (!function_exists('storage_url')) {
    /**
     * Get the public URL for a stored file (works with both local and S3/R2)
     */
    function storage_url(?string $path): ?string
    {
        return StorageHelper::url($path);
    }
}

if (!function_exists('storage_disk')) {
    /**
     * Get the current storage disk
     */
    function storage_disk(): string
    {
        return StorageHelper::disk();
    }
}

if (!function_exists('transform_images')) {
    /**
     * Transform images array to include full storage URLs
     */
    function transform_images(?array $images): array
    {
        return StorageHelper::transformImages($images);
    }
}

if (!function_exists('organization_logo')) {
    /**
     * Get the current organization's logo URL with fallback
     * OPTIMIZATION: Cache logo URL in session to avoid repeated DB queries
     */
    function organization_logo(): string
    {
        $currentOrgId = session('current_organization_id');
        
        // If no organization in session, try to get from authenticated user
        if (!$currentOrgId && auth()->check()) {
            $firstOrg = auth()->user()->activeOrganizations()->first();
            if ($firstOrg) {
                $currentOrgId = $firstOrg->id;
                session(['current_organization_id' => $currentOrgId]);
            }
        }
        
        // Check session cache first to avoid repeated DB queries
        $cacheKey = 'org_logo_' . $currentOrgId;
        if ($currentOrgId && session()->has($cacheKey)) {
            $logoUrl = session($cacheKey);
        } else {
            $logoUrl = null;
            
            if ($currentOrgId) {
                $settings = OrganizationSettings::where('organization_id', $currentOrgId)->first();
                
                if ($settings && $settings->logo_url) {
                    $logoUrl = $settings->logo_url;
                }
            }
            
            if (!$logoUrl) {
                $logoUrl = 'https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/branding/LSPU_LOGO.png';
            }
            
            // Cache in session for this request
            if ($currentOrgId) {
                session([$cacheKey => $logoUrl]);
            }
        }
        
        if (str_starts_with($logoUrl, 'http')) {
            return $logoUrl . '?v=' . time();
        }
        
        return $logoUrl;
    }
}

if (!function_exists('organization_name')) {
    /**
     * Get the current organization's name with fallback
     * OPTIMIZATION: Cache organization name in session
     */
    function organization_name(): string
    {
        $currentOrgId = session('current_organization_id');
        
        // If no organization in session, try to get from authenticated user
        if (!$currentOrgId && auth()->check()) {
            $firstOrg = auth()->user()->activeOrganizations()->first();
            if ($firstOrg) {
                $currentOrgId = $firstOrg->id;
                // Set it in session for future requests
                session(['current_organization_id' => $currentOrgId]);
            }
        }
        
        // OPTIMIZATION: Check session cache first
        $cacheKey = "org_name_{$currentOrgId}";
        $cachedName = session($cacheKey);
        
        if ($cachedName !== null) {
            return $cachedName;
        }
        
        // Not in cache, fetch from database
        $orgName = null;
        
        if ($currentOrgId) {
            $organization = \App\Models\Organization::find($currentOrgId);
            
            if ($organization) {
                $orgName = $organization->name;
            }
        }
        
        // Fallback to default name
        if (!$orgName) {
            $orgName = 'SOCCSync';
        }
        
        // Cache in session
        session([$cacheKey => $orgName]);
        
        return $orgName;
    }
}

if (!function_exists('organization_acronym')) {
    /**
     * Get the current organization's acronym with fallback to name
     * OPTIMIZATION: Cache organization acronym in session
     */
    function organization_acronym(): string
    {
        $currentOrgId = session('current_organization_id');
        
        // If no organization in session, try to get from authenticated user
        if (!$currentOrgId && auth()->check()) {
            $firstOrg = auth()->user()->activeOrganizations()->first();
            if ($firstOrg) {
                $currentOrgId = $firstOrg->id;
                // Set it in session for future requests
                session(['current_organization_id' => $currentOrgId]);
            }
        }
        
        // OPTIMIZATION: Check session cache first
        $cacheKey = "org_acronym_{$currentOrgId}";
        $cachedAcronym = session($cacheKey);
        
        if ($cachedAcronym !== null) {
            return $cachedAcronym;
        }
        
        // Not in cache, fetch from database
        $orgAcronym = null;
        
        if ($currentOrgId) {
            $organization = \App\Models\Organization::find($currentOrgId);
            
            if ($organization) {
                // Use org_acronym if available, otherwise use name
                $orgAcronym = $organization->org_acronym ?? $organization->name;
            }
        }
        
        // Fallback to default acronym
        if (!$orgAcronym) {
            $orgAcronym = 'SOCCS';
        }
        
        // Cache in session
        session([$cacheKey => $orgAcronym]);
        
        return $orgAcronym;
    }
}

if (!function_exists('organization_department')) {
    /**
     * Get the current organization's department name
     */
    function organization_department(): string
    {
        $currentOrgId = session('current_organization_id');

        if (!$currentOrgId && auth()->check()) {
            $firstOrg = auth()->user()->activeOrganizations()->first();
            if ($firstOrg) {
                $currentOrgId = $firstOrg->id;
                session(['current_organization_id' => $currentOrgId]);
            }
        }

        $cacheKey = "org_department_{$currentOrgId}";
        $cachedDept = session($cacheKey);

        if ($cachedDept !== null) {
            return $cachedDept;
        }

        $deptName = null;

        if ($currentOrgId) {
            $organization = \App\Models\Organization::with('department')->find($currentOrgId);

            if ($organization && $organization->department) {
                $deptName = $organization->department->department_name;
            }
        }

        if (!$deptName) {
            $deptName = 'College of Computer Studies';
        }

        session([$cacheKey => $deptName]);

        return $deptName;
    }
}

// ============================================================================
// MODULE HELPER FUNCTIONS
// ============================================================================

if (!function_exists('module_enabled')) {
    /**
     * Check if a module is enabled for the current organization
     * Permanent modules (announcements, events, payments, chatbot) are always enabled
     * Optional modules are checked against organization_settings
     * OPTIMIZATION: Cache module status in session
     *
     * @param string $module Module name (e.g., 'elections', 'elibrary', 'edushare', 'complaints', 'documents', 'chatbot')
     * @return bool
     */
    function module_enabled(string $module): bool
    {
        // Permanent modules are always enabled
        $permanentModules = OrganizationSettings::permanentModules();
        if (in_array($module, $permanentModules)) {
            return true;
        }

        $currentOrgId = session('current_organization_id');
        
        // If no organization in session, try to get from authenticated user
        if (!$currentOrgId && auth()->check()) {
            $firstOrg = auth()->user()->activeOrganizations()->first();
            if ($firstOrg) {
                $currentOrgId = $firstOrg->id;
                session(['current_organization_id' => $currentOrgId]);
            }
        }

        // If still no organization, return false
        if (!$currentOrgId) {
            return false;
        }

        // OPTIMIZATION: Check session cache first
        $cacheKey = "module_enabled_{$currentOrgId}_{$module}";
        $cachedStatus = session($cacheKey);
        
        if ($cachedStatus !== null) {
            return $cachedStatus;
        }

        // Not in cache, fetch from database
        $settings = OrganizationSettings::where('organization_id', $currentOrgId)->first();
        
        if (!$settings) {
            // If no settings row exists yet (e.g. newly activated system),
            // all standard modules are enabled by default so the organization
            // admin/student experience is fully functional.
            session([$cacheKey => true]);
            return true;
        }

        // Check if module is enabled
        $hasColumn = 'has_' . $module;
        $isEnabled = $settings->$hasColumn ?? false;

        // Cache in session
        session([$cacheKey => $isEnabled]);

        return $isEnabled;
    }
}

if (!function_exists('enabled_modules')) {
    /**
     * Get array of all enabled modules for the current organization
     * OPTIMIZATION: Cache enabled modules list in session
     *
     * @return array
     */
    function enabled_modules(): array
    {
        $currentOrgId = session('current_organization_id');
        
        // If no organization in session, try to get from authenticated user
        if (!$currentOrgId && auth()->check()) {
            $firstOrg = auth()->user()->activeOrganizations()->first();
            if ($firstOrg) {
                $currentOrgId = $firstOrg->id;
                session(['current_organization_id' => $currentOrgId]);
            }
        }

        // If still no organization, return only permanent modules
        if (!$currentOrgId) {
            return OrganizationSettings::permanentModules();
        }

        // OPTIMIZATION: Check session cache first
        $cacheKey = "enabled_modules_{$currentOrgId}";
        $cachedModules = session($cacheKey);
        
        if ($cachedModules !== null) {
            return $cachedModules;
        }

        // Not in cache, fetch from database
        $settings = OrganizationSettings::where('organization_id', $currentOrgId)->first();
        
        if (!$settings) {
            // No settings found, only permanent modules are enabled
            $modules = OrganizationSettings::permanentModules();
            session([$cacheKey => $modules]);
            return $modules;
        }

        // Get enabled modules from settings
        $modules = $settings->enabledModules();

        // Cache in session
        session([$cacheKey => $modules]);

        return $modules;
    }
}

// ============================================================================
// ORGANIZATION TYPE HELPER FUNCTIONS
// ============================================================================

if (!function_exists('is_mother_org')) {
    /**
     * Check if the current organization is a Mother Org
     * OPTIMIZATION: Cache organization type in session
     *
     * @return bool
     */
    function is_mother_org(): bool
    {
        $currentOrgId = session('current_organization_id');
        
        // If no organization in session, try to get from authenticated user
        if (!$currentOrgId && auth()->check()) {
            $firstOrg = auth()->user()->activeOrganizations()->first();
            if ($firstOrg) {
                $currentOrgId = $firstOrg->id;
                session(['current_organization_id' => $currentOrgId]);
            }
        }

        // If still no organization, return false
        if (!$currentOrgId) {
            return false;
        }

        // OPTIMIZATION: Check session cache first
        $cacheKey = "org_type_{$currentOrgId}";
        $cachedType = session($cacheKey);
        
        if ($cachedType !== null) {
            return $cachedType === 'Mother Org';
        }

        // Not in cache, fetch from database
        $organization = \App\Models\Organization::find($currentOrgId);
        
        if (!$organization) {
            return false;
        }

        // Cache in session
        session([$cacheKey => $organization->type]);

        return $organization->isMotherOrg();
    }
}

if (!function_exists('is_sub_org')) {
    /**
     * Check if the current organization is a Sub-Org
     * OPTIMIZATION: Cache organization type in session
     *
     * @return bool
     */
    function is_sub_org(): bool
    {
        $currentOrgId = session('current_organization_id');
        
        // If no organization in session, try to get from authenticated user
        if (!$currentOrgId && auth()->check()) {
            $firstOrg = auth()->user()->activeOrganizations()->first();
            if ($firstOrg) {
                $currentOrgId = $firstOrg->id;
                session(['current_organization_id' => $currentOrgId]);
            }
        }

        // If still no organization, return false
        if (!$currentOrgId) {
            return false;
        }

        // OPTIMIZATION: Check session cache first
        $cacheKey = "org_type_{$currentOrgId}";
        $cachedType = session($cacheKey);
        
        if ($cachedType !== null) {
            return $cachedType === 'Sub-Org';
        }

        // Not in cache, fetch from database
        $organization = \App\Models\Organization::find($currentOrgId);
        
        if (!$organization) {
            return false;
        }

        // Cache in session
        session([$cacheKey => $organization->type]);

        return $organization->isSubOrg();
    }
}

if (!function_exists('can_access_student_pages')) {
    /**
     * Check if the current user can access student pages based on role and organization type
     * Rules:
     * - Mother Org: Dean, Advisers, Officers, Students all have access
     * - Sub-Org: Only Advisers and Officers have access (no student pages)
     *
     * @return bool
     */
    function can_access_student_pages(): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();
        $currentOrgId = session('current_organization_id');

        if (!$currentOrgId) {
            return false;
        }

        // Get user's role in the current organization
        $membership = $user->organizations()
            ->where('organizations.id', $currentOrgId)
            ->first();

        if (!$membership) {
            return false;
        }

        $userRole = $membership->pivot->role;

        // If Mother Org, all roles have access
        if (is_mother_org()) {
            return true;
        }

        // If Sub-Org, only Advisers and Officers have access (no student pages)
        if (is_sub_org()) {
            return in_array($userRole, ['Adviser', 'Officer']);
        }

        return false;
    }
}

if (!function_exists('show_student_navigation')) {
    /**
     * Check if student navigation should be shown
     * Student navigation is only shown for Mother Org
     *
     * @return bool
     */
    function show_student_navigation(): bool
    {
        return is_mother_org();
    }
}

if (!function_exists('show_dean_navigation')) {
    /**
     * Check if dean navigation should be shown
     * Dean navigation is only shown if user is Dean AND organization is Mother Org
     *
     * @return bool
     */
    function show_dean_navigation(): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();
        $currentOrgId = session('current_organization_id');

        if (!$currentOrgId) {
            return false;
        }

        // Get user's role in the current organization
        $membership = $user->organizations()
            ->where('organizations.id', $currentOrgId)
            ->first();

        if (!$membership) {
            return false;
        }

        $userRole = $membership->pivot->role;

        // Dean navigation only shows if user is Dean AND organization is Mother Org
        return $userRole === 'Dean' && is_mother_org();
    }
}

if (!function_exists('course_acronym')) {
    /**
     * Get the acronym for a course name
     */
    function course_acronym(?string $course): string
    {
        if (!$course) {
            return '';
        }
        
        $courseLower = strtolower(trim($course));
        
        // Exact mappings for common courses
        $map = [
            'bachelor of science in information technology' => 'BSIT',
            'bachelor of science in computer science' => 'BSCS',
            'bachelor of science in computer engineering' => 'BSCpE',
            'bachelor of science in industrial technology major in architectural drafting' => 'BSIT-AD',
            'bachelor of science in industrial technology major in electronics technology' => 'BSIT-ElxT',
            'bachelor of science in industrial technology major in electrical technology' => 'BSIT-EltT',
            'bachelor of science in industrial technology major in automotive technology' => 'BSIT-AT',
        ];
        
        if (isset($map[$courseLower])) {
            return $map[$courseLower];
        }
        
        // Dynamic fallback: extract uppercase letters or first letters of words
        $cleaned = preg_replace('/\b(of|in|and|major|to)\b/i', '', $course);
        $words = preg_split('/[\s-]+/', $cleaned);
        $acronym = '';
        foreach ($words as $word) {
            if (!empty($word)) {
                $acronym .= strtoupper($word[0]);
            }
        }
        
        return $acronym ?: strtoupper($course);
    }
}


// ============================================================================
// BROADCAST HELPER FUNCTIONS
// ============================================================================

if (!function_exists('safe_broadcast')) {
    /**
     * Broadcast an event without letting a Pusher connection failure
     * (e.g. connection timeout when the websocket server is unreachable)
     * break the request. Any BroadcastException/Throwable is logged instead.
     *
     * @param  mixed  $event
     * @param  bool  $toOthers
     * @return void
     */
    function safe_broadcast($event, bool $toOthers = false): void
    {
        try {
            $broadcast = broadcast($event);
            if ($toOthers) $broadcast->toOthers();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast failed: ' . $e->getMessage(), [
                'event' => get_class($event),
            ]);
        }
    }
}

// ============================================================================
// NAME DISPLAY HELPERS
// ============================================================================

if (!function_exists('title_case_name')) {
    /**
     * Convert a person's name to proper title case while preserving name
     * suffixes (Jr., Sr., II, III, IV, ...) and capitalization after
     * apostrophes and hyphens.
     *
     * e.g. "JUAN CARLOS DELA CRUZ" → "Juan Carlos Dela Cruz"
     *      "MARIA SANTOS JR."       → "Maria Santos Jr."
     *      "JUAN SANTOS PHD"        → "Juan Santos PhD"
     *      "MIA VILLARICA, DIT, LPT" → "Mia Villarica, DIT, LPT"
     *      "O'BRIEN"                → "O'Brien"
     *      "ANNA-MARIE"             → "Anna-Marie"
     */
    function title_case_name(?string $name): string
    {
        if ($name === null || trim($name) === '') {
            return '';
        }

        // Generational suffixes (Jr., Sr., II, III, IV, ...) are recognized in
        // any position, matching historical behavior.
        $generationalSuffixes = [
            'jr' => 'Jr.', 'sr' => 'Sr.',
            'junior' => 'Jr.', 'senior' => 'Sr.',
            'ii' => 'II', 'iii' => 'III', 'iv' => 'IV', 'v' => 'V',
            'vi' => 'VI', 'vii' => 'VII', 'viii' => 'VIII', 'ix' => 'IX', 'x' => 'X',
            '2nd' => '2nd', '3rd' => '3rd', '4th' => '4th', '5th' => '5th',
        ];

        // Academic degrees and professional credentials. These are only applied
        // to the trailing credential cluster — the last word of the name, or
        // comma-delimited items like "DIT, LPT" — and only when the name has at
        // least three tokens, so ordinary name words such as "Ma." (Maria) or
        // surnames like "Ma"/"Do" are never mistaken for degrees.
        $credentialSuffixes = [
            // Doctoral degrees
            'phd' => 'PhD', 'edd' => 'EdD', 'dba' => 'DBA', 'dit' => 'DIT',
            'dpa' => 'DPA', 'dsc' => 'DSc', 'scd' => 'ScD', 'dlitt' => 'DLitt',
            'littd' => 'LittD', 'dphil' => 'DPhil', 'deng' => 'DEng',
            'dmin' => 'DMin', 'dth' => 'DTh', 'thd' => 'ThD', 'std' => 'STD',
            'jd' => 'JD', 'jsd' => 'JSD', 'sjd' => 'SJD', 'dcl' => 'DCL',
            'lld' => 'LLD', 'md' => 'MD', 'do' => 'DO', 'dmd' => 'DMD',
            'dds' => 'DDS', 'dvm' => 'DVM', 'vmd' => 'VMD', 'pharmd' => 'PharmD',
            'psyd' => 'PsyD', 'dnp' => 'DNP', 'aud' => 'AuD', 'od' => 'OD',
            'dc' => 'DC', 'dpt' => 'DPT', 'dph' => 'DPH', 'drph' => 'DrPH',
            'dmgt' => 'DMgt', 'dtech' => 'DTech', 'dmus' => 'DMus', 'dma' => 'DMA',
            'dm' => 'DM', 'dd' => 'DD', 'dsn' => 'DSN',
            // Master's degrees
            'ma' => 'MA', 'ms' => 'MS', 'msc' => 'MSc', 'mba' => 'MBA',
            'mpa' => 'MPA', 'med' => 'MEd', 'edm' => 'EdM', 'mphil' => 'MPhil',
            'mres' => 'MRes', 'mfa' => 'MFA', 'mmus' => 'MMus', 'msw' => 'MSW',
            'mph' => 'MPH', 'msn' => 'MSN', 'meng' => 'MEng', 'march' => 'MArch',
            'macc' => 'MAcc', 'mfin' => 'MFin', 'mcs' => 'MCS', 'mism' => 'MISM',
            'mis' => 'MIS', 'mls' => 'MLS', 'mlis' => 'MLIS', 'mll' => 'MLL',
            'mth' => 'MTh', 'mm' => 'MM', 'mpm' => 'MPM', 'mscs' => 'MSCS',
            'mse' => 'MSE', 'msee' => 'MSEE', 'msme' => 'MSME', 'msce' => 'MSCE',
            'llm' => 'LLM',
            // Bachelor's degrees
            'ba' => 'BA', 'ab' => 'AB', 'bs' => 'BS', 'bsc' => 'BSc',
            'bba' => 'BBA', 'bbm' => 'BBM', 'bsa' => 'BSA', 'bsn' => 'BSN',
            'bsed' => 'BSEd', 'beed' => 'BEEd', 'bscs' => 'BSCS', 'bsit' => 'BSIT',
            'bsce' => 'BSCE', 'bsme' => 'BSME', 'bsee' => 'BSEE', 'bsche' => 'BSChE',
            'bsece' => 'BSECE', 'bsie' => 'BSIE', 'bsge' => 'BSGE', 'bsabe' => 'BSABE',
            'bscpe' => 'BSCpE', 'llb' => 'LLB', 'bl' => 'BL', 'bfa' => 'BFA',
            'bmus' => 'BMus', 'bpe' => 'BPE', 'bth' => 'BTh', 'bdiv' => 'BDiv',
            'bsw' => 'BSW', 'bcom' => 'BCom', 'bed' => 'BEd', 'beng' => 'BEng',
            'bj' => 'BJ',
            // Professional licenses and certifications (Philippine PRC + international)
            'lpt' => 'LPT', 'rn' => 'RN', 'rm' => 'RM', 'rmt' => 'RMT',
            'rph' => 'RPh', 'rnd' => 'RND', 'rsw' => 'RSW', 'rgc' => 'RGC',
            'rpsy' => 'RPsy', 'rpsych' => 'RPsych', 'rpm' => 'RPM', 'rl' => 'RL',
            'rrt' => 'RRT', 'rpt' => 'RPT', 'rot' => 'ROT', 'cpa' => 'CPA',
            'cma' => 'CMA', 'cia' => 'CIA', 'cisa' => 'CISA', 'cism' => 'CISM',
            'cissp' => 'CISSP', 'pmp' => 'PMP', 'cfa' => 'CFA', 'cfp' => 'CFP',
            'frm' => 'FRM', 'prm' => 'PRM',
            'ece' => 'ECE', 'ee' => 'EE', 'me' => 'ME', 'ce' => 'CE',
            'che' => 'ChE', 'ie' => 'IE', 'ale' => 'ALE', 'abe' => 'ABE',
            'pe' => 'PE', 'pece' => 'PECE', 'pie' => 'PIE', 'pme' => 'PME',
            'pee' => 'PEE', 'pce' => 'PCE', 'ree' => 'REE', 'rme' => 'RME',
            'rea' => 'REA', 'rce' => 'RCE', 'ge' => 'GE', 'se' => 'SE',
            'es' => 'ES', 'cr' => 'CR', 'rcrim' => 'RCrim', 'rg' => 'RG',
            'rpgeo' => 'RPGeo', 'uap' => 'UAP', 'pt' => 'PT', 'ot' => 'OT',
            'np' => 'NP',
            // Medical fellowships
            'fpcp' => 'FPCP', 'fpafp' => 'FPAFP', 'fpsp' => 'FPSP',
            'fpsmid' => 'FPSMID', 'fpcs' => 'FPCS', 'facs' => 'FACS',
            'facp' => 'FACP', 'facc' => 'FACC', 'fccp' => 'FCCP',
            'frcp' => 'FRCP', 'frcs' => 'FRCS', 'fams' => 'FAMS',
            // IT certifications
            'ccna' => 'CCNA', 'ccnp' => 'CCNP', 'ccie' => 'CCIE',
            'mcse' => 'MCSE', 'mcp' => 'MCP', 'rhce' => 'RHCE',
            'rhcsa' => 'RHCSA', 'lpic' => 'LPIC',
            // Religious orders
            'sj' => 'SJ', 'osb' => 'OSB', 'op' => 'OP', 'ofm' => 'OFM',
            'sdb' => 'SDB', 'cmf' => 'CMF', 'cssr' => 'CSSR', 'rv' => 'RV',
            // Professional titles used as post-nominals
            'atty' => 'Atty.', 'engr' => 'Engr.', 'arch' => 'Arch.',
        ];

        $titleWord = static function (string $word): string {
            // Capitalize the first letter of each segment so apostrophes and
            // hyphens keep their following letter uppercase: "O'BRIEN" → "O'Brien",
            // "ANNA-MARIE" → "Anna-Marie".
            $parts = preg_split("/(['-])/", $word, -1, PREG_SPLIT_DELIM_CAPTURE);
            $out = '';

            foreach ($parts as $part) {
                if ($part === "'" || $part === '-' || $part === '') {
                    $out .= $part;
                    continue;
                }

                $lower = mb_strtolower($part, 'UTF-8');
                $out .= mb_strtoupper(mb_substr($lower, 0, 1, 'UTF-8'), 'UTF-8')
                    . mb_substr($lower, 1, null, 'UTF-8');
            }

            return $out;
        };

        $words = preg_split('/\s+/', trim($name));
        $count = count($words);

        $mapWord = static function (string $word, int $index) use (
            $generationalSuffixes,
            $credentialSuffixes,
            $count,
            $titleWord
        ): string {
            $clean = strtolower(str_replace(['.', ','], '', $word));

            if (isset($generationalSuffixes[$clean])) {
                return $generationalSuffixes[$clean];
            }

            $isCredentialPosition = $count >= 3
                && ($index === $count - 1 || str_ends_with($word, ','));

            if ($isCredentialPosition && isset($credentialSuffixes[$clean])) {
                $mapped = $credentialSuffixes[$clean];

                // Keep the comma that separates a multi-credential list, e.g.
                // "DIT, LPT" → "DIT," + "LPT".
                if (str_ends_with($word, ',')) {
                    $mapped .= ',';
                }

                return $mapped;
            }

            return $titleWord($word);
        };

        return implode(' ', array_map($mapWord, $words, array_keys($words)));
    }
}

if (!function_exists('name_with_suffix')) {
    /**
     * Build a person's display name from its parts, always including the
     * suffix when present, and normalize it to title case.
     *
     * e.g. name_with_suffix('JUAN', 'D', 'DELA CRUZ', 'PHD') → "Juan D Dela Cruz PhD"
     */
    function name_with_suffix(?string $first, ?string $middle, ?string $last, ?string $suffix): string
    {
        $name = trim(trim((string) $first . ' ' . (string) $middle) . ' ' . (string) $last);

        $suffix = trim((string) $suffix);
        if ($suffix !== '') {
            $name .= ' ' . $suffix;
        }

        return title_case_name($name);
    }
}
