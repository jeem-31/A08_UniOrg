<?php

// Admin Controllers
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\ChatbotController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\EdushareController as AdminEdushareController;
use App\Http\Controllers\Admin\ElectionController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\LibraryController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\RenewController;
use App\Http\Controllers\Admin\SettingsController;
// Auth Controllers
use App\Http\Controllers\ApplyOrgController;
use App\Http\Controllers\Auth\AuthController;
// Dean Controllers
use App\Http\Controllers\Auth\OrgAuthController;
// Student Controllers
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Dean\DeanController;
// Shared Controllers
use App\Http\Controllers\LogViewerController;
use App\Http\Controllers\OrganizationConfigurationController;
use App\Http\Controllers\Osas\DocumentIntelligenceController;
use App\Http\Controllers\Osas\DocumentLibrarySourceController;
use App\Http\Controllers\Osas\DocumentTestingController;
use App\Http\Controllers\Osas\OsasController;
use App\Http\Controllers\Osas\SmartSearchController;
use App\Http\Controllers\OsasChatController;
use App\Http\Controllers\StorageController;
use App\Http\Controllers\Student\EdushareController;
use App\Http\Controllers\Student\ProfileController;
use App\Http\Controllers\ViewSwitchController;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Services\GoogleCloudUsageTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Student event pages aggregate a Mother Org and its department Sub-Orgs.
// Keep this binding separate from admin event routes, which stay organization-scoped.
Route::bind('studentEvent', static fn ($value): Event => Event::allOrganizations()->findOrFail($value));

// Storage route for serving files
Route::get('storage/{path}', [StorageController::class, 'serve'])->where('path', '.*');

// Health check route for Railway
Route::get('/health', function () {
    return response()->json(['status' => 'ok'], 200);
});

// Temporary one-time Google OAuth setup. Remove after GOOGLE_REFRESH_TOKEN is saved in Render.
Route::get('/google/authorize', [\App\Http\Controllers\GoogleOAuthController::class, 'authorizeGoogle']);
Route::get('/google/callback', [\App\Http\Controllers\GoogleOAuthController::class, 'callback']);

// Public routes
Route::get('/', function () {
    return redirect()->route('org.login');
})->name('home');

// Guest routes - accessible without authentication
Route::get('/guest/dashboard', [DashboardController::class, 'guestDashboard'])->name('guest.dashboard');
Route::get('/guest/announcements', [AnnouncementController::class, 'guestIndex'])->name('guest.announcements.index');
Route::get('/guest/announcements/{announcement}', [AnnouncementController::class, 'guestShow'])->name('guest.announcements.show');
Route::get('/guest/events', [EventController::class, 'guestIndex'])->name('guest.events.index');
Route::get('/guest/events/{event}', [EventController::class, 'guestShow'])->name('guest.events.show');

// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware(['throttle:login', 'turnstile:login', 'auth.activity']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/validate-email', [AuthController::class, 'validateEmail'])->name('validate.email');
    Route::post('/forgot-password', [AuthController::class, 'sendResetCode'])->name('password.email')->middleware(['throttle:password-email', 'turnstile:password_email']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.reset')->middleware(['throttle:password-reset', 'turnstile:password_reset']);
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Organization Authentication routes
Route::prefix('org')->name('org.')->middleware('guest')->group(function () {
    Route::get('/login', [OrgAuthController::class, 'showLoginRegister'])->name('login');
    Route::post('/login', [OrgAuthController::class, 'login'])->name('login.post')->middleware(['throttle:login', 'turnstile:login', 'auth.activity']);
    Route::get('/register', [OrgAuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [OrgAuthController::class, 'register'])->name('register.post');
    Route::post('/register/verify-otp', [OrgAuthController::class, 'verifyOtp'])->name('verify-otp');
    Route::post('/register/resend-otp', [OrgAuthController::class, 'resendOtp'])->name('resend-otp');
    Route::post('/upload-cor', [OrgAuthController::class, 'uploadCor'])->name('upload.cor');
});

// WebAuthn Fingerprint routes (student only, mobile)
// Guest routes for fingerprint login (no auth required)
Route::prefix('webauthn')->name('webauthn.')->group(function () {
    Route::post('/login/options', [\App\Http\Controllers\WebAuthnController::class, 'loginOptions'])->name('login.options')->middleware('turnstile:login');
    Route::post('/login/verify', [\App\Http\Controllers\WebAuthnController::class, 'loginVerify'])->name('login.verify');
});
// Authenticated routes for registration / management
Route::prefix('webauthn')->name('webauthn.')->middleware(['auth'])->group(function () {
    Route::get('/status', [\App\Http\Controllers\WebAuthnController::class, 'status'])->name('status');
    Route::post('/register/options', [\App\Http\Controllers\WebAuthnController::class, 'registerOptions'])->name('register.options');
    Route::post('/register/verify', [\App\Http\Controllers\WebAuthnController::class, 'registerVerify'])->name('register.verify');
    Route::delete('/credential', [\App\Http\Controllers\WebAuthnController::class, 'destroy'])->name('destroy');
});

// Apply Org Routes (public)
Route::get('/apply-org/form', [ApplyOrgController::class, 'showCombined'])->name('system-request.combined');
Route::post('/apply-org', [ApplyOrgController::class, 'submit'])->name('system-request.submit');
Route::get('/apply-org/check-mother-org/{departmentId}', [ApplyOrgController::class, 'checkMotherOrgMembers'])->name('apply-org.check-mother-org');
Route::get('/apply-org/assigned-dean/{departmentId}', [ApplyOrgController::class, 'assignedDean'])->name('apply-org.assigned-dean')->middleware('throttle:60,1');
Route::post('/apply-org/prefill-from-officers', [ApplyOrgController::class, 'prefillFromOfficers'])->name('apply-org.prefill-from-officers');
Route::post('/apply-org/parse-member-rows', [ApplyOrgController::class, 'parseMemberRows'])->name('apply-org.parse-member-rows');
Route::post('/apply-org/match-officer-ids', [ApplyOrgController::class, 'matchOfficerIds'])->name('apply-org.match-officer-ids');
Route::get('/apply-org/mother-org-member-files/{departmentId}', [ApplyOrgController::class, 'motherOrgMemberFiles'])->name('apply-org.mother-org-member-files')->middleware('throttle:60,1');
Route::post('/apply-org/match-mother-org-ids', [ApplyOrgController::class, 'matchMotherOrgIds'])->name('apply-org.match-mother-org-ids')->middleware('throttle:120,1');

// Organization Configuration routes
Route::post('/organization/initiate-configuration', [OrganizationConfigurationController::class, 'initiate'])
    ->name('organization.initiate-configuration')
    ->middleware('auth');
Route::get('/organization/configure/{token}', [OrganizationConfigurationController::class, 'showConfigurationPage'])
    ->name('organization.configure');
Route::post('/organization/save-configuration', [OrganizationConfigurationController::class, 'saveConfiguration'])
    ->name('organization.save-configuration');

// API route for student lookup (used in system request forms) - normalization via digits handles both 0000-0000 and 00-0000-0000
Route::get('/api/student/{studentId}', function ($studentId) {
    $digits = preg_replace('/\D/', '', $studentId);
    // Try exact match first, then normalized digits match (handles dash variations)
    $user = User::where('student_id', $studentId)->first();
    if (! $user && $digits) {
        $user = User::whereRaw("REPLACE(REPLACE(student_id, '-', ''), ' ', '') = ?", [$digits])->first();
        if (! $user) {
            $user = User::where('student_id', $digits)->first();
        }
    }

    if ($user) {
        $mi = $user->middle_initial ?? '';
        if ($mi === '' && !empty($user->middle_name)) {
            $mi = strtoupper(substr(trim($user->middle_name), 0, 1));
        }
        return response()->json([
            'success' => true,
            'student' => [
                'first_name' => \App\Helpers\NameParser::toTitleCase($user->first_name ?? ''),
                'last_name' => \App\Helpers\NameParser::toTitleCase($user->last_name ?? ''),
                'middle_name' => \App\Helpers\NameParser::toTitleCase($user->middle_name ?? ''),
                'middle_initial' => $mi,
                'suffix' => $user->suffix,
                'email' => $user->email,
                'course' => $user->course,
                'year_level' => $user->year_level,
                'section' => $user->section,
            ],
        ]);
    }

    return response()->json([
        'success' => false,
        'message' => 'User not found',
    ], 404);
})->name('api.student.lookup');

// API route for loading departments
Route::get('/api/departments', function () {
    $departments = Department::select('id', 'department_name as name', 'department_acronym')
        ->orderBy('department_name')
        ->get();

    return response()->json([
        'success' => true,
        'departments' => $departments,
    ]);
})->name('api.departments.list');

// API route for loading existing organizations (used in renew form)
Route::get('/api/organizations', function (Request $request) {
    $query = Organization::select('id', 'name', 'org_acronym', 'department_id')
        ->where('status', 'Approved'); // Changed from 'approved' to 'Approved'

    // Filter by department if provided
    if ($request->has('department_id') && $request->department_id) {
        $query->where('department_id', $request->department_id);
    }

    $organizations = $query->orderBy('name')->get();

    return response()->json([
        'success' => true,
        'organizations' => $organizations,
    ]);
})->name('api.organizations.list');

// API route for loading approved organizations by department (for Create System)
Route::get('/api/organizations-by-department/{departmentId}', function ($departmentId) {
    $query = Organization::select('id', 'name', 'org_acronym as acronym', 'type')
        ->where('status', 'Approved')
        ->where(function ($q) {
            $q->whereRaw('has_system IS FALSE')
                ->orWhereNull('has_system');
        });

    if ($departmentId === 'non_academic') {
        $query->where('type', 'Non-Academic');
    } else {
        $query->where('department_id', $departmentId);
    }

    $organizations = $query->orderBy('name')->get();

    return response()->json([
        'success' => true,
        'organizations' => $organizations,
    ]);
})->name('api.organizations.by-department');

// API route for user lookup with department/organization filtering (for Create System activator) - normalized via digits
Route::get('/api/user-by-id/{userId}', function ($userId, Request $request) {
    $departmentId = $request->query('department_id');
    $organizationId = $request->query('organization_id');

    $digits = preg_replace('/\D/', '', $userId);
    // Find user by student_id with normalization (handles dash variations)
    $user = User::where('student_id', $userId)->first();
    if (! $user && $digits) {
        $user = User::whereRaw("REPLACE(REPLACE(student_id, '-', ''), ' ', '') = ?", [$digits])->first();
        if (! $user) {
            $user = User::where('student_id', $digits)->first();
        }
    }

    if (! $user) {
        return response()->json([
            'success' => false,
            'message' => 'User not found',
        ], 404);
    }

    // Check if user belongs to the selected department (bypass for Non-Academic department 11)
    if ($departmentId && $departmentId != 11 && $user->department_id != $departmentId) {
        return response()->json([
            'success' => false,
            'message' => 'User does not belong to the selected department',
        ], 403);
    }

    // Check if user is a member of the selected organization with President or Adviser role
    if ($organizationId) {
        $membership = DB::table('organization_user')
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->whereIn('role', ['President', 'Adviser'])
            ->where('membership_status', 'Active')
            ->first();

        if (! $membership) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a President or Adviser in this organization',
            ], 403);
        }
    }

    $mi2 = $user->middle_initial ?? '';
    if ($mi2 === '' && !empty($user->middle_name)) {
        $mi2 = strtoupper(substr(trim($user->middle_name), 0, 1));
    }
    return response()->json([
        'success' => true,
        'user' => [
            'first_name' => \App\Helpers\NameParser::toTitleCase($user->first_name ?? ''),
            'last_name' => \App\Helpers\NameParser::toTitleCase($user->last_name ?? ''),
            'middle_name' => \App\Helpers\NameParser::toTitleCase($user->middle_name ?? ''),
            'middle_initial' => $mi2,
            'email' => $user->email,
            'department_id' => $user->department_id,
        ],
    ]);
})->name('api.user.by-id');

Route::post('/org/logout', [OrgAuthController::class, 'logout'])->name('org.logout')->middleware('auth');

// Organization switching
Route::post('/organization/switch', [AuthController::class, 'switchOrganization'])->name('organization.switch')->middleware('auth');

// Admin/Student view switching
Route::post('/view/switch', [ViewSwitchController::class, 'switch'])->name('view.switch')->middleware('auth');

// Authenticated routes
Route::middleware(['auth', 'active.account'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'changePassword'])->name('profile.password');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');

    // Announcements (available to every organization, including global OSAS announcements)
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('student.announcements.index');
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->name('student.announcements.show');

    // Events (Student pages - Mother Org only)
    Route::middleware(['check.mother.org'])->group(function () {
        Route::get('/events', [EventController::class, 'index'])->name('events.index');
        Route::get('/events/calendar', [EventController::class, 'calendar'])->name('events.calendar');
    });
    Route::get('/events/{studentEvent}/json', [EventController::class, 'getEventJson'])->name('events.json.student');
    Route::get('/events/{studentEvent}', [EventController::class, 'show'])->name('events.show');
    Route::post('/events/{studentEvent}/register', [EventController::class, 'register'])->name('events.register');

    // Payments (Student pages - Mother Org only)
    Route::middleware(['check.mother.org'])->group(function () {
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    });
    Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::post('/payments/create-checkout', [PaymentController::class, 'createCheckout'])->name('payments.create-checkout');
    Route::get('/payments/success', [PaymentController::class, 'paymentSuccess'])->name('payments.success');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::get('/payments/{payment}/qr-code', [PaymentController::class, 'getQRCode'])->name('payments.qr-code');
    Route::get('/payments/{payment}/receipt-html', [PaymentController::class, 'studentReceiptHtml'])->name('payments.student-receipt-html');
    Route::get('/payments/{payment}/download-receipt', [PaymentController::class, 'downloadReceipt'])->name('payments.download-receipt');
    Route::post('/payments/{announcement}/gcash', [PaymentController::class, 'processGcashPayment'])->name('payments.gcash');
    Route::post('/payments/{announcement}/complete', [PaymentController::class, 'completePayment'])->name('payments.complete');
    Route::post('/payments/gcash/create-pending', [PaymentController::class, 'createPendingGcashPayment'])->name('payments.gcash.create-pending');
    Route::post('/payments/gcash/upload-receipt', [PaymentController::class, 'uploadGcashReceipt'])->name('payments.gcash.upload-receipt');
    Route::post('/payments/gcash/submit-with-receipt', [PaymentController::class, 'submitGcashWithReceipt'])->name('payments.gcash.submit-with-receipt');

    // Notifications
    Route::get('/notifications', function () {
        $user = auth()->user();
        $notifications = $user->notifications()->latest();
        $unreadNotifications = $user->unreadNotifications();

        // Election notifications are student-facing. Eligible officers keep both
        // their email and database record, but the Admin View bell hides these
        // records until the officer switches back to Student View.
        if ($user->isEffectivelyAdmin()) {
            $studentViewOnlyTypes = [
                'election_announced',
                'voting_now_open',
                'election_results_published',
                'officer_access_approved',
                'officer_access_removed',
            ];

            $excludeStudentViewOnlyNotifications = function ($query) use ($studentViewOnlyTypes) {
                $query->where(function ($notificationQuery) use ($studentViewOnlyTypes) {
                    if ($notificationQuery->getModel()->getConnection()->getDriverName() === 'pgsql') {
                        // The notifications migration stores data as text on
                        // PostgreSQL, so cast it before using JSON operators.
                        $placeholders = implode(',', array_fill(0, count($studentViewOnlyTypes), '?'));

                        $notificationQuery->whereRaw(
                            "COALESCE((data::jsonb ->> 'type'), '') NOT IN ({$placeholders})",
                            $studentViewOnlyTypes
                        );
                    } else {
                        $notificationQuery
                            ->whereNull('data->type')
                            ->orWhereNotIn('data->type', $studentViewOnlyTypes);
                    }
                });
            };

            $excludeStudentViewOnlyNotifications($notifications);
            $excludeStudentViewOnlyNotifications($unreadNotifications);
        }
        $notifications = \App\Support\NotificationVisibility::forUser($user, $notifications->get());
        $visibleUnreadNotifications = \App\Support\NotificationVisibility::forUser($user, $unreadNotifications->get());
        $unreadCount = $visibleUnreadNotifications->count();
        return response()->json([
            'notifications' => $notifications->take(20)->values(),
            'unread_count' => $unreadCount,
        ]);
    })->name('notifications.index');

    Route::post('/notifications/{notification}/read', function ($id) {
        auth()->user()->notifications()->where('id', $id)->first()?->markAsRead();

        return response()->json(['success' => true]);
    })->name('notifications.mark-read');

    Route::delete('/notifications/{notification}/delete', function ($id) {
        $notification = auth()->user()->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->delete();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false], 404);
    })->name('notifications.delete');
    
    Route::post('/notifications/mark-all-read', function() {
        $user = auth()->user();
        $visibleUnreadNotifications = \App\Support\NotificationVisibility::forUser(
            $user,
            $user->unreadNotifications()->get()
        );
        $visibleUnreadNotifications->each->markAsRead();
        return response()->json(['success' => true, 'message' => 'All notifications marked as read']);
    })->name('notifications.mark-all-read');

    // Elections
    Route::middleware(['module:elections'])->group(function () {
        Route::get('/elections', [ElectionController::class, 'index'])->name('elections.index');
        Route::get('/elections/{election}', [ElectionController::class, 'show'])->name('elections.show');
        Route::post('/elections/{election}/vote', [ElectionController::class, 'vote'])->name('elections.vote');
        Route::get('/elections/{election}/results', [ElectionController::class, 'results'])->name('elections.results');
    });

    // Documents
    Route::middleware(['module:documents'])->group(function () {
        Route::get('/documents/my-documents', [DocumentController::class, 'myDocuments'])->name('documents.my-documents');
        Route::post('/documents/upload', [DocumentController::class, 'uploadDocument'])->name('documents.upload');
        Route::post('/documents/{document}/share', [DocumentController::class, 'shareDocument'])->name('documents.share');
        Route::delete('/documents/{document}/unshare', [DocumentController::class, 'unshareDocument'])->name('documents.unshare');
        Route::delete('/documents/{document}', [DocumentController::class, 'deleteDocument'])->name('documents.delete');
    });

    // e-Library
    Route::middleware(['module:elibrary'])->group(function () {
        Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
        Route::get('/library/{resource}', [LibraryController::class, 'show'])->name('library.show');
        Route::get('/library/{resource}/view', [LibraryController::class, 'view'])->name('library.view');
        Route::get('/library/{resource}/download', [LibraryController::class, 'download'])->name('library.download');
    });

    // EduShare
    Route::middleware(['module:edushare'])->group(function () {
        Route::get('/edushare', [EdushareController::class, 'index'])->name('edushare.index');
        Route::post('/edushare/folder', [EdushareController::class, 'createFolder'])->name('edushare.folder.create');
        Route::post('/edushare/upload', [EdushareController::class, 'store'])->name('edushare.store');
        Route::get('/edushare/shares', [EdushareController::class, 'getShares'])->name('edushare.shares.get');
        Route::post('/edushare/share', [EdushareController::class, 'share'])->name('edushare.share');
        Route::post('/edushare/remove-share', [EdushareController::class, 'removeShare'])->name('edushare.remove.share');
        Route::delete('/edushare/unshare-email', [EdushareController::class, 'unshareEmail'])->name('edushare.unshare.email');
        Route::post('/edushare/folder/{folder}/rename', [EdushareController::class, 'renameFolder'])->name('edushare.folder.rename');
        Route::post('/edushare/item/{item}/rename', [EdushareController::class, 'renameItem'])->name('edushare.item.rename');
        Route::post('/edushare/folder/{folder}/move', [EdushareController::class, 'moveFolder'])->name('edushare.folder.move');
        Route::post('/edushare/item/{item}/move', [EdushareController::class, 'moveItem'])->name('edushare.item.move');
        Route::delete('/edushare/item/{item}', [EdushareController::class, 'destroy'])->name('edushare.destroy');
        Route::delete('/edushare/folder/{folder}', [EdushareController::class, 'destroyFolder'])->name('edushare.folder.destroy');
        Route::get('/edushare/{item}/view', [EdushareController::class, 'show'])->name('edushare.view');
        Route::get('/edushare/{item}/download', [EdushareController::class, 'download'])->name('edushare.download');
    });

    // Complaints & Suggestions
    Route::middleware(['module:complaints'])->group(function () {
        Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints.index');
        Route::get('/complaints/create', [ComplaintController::class, 'create'])->name('complaints.create');
        Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaints.store');
        Route::delete('/complaints/{complaint}', [ComplaintController::class, 'destroy'])->name('complaints.destroy');
        Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
    });

    // Chatbot (Chat/Manage FAQs)
    Route::middleware(['module:chatbot'])->group(function () {
        Route::get('/chatbot', [ChatbotController::class, 'index'])->name('chatbot.index');
        Route::post('/chatbot/ask', [ChatbotController::class, 'ask'])->name('chatbot.ask');
        Route::post('/chatbot/feedback', [ChatbotController::class, 'feedback'])->name('chatbot.feedback');

        // Student Chat with Admin (In-page mode)
        Route::get('/chatbot/messages', [ChatbotController::class, 'getMessages'])->name('chatbot.messages');
        Route::post('/chatbot/send', [ChatbotController::class, 'sendMessage'])->name('chatbot.send');
        Route::post('/chatbot/mark-read', [ChatbotController::class, 'markMessagesAsRead'])->name('chatbot.mark-read');
        Route::get('/chatbot/unread-count', [ChatbotController::class, 'getUnreadCount'])->name('chatbot.unread-count');
        Route::get('/chatbot/student-unread-count', [ChatbotController::class, 'getStudentUnreadCount'])->name('chatbot.student-unread-count');
        Route::get('/chatbot/attachment/{chat}', [ChatbotController::class, 'downloadAttachment'])->name('chatbot.attachment');
    });
});

// Admin routes (org officers only)
Route::middleware(['auth', 'active.account', 'role:admin', 'comelec.election.only'])->prefix('admin')->name('admin.')->group(function () {
    // Admin Dashboard
    Route::get('/dashboard', [DashboardController::class, 'adminDashboard'])->name('dashboard');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/role-permissions', [SettingsController::class, 'updateRolePermission'])->name('settings.role-permissions.update');
    Route::post('/settings/accounts/toggle', [SettingsController::class, 'updateAccountStatus'])->name('settings.accounts.toggle');
    Route::post('/settings/positions', [SettingsController::class, 'storePosition'])->name('settings.positions.store');
    Route::put('/settings/positions/{position}', [SettingsController::class, 'updatePosition'])->name('settings.positions.update');
    Route::delete('/settings/positions/{position}', [SettingsController::class, 'destroyPosition'])->name('settings.positions.destroy');
    Route::post('/settings/positions/{position}/assign', [SettingsController::class, 'assignPosition'])->name('settings.positions.assign');
    Route::delete('/settings/positions/{position}/members/{user}', [SettingsController::class, 'removePositionMember'])->name('settings.positions.members.remove');
    Route::post('/settings/officer-transition/approve', [SettingsController::class, 'approveOfficerTransition'])->name('settings.officer-transition.approve');
    Route::post('/settings/officer-transition/reschedule', [SettingsController::class, 'rescheduleOfficerTransition'])->name('settings.officer-transition.reschedule');
    Route::post('/settings/officer-transition/cancel', [SettingsController::class, 'cancelOfficerTransition'])->name('settings.officer-transition.cancel');
    Route::get('/members/lookup/{studentId}', [SettingsController::class, 'lookupMember'])->name('members.lookup');

    // Announcements Management
    Route::middleware(['org.permission:announcements,full'])->group(function () {
        Route::get('/announcements', [AnnouncementController::class, 'adminIndex'])->name('announcements.index');
        Route::get('/announcements/{announcement}/json', [AnnouncementController::class, 'getAnnouncement'])->name('announcements.get');
        Route::get('/announcements/create', [AnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('/announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('announcements.edit');
        Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    });

    // Events Management
    Route::middleware(['org.permission:events,full'])->group(function () {
        Route::get('/events', [EventController::class, 'adminIndex'])->name('events.index');
        Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
        Route::post('/events', [EventController::class, 'store'])->name('events.store');
        Route::post('/events/{event}/post', [EventController::class, 'postEvent'])->name('events.post');
        Route::post('/events/{event}/submit-to-osas', [EventController::class, 'submitToOsas'])->name('events.submit-to-osas');
        Route::post('/events/{event}/resubmit', [EventController::class, 'resubmitEvent'])->name('events.resubmit');
        Route::post('/events/{event}/request-reschedule', [EventController::class, 'requestReschedule'])->name('events.request-reschedule');
        Route::get('/events/{event}/json', [EventController::class, 'getEventJson'])->name('events.json');
        Route::get('/events/venue-availability', [EventController::class, 'checkVenueAvailability'])->name('events.venue-availability');
        Route::get('/events/suggest-dates', [EventController::class, 'suggestEventDates'])->name('events.suggest-dates');
        Route::get('/events/geocode', [EventController::class, 'geocodeLocation'])->name('events.geocode');
        Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
        Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
        Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
    });

    // Payments Management
    Route::middleware(['org.permission:payments,read'])->group(function () {
        Route::get('/payments', [PaymentController::class, 'adminIndex'])->name('payments.index');
        Route::get('/payments/export', [PaymentController::class, 'exportExcel'])->name('payments.export');
        Route::get('/payments/{payment}/receipt-html', [PaymentController::class, 'adminReceiptHtml'])->name('payments.receipt-html');

        // Nested group for write operations - requires full access
        Route::middleware(['org.permission:payments,full'])->group(function () {
            Route::post('/payments/{payment}/verify', [PaymentController::class, 'verify'])->name('payments.verify');
            Route::post('/payments/{payment}/reject', [PaymentController::class, 'reject'])->name('payments.reject');

            // Payment Announcements
            Route::get('/payments/announcements', [PaymentController::class, 'announcementsIndex'])->name('payments.announcements');
            Route::get('/payments/announcements/create', [PaymentController::class, 'createAnnouncement'])->name('payments.announcements.create');
            Route::post('/payments/announcements', [PaymentController::class, 'storeAnnouncement'])->name('payments.announcements.store');
            Route::get('/payments/announcements/{announcement}/edit', [PaymentController::class, 'editAnnouncement'])->name('payments.announcements.edit');
            Route::put('/payments/announcements/{announcement}', [PaymentController::class, 'updateAnnouncement'])->name('payments.announcements.update');
            Route::delete('/payments/announcements/{announcement}', [PaymentController::class, 'deleteAnnouncement'])->name('payments.announcements.delete');

            // Manual Payment Processing
            Route::get('/payments/manual/create', [PaymentController::class, 'createManualPayment'])->name('payments.manual.create');
            Route::post('/payments/manual', [PaymentController::class, 'storeManualPayment'])->name('payments.manual.store');

            // GCash Payment Verification
            Route::post('/payments/gcash/{payment}/approve', [PaymentController::class, 'approveGcashPayment'])->name('payments.gcash.approve');
            Route::post('/payments/gcash/{payment}/reject', [PaymentController::class, 'rejectGcashPayment'])->name('payments.gcash.reject');
        });
    });

    // Elections Management
    Route::middleware(['org.permission:elections,read'])->group(function () {
        Route::get('/elections', [ElectionController::class, 'adminIndex'])->name('elections.index');
        Route::get('/elections/{election}/results', [ElectionController::class, 'adminResults'])->name('elections.results');
    });
    Route::middleware(['org.permission:elections,full'])->group(function () {
        Route::get('/elections/create', [ElectionController::class, 'create'])->name('elections.create');
        Route::post('/elections', [ElectionController::class, 'store'])->name('elections.store');
        Route::post('/elections/{election}/post', [ElectionController::class, 'postElection'])->name('elections.post');
        Route::post('/elections/{election}/unpost', [ElectionController::class, 'unpostElection'])->name('elections.unpost');
        Route::get('/elections/{election}/edit', [ElectionController::class, 'edit'])->name('elections.edit');
        Route::put('/elections/{election}', [ElectionController::class, 'update'])->name('elections.update');
        Route::delete('/elections/{election}', [ElectionController::class, 'destroy'])->name('elections.destroy');
        Route::get('/elections/{election}/candidates', [ElectionController::class, 'manageCandidates'])->name('elections.candidates');
        Route::post('/elections/{election}/candidates', [ElectionController::class, 'storeCandidate'])->name('elections.candidates.store');
        Route::delete('/candidates/{candidate}', [ElectionController::class, 'destroyCandidate'])->name('candidates.destroy');
        Route::get('/elections/{election}/secretary-candidates', [ElectionController::class, 'getSecretaryCandidates'])->name('elections.secretary-candidates');
        Route::post('/elections/{election}/assign-secretary', [ElectionController::class, 'assignSecretary'])->name('elections.assign-secretary');
        Route::post('/elections/{election}/publish-results', [ElectionController::class, 'publishResults'])->name('elections.publish-results');
    });

    // Documents Management
    Route::get('/documents/student-documents', [DocumentController::class, 'adminStudentDocuments'])->name('documents.student-documents');
    Route::get('/documents/student/{user}', [DocumentController::class, 'viewStudentDocuments'])->name('documents.view-student');
    Route::get('/documents/shared/{user}', [DocumentController::class, 'viewSharedDocuments'])->name('documents.view-shared');
    Route::get('/documents/download-student/{user}', [DocumentController::class, 'downloadStudentDocuments'])->name('documents.download-student');
    Route::get('/documents/download-all', [DocumentController::class, 'downloadAllDocuments'])->name('documents.download-all');
    Route::post('/documents/{document}/verify', [DocumentController::class, 'verifyDocument'])->name('documents.verify');

    // Library Management
    Route::middleware(['org.permission:library,full'])->group(function () {
        Route::get('/library', [LibraryController::class, 'adminIndex'])->name('library.index');
        Route::post('/library', [LibraryController::class, 'store'])->name('library.store');
        Route::put('/library/{resource}', [LibraryController::class, 'update'])->name('library.update');
        Route::delete('/library/{resource}', [LibraryController::class, 'destroy'])->name('library.destroy');
    });

    // Complaints Management
    Route::middleware(['org.permission:complaints,full'])->group(function () {
        Route::get('/complaints', [ComplaintController::class, 'adminIndex'])->name('complaints.index');
        Route::get('/complaints/{complaint}', [ComplaintController::class, 'adminShow'])->name('complaints.show');
        Route::post('/complaints/{complaint}/respond', [ComplaintController::class, 'respond'])->name('complaints.respond');
    });

    // Chatbot Management
    Route::middleware(['org.permission:chatbot,full'])->group(function () {
        Route::get('/chatbot/faqs', [ChatbotController::class, 'adminFaqs'])->name('chatbot.faqs');
        Route::get('/chatbot/faqs/create', [ChatbotController::class, 'createFaq'])->name('chatbot.faqs.create');
        Route::post('/chatbot/faqs', [ChatbotController::class, 'storeFaq'])->name('chatbot.faqs.store');
        Route::get('/chatbot/faqs/{faq}/edit', [ChatbotController::class, 'editFaq'])->name('chatbot.faqs.edit');
        Route::put('/chatbot/faqs/{faq}', [ChatbotController::class, 'updateFaq'])->name('chatbot.faqs.update');
        Route::delete('/chatbot/faqs/{faq}', [ChatbotController::class, 'destroyFaq'])->name('chatbot.faqs.destroy');
        Route::get('/chatbot/logs', [ChatbotController::class, 'chatLogs'])->name('chatbot.logs');

        // Admin Chat with Students (floating widget endpoints only)
        Route::get('/chatbot/students', [ChatbotController::class, 'getStudents'])->name('chatbot.students');
        Route::get('/chatbot/messages/{userId}', [ChatbotController::class, 'getStudentMessages'])->name('chatbot.student-messages');
        Route::post('/chatbot/send', [ChatbotController::class, 'sendAdminMessage'])->name('chatbot.send');
        Route::get('/chat/unread-count', [ChatbotController::class, 'getAdminUnreadCount'])->name('admin.chat.unread-count');
    });

    // Admin EduShare
    Route::middleware(['org.permission:edushare,full'])->group(function () {
        Route::get('/edushare', [AdminEdushareController::class, 'index'])->name('edushare.index');
    });
    Route::post('/edushare/folder', [AdminEdushareController::class, 'createFolder'])->name('edushare.folder.create');
    Route::post('/edushare/upload', [AdminEdushareController::class, 'uploadFile'])->name('edushare.upload');
    Route::get('/edushare/shares', [AdminEdushareController::class, 'getShares'])->name('edushare.shares.get');
    Route::post('/edushare/share', [AdminEdushareController::class, 'share'])->name('edushare.share');
    Route::post('/edushare/folder/{folder}/rename', [AdminEdushareController::class, 'renameFolder'])->name('edushare.folder.rename');
    Route::post('/edushare/item/{item}/rename', [AdminEdushareController::class, 'renameItem'])->name('edushare.item.rename');
    Route::delete('/edushare/item/{item}', [AdminEdushareController::class, 'deleteItem'])->name('edushare.item.delete');
    Route::delete('/edushare/folder/{folder}', [AdminEdushareController::class, 'deleteFolder'])->name('edushare.folder.delete');
    Route::get('/edushare/download/{item}', [AdminEdushareController::class, 'download'])->name('edushare.download');

    // Renew Organization
    Route::get('/renew', [RenewController::class, 'index'])->name('renew.index');
    Route::post('/renew/submit', [RenewController::class, 'submit'])->name('renew.submit');

    // OSAS Required Submissions (checklist set by OSAS, upload + unlimited resubmit)
    Route::get('/required-submissions', [\App\Http\Controllers\Admin\RequiredSubmissionController::class, 'index'])->name('required-submissions.index');
    Route::post('/required-submissions', [\App\Http\Controllers\Admin\RequiredSubmissionController::class, 'store'])->name('required-submissions.store');

    // OSAS Chat (floating chat widget endpoints; the dedicated page was removed)
    Route::post('/chat/send', [OsasChatController::class, 'sendMessage'])->name('chat.send');
    Route::get('/chat/messages', [OsasChatController::class, 'getMessages'])->name('chat.messages');
    Route::get('/chat/history', [OsasChatController::class, 'getHistory'])->name('chat.history');
    Route::get('/chat/{chat}/attachment', [OsasChatController::class, 'downloadAttachment'])->name('chat.attachment');

    // Admin Profile
    Route::get('/profile', [App\Http\Controllers\Admin\ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [App\Http\Controllers\Admin\ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [App\Http\Controllers\Admin\ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::put('/profile/password', [App\Http\Controllers\Admin\ProfileController::class, 'changePassword'])->name('profile.password');
});

// OSAS routes (OSAS Admin only - University Level)
Route::middleware(['auth', 'active.account', 'role:osas_admin'])->prefix('osas')->name('osas.')->group(function () {
    // OSAS Account Management
    Route::prefix('accounts')->name('accounts.')->group(function () {
        Route::get('/', [\App\Http\Controllers\AccountManagement\DirectoryController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\AccountManagement\AccountController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\AccountManagement\AccountController::class, 'store'])->name('store');
        Route::get('/{account}', [\App\Http\Controllers\AccountManagement\AccountController::class, 'show'])->name('show');
        Route::get('/{account}/edit', [\App\Http\Controllers\AccountManagement\AccountController::class, 'edit'])->name('edit');
        Route::put('/{account}/profile', [\App\Http\Controllers\AccountManagement\AccountController::class, 'updateProfile'])->name('update-profile');
        Route::put('/{account}/role', [\App\Http\Controllers\AccountManagement\AccountController::class, 'updateRole'])->name('update-role');
        Route::put('/{account}/lifecycle', [\App\Http\Controllers\AccountManagement\LifecycleController::class, 'change'])->name('lifecycle');
        Route::delete('/{account}', [\App\Http\Controllers\AccountManagement\AccountController::class, 'destroy'])->name('destroy');

        Route::post('/{account}/appointments/dean', [\App\Http\Controllers\AccountManagement\DeanAppointmentController::class, 'appoint'])->name('appointments.dean');
        Route::post('/{account}/appointments/organization/{organization}', [\App\Http\Controllers\AccountManagement\OrganizationAppointmentController::class, 'appoint'])->name('appointments.organization');
    });

    Route::get('/audits', [\App\Http\Controllers\AccountManagement\AuditController::class, 'index'])->name('audits.index');

    // OSAS Dashboard
    Route::get('/dashboard', [OsasController::class, 'dashboard'])->name('dashboard');

    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\Osas\OsasNotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/state', [\App\Http\Controllers\Osas\OsasNotificationController::class, 'state'])->name('notifications.state');
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Osas\OsasNotificationController::class, 'markRead'])->name('notifications.mark-read');
    Route::post('/notifications/read-all', [\App\Http\Controllers\Osas\OsasNotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');

    // Calendar & Event Proposals
    Route::get('/calendar', [OsasController::class, 'masterCalendar'])->name('calendar');
    Route::post('/events/{event}/approve', [OsasController::class, 'approveEvent'])->name('events.approve');
    Route::post('/events/{event}/reject', [OsasController::class, 'rejectEvent'])->name('events.reject');

    Route::get('/proposals', [OsasController::class, 'proposals'])->name('proposals.index');
    Route::get('/proposals/{event}', [OsasController::class, 'showProposal'])->name('proposals.show');
    Route::post('/proposals/{event}/approve', [OsasController::class, 'approveProposal'])->name('proposals.approve');
    Route::post('/proposals/{event}/reject', [OsasController::class, 'rejectProposal'])->name('proposals.reject');
    Route::post('/proposals/{event}/reschedule/approve', [OsasController::class, 'approveReschedule'])->name('proposals.reschedule.approve');
    Route::post('/proposals/{event}/reschedule/reject', [OsasController::class, 'rejectReschedule'])->name('proposals.reschedule.reject');
    Route::get('/proposals/{event}/download-letter', [OsasController::class, 'downloadRequestLetter'])->name('proposals.download-letter');
    Route::post('/proposals/{event}/hardcopy-received', [OsasController::class, 'markHardcopyReceived'])->name('proposals.hardcopy-received');

    // Apply Org Management
    Route::get('/apply-org', [OsasController::class, 'applyOrgs'])->name('apply-org.index');
    Route::post('/apply-org/generate-renewals', [OsasController::class, 'generateExpiredRenewals'])->middleware('throttle:3,1')->name('apply-org.generate-renewals');
    Route::get('/apply-org/{applyOrg}', [OsasController::class, 'showApplyOrg'])->name('apply-org.show');
    Route::post('/apply-org/{applyOrg}/approve', [ApplyOrgController::class, 'approve'])->name('apply-org.approve');
    Route::post('/apply-org/{applyOrg}/reject', [ApplyOrgController::class, 'reject'])->name('apply-org.reject');

    // Organizations Management
    Route::get('/organizations', [OsasController::class, 'organizations'])->name('organizations.index');
    Route::get('/organizations/{organization}', [OsasController::class, 'showOrganization'])->name('organizations.show');
    Route::post('/organizations/{organization}/toggle-status', [OsasController::class, 'toggleOrganizationStatus'])->name('organizations.toggle-status');

    // Reports & Analytics
    Route::get('/analytics', [OsasController::class, 'analytics'])->name('analytics');
    Route::get('/reports/organizations/pdf', [OsasController::class, 'exportOrganizationsPDF'])->name('reports.organizations.pdf');
    Route::get('/reports/organizations/{organization}/pdf', [OsasController::class, 'exportOrganizationPDF'])->name('reports.organization.pdf');

    // Settings & Profile
    Route::get('/settings', [OsasController::class, 'settings'])->name('settings');
    Route::post('/settings/profile', [OsasController::class, 'updateProfile'])->name('settings.profile.update');
    Route::post('/settings/password', [OsasController::class, 'updatePassword'])->name('settings.password.update');
    Route::post('/settings/photo', [OsasController::class, 'updatePhoto'])->name('settings.photo.update');
    Route::post('/settings/accreditation', [OsasController::class, 'setAccreditationPeriod'])->name('settings.accreditation.update');
    Route::get('/audit-logs', function () {
        return redirect()->route('osas.settings', ['tab' => 'audit-logs']);
    })->name('audit-logs');

    // OSAS Profile (redirect to settings)
    Route::get('/profile', function () {
        return redirect()->route('osas.settings', ['tab' => 'profile']);
    })->name('profile.show');
    Route::put('/profile', function () {
        return redirect()->route('osas.settings', ['tab' => 'profile']);
    })->name('profile.update');
    Route::post('/profile/photo', function () {
        return redirect()->route('osas.settings', ['tab' => 'profile']);
    })->name('profile.photo.update');
    Route::put('/profile/password', function () {
        return redirect()->route('osas.settings', ['tab' => 'profile']);
    })->name('profile.password');

    // Advanced Features (bulk email removed — messaging now uses the floating chat widget)
    // System-wide Announcements Management
    Route::get('/announcements', [AnnouncementController::class, 'adminIndex'])->name('announcements.index');
    Route::get('/announcements/{announcement}/json', [AnnouncementController::class, 'getAnnouncement'])->name('announcements.get');
    Route::get('/announcements/create', [AnnouncementController::class, 'create'])->name('announcements.create');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::get('/announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('announcements.edit');
    Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    // OSAS Chat (Floating Widget APIs + Attachment support)
    Route::get('/chat', function () {
        return redirect()->route('osas.dashboard');
    })->name('feedback.index');
    Route::get('/chat/orgs', [OsasChatController::class, 'orgs'])->name('chat.orgs');
    Route::get('/chat/unread-count', [OsasChatController::class, 'unreadCount'])->name('chat.unread-count');
    Route::post('/chat/send', [OsasChatController::class, 'sendMessage'])->name('chat.send');
    Route::get('/chat/messages', [OsasChatController::class, 'getMessages'])->name('chat.messages');
    Route::get('/chat/history', [OsasChatController::class, 'getHistory'])->name('chat.history');
    Route::get('/chat/{chat}/attachment', [OsasChatController::class, 'downloadAttachment'])->name('chat.attachment');

    // View Document for both live and archived library (Option B union) — outside scopeBindings so archived ids work
    Route::get('/apply-org/{applyOrg}/library-documents/{document}/source', DocumentLibrarySourceController::class)
        ->name('apply-org.library-documents.source');
    Route::get('/apply-org/{applyOrg}/analyses/{analysis}/source', [DocumentIntelligenceController::class, 'source'])
        ->name('apply-org.analyses.source');

    // Persisted document intelligence APIs and indexed source fallback use nested scoped binding.
    Route::scopeBindings()->group(function () {
        Route::get('/apply-org/{applyOrg}/analyses/{analysis}', [DocumentIntelligenceController::class, 'status'])
            ->can('view', ['analysis', 'applyOrg'])->name('apply-org.analyses.status');
        Route::get('/apply-org/{applyOrg}/analyses/{analysis}/summary', [DocumentIntelligenceController::class, 'summary'])
            ->can('viewSummary', ['analysis', 'applyOrg'])->name('apply-org.analyses.summary');
        Route::get('/apply-org/{applyOrg}/analyses/{analysis}/details', [DocumentIntelligenceController::class, 'details'])
            ->can('viewDetails', ['analysis', 'applyOrg'])->name('apply-org.analyses.details');
        Route::post('/apply-org/{applyOrg}/analyses/{analysis}/retry', [DocumentIntelligenceController::class, 'retry'])
            ->can('retry', ['analysis', 'applyOrg'])->name('apply-org.analyses.retry');
        Route::post('/apply-org/{applyOrg}/analyses/{analysis}/resummarize', [DocumentIntelligenceController::class, 'resummarize'])
            ->can('resummarize', ['analysis', 'applyOrg'])->name('apply-org.analyses.resummarize');
        Route::post('/apply-org/{applyOrg}/analyses/{analysis}/retag', [DocumentIntelligenceController::class, 'retag'])
            ->can('retag', ['analysis', 'applyOrg'])->name('apply-org.analyses.retag');
    });

    // OSAS Required Documents (set requirements, review submissions, summary via linker analyses)
    Route::get('/required-documents', [\App\Http\Controllers\Osas\RequiredDocumentController::class, 'index'])->name('required-documents.index');
    Route::post('/required-documents', [\App\Http\Controllers\Osas\RequiredDocumentController::class, 'store'])->name('required-documents.store');
    Route::post('/required-documents/{requiredDocument}/toggle', [\App\Http\Controllers\Osas\RequiredDocumentController::class, 'toggle'])->name('required-documents.toggle');
    Route::get('/required-documents/{requiredDocument}/submissions', [\App\Http\Controllers\Osas\RequiredDocumentController::class, 'submissions'])->name('required-documents.submissions');
    Route::post('/required-documents/submissions/{submission}/review', [\App\Http\Controllers\Osas\RequiredDocumentController::class, 'review'])->name('required-documents.review');

    // The Document Library preserves the Smart Search destination; the processing test lab remains separate.
    Route::get('/smart-search', [SmartSearchController::class, 'index'])->name('smart-search');
    Route::get('/document-testing', [DocumentTestingController::class, 'show'])->name('document-testing');
    Route::post('/document-testing/process', [DocumentTestingController::class, 'process'])->name('document-testing.process');

    // Debug Logs Viewer
    Route::get('/logs', [LogViewerController::class, 'index'])->name('logs.index');
    Route::post('/logs/clear', [LogViewerController::class, 'clear'])->name('logs.clear');
});

// Dean routes (Super Admin)
Route::middleware(['auth', 'active.account', 'role:super_admin'])->prefix('dean')->name('dean.')->group(function () {
    // Dean Dashboard
    Route::get('/dashboard', [DashboardController::class, 'deanDashboard'])->name('dashboard');

    // Approvals Management (Payments & Complaints)
    Route::get('/approvals', [PaymentController::class, 'deanApprovals'])->name('approvals');

    // Event Proposals (Event Requests)
    Route::get('/proposals', [DeanController::class, 'proposals'])->name('proposals.index');
    Route::post('/events/{event}/approve', [DeanController::class, 'approveEvent'])->name('events.approve');
    Route::post('/events/{event}/reject', [DeanController::class, 'rejectEvent'])->name('events.reject');
    Route::delete('/events/{event}', [DeanController::class, 'deleteEvent'])->name('events.delete');

    // EventProposal routes
    Route::get('/proposals/{proposal}', [DeanController::class, 'showProposal'])->name('proposals.show');
    Route::post('/proposals/{proposal}/approve', [DeanController::class, 'approveProposal'])->name('proposals.approve');
    Route::post('/proposals/{proposal}/reject', [DeanController::class, 'rejectProposal'])->name('proposals.reject');

    // Financial Reports
    Route::get('/financial-reports', [DeanController::class, 'financialReports'])->name('financial-reports.index');
    Route::get('/financial-reports/export', [DeanController::class, 'exportFinancialReports'])->name('financial-reports.export');
    Route::get('/financial-reports/{report}', [DeanController::class, 'showFinancialReport'])->name('financial-reports.show');
    Route::post('/financial-reports/{report}/approve', [DeanController::class, 'approveFinancialReport'])->name('financial-reports.approve');
    Route::post('/financial-reports/{report}/reject', [DeanController::class, 'rejectFinancialReport'])->name('financial-reports.reject');

    // Registration Reports
    Route::get('/registration-reports', [DeanController::class, 'registrationReports'])->name('registration-reports');
    Route::get('/registration-reports/export', [DeanController::class, 'exportRegistrationReports'])->name('registration-reports.export');
    Route::get('/events/{event}/registrations', [DeanController::class, 'eventRegistrationDetails'])->name('event-registration-details');

    // Reports
    Route::get('/reports', [DashboardController::class, 'deanReports'])->name('reports.index');
    Route::get('/reports/export', [DeanController::class, 'exportSystemReports'])->name('reports.export');

    // Content Overview (Announcements, Events, Complaints)
    Route::get('/content-overview', [DashboardController::class, 'contentOverview'])->name('content-overview');

    // Download Request Letter
    Route::get('/proposals/{event}/download-letter', [DeanController::class, 'downloadRequestLetter'])->name('proposals.download-letter');

    // User Management (Students & Admins) — Dean is deactivate-only, students are view-only
    Route::get('/users', [DeanController::class, 'users'])->name('users.index');
    Route::put('/users/{user}/lifecycle', [DeanController::class, 'toggleUserStatus'])->name('users.lifecycle');
    Route::delete('/users/{user}', [DeanController::class, 'deleteUser'])->name('users.delete');

    // Dean Profile
    Route::get('/profile', [App\Http\Controllers\Dean\ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [App\Http\Controllers\Dean\ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [App\Http\Controllers\Dean\ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::put('/profile/password', [App\Http\Controllers\Dean\ProfileController::class, 'changePassword'])->name('profile.password');
});

// Google Cloud Usage Dashboard
Route::get('/google-cloud-usage', function () {
    $usage = GoogleCloudUsageTracker::getUsageSummary();
    $withinFreeTier = GoogleCloudUsageTracker::isWithinFreeTier();

    return view('google-cloud-usage', compact('usage', 'withinFreeTier'));
})->middleware('auth');
