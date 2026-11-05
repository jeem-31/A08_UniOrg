<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\OcrService;
use App\Services\GoogleCloudVisionService;
use App\Models\User;
use App\Helpers\NameParser;
use App\Services\AccountManagement\StudentAccountValidity;
use App\Services\MembershipFeeService;
use App\Support\SystemlessOrgFallbackResolver;
use App\Mail\OtpVerificationMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class OrgAuthController extends Controller
{
    public function showLoginRegister()
    {
        $departments = \App\Models\Department::where('department_name', '!=', 'Non-Academic')
            ->orderBy('department_name')
            ->get();
        return view('auth.org_auth', [
            'mode' => 'login',
            'departments' => $departments,
            'systemDepartments' => $this->getSystemDepartments(),
            'courseDepartmentMap' => self::courseDepartmentMap(),
        ]);
    }
    
    public function showRegister()
    {
        $departments = \App\Models\Department::where('department_name', '!=', 'Non-Academic')
            ->orderBy('department_name')
            ->get();
        return view('auth.org_auth', [
            'mode' => 'register',
            'departments' => $departments,
            'systemDepartments' => $this->getSystemDepartments(),
            'courseDepartmentMap' => self::courseDepartmentMap(),
        ]);
    }

    /**
     * Keyword map of courses to the department they belong to.
     * Order matters: the first department whose keyword matches wins.
     *
     * @return array<int, list<string>>
     */
    public static function courseDepartmentMap(): array
    {
        return [
            // CCS
            7 => ['computer science', 'bscs', 'information technology', 'bsit', 'information systems', 'bsis'],
            // COE
            1 => ['civil engineering', 'bsce', 'mechanical engineering', 'bsme', 'electrical engineering', 'bsee', 'electronics engineering', 'bsece', 'computer engineering', 'bscpe', 'chemical engineering', 'bsche', 'engineering'],
            // CIHTM
            2 => ['hospitality', 'tourism', 'hotel', 'bshrm', 'bstm', 'bshm'],
            // CONAH
            3 => ['nursing', 'nurse', 'bsn', 'pharmacy', 'medical technology', 'bsmt', 'radiology', 'bsrt', 'physical therapy', 'bspt', 'allied health'],
            // CBAA
            4 => ['accountancy', 'bsa', 'business administration', 'bsba', 'accounting', 'public administration', 'financial management', 'marketing management', 'entrepreneurship', 'bsentrep', 'office administration'],
            // CIT
            5 => ['industrial technology', 'bsit-tech', 'culinary', 'food technology', 'bsfoodtech', 'garments', 'automotive', 'electrical technology', 'electronics technology', 'drafting', 'welding', 'refrigeration'],
            // CTE
            6 => ['teacher', 'education', 'bsed', 'beed', 'btte', 'btled', 'secondary education', 'elementary education', 'physical education', 'bspe', 'home economics', 'industrial arts'],
            // CAS
            8 => ['broadcasting', 'chemistry', 'arts', 'sciences', 'bsmath', 'mathematics', 'bsphysics', 'physics', 'bsbio', 'biology', 'abcomm', 'communication', 'bapsychology', 'psychology', 'liberal arts', 'ba '],
            // CCJE
            9 => ['criminology', 'bscrim', 'criminal justice', 'forensics'],
        ];
    }

    /**
     * Resolve a course string to its department ID, or null when unknown.
     */
    public static function courseToDepartmentId(?string $course): ?int
    {
        $c = strtolower(trim((string) $course));

        if ($c === '') {
            return null;
        }

        foreach (self::courseDepartmentMap() as $departmentId => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($c, $keyword)) {
                    return $departmentId;
                }
            }
        }

        return null;
    }

    /**
     * Get department IDs whose Mother Org has an active system.
     */
    private function getSystemDepartments()
    {
        return \App\Models\Organization::where('type', 'Mother Org')
            ->where('status', 'Approved')
            ->whereRaw('has_system IS TRUE')
            ->pluck('department_id')
            ->all();
    }
    
    public function uploadCor(Request $request, OcrService $ocrService, GoogleCloudVisionService $vision)
    {
        $request->validate([
            'cor_file' => 'required|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        $file = $request->file('cor_file');
        $isImage = in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png'], true);

        try {
            $path = $file->store('temp', 'local');
            $fullPath = storage_path('app/' . $path);

            try {
                // Primary: Fast Optimized Local Tesseract OCR
                $extractedText = $ocrService->basahinAngPicture($fullPath);
                $ocrMethod = 'tesseract';

                // Standby: Google Cloud Vision (available if enabled and extra OCR needed)
                if (empty(trim($extractedText)) && $vision->isEnabled()) {
                    try {
                        $result = $isImage
                            ? $vision->extractText($fullPath)
                            : $vision->extractTextFromPdf($fullPath);

                        if (!empty($result['success']) && !empty(trim($result['text'] ?? ''))) {
                            $extractedText = $result['text'];
                            $ocrMethod = 'google_cloud_vision';
                            \Log::info('COR Upload: Using Google Cloud Vision (Standby)', [
                                'confidence' => $result['confidence'] ?? 'N/A',
                            ]);
                        }
                    } catch (\Throwable $e) {
                        \Log::warning('Standby Google Cloud Vision skipped: ' . $e->getMessage());
                    }
                }

                $fullName = $this->extractStudentName($extractedText);

                // Fix common OCR mistakes for Roman numerals
                $fullName = $this->fixOcrRomanNumerals($fullName);

                $parsedName = NameParser::parse($fullName);

                $studentNumber = $this->extractStudentNumber($extractedText);

                // Reject files that are not (or do not read like) a COR.
                $textEmpty = trim((string) $extractedText) === '';
                $score = self::corScore($extractedText);
                $fieldsMissing = $fullName === '' || $studentNumber === '';

                if ($textEmpty) {
                    return $this->corUploadError($isImage, true);
                }
                if ($score < self::COR_SCORE_THRESHOLD) {
                    return $this->corUploadError($isImage, false);
                }
                if ($fieldsMissing) {
                    return $this->corUploadError($isImage, true);
                }

                $data = [
                    'first_name' => $parsedName['first_name'],
                    'middle_name' => $parsedName['middle_name'],
                    'last_name' => $parsedName['last_name'],
                    'suffix' => $parsedName['suffix'],
                    'student_name' => $fullName, // For backward compatibility
                    'student_number' => $studentNumber,
                    'course' => $this->extractCourse($extractedText),
                    'year_level' => $this->extractYearLevel($extractedText),
                    'section' => $this->extractSection($extractedText),
                    'semester' => $this->extractSemester($extractedText),
                    'school_year' => $this->extractSchoolYear($extractedText),
                ];

                // Add debug info
                \Log::info('COR Upload Debug', [
                    'ocr_method' => $ocrMethod,
                    'extracted_full_name' => $fullName,
                    'parsed_name' => $parsedName,
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'raw_text' => $extractedText,
                    'debug' => [
                        'ocr_method' => $ocrMethod,
                        'extracted_full_name' => $fullName,
                        'parsed_first' => $parsedName['first_name'],
                        'parsed_middle' => $parsedName['middle_name'],
                        'parsed_last' => $parsedName['last_name'],
                        'parsed_suffix' => $parsedName['suffix'],
                    ]
                ]);
            } finally {
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }
        } catch (\Exception $e) {
            \Log::error('COR Upload failed', [
                'message' => $e->getMessage(),
                'original_name' => $file->getClientOriginalName(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'The COR could not be processed. Please try again.'
            ], 422);
        }
    }

    /**
     * Minimum number of COR evidence signals required to accept a file.
     */
    private const COR_SCORE_THRESHOLD = 4;

    /**
     * Score how strongly an OCR'd text resembles an LSPU Certificate of
     * Registration. Each matching signal adds one point.
     */
    public static function corScore(?string $text): int
    {
        $t = strtolower((string) $text);

        if (trim($t) === '') {
            return 0;
        }

        $score = 0;

        if (preg_match('/student\s*name/i', $t)) {
            $score++;
        }
        if (preg_match('/student\s*(?:no\.?|number|id|no)/i', $t)) {
            $score++;
        }
        if (preg_match('/\b\d{2,4}-\d{4,6}\b|\b\d{2}-\d{4}-\d{4}\b/', $t)) {
            $score++;
        }
        if (preg_match('/certificate\s+of\s+registr\w*/i', $t)) {
            $score++;
        }
        if (preg_match('/\ba\.\s*y\.|semester|sem\b/i', $t)) {
            $score++;
        }
        if (str_contains($t, 'course') || str_contains($t, 'year level') || str_contains($t, 'bachelor')) {
            $score++;
        }
        if (preg_match('/lspu|laguna\s+state\s+polytechnic|polytechnic\s+university/i', $t)) {
            $score++;
        }

        return $score;
    }

    /**
     * Build the rejection response for a file that is not a readable COR.
     * Images may just be blurry, so ask for a clearer one; PDFs are usually
     * clear, so a rejected PDF is simply not a COR.
     */
    private function corUploadError(bool $isImage, bool $unreadable)
    {
        $message = $isImage
            ? 'This file does not appear to be a valid COR. Please upload a clearer image or PDF of your COR.'
            : 'This file does not appear to be a valid COR. Please upload your Certificate of Registration.';

        return response()->json([
            'success' => false,
            'message' => $message
        ], 422);
    }
    
    /**
     * Fix common OCR mistakes for Roman numerals
     * OCR often misreads II as IT, III as IIT, etc.
     */
    private function fixOcrRomanNumerals($name)
    {
        $corrections = [
            '/\s+IT$/i' => ' II',      // "IT" → "II"
            '/\s+IIT$/i' => ' III',    // "IIT" → "III"
            '/\s+ITI$/i' => ' III',    // "ITI" → "III"
            '/\s+IIII$/i' => ' IV',    // "IIII" → "IV"
            '/\s+I\s*T$/i' => ' II',   // "I T" → "II"
            '/\s+I\s*I\s*T$/i' => ' III', // "I I T" → "III"
        ];
        
        foreach ($corrections as $pattern => $replacement) {
            $name = preg_replace($pattern, $replacement, $name);
        }
        
        return $name;
    }
    
    public function register(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:5',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:10',
            'student_number' => 'required|string|unique:users,student_id',
            'course' => 'required|string',
            'year_level' => 'required|string',
            'school_year' => 'nullable|string|max:20',
            'section' => 'required|string',
            'department_id' => 'required|exists:departments,id',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        // The selected department must match the course whenever the course
        // is known. Unknown courses are allowed to pick any department.
        $expectedDepartmentId = self::courseToDepartmentId($request->course);
        if ($expectedDepartmentId !== null && (int) $request->department_id !== $expectedDepartmentId) {
            $expectedDepartment = \App\Models\Department::find($expectedDepartmentId);
            $errorMessage = 'Department does not match this course.';
            if ($expectedDepartment) {
                $errorMessage .= ' It belongs to ' . $expectedDepartment->department_name . '.';
            }
            $errorMessage .= ' Please select the correct department.';

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => ['department_id' => [$errorMessage]]
                ], 422);
            }
            return back()->withErrors(['department_id' => $errorMessage])->withInput();
        }

        // Find the department's Mother Org
        $motherOrg = \App\Models\Organization::where('department_id', $request->department_id)
            ->where('type', 'Mother Org')
            ->where('status', 'Approved')
            ->first();

        // Block registration until the organization has a system created
        if (!$motherOrg || !$motherOrg->has_system) {
            $errorMessage = 'Your organization does not have a system yet. Please wait until your organization\'s system has been created before registering.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => ['department_id' => [$errorMessage]]
                ], 422);
            }
            return back()->with('error', $errorMessage)->withInput();
        }

        // Check if student number exists in the enrolled students list.
        // Students can only register if they have been enrolled by OSAS.
        $isEnrolled = \App\Models\EnrolledStudent::where('student_id_number', $request->student_number)->exists();
        if (!$isEnrolled) {
            $errorMessage = 'Your student ID is not found in the enrolled students list. Please contact OSAS to verify your enrollment.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => ['student_number' => [$errorMessage]]
                ], 422);
            }
            return back()->withErrors(['student_number' => $errorMessage])->withInput();
        }

        // Security: the account is NOT created yet. A 6-digit OTP is sent to the
        // provided email first so we can be sure the email actually belongs to
        // the registrant. Only after the OTP is verified is the account created.
        try {
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // middle_name keeps the full middle name; middle_initial stores only
            // the initial with a dot (e.g. "P.") even if the dot was removed.
            $middleInitial = $this->normalizeMiddleInitial($request->middle_initial, $request->middle_name);

            $registrationData = [
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'middle_initial' => $middleInitial,
                'last_name' => $request->last_name,
                'suffix' => $request->suffix,
                'student_number' => $request->student_number,
                'course' => $request->course,
                'year_level' => $request->year_level,
                'school_year' => $request->school_year,
                'section' => $request->section,
                'department_id' => $request->department_id,
                'email' => $request->email,
                'password' => $request->password,
            ];

            DB::table('registration_verifications')->updateOrInsert(
                ['email' => $request->email],
                [
                    'otp_hash' => Hash::make($otp),
                    'data' => json_encode($registrationData),
                    'attempts' => 0,
                    'expires_at' => now()->addMinutes(10),
                    'last_sent_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            Mail::to($request->email)->send(new OtpVerificationMail($otp));

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'needs_otp' => true,
                    'message' => 'A 6-digit verification code has been sent to ' . $request->email . '. Enter the code to complete your registration.',
                    'redirect' => route('org.login')
                ]);
            }

            return back()->with('success', 'A 6-digit verification code has been sent to ' . $request->email . '. Enter the code to complete your registration.');
        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => ['email' => ['Registration failed. Please try again.']]
                ], 500);
            }
            return back()->with('error', 'Registration failed: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Verify the OTP sent to the registrant's email and create the account.
     * The account is only created (and only becomes login-able) once the
     * correct code is entered.
     */
    public function verifyOtp(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $verification = DB::table('registration_verifications')
            ->where('email', $request->email)
            ->first();

        if (!$verification) {
            return $this->otpFailure($request, 'No pending registration found for this email. Please register again.', 'email');
        }

        if ($verification->attempts >= 5) {
            return $this->otpFailure($request, 'Too many incorrect attempts. Please request a new code.', 'otp');
        }

        if (now()->greaterThan($verification->expires_at)) {
            return $this->otpFailure($request, 'This code has expired. Please request a new code.', 'otp');
        }

        if (!Hash::check($request->otp, $verification->otp_hash)) {
            DB::table('registration_verifications')
                ->where('email', $request->email)
                ->increment('attempts');

            $attemptsLeft = 5 - ($verification->attempts + 1);
            return $this->otpFailure($request, 'The code you entered is incorrect. ' . $attemptsLeft . ' attempt(s) left.', 'otp');
        }

        try {
            $data = is_string($verification->data)
                ? json_decode($verification->data, true)
                : (array) $verification->data;

            // Re-check uniqueness right before creating the account, in case
            // the same ID number or email was registered while the OTP was pending.
            if (User::where('student_id', $data['student_number'])->exists() ||
                User::where('email', $data['email'])->exists()) {
                DB::table('registration_verifications')->where('email', $request->email)->delete();
                return $this->otpFailure($request, 'This student ID or email has already been registered. Please login instead.', 'email');
            }

            $user = $this->createUserFromRegistration($data);

            DB::table('registration_verifications')->where('email', $request->email)->delete();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email verified! Registration complete. You can now login with your credentials.',
                    'redirect' => route('org.login')
                ]);
            }

            return redirect()->route('org.login')->with('success', 'Email verified! Registration complete. You can now login with your credentials.');
        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => ['email' => ['Registration failed. Please try again.']]
                ], 500);
            }
            return back()->with('error', 'Registration failed: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Resend the OTP for a pending registration, with a 60-second cooldown.
     */
    public function resendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $verification = DB::table('registration_verifications')
            ->where('email', $request->email)
            ->first();

        if (!$verification) {
            return response()->json(['error' => 'No pending registration found for this email. Please register again.'], 404);
        }

        $lastSentAt = $verification->last_sent_at ? \Carbon\Carbon::parse($verification->last_sent_at) : null;
        if ($lastSentAt && $lastSentAt->gt(now()->subSeconds(60))) {
            return response()->json(['error' => 'Please wait a moment before requesting a new code.'], 429);
        }

        try {
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            DB::table('registration_verifications')
                ->where('email', $request->email)
                ->update([
                    'otp_hash' => Hash::make($otp),
                    'attempts' => 0,
                    'expires_at' => now()->addMinutes(10),
                    'last_sent_at' => now(),
                    'updated_at' => now(),
                ]);

            Mail::to($request->email)->send(new OtpVerificationMail($otp));

            return response()->json(['success' => true, 'message' => 'A new verification code has been sent to ' . $request->email . '.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to send email. Please try again later.'], 500);
        }
    }

    /**
     * Build a consistent JSON / redirect failure response for OTP steps.
     */
    private function otpFailure(Request $request, string $message, string $field = 'otp')
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'errors' => [$field => [$message]]
            ], 422);
        }
        return back()->withErrors([$field => $message])->withInput();
    }

    /**
     * Create the student account and auto-join the department's Mother Org.
     */
    private function createUserFromRegistration(array $data)
    {
        $motherOrg = \App\Models\Organization::where('department_id', $data['department_id'])
            ->where('type', 'Mother Org')
            ->where('status', 'Approved')
            ->first();

        $period = \App\Models\Organization::currentAccreditationPeriod();
        $periodStart = $period?->started_at ?? null;
        $expiresAt = (new StudentAccountValidity(new MembershipFeeService()))
            ->expiresAtFor($data['year_level'], $data['school_year'] ?? null, $periodStart);

        // middle_name keeps the full middle name; middle_initial stores only
        // the initial with a dot (e.g. "P.") even if the dot was removed.
        $middleInitial = $this->normalizeMiddleInitial(
            $data['middle_initial'] ?? null,
            $data['middle_name'] ?? null
        );

        $user = User::create([
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'middle_initial' => $middleInitial,
            'last_name' => $data['last_name'],
            'suffix' => $data['suffix'] ?? null,
            'student_id' => $data['student_number'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'student',
            'course' => $data['course'],
            'year_level' => $data['year_level'],
            'section' => $data['section'],
            'department_id' => $data['department_id'],
            'expires_at' => $expiresAt,
        ]);

        // Auto-join Mother Org of the department
        if ($data['department_id'] && $motherOrg) {
            $motherOrg->users()->attach($user->id, [
                'role' => 'Member',
                'membership_status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $user;
    }

    /**
     * Reduce a middle name to a single uppercase initial with a dot (e.g. "P.").
     * Prefers the explicitly entered initial, otherwise derives it from the
     * first letter of the full middle name.
     */
    private function normalizeMiddleInitial(?string $middleInitial, ?string $middleName): ?string
    {
        $value = trim((string) $middleInitial);

        if ($value === '') {
            $value = trim((string) $middleName);
        }

        if ($value === '') {
            return null;
        }

        return strtoupper(mb_substr($value, 0, 1)) . '.';
    }
    
    public function login(Request $request)
    {
        $validator = \Validator::make($request->all(), [
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

        // Find user by student_id
        $user = User::where('student_id', $request->student_id)->first();

        // Check if user exists and password matches (hashed comparison)
        if ($user && Hash::check($request->password, $user->password)) {
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
                    return back()->with('error', $errorMessage)->onlyInput('student_id');
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

                // Set organization context in session immediately upon login
                if ($user->role !== 'super_admin' && $user->role !== 'osas_admin') {
                    $firstOrganization = $user->activeOrganizations()->first();
                    if ($firstOrganization) {
                        $request->session()->put('current_organization_id', $firstOrganization->id);
                    }
                }
            }

            // Determine redirect based on user role
            if ($user->isSuperAdmin()) {
                $redirectUrl = url('/dean/dashboard');
            } else {
                // All students and regular users go to main dashboard
                $redirectUrl = url('/dashboard');
            }

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
    
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('org.login');
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
    
    // Helper methods
    private function extractFirstName($text)
    {
        $fullName = $this->extractStudentName($text);
        if (empty($fullName)) return '';
        
        $names = preg_split('/\s+/', trim($fullName));
        $count = count($names);
        
        if ($count == 2) {
            // "First Last"
            return $names[0];
        } elseif ($count == 3) {
            // "First Middle Last"
            return $names[0];
        } elseif ($count >= 4) {
            // Check if last 2 words form a compound last name
            $secondToLast = strtolower($names[$count - 2]);
            if ($this->isCompoundLastNamePrefix($secondToLast)) {
                // "First Middle Dela Cruz" (4 words)
                // First name is just the first word
                return $names[0];
            }
            
            // "First1 First2 Middle Last" (4+ words)
            // Assume first word only is first name
            return $names[0];
        }
        
        return $names[0];
    }
    
    private function extractLastName($text)
    {
        $fullName = $this->extractStudentName($text);
        if (empty($fullName)) return '';
        
        $names = preg_split('/\s+/', trim($fullName));
        $count = count($names);
        
        if ($count == 2) {
            return $names[1];
        } elseif ($count == 3) {
            return $names[2];
        } elseif ($count >= 4) {
            // Check if last 2 words form a compound last name
            $secondToLast = strtolower($names[$count - 2]);
            if ($this->isCompoundLastNamePrefix($secondToLast)) {
                // Return last 2 words as compound last name
                return $names[$count - 2] . ' ' . $names[$count - 1];
            }
            
            // Otherwise, return last word only
            return $names[$count - 1];
        }
        
        return $names[$count - 1];
    }
    
    private function extractMiddleName($text)
    {
        $fullName = $this->extractStudentName($text);
        if (empty($fullName)) return '';
        
        $names = preg_split('/\s+/', trim($fullName));
        $count = count($names);
        
        if ($count <= 2) {
            // No middle name
            return '';
        } elseif ($count == 3) {
            // "First Middle Last"
            return $names[1];
        } elseif ($count >= 4) {
            // Check if last 2 words form a compound last name
            $secondToLast = strtolower($names[$count - 2]);
            if ($this->isCompoundLastNamePrefix($secondToLast)) {
                // "First Middle Dela Cruz"
                // Middle name is the word before compound last name
                return $names[$count - 3];
            }
            
            // "First Middle1 Middle2 Last"
            // Return middle names (everything between first and last)
            $middleNames = array_slice($names, 1, $count - 2);
            return implode(' ', $middleNames);
        }
        
        return '';
    }
    
    private function isCompoundLastNamePrefix($word)
    {
        $compoundPrefixes = [
            'dela', 'de', 'del',
            'san', 'santa', 'santo',
            'delos', 'las', 'los',
            'van', 'von', 'di', 'da'
        ];
        
        return in_array(strtolower($word), $compoundPrefixes);
    }
    
    private function extractStudentName($text)
    {
        $patterns = [
            // Pattern 1: Match after "Student Name" (with or without colon) until "Student No" or newline
            '/Student\s*Name\s*[:\-\s]\s*([A-Za-z\s\.\,]+?)(?=\s+Student\s*(?:No|Number|ID|no)|\n|\r|$)/i',
            '/Student\s*Name\s*[:\-\s]\s*([A-Za-z\s\.\,]+?(?:Jr\.?|Sr\.?|I{1,3}|IV|V|VI{0,3}|IX|X)?)(?=\s+Student)/i',
            '/Student\s*Name\s*[:\-\s]\s*([A-Za-z\s\.\,]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $name = trim($matches[1]);
                $name = preg_replace('/\s+/', ' ', $name);
                $name = preg_replace('/\s+(?:Student|Course|Year).*$/i', '', $name);
                if (strlen($name) > 2) {
                    return trim($name);
                }
            }
        }
        return '';
    }
    
    private function extractStudentNumber($text)
    {
        // 1. Direct label match (handles spaces, colons, newlines, tabs up to 30 chars between label and number)
        if (preg_match('/(?:Student\s*(?:No\.?|Number|ID|Num)|S\.N\.)[\s:\-\t\r\n]{1,30}([0-9]{2,4}\-[0-9]{4,6})/i', $text, $matches)) {
            return trim($matches[1]);
        }

        // 2. Multi-line label search
        if (preg_match('/(?:Student\s*(?:No\.?|Number|ID|Num)|S\.N\.)[\s\S]{1,40}?([0-9]{2,4}\-[0-9]{4,6})/i', $text, $matches)) {
            return trim($matches[1]);
        }

        // 3. Fallback: Find all ID-like patterns and exclude School Years (e.g. 2025-2026 or A.Y. 2024-2025)
        if (preg_match_all('/\b([0-9]{2,4}\-[0-9]{4,6})\b/', $text, $allMatches)) {
            foreach ($allMatches[1] as $candidate) {
                $parts = explode('-', $candidate);
                if (count($parts) === 2) {
                    $p1 = (int) $parts[0];
                    $p2 = (int) $parts[1];
                    // Check if this is a School Year like 2025-2026 (both 4 digits and consecutive years)
                    if (strlen($parts[0]) === 4 && strlen($parts[1]) === 4 && $p2 === $p1 + 1 && $p1 >= 2000 && $p1 <= 2099) {
                        continue; // Skip school year
                    }
                    // Check if preceded by A.Y. or SY in text
                    if (preg_match('/(?:A\.Y\.|SY|Academic\s*Year)[\s:]*' . preg_quote($candidate, '/') . '/i', $text)) {
                        continue; // Skip school year
                    }
                    return $candidate;
                }
            }
        }

        return '';
    }
    
    private function extractCourse($text)
    {
        $patterns = [
            // Pattern 1: Capture anything after "Course" (with optional colon/hyphen/dash) up to Year Level, Gender, Section, Date Enrolled, or newline
            '/Course\s*[:\-\s]\s*([A-Za-z\s\.\,\-]+?)(?=\s*(?:Year\s*Level|Gender|Date\s*Enrolled|Section|Status|Major|\n|\r|$))/i',
            // Pattern 2: Explicit Bachelor/Master/Associate pattern
            '/\b((?:Bachelor|Master|Doctor|Associate|Diploma)\s+of\s+[A-Za-z\s]+?)(?=\s*(?:Year\s*Level|Gender|Date\s*Enrolled|\n|\r|$))/i',
            // Pattern 3: BS / AB abbreviation format
            '/\b((?:BS|AB|BE|BT)\s+(?:in\s+)?[A-Za-z\s]+?)(?=\s*(?:Year\s*Level|Gender|Date\s*Enrolled|\n|\r|$))/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $course = trim($matches[1]);
                // Clean up trailing field labels if any leaked in
                $course = preg_replace('/\s+(?:Year\s*Level|Third\s*Year|Second\s*Year|First\s*Year|Fourth\s*Year|1st\s*Year|2nd\s*Year|3rd\s*Year|4th\s*Year|Gender|Male|Female|Date|Enrolled).*$/i', '', $course);
                $course = preg_replace('/\s+/', ' ', $course);
                if (strlen($course) >= 2) {
                    return trim($course);
                }
            }
        }

        return '';
    }
    
    private function extractYearLevel($text)
    {
        if (preg_match('/(?:Year\s*Level\s*:\s*|\b)(First|Second|Third|Fourth)\s*Year/i', $text, $matches)) {
            $map = [
                'First' => '1st Year',
                'Second' => '2nd Year',
                'Third' => '3rd Year',
                'Fourth' => '4th Year',
            ];
            return $map[ucfirst(strtolower($matches[1]))] ?? ($matches[1] . ' Year');
        }

        if (preg_match('/\b([1-4])(?:st|nd|rd|th)?\s*Year\b/i', $text, $matches)) {
            $suffixes = ['1' => '1st Year', '2' => '2nd Year', '3' => '3rd Year', '4' => '4th Year'];
            return $suffixes[$matches[1]] ?? ($matches[1] . 'th Year');
        }

        return '';
    }
    
    private function extractSection($text)
    {
        // Extract section letter only (A, B, C, etc.)
        $patterns = [
            '/\b(?:CPE|BSIT|BSCS|BSIS|BSEMC|BSED|BEED|BSBA|BSHM|BSTM|BSN|BSCRIM|CIT|CAS|CTE)[\s\-]*[1-4]\s*([A-Z])\b/i',
            '/Section\s*:\s*([A-Za-z0-9\s\-]+)/i',
            '/\b[1-4]\s*[\-\s]\s*([A-Z])\b/i',
            '/\b[1-4]([A-Z])\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $sec = trim($matches[1]);
                if (preg_match('/([A-Z])$/i', $sec, $m2)) {
                    return strtoupper($m2[1]);
                }
                if (strlen($sec) === 1 && ctype_alpha($sec)) {
                    return strtoupper($sec);
                }
            }
        }

        return '';
    }
    
    private function extractSemester($text)
    {
        if (preg_match('/(First|Second|Summer)\s*\([0-9]+(?:st|nd|rd|th)\)\s*Semester/i', $text, $matches)) {
            return trim($matches[0]);
        }
        return '';
    }
    
    private function extractSchoolYear($text)
    {
        if (preg_match('/A\.Y\.\s*([0-9]{4}-[0-9]{4})/i', $text, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }
}
