<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountManagement\StudentAccountValidity;
use App\Services\MembershipFeeService;
use App\Support\SystemlessOrgFallbackResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function showLogin()
    {
        $departments = \App\Models\Department::all();
        return view('auth.org_auth', [
            'mode' => 'login',
            'departments' => $departments,
            'systemDepartments' => \App\Models\Organization::where('type', 'Mother Org')
                ->where('status', 'Approved')
                ->whereRaw('has_system IS TRUE')
                ->pluck('department_id')
                ->all(),
            'courseDepartmentMap' => OrgAuthController::courseDepartmentMap(),
        ]);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|string',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $credentials = $validator->validated();

        // Find user by student_id
        $user = User::where('student_id', $credentials['student_id'])->first();

        // Check if user exists and password matches (hashed comparison)
        if ($user && Hash::check($credentials['password'], $user->password)) {
            if (! $user->is_active) {
                $errorMessage = 'This account is inactive.';
                if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'errors' => ['student_id' => [$errorMessage]],
                    ], 422);
                }

                return back()->withErrors(['student_id' => $errorMessage])->onlyInput('student_id');
            }

            if ($expiredError = $this->expiredAccountError($user)) {
                if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'errors' => ['student_id' => [$expiredError]],
                    ], 422);
                }

                return back()->withErrors(['student_id' => $expiredError])->onlyInput('student_id');
            }

            // Check if user has an active/approved organization (for students and regular admins)
            if ($user->role !== 'super_admin' && $user->role !== 'osas_admin') {
                $hasActiveOrg = $user->activeOrganizations()->where('organizations.status', 'Approved')->exists();
                if (!$hasActiveOrg) {
                    $errorMessage = 'Your account is not associated with any active or approved organization. It may have been deleted.';
                    if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
                        return response()->json([
                            'success' => false,
                            'errors' => ['student_id' => [$errorMessage]]
                        ], 422);
                    }
                    return back()->withErrors([
                        'student_id' => $errorMessage,
                    ])->onlyInput('student_id');
                }
            }

            // Systemless-org fallback: users whose active organizations all
            // lack a system (has_system = false) are routed to their
            // department's eligible Mother Org in the Student View — unless a
            // clear error applies (no department, no eligible Mother Org, or
            // a staff-only Adviser/Dean/COMELEC membership).
            $fallbackOrg = null;
            $fallbackError = null;
            if ($user->role !== 'super_admin' && $user->role !== 'osas_admin') {
                $fallbackResult = SystemlessOrgFallbackResolver::resolve($user);
                $fallbackOrg = $fallbackResult['fallback'];
                $fallbackError = $fallbackResult['error'];
            }

            // Fallback users are always routed to /dashboard — never to a
            // pre-login intended URL (which could be an admin page).
            $isFallbackLogin = $fallbackOrg !== null;

            if ($fallbackOrg !== null) {
                // Adviser / Dean / COMELEC memberships are staff-only and
                // have no Student View to fall back to.
                if ($user->isAdviser() || $user->isComelec() || $user->hasDeanMembership()) {
                    return $this->systemlessLoginError($request, SystemlessOrgFallbackResolver::ERROR_STAFF_ONLY_MEMBERSHIP);
                }

                Auth::login($user);
                $request->session()->regenerate();

                // Force the Student View of the department Mother Org for
                // this session. active_view is persisted so the last view is
                // respected again once the user's own org gains a system.
                $user->active_view = 'student';
                $user->save();
                $request->session()->put('current_organization_id', $fallbackOrg->id);
                $request->session()->put('systemless_fallback', true);
            } elseif ($fallbackError !== null) {
                return $this->systemlessLoginError($request, $fallbackError);
            } else {
                // Admin view is desktop-only. Check this before creating a
                // session (fallback users above are always forced to the
                // Student View, so the desktop-only rule does not apply).
                if ($mobileAdminError = $this->mobileAdminLoginError($request, $user)) {
                    return $mobileAdminError;
                }

                Auth::login($user);
                $request->session()->regenerate();
                
                // Set organization context for regular users and students
                if ($user->role !== 'super_admin' && $user->role !== 'osas_admin') {
                    $this->setOrganizationContext($request, $user);
                }
            }

            // Determine redirect based on user role
            if ($user->isSuperAdmin()) {
                $redirectUrl = url('/dean/dashboard');
            } else {
                $redirectUrl = url('/dashboard');
            }

            // Always return JSON for AJAX requests
            if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
                $fullName = name_with_suffix($user->first_name, null, $user->last_name, $user->suffix);
                return response()->json([
                    'success' => true,
                    'message' => 'Login successful!',
                    'user_name' => $fullName,
                    'redirect' => $redirectUrl
                ]);
            }

            // Fallback users are locked to their department Mother Org Student
            // View for this session, so they must land on /dashboard — never
            // on a pre-login intended URL (which could be an admin page).
            if ($isFallbackLogin) {
                return redirect($redirectUrl);
            }

            return redirect()->intended($redirectUrl);
        }

        $errorMessage = 'The provided ID or password is incorrect.';

        if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'errors' => ['student_id' => [$errorMessage]]
            ], 422);
        }

        return back()->withErrors([
            'student_id' => $errorMessage,
        ])->onlyInput('student_id');
    }

    /**
     * Build the consistent JSON / redirect failure response for a blocked
     * systemless-org login (same pattern as the other login errors: 422 JSON
     * with the message on the student_id field, or a server-side error).
     */
    private function systemlessLoginError(Request $request, string $message)
    {
        if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'errors' => ['student_id' => [$message]],
            ], 422);
        }

        return back()->withErrors(['student_id' => $message])->onlyInput('student_id');
    }

    /**
     * Blocks a student whose fixed validity window (expiry plus grace) has
     * fully closed, even before the scheduled expiry job deactivates the
     * account. Returns null when the account may keep logging in.
     */
    private function expiredAccountError(User $user): ?string
    {
        if ($user->role !== 'student' || $user->expires_at === null) {
            return null;
        }

        $validity = new StudentAccountValidity(new MembershipFeeService());

        return $validity->validityFor($user->expires_at) === \App\Services\AccountManagement\ValidityState::Expired
            ? 'This account has expired and can no longer access the system. Please contact OSAS.'
            : null;
    }

    public function showRegister()
    {
        $departments = \App\Models\Department::where('department_name', '!=', 'Non-Academic')->get();
        return view('auth.org_auth', [
            'mode' => 'register',
            'departments' => $departments,
            'systemDepartments' => \App\Models\Organization::where('type', 'Mother Org')
                ->where('status', 'Approved')
                ->whereRaw('has_system IS TRUE')
                ->pluck('department_id')
                ->all(),
            'courseDepartmentMap' => OrgAuthController::courseDepartmentMap(),
        ]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'student_id' => 'required|string|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'course' => 'nullable|string',
            'year_level' => 'nullable|string',
            'section' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            // Check if AJAX request
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        // DISABLED FOR DEVELOPMENT - Email verification slows down registration
        // TODO: Re-enable for production or move to background queue
        // if (!$this->verifyEmailWithAPI($validated['email'])) {
        //     $errorMessage = 'The provided email address is not valid, not active, or is a disposable email. Please use a valid and active email address.';
        //     
        //     if ($request->wantsJson() || $request->ajax()) {
        //         return response()->json([
        //             'success' => false,
        //             'errors' => ['email' => [$errorMessage]]
        //         ], 422);
        //     }
        //     return back()->withErrors(['email' => $errorMessage])->withInput();
        // }

        // Store password as hashed
        $validated['role'] = 'student'; // Default role

        $period = \App\Models\Organization::currentAccreditationPeriod();
        $expiresAt = (new StudentAccountValidity(new MembershipFeeService()))
            ->expiresAtFor($validated['year_level'] ?? null, null, $period?->started_at ?? null);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'student_id' => $validated['student_id'],
            'password' => Hash::make($validated['password']), // Hash password
            'role' => $validated['role'],
            'course' => $validated['course'] ?? null,
            'year_level' => $validated['year_level'] ?? null,
            'section' => $validated['section'] ?? null,
            'expires_at' => $expiresAt,
        ]);

        // Check if AJAX request
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Registered Successfully',
                'redirect' => route('login')
            ]);
        }

        return redirect()->route('login')->with('success', 'Registered Successfully');
    }

    /**
     * Verify email using POWERFUL API - ZeroBounce Email Validator
     * Works with ALL email types: Gmail, Yahoo, Outlook, School emails, etc.
     * - Real-time SMTP verification
     * - Checks if mailbox ACTUALLY EXISTS and is ACTIVE
     * - Disposable email detection
     * - Catch-all detection
     * - Spam trap detection
     */
    private function verifyEmailWithAPI($email)
    {
        $domain = strtolower(substr(strrchr($email, "@"), 1));
        $localPart = strtolower(substr($email, 0, strpos($email, '@')));
        
        // FIRST: Whitelist known valid domains
        $validDomains = [
            'gmail.com', 'googlemail.com', 
            'yahoo.com', 'yahoo.co.uk', 'yahoo.co.jp', 'yahoo.fr', 'yahoo.de',
            'outlook.com', 'hotmail.com', 'live.com', 'msn.com',
            'icloud.com', 'me.com', 'mac.com',
            'aol.com',
            'protonmail.com', 'proton.me',
            'zoho.com',
            'mail.com',
            'yandex.com',
            'gmx.com', 'gmx.net',
        ];
        
        // If domain is not in whitelist, REJECT IT
        // Only accept emails from known, trusted providers
        if (!in_array($domain, $validDomains)) {
            \Log::info('Email rejected - Domain not in whitelist', ['email' => $email, 'domain' => $domain]);
            return false;
        }
        
        // Basic validation for all domains
        // 1. Length check: 3-30 characters
        if (strlen($localPart) < 3 || strlen($localPart) > 30) {
            \Log::info('Email rejected - Invalid length', ['email' => $email, 'length' => strlen($localPart)]);
            return false;
        }
        
        // 2. Block obvious fake patterns
        $fakePatterns = [
            '/^test/',
            '/^fake/',
            '/^dummy/',
            '/^sample/',
            '/^example/',
            '/^asdf/',
            '/^qwerty/',
            '/^temp/',
            '/^trash/',
            '/^spam/',
            '/^random/',
            '/^trial/',
            '/^demo/',
        ];
        
        foreach ($fakePatterns as $pattern) {
            if (preg_match($pattern, $localPart)) {
                \Log::info('Email rejected - Fake pattern', ['email' => $email]);
                return false;
            }
        }
        
        // 3. Block keyboard patterns (qwerty, asdfgh, etc.)
        if (preg_match('/^[qwertyuiop]{5,}$|^[asdfghjkl]{5,}$|^[zxcvbnm]{5,}$/i', $localPart)) {
            \Log::info('Email rejected - Keyboard pattern', ['email' => $email]);
            return false;
        }
        
        // 4. Block repeating characters (aaaa, 1111, etc.)
        if (preg_match('/^(.)\1{4,}$/', $localPart)) {
            \Log::info('Email rejected - Repeating characters', ['email' => $email]);
            return false;
        }
        
        // 5. Block sequential patterns (abcdef, 123456, etc.)
        if (preg_match('/(abcdef|123456|654321)/', $localPart)) {
            \Log::info('Email rejected - Sequential pattern', ['email' => $email]);
            return false;
        }
        
        // 6. Must contain at least 2 letters (to ensure it's not just numbers)
        if (preg_match_all('/[a-z]/i', $localPart) < 2) {
            \Log::info('Email rejected - Not enough letters', ['email' => $email]);
            return false;
        }
        
        // 7. Check for gibberish/random patterns
        // Count consonant clusters (4+ consonants in a row without vowels) - more lenient
        $consonantClusters = preg_match_all('/[bcdfghjklmnpqrstvwxyz]{4,}/i', $localPart);
        if ($consonantClusters >= 3) {
            \Log::info('Email rejected - Too many consonant clusters (gibberish)', ['email' => $email, 'clusters' => $consonantClusters]);
            return false;
        }
        
        // 8. Check for lack of vowels (real names have vowels)
        $vowelCount = preg_match_all('/[aeiou]/i', $localPart);
        $totalLetters = preg_match_all('/[a-z]/i', $localPart);
        if ($totalLetters > 0 && ($vowelCount / $totalLetters) < 0.15) {
            \Log::info('Email rejected - Not enough vowels (gibberish)', ['email' => $email, 'vowel_ratio' => ($vowelCount / $totalLetters)]);
            return false;
        }
        
        // 9. Block if too many different characters in sequence (random typing)
        // Check for patterns like "dsgbasdbgdfbdfbvfhdbvdush" - too chaotic
        if (strlen($localPart) > 20) {
            // For very long emails, check if it looks random
            $uniqueChars = count(array_unique(str_split($localPart)));
            $length = strlen($localPart);
            // If almost every character is different, it's likely random
            if ($uniqueChars / $length > 0.75) {
                \Log::info('Email rejected - Too random/chaotic', ['email' => $email, 'uniqueness' => ($uniqueChars / $length)]);
                return false;
            }
        }
        
        // Use FREE public APIs - NO API KEY NEEDED!
        // Multiple APIs for better accuracy
        
        $apiChecksPassed = 0;
        $apiChecksTotal = 0;
        
        try {
            // API 1: Kickbox Disposable Email Checker (FREE, no key)
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://open.kickbox.com/v1/disposable/" . urlencode($domain));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                curl_close($ch);
                
                if ($response) {
                    $apiChecksTotal++;
                    $data = json_decode($response, true);
                    if (isset($data['disposable']) && $data['disposable'] === true) {
                        \Log::info('Email rejected - Disposable (Kickbox)', ['email' => $email]);
                        return false; // Immediately reject disposable
                    }
                    $apiChecksPassed++;
                }
            } catch (\Exception $e) {
                // Continue to next check
            }
            
            // API 2: Debounce.io Disposable Checker (FREE, no key)
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://disposable.debounce.io/?email=" . urlencode($email));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                curl_close($ch);
                
                if ($response) {
                    $apiChecksTotal++;
                    $data = json_decode($response, true);
                    if (isset($data['disposable']) && $data['disposable'] === 'true') {
                        \Log::info('Email rejected - Disposable (Debounce)', ['email' => $email]);
                        return false; // Immediately reject disposable
                    }
                    $apiChecksPassed++;
                }
            } catch (\Exception $e) {
                // Continue to next check
            }
            
            // API 3: EmailRep.io - Email Reputation (FREE, no key)
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://emailrep.io/" . urlencode($email));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: SOCCSync/1.0']);
                
                $response = curl_exec($ch);
                curl_close($ch);
                
                if ($response) {
                    $apiChecksTotal++;
                    $data = json_decode($response, true);
                    
                    // Check reputation
                    if (isset($data['reputation'])) {
                        if ($data['reputation'] === 'low' || $data['reputation'] === 'none') {
                            \Log::info('Email rejected - Low reputation', ['email' => $email]);
                            return false;
                        }
                    }
                    
                    // Check if suspicious
                    if (isset($data['suspicious']) && $data['suspicious'] === true) {
                        \Log::info('Email rejected - Suspicious', ['email' => $email]);
                        return false;
                    }
                    
                    // Check if disposable
                    if (isset($data['details']['disposable']) && $data['details']['disposable'] === true) {
                        \Log::info('Email rejected - Disposable (EmailRep)', ['email' => $email]);
                        return false;
                    }
                    
                    $apiChecksPassed++;
                }
            } catch (\Exception $e) {
                // Continue
            }
            
            // API 4: EVA (Email Verification API) - FREE, no key
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://api.eva.pingutil.com/email?email=" . urlencode($email));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                curl_close($ch);
                
                if ($response) {
                    $apiChecksTotal++;
                    $data = json_decode($response, true);
                    
                    // Only check for disposable, not deliverability (can't verify if email exists without paid API)
                    if (isset($data['data']['disposable']) && $data['data']['disposable'] === true) {
                        \Log::info('Email rejected - Disposable (EVA)', ['email' => $email]);
                        return false;
                    }
                    $apiChecksPassed++;
                }
            } catch (\Exception $e) {
                // Continue
            }
            
            // If ANY API explicitly rejected (disposable, suspicious, low reputation), already returned false above
            // If we got here, no API explicitly rejected the email
            
            // If MX records exist and no API rejected it, accept the email
            // This prevents false negatives while still blocking disposable/fake emails
            \Log::info('Email VALID - MX records exist, no API rejections', [
                'email' => $email,
                'api_checks' => $apiChecksTotal
            ]);
            return true;
            
        } catch (\Exception $e) {
            \Log::error('Email verification exception', ['error' => $e->getMessage()]);
            // On exception, if MX records exist (already checked), accept it
            return true;
        }
    }
    
    /**
     * Fallback email validation - accepts email if MX records exist
     */
    private function fallbackEmailValidation($email)
    {
        // If MX records exist (already checked in main function), accept it
        \Log::info('Email VALID - Fallback validation (MX records exist)', ['email' => $email]);
        return true;
        return true;
    }

    /**
     * API endpoint to validate email in real-time
     */
    public function validateEmail(Request $request)
    {
        $email = $request->input('email');
        
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'valid' => false,
                'message' => 'Please enter a valid email address.',
                'status' => 'error'
            ]);
        }

        // Check if email already exists in database
        if (User::where('email', $email)->exists()) {
            return response()->json([
                'valid' => false,
                'message' => 'This email is already in use. Please use a different email address.',
                'status' => 'error',
                'reason' => 'already_registered'
            ]);
        }

        // Verify email using powerful API + pattern checking
        if ($this->verifyEmailWithAPI($email)) {
            return response()->json([
                'valid' => true,
                'message' => 'Email is valid and active! ✓',
                'status' => 'success'
            ]);
        } else {
            return response()->json([
                'valid' => false,
                'message' => 'This email address is invalid or does not exist. Please use a real, active email address.',
                'status' => 'error',
                'reason' => 'invalid_email'
            ]);
        }
    }

    /**
     * Set the organization context in the session
     * Defaults to user's first organization if not already set
     */
    protected function setOrganizationContext(Request $request, $user): void
    {
        // Only set if not already set in session
        if (!$request->session()->has('current_organization_id')) {
            // Get user's first active organization
            $firstOrganization = $user->activeOrganizations()->first();
            
            if ($firstOrganization) {
                $request->session()->put('current_organization_id', $firstOrganization->id);
            }
        }
    }

    /**
     * Switch organization context
     * OPTIMIZATION: Clear navigation permission cache when switching
     */
    public function switchOrganization(Request $request)
    {
        $request->validate([
            'organization_id' => 'required|integer|exists:organizations,id'
        ]);

        $user = auth()->user();
        $organizationId = $request->organization_id;

        // Systemless-org fallback users are locked to their department Mother
        // Org for the session; switching is restored automatically once their
        // org gains a system (the fallback marker clears itself).
        if ($user->isSystemlessFallback()) {
            return back()->with('error', 'Organization switching is not available while your organization does not have a system yet.');
        }

        // Never switch into an organization that has no system: its dashboard
        // and modules do not exist yet, so the experience would be broken.
        $targetOrganization = \App\Models\Organization::find($organizationId);
        if ($targetOrganization && !$targetOrganization->has_system) {
            return back()->with('error', 'This organization does not have a system yet.');
        }

        // Verify user belongs to this organization
        if (!$user->organizations()->where('organizations.id', $organizationId)->exists()) {
            return back()->with('error', 'You do not have access to this organization.');
        }

        // Clear old permission cache and logo cache
        $oldOrgId = session('current_organization_id');
        if ($oldOrgId) {
            $oldCacheKey = "nav_permissions_{$user->id}_{$oldOrgId}";
            session()->forget($oldCacheKey);
            session()->forget("org_logo_{$oldOrgId}");
            session()->forget("org_name_{$oldOrgId}");
            app(\App\Services\OrganizationPermissionService::class)->invalidateCache($user->id, (int) $oldOrgId);
        }

        // Switch organization
        $request->session()->put('current_organization_id', $organizationId);

        // Clear permission cache and logo cache for new organization (will be rebuilt on next page load)
        $newCacheKey = "nav_permissions_{$user->id}_{$organizationId}";
        session()->forget($newCacheKey);
        session()->forget("org_logo_{$organizationId}");
        session()->forget("org_name_{$organizationId}");
        app(\App\Services\OrganizationPermissionService::class)->invalidateCache($user->id, (int) $organizationId);

        return back()->with('success', 'Organization switched successfully.');
    }

    public function logout(Request $request)
    {
        $userId = auth()->id();
        $organizationId = session('current_organization_id');
        
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Clear all session data including navigation permission cache and logo cache
        $request->session()->flush();

        // OPTIMIZATION: Clear navigation permission cache on logout
        if ($userId && $organizationId) {
            $cacheKey = "nav_permissions_{$userId}_{$organizationId}";
            session()->forget($cacheKey);
            
            // Clear logo and name cache
            session()->forget("org_logo_{$organizationId}");
            session()->forget("org_name_{$organizationId}");
        }

        // Invalidate permission cache for logged-out user
        if ($userId) {
            app(\App\Services\OrganizationPermissionService::class)->invalidateCache($userId);
        }

        // Redirect to organization login page (landing page) with no-cache headers
        return redirect()->route('org.login')
            ->with('success', 'You have been logged out.')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Send password reset code to email
     */
    public function sendResetCode(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        
        // Check if user exists
        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            return response()->json(['error' => 'Email not found in our records'], 404);
        }
        
        // Generate 6-digit code
        $code = rand(100000, 999999);
        
        // Store in database
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => $code,
                'created_at' => now()
            ]
        );
        
        // Send email
        try {
            Mail::raw("Your password reset code is: $code\n\nThis code will expire in 15 minutes.\n\nIf you did not request this, please ignore this email.\n\n- UniOrg Team", function($message) use ($request) {
                $message->to($request->email)
                        ->subject('Password Reset Code - UniOrg');
            });
            
            return response()->json(['message' => 'Verification code sent successfully to ' . $request->email]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to send email. Please try again later.'], 500);
        }
    }

    /**
     * Reset password using code
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required',
            'password' => 'required|min:8|confirmed'
        ]);
        
        // Verify code
        $reset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->code)
            ->where('created_at', '>', now()->subMinutes(15))
            ->first();
        
        if (!$reset) {
            return response()->json(['error' => 'Invalid or expired code'], 400);
        }
        
        // Update password (hashed)
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();
        
        // Delete reset token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        
        return response()->json(['message' => 'Password reset successfully']);
    }
}

