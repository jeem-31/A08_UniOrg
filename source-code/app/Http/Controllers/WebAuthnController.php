<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WebAuthnCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebAuthnController extends Controller
{
    /**
     * Helpers for base64url encoding used by WebAuthn.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    private function randomChallenge(int $bytes = 32): string
    {
        return $this->base64UrlEncode(random_bytes($bytes));
    }

    private function rpId(Request $request): string
    {
        $host = parse_url($request->header('Origin') ?? $request->getSchemeAndHttpHost(), PHP_URL_HOST) ?? $request->getHost();
        // For localhost WebAuthn rpId must be localhost
        if ($host === '127.0.0.1') return 'localhost';
        return $host;
    }

    /**
     * Portfolio: options for registering a new fingerprint.
     * Requires auth, student only, 1 per account enforced.
     */
    public function registerOptions(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'student') {
            return response()->json(['success' => false, 'message' => 'Only student accounts can use fingerprint login.'], 403);
        }
        // Extra guard: admin masquerading as student via view switch not allowed
        if ($user->isAdviser() || $user->isComelec() || $user->hasDeanMembership()) {
            return response()->json(['success' => false, 'message' => 'This account type cannot use fingerprint login.'], 403);
        }

        // Enforce 1 per account - if already has, require delete first
        if ($user->webauthnCredential()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Fingerprint already registered. Remove the existing one first to register a new device.',
                'has_existing' => true,
            ], 422);
        }

        $challenge = $this->randomChallenge(32);
        // Store in session with expiry (5 min)
        session([
            'webauthn_register_challenge' => $challenge,
            'webauthn_register_challenge_expires' => now()->addMinutes(5),
        ]);

        $rpName = 'UniOrg';
        $rpId = $this->rpId($request);
        $userIdB64 = $this->base64UrlEncode((string) $user->id);

        $options = [
            'challenge' => $challenge,
            'rp' => [
                'name' => $rpName,
                'id' => $rpId,
            ],
            'user' => [
                'id' => $userIdB64,
                'name' => $user->student_id ?? $user->email,
                'displayName' => $user->full_name ?? ($user->first_name . ' ' . $user->last_name),
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],  // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'userVerification' => 'required',
                'residentKey' => 'required',
                'requireResidentKey' => true,
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            // Empty excludeCredentials because we only allow 1 - if already has, we error above
            'excludeCredentials' => [],
        ];

        return response()->json(['success' => true, 'options' => $options]);
    }

    /**
     * Verify attestation from navigator.credentials.create() and store credential.
     * Simplified verification for school project: we validate challenge, origin, and store credentialId + publicKey placeholder.
     * Counter and signature verification is stored but not fully cryptographically verified with webauthn-lib
     * to avoid heavy dependency while keeping 1-per-account enforcement.
     */
    public function registerVerify(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'student') {
            return response()->json(['success' => false, 'message' => 'Only student accounts can use fingerprint login.'], 403);
        }

        if ($user->webauthnCredential()->exists()) {
            return response()->json(['success' => false, 'message' => 'Fingerprint already registered. Remove existing first.'], 422);
        }

        $expectedChallenge = session('webauthn_register_challenge');
        $expires = session('webauthn_register_challenge_expires');

        if (!$expectedChallenge || !$expires || now()->greaterThan($expires)) {
            return response()->json(['success' => false, 'message' => 'Challenge expired. Please try again.'], 422);
        }

        $validated = $request->validate([
            'id' => 'required|string',
            'rawId' => 'required|string',
            'type' => 'required|string|in:public-key',
            'response.clientDataJSON' => 'required|string',
            'response.attestationObject' => 'nullable|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        // Decode clientDataJSON
        try {
            $clientDataJson = $this->base64UrlDecode($validated['response']['clientDataJSON']);
            $clientData = json_decode($clientDataJson, true);
            if (!$clientData || ($clientData['type'] ?? null) !== 'webauthn.create') {
                return response()->json(['success' => false, 'message' => 'Invalid client data.'], 422);
            }
            // Verify challenge matches
            $clientChallenge = $clientData['challenge'] ?? null;
            // client challenge is base64url encoded
            // Compare directly to stored (both base64url)
            if ($clientChallenge !== $expectedChallenge) {
                return response()->json(['success' => false, 'message' => 'Challenge mismatch.'], 422);
            }
            // Verify origin
            $origin = $clientData['origin'] ?? '';
            $expectedOrigin = $request->header('Origin') ?? $request->getSchemeAndHttpHost();
            // Allow exact origin or host match (for localhost http vs https)
            $originHost = parse_url($origin, PHP_URL_HOST);
            $expectedHost = parse_url($expectedOrigin, PHP_URL_HOST) ?? $request->getHost();
            if ($originHost && $expectedHost && $originHost !== $expectedHost) {
                // Also allow 127.0.0.1 <-> localhost
                $allowedPairs = [['127.0.0.1','localhost'], ['localhost','127.0.0.1']];
                $isAllowed = false;
                foreach ($allowedPairs as [$a,$b]) {
                    if ($originHost === $a && $expectedHost === $b) { $isAllowed = true; break; }
                }
                if (!$isAllowed) {
                    return response()->json(['success' => false, 'message' => 'Origin mismatch.'], 422);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('WebAuthn registerVerify clientData parse failed: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Invalid attestation data.'], 422);
        }

        $credentialId = $validated['id']; // already base64url
        $rawId = $validated['rawId'];

        // Check global uniqueness of credential_id
        if (WebAuthnCredential::where('credential_id', $credentialId)->exists() ||
            WebAuthnCredential::where('credential_id', $rawId)->exists()) {
            return response()->json(['success' => false, 'message' => 'This fingerprint is already registered to another account.'], 422);
        }

        // Also check rawId duplicate (some browsers return same)
        // Store placeholder publicKey as rawId for now; real verification would extract COSE key from attestationObject
        $publicKeyPlaceholder = $rawId; // For demo we store rawId; proper lib would store COSE public key

        // Parse attestationObject if available to extract counter / aaguid (best effort, not required for flow)
        $counter = 0;
        $aaguid = null;
        if (!empty($validated['response']['attestationObject'])) {
            try {
                $attObjBin = $this->base64UrlDecode($validated['response']['attestationObject']);
                // Attestation object is CBOR; we try to extract AAGUID and counter without full CBOR lib
                // Simple heuristic: counter is at certain offset; we skip full parse and keep 0
                // aaguid is 16 bytes after 53 bytes header; best effort
                if (strlen($attObjBin) > 70) {
                    // Try to find aaguid pattern (16 bytes) - no strict
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        try {
            WebAuthnCredential::create([
                'user_id' => $user->id,
                'credential_id' => $credentialId,
                'public_key' => $publicKeyPlaceholder,
                'counter' => $counter,
                'transports' => $request->input('transports', ['internal']),
                'device_name' => $request->input('device_name') ?? 'My Device',
                'aaguid' => $aaguid,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique violation
            if (str_contains($e->getMessage(), 'unique') || $e->getCode() === '23000') {
                return response()->json(['success' => false, 'message' => 'Fingerprint already registered for this account.'], 422);
            }
            throw $e;
        }

        // Clear challenge
        session()->forget(['webauthn_register_challenge', 'webauthn_register_challenge_expires']);

        return response()->json(['success' => true, 'message' => 'Fingerprint registered successfully! You can now use it to login.', 'student_id' => $user->student_id]);
    }

    /**
     * Delete/replace fingerprint - requires password confirm.
     */
    public function destroy(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $request->validate([
            'password' => 'required|string',
        ]);

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Password is incorrect.'], 422);
        }

        $cred = $user->webauthnCredential()->first();
        if (!$cred) {
            return response()->json(['success' => false, 'message' => 'No fingerprint registered.'], 404);
        }

        $cred->delete();

        return response()->json(['success' => true, 'message' => 'Fingerprint removed. You can now register a new one.']);
    }

    /**
     * Check status for portfolio card.
     */
    public function status(Request $request)
    {
        $user = $request->user();
        $cred = $user->webauthnCredential()->first();
        if (!$cred) {
            return response()->json(['success' => true, 'has_credential' => false]);
        }
        return response()->json([
            'success' => true,
            'has_credential' => true,
            'credential' => [
                'created_at' => $cred->created_at->toISOString(),
                'device_name' => $cred->device_name,
                'counter' => $cred->counter,
            ]
        ]);
    }

    /**
     * Guest: generate login options for fingerprint login.
     * B-mode: works both with student_id (filtered) and without (discoverable/passwordless).
     * With ID: allowCredentials contains that user's credential (fast, no picker).
     * Without ID: allowCredentials empty → browser uses resident key + returns userHandle.
     */
    public function loginOptions(Request $request)
    {
        $request->validate([
            'student_id' => 'nullable|string',
        ]);

        $studentId = $request->input('student_id');
        $hasId = !empty($studentId);

        if ($hasId) {
            $user = User::where('student_id', $studentId)->first();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'ID number not found.'], 404);
            }
            if ($user->role !== 'student') {
                return response()->json(['success' => false, 'message' => 'Fingerprint login is only for student accounts.'], 403);
            }
            if (! $user->is_active) {
                return response()->json(['success' => false, 'message' => 'This account is inactive.'], 403);
            }

            $cred = $user->webauthnCredential()->first();
            if (!$cred) {
                return response()->json([
                    'success' => false,
                    'message' => 'No fingerprint registered for this ID. Please login with password and enable it in Portfolio. If you want passwordless, just tap fingerprint without typing ID (after re-registering with new version).',
                    'has_credential' => false,
                ], 404);
            }

            $challenge = $this->randomChallenge(32);
            session([
                'webauthn_login_challenge' => $challenge,
                'webauthn_login_user_id' => $user->id,
                'webauthn_login_challenge_expires' => now()->addMinutes(3),
                'webauthn_login_mode' => 'filtered',
            ]);

            $rpId = $this->rpId($request);

            $options = [
                'challenge' => $challenge,
                'timeout' => 60000,
                'rpId' => $rpId,
                'allowCredentials' => [
                    [
                        'type' => 'public-key',
                        'id' => $cred->credential_id,
                        'transports' => $cred->transports ?? ['internal'],
                    ]
                ],
                'userVerification' => 'required',
            ];

            return response()->json(['success' => true, 'options' => $options, 'mode' => 'filtered']);
        }

        // Discoverable / passwordless — no ID provided
        // Bank-like first-time: return allowCredentials with ALL credentials so even non-resident works
        // (browser will filter to the one present on this device, no need for prior stored ID)
        $challenge = $this->randomChallenge(32);
        session([
            'webauthn_login_challenge' => $challenge,
            'webauthn_login_user_id' => null,
            'webauthn_login_challenge_expires' => now()->addMinutes(3),
            'webauthn_login_mode' => 'discoverable',
        ]);

        $rpId = $this->rpId($request);

        // For true passwordless (resident) empty is enough, but for first-time without stored ID
        // and for old non-resident credentials, we provide all IDs so browser can match device key
        $allCreds = WebAuthnCredential::select('credential_id', 'transports')->limit(50)->get();
        $allowCredentials = [];
        foreach ($allCreds as $c) {
            $allowCredentials[] = [
                'type' => 'public-key',
                'id' => $c->credential_id,
                'transports' => $c->transports ?? ['internal'],
            ];
        }

        $options = [
            'challenge' => $challenge,
            'timeout' => 60000,
            'rpId' => $rpId,
            'allowCredentials' => $allowCredentials, // if empty, browser uses resident; if has items, works for non-resident too (first-time)
            'userVerification' => 'required',
        ];

        return response()->json(['success' => true, 'options' => $options, 'mode' => 'discoverable']);
    }

    /**
     * Verify login assertion. Simplified: check challenge, credentialId, origin.
     * In production, verify signature with stored publicKey using webauthn-lib.
     * Supports both filtered (with student_id) and discoverable (no ID, resolve via credentialId / userHandle).
     */
    public function loginVerify(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string',
            'rawId' => 'required|string',
            'type' => 'required|string|in:public-key',
            'response.clientDataJSON' => 'required|string',
            'response.authenticatorData' => 'required|string',
            'response.signature' => 'required|string',
            'response.userHandle' => 'nullable|string',
            'student_id' => 'nullable|string',
        ]);

        $expectedChallenge = session('webauthn_login_challenge');
        $expectedUserId = session('webauthn_login_user_id');
        $expires = session('webauthn_login_challenge_expires');
        $mode = session('webauthn_login_mode', 'filtered');

        if (!$expectedChallenge || !$expires || now()->greaterThan($expires)) {
            return response()->json(['success' => false, 'message' => 'Challenge expired. Please try again.'], 422);
        }

        // Resolve user: filtered mode needs student_id + session match; discoverable resolves via credential/userHandle
        $user = null;
        if (!empty($validated['student_id'])) {
            $user = User::where('student_id', $validated['student_id'])->first();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'ID number not found.'], 404);
            }
            // If we were in filtered mode, ensure session user matches
            if ($mode === 'filtered' && $expectedUserId && $expectedUserId != $user->id) {
                return response()->json(['success' => false, 'message' => 'Challenge mismatch. Please try again.'], 422);
            }
        } else {
            // Discoverable — resolve via credential_id first (most reliable), then userHandle
            // Tolerant: exact match first, then normalized (no padding) fallback
            $ids = array_values(array_unique(array_filter([$validated['id'], $validated['rawId']])));
            $credLookup = null;
            if (!empty($ids)) {
                $credLookup = WebAuthnCredential::whereIn('credential_id', $ids)->first();
            }
            // If not found, try normalized comparison (strip trailing '=' and compare)
            if (!$credLookup && !empty($ids)) {
                $normIds = array_map(fn($v) => rtrim($v, '='), $ids);
                // Try whereIn with normalized ids as well
                $credLookup = WebAuthnCredential::whereIn('credential_id', $normIds)->first();
                if (!$credLookup) {
                    // Last resort: load and compare normalized stored values (small table, safe)
                    $allCreds = WebAuthnCredential::all();
                    foreach ($allCreds as $c) {
                        $storedNorm = rtrim($c->credential_id, '=');
                        if (in_array($storedNorm, $normIds, true) || in_array($c->credential_id, $ids, true)) {
                            $credLookup = $c;
                            break;
                        }
                    }
                }
            }
            if ($credLookup) {
                $user = $credLookup->user;
            }
            if (!$user && !empty($validated['response']['userHandle'])) {
                try {
                    $decoded = $this->base64UrlDecode($validated['response']['userHandle']);
                    if (is_numeric(trim($decoded))) {
                        $user = User::find((int) trim($decoded));
                        // If we found user via handle but credLookup missed, still bind that user's cred
                        if ($user && !$credLookup) {
                            $userCred = $user->webauthnCredential()->first();
                            if ($userCred) {
                                $credLookup = $userCred;
                                Log::info('WebAuthn discoverable: resolved via userHandle', ['user_id' => $user->id, 'incoming_ids' => $ids, 'stored' => $userCred->credential_id]);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('WebAuthn userHandle decode failed: '.$e->getMessage());
                }
            }
            if (!$user && $expectedUserId) {
                // Fallback to session stored user (if filtered but client omitted id)
                $user = User::find($expectedUserId);
            }
            if (!$user) {
                Log::warning('WebAuthn discoverable failed', ['id' => $validated['id'], 'rawId' => $validated['rawId'], 'userHandle' => $validated['response']['userHandle'] ?? null]);
                return response()->json(['success' => false, 'message' => 'Could not identify account from fingerprint. Try typing your ID first, or re-register fingerprint once in Portfolio → Change Fingerprint to enable login without ID. Old fingerprints need one Change to become passwordless.'], 404);
            }
        }

        $cred = $user->webauthnCredential()->first();
        if (!$cred) {
            return response()->json(['success' => false, 'message' => 'No fingerprint registered.'], 404);
        }

        // Verify credentialId matches (tolerant: exact or normalized)
        $incomingIds = [$validated['id'], $validated['rawId']];
        $incomingNorm = array_map(fn($v) => rtrim($v, '='), $incomingIds);
        $storedNorm = rtrim($cred->credential_id, '=');
        $isMatch = in_array($cred->credential_id, $incomingIds, true) || in_array($storedNorm, $incomingNorm, true);
        if (!$isMatch) {
            return response()->json(['success' => false, 'message' => 'Fingerprint does not match this account. This fingerprint belongs to another device. Please use the device where you registered it or login with password and Change it in Portfolio.'], 422);
        }

        // Verify clientData challenge + origin
        try {
            $clientDataJson = $this->base64UrlDecode($validated['response']['clientDataJSON']);
            $clientData = json_decode($clientDataJson, true);
            if (!$clientData || ($clientData['type'] ?? null) !== 'webauthn.get') {
                return response()->json(['success' => false, 'message' => 'Invalid client data.'], 422);
            }
            if (($clientData['challenge'] ?? null) !== $expectedChallenge) {
                return response()->json(['success' => false, 'message' => 'Challenge mismatch.'], 422);
            }
            $origin = $clientData['origin'] ?? '';
            $originHost = parse_url($origin, PHP_URL_HOST);
            $expectedHost = $request->getHost();
            if ($originHost && $originHost !== $expectedHost) {
                $allowedPairs = [['127.0.0.1','localhost'], ['localhost','127.0.0.1']];
                $isAllowed = false;
                foreach ($allowedPairs as [$a,$b]) {
                    if ($originHost === $a && $expectedHost === $b) { $isAllowed = true; break; }
                }
                if (!$isAllowed) {
                    return response()->json(['success' => false, 'message' => 'Origin mismatch.'], 422);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('WebAuthn loginVerify parse failed: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Invalid assertion data.'], 422);
        }

        // Counter check (basic replay protection) - decode authenticatorData to get counter
        try {
            $authDataBin = $this->base64UrlDecode($validated['response']['authenticatorData']);
            if (strlen($authDataBin) >= 37) {
                $counter = unpack('N', substr($authDataBin, 33, 4))[1] ?? 0;
                if ($counter !== 0 && $counter <= $cred->counter) {
                    // Possible cloned authenticator
                    Log::warning('WebAuthn counter not increased', ['user_id' => $user->id, 'old' => $cred->counter, 'new' => $counter]);
                    // For school demo we allow but log; in production would reject
                }
                $cred->update(['counter' => max($cred->counter, $counter)]);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Checks for is_active, expired, etc. same as OrgAuthController login
        if (! $user->is_active) {
            return response()->json(['success' => false, 'message' => 'This account is inactive.'], 422);
        }
        // Check expired via StudentAccountValidity if exists
        if ($user->role === 'student' && $user->expires_at) {
            try {
                $validity = new \App\Services\AccountManagement\StudentAccountValidity(new \App\Services\MembershipFeeService());
                if ($validity->validityFor($user->expires_at) === \App\Services\AccountManagement\ValidityState::Expired) {
                    return response()->json(['success' => false, 'message' => 'This account has expired and can no longer access the system. Please contact OSAS.'], 422);
                }
            } catch (\Throwable $e) { /* ignore if service not available */ }
        }

        // Check active organization like login
        if ($user->role !== 'super_admin' && $user->role !== 'osas_admin') {
            $hasActiveOrg = $user->activeOrganizations()->where('organizations.status', 'Approved')->exists();
            if (!$hasActiveOrg) {
                return response()->json(['success' => false, 'message' => 'Your account is not associated with any active or approved organization.'], 422);
            }
        }

        // Systemless fallback resolve
        $fallbackOrg = null;
        $fallbackError = null;
        if ($user->role !== 'super_admin' && $user->role !== 'osas_admin') {
            $fallbackResult = \App\Support\SystemlessOrgFallbackResolver::resolve($user);
            $fallbackOrg = $fallbackResult['fallback'] ?? null;
            $fallbackError = $fallbackResult['error'] ?? null;
            if ($fallbackOrg !== null && ($user->isAdviser() || $user->isComelec() || $user->hasDeanMembership())) {
                return response()->json(['success' => false, 'message' => \App\Support\SystemlessOrgFallbackResolver::ERROR_STAFF_ONLY_MEMBERSHIP], 422);
            }
            if ($fallbackError !== null && $fallbackOrg === null) {
                return response()->json(['success' => false, 'message' => $fallbackError], 422);
            }
        }

        // Login the user
        Auth::login($user);
        $request->session()->regenerate();

        // Set org context like normal login
        if ($user->role !== 'super_admin' && $user->role !== 'osas_admin') {
            if ($fallbackOrg !== null) {
                $user->active_view = 'student';
                $user->save();
                $request->session()->put('current_organization_id', $fallbackOrg->id);
                $request->session()->put('systemless_fallback', true);
            } else {
                $firstOrg = $user->activeOrganizations()->first();
                if ($firstOrg) $request->session()->put('current_organization_id', $firstOrg->id);
            }
        }

        // Clear webauthn login session
        session()->forget(['webauthn_login_challenge', 'webauthn_login_user_id', 'webauthn_login_challenge_expires', 'webauthn_login_mode']);

        $redirectUrl = $user->isSuperAdmin() ? url('/dean/dashboard') : url('/dashboard');
        $fullName = function_exists('name_with_suffix') ? name_with_suffix($user->first_name, null, $user->last_name, $user->suffix) : ($user->first_name.' '.$user->last_name);

        return response()->json([
            'success' => true,
            'message' => 'Fingerprint verified! Welcome, '.$fullName.'!',
            'redirect' => $redirectUrl,
            'user_name' => $fullName,
            'student_id' => $user->student_id,
        ]);
    }
}
