<?php

namespace App\Http\Controllers;

use App\Models\ConfigurationToken;
use App\Models\Organization;
use App\Models\OrganizationSettings;
use App\Mail\ConfigurationLinkEmail;
use App\Mail\SystemActivatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class OrganizationConfigurationController extends Controller
{
    /**
     * Initiate the configuration process for an organization
     * Validates eligibility and sends configuration link via email
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function initiate(Request $request)
    {
        try {
            // Check if user is authenticated
            if (!auth()->check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must be logged in to initiate system configuration.'
                ], 401);
            }

            $user = auth()->user();

            // Get user's organization from organization_user pivot
            $organization = $user->organizations()->first();

            if (!$organization) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not associated with any organization.'
                ], 403);
            }

            // Validate organization status is 'Approved'
            if ($organization->status !== 'Approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your organization must be approved before you can create a system. Current status: ' . $organization->status
                ], 403);
            }

            // Validate organization.has_system is false
            if ($organization->has_system) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your organization already has a system configured.'
                ], 403);
            }

            // Get user's role in the organization
            $userRole = $organization->pivot->role;

            // Validate user role is Officer or Adviser
            if (!in_array($userRole, ['Officer', 'Adviser'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Officers and Advisers can initiate system configuration. Your role: ' . $userRole
                ], 403);
            }

            // Generate ConfigurationToken
            $configToken = ConfigurationToken::generate($organization->id, $user->id);

            // Send ConfigurationLinkEmail
            Mail::to($user->email)->send(new ConfigurationLinkEmail($configToken));

            return response()->json([
                'success' => true,
                'message' => 'Configuration link has been sent to your email address. Please check your inbox and follow the instructions to configure your organization system.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while initiating configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the configuration page for an organization
     * Validates the token and shows the configuration form
     *
     * @param string $token
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showConfigurationPage(string $token)
    {
        try {
            // Find ConfigurationToken by token
            $configToken = ConfigurationToken::where('token', $token)->first();

            if (!$configToken) {
                return redirect()->route('login')->with('error', 'Invalid configuration token.');
            }

            // Validate token with isValid() method
            if (!$configToken->isValid()) {
                $errorMessage = 'This configuration link is no longer valid. ';
                
                if ($configToken->is_used) {
                    $errorMessage .= 'It has already been used.';
                } elseif ($configToken->expires_at->isPast()) {
                    $errorMessage .= 'It has expired.';
                } elseif ($configToken->organization->has_system) {
                    $errorMessage .= 'The organization system has already been configured.';
                }

                return redirect()->route('login')->with('error', $errorMessage);
            }

            // Load organization details with settings
            $organization = $configToken->organization()->with('settings')->first();

            // Get permanent and optional modules from OrganizationSettings
            $permanentModules = OrganizationSettings::permanentModules();
            $optionalModules = OrganizationSettings::optionalModules();

            // Get current settings if they exist
            $currentSettings = $organization->settings;

            // Return view with organization, modules, token data
            return view('organization.configure-system', [
                'token' => $token,
                'organization' => $organization,
                'permanentModules' => $permanentModules,
                'optionalModules' => $optionalModules,
                'currentSettings' => $currentSettings,
                'user' => $configToken->user,
            ]);

        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'An error occurred while loading the configuration page: ' . $e->getMessage());
        }
    }

    /**
     * Save the organization configuration
     * Updates modules, theme colors, logo, and activates the system
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function saveConfiguration(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
                'confirm_acronym' => 'required|string',
                'modules' => 'nullable|array',
                'modules.*' => 'string|in:elections,elibrary,edushare,complaints,documents',
                'primary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'complementary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'logo' => 'required|image|mimes:png|max:2048', // Required, PNG only, 2MB max
                'remove_logo' => 'nullable|in:0,1',
            ]);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            // Find and validate token
            $configToken = ConfigurationToken::where('token', $request->token)->first();

            if (!$configToken || !$configToken->isValid()) {
                return redirect()->route('login')->with('error', 'Invalid or expired configuration token.');
            }

            $organization = $configToken->organization;
            
            // Update organization acronym if changed
            if (strtoupper($request->confirm_acronym) !== strtoupper($organization->org_acronym)) {
                $organization->update(['org_acronym' => strtoupper($request->confirm_acronym)]);
            }
            
            $selectedModules = $request->input('modules', []);

            // Begin database transaction
            DB::beginTransaction();

            try {
                // Handle logo upload
                $logoUrl = null;
                
                if ($request->hasFile('logo')) {
                    // Upload new logo to S3/storage
                    $logoFile = $request->file('logo');
                    $logoPath = $logoFile->store('logos', 's3');
                    $logoUrl = \Illuminate\Support\Facades\Storage::disk('s3')->url($logoPath);
                } elseif ($request->input('remove_logo') == '1') {
                    // Remove logo - set to null
                    $logoUrl = null;
                    
                    // Delete old logo from storage if exists
                    if ($organization->settings && $organization->settings->logo_url) {
                        $oldLogoPath = str_replace(\Illuminate\Support\Facades\Storage::disk('s3')->url(''), '', $organization->settings->logo_url);
                        \Illuminate\Support\Facades\Storage::disk('s3')->delete($oldLogoPath);
                    }
                } else {
                    // Keep existing logo
                    $logoUrl = $organization->settings ? $organization->settings->logo_url : null;
                }

                // Update or create OrganizationSettings
                $settingsData = [
                    'primary_color' => $request->primary_color,
                    'complementary_color' => $request->complementary_color,
                    'logo_url' => $logoUrl, // Always include logo_url
                    // Set all permanent modules to true
                    'has_announcements' => DB::raw('TRUE'),
                    'has_events' => DB::raw('TRUE'),
                    'has_payments' => DB::raw('TRUE'),
                    'has_chatbot' => DB::raw('TRUE'),
                    // Set optional modules based on selection
                    'has_elections' => in_array('elections', $selectedModules) ? DB::raw('TRUE') : DB::raw('FALSE'),
                    'has_elibrary' => in_array('elibrary', $selectedModules) ? DB::raw('TRUE') : DB::raw('FALSE'),
                    'has_edushare' => in_array('edushare', $selectedModules) ? DB::raw('TRUE') : DB::raw('FALSE'),
                    'has_complaints' => in_array('complaints', $selectedModules) ? DB::raw('TRUE') : DB::raw('FALSE'),
                    'has_documents' => in_array('documents', $selectedModules) ? DB::raw('TRUE') : DB::raw('FALSE'),
                ];

                $settings = OrganizationSettings::updateOrCreate(
                    ['organization_id' => $organization->id],
                    $settingsData
                );

                // Set organization.has_system = true
                $organization->update(['has_system' => DB::raw('TRUE')]);

                // Mark token as used
                $configToken->markAsUsed();

                // Get all officers/members of this organization to send them their account credentials
                // This includes the President/activator since they also need to receive their temporary credentials to log in
                $organizationMembers = $organization->users()
                    ->where('organization_user.role', '!=', 'Member')
                    ->get();

                // Send account credentials to each officer
                foreach ($organizationMembers as $member) {
                    // Generate temporary password
                    $tempPassword = \Illuminate\Support\Str::random(10);

                    // Update user with new password and activate account.
                    // Use raw DB update to avoid PostgreSQL boolean cast issues.
                    DB::table('users')->where('id', $member->id)->update([
                        'password'   => bcrypt($tempPassword),
                        'is_active'  => DB::raw('TRUE'),
                        'updated_at' => now(),
                    ]);

                    // Send email directly (synchronous) — avoids queue From-header issues
                    try {
                        $memberRole = $member->pivot->role ?? 'Member';
                        Mail::to($member->email)->send(
                            new \App\Mail\UserAccountCreated(
                                $member->first_name . ' ' . $member->last_name,
                                $organization->name,
                                $memberRole,
                                $tempPassword
                            )
                        );
                    } catch (\Exception $mailError) {
                        \Illuminate\Support\Facades\Log::error('Failed to send account email to ' . $member->email . ': ' . $mailError->getMessage());
                    }
                }

                // Commit transaction
                DB::commit();
                
                // Clear all permission caches for this organization
                \Illuminate\Support\Facades\Cache::flush();
                
                // Clear session-based navigation permissions for all users in this organization
                // Users will need to refresh their browser to see the updated modules
                
                // Get enabled modules for notification
                $enabledModules = $settings->enabledModules();

                // Send SystemActivatedNotification to OSAS
                // Get OSAS admin email from config or env
                $osasEmail = config('mail.osas_email', env('OSAS_EMAIL', 'osas@lspu.edu.ph'));
                
                Mail::to($osasEmail)->queue(
                    new SystemActivatedNotification($organization, $configToken->user, $enabledModules)
                );

                // Redirect to login or dashboard with success message
                return redirect()->route('login')->with('success', 
                    'System configuration completed successfully! Your organization system has been activated. You can now log in and start using the platform.'
                );

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'An error occurred while saving configuration: ' . $e->getMessage())
                ->withInput();
        }
    }
}
