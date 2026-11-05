# Implementation Plan - Admin/Student Context Switching

## Problem Statement
Mga user na may `role = 'admin'` ay pareho ring may access sa student-facing features (payments, events, elections, etc.) dahil member din sila ng org bilang student. Kailangan ng paraan para lumipat sila between Admin View at Student View gamit ang parehong account, at ma-remember ang huling pinili nilang view kahit maglogout-login.

## Requirements
- Column `active_view` sa `users` table (values: `'admin'`, `'student'`; `null`/default = `'admin'` para sa admin-role users).
- Lahat na may `role = 'admin'` lang makakakita ng switch button (hindi `super_admin`, `osas_admin`, `dean`).
- Persisted sa database (hindi session/cookie) kaya nagpe-persist across logout/login.
- Switch button:
  - Sa **Student View**: nasa profile dropdown ng navbar (`navigation.blade.php`), "Switch to Admin View".
  - Sa **Admin View**: nasa admin profile page (`admin.profile.show`), may pindutan "Switch to Student View".
- Pagka-click, redirect agad sa `/dashboard`, na magde-decide kung `adminDashboard()` o `studentDashboard()` base sa `active_view`.
- Dapat gumana pa rin lahat ng existing admin-only routes/middleware (`role:super_admin,admin,osas_admin` sa `admin.*` prefix) — hindi apektado ang access control, `active_view` lang ang nagbabago ng default landing/nav, hindi nag-a-alter ng `role` mismo.

## Background
- `User` model (`app/Models/User.php`) may `role` column (`student`, `admin`, `osas_admin`, `dean`, `super_admin`) at helper methods `isAdmin()`, `isStudent()`, `isSuperAdmin()`.
- `DashboardController::index()` (`app/Http/Controllers/DashboardController.php`) ang gateway: kina-check `role === 'osas_admin'` muna, tapos `isAdmin()` bago mag-fallback sa `studentDashboard()`.
- Navigation branches sa dalawang magkaibang blade files:
  - `resources/views/layouts/navigation.blade.php` (student-style layout, may profile dropdown)
  - `resources/views/layouts/admin.blade.php` (sidebar-style admin layout, walang profile dropdown; notification bell + logout deretso sa topbar)
- Routes: `/admin/*` prefix protected ng `role:super_admin,admin,osas_admin` middleware (`routes/web.php`) — hindi ito dapat magbago.
- Profile controllers/views:
  - `app/Http/Controllers/Admin/ProfileController.php` → `admin.profile.show` route (`/admin/profile`)
  - `app/Http/Controllers/Student/ProfileController.php` → `profile.show` route (`/profile`)

## Proposed Solution
1. Magdagdag ng `active_view` column (string, nullable, default null) sa `users` table via migration.
2. Idagdag sa `User` model:
   - `getActiveView()`: returns `$this->active_view ?? 'admin'` (default sa admin kung role nila ay admin).
   - `canSwitchView()`: `return $this->role === 'admin';`
3. Gawing route + controller method (`POST /view/switch` → `ViewSwitchController@switch`) na:
   - Nag-verify na `auth()->user()->role === 'admin'` (abort 403 kung hindi).
   - Toggle `active_view` between `'admin'`/`'student'`, i-save sa DB.
   - Redirect sa `route('dashboard')`.
4. I-update ang `DashboardController::index()`:
   ```php
   if ($user->role === 'osas_admin') { /* existing osas dashboard redirect */ }
   if ($user->role === 'admin' && $user->getActiveView() === 'student') {
       return $this->studentDashboard();
   }
   if ($user->isAdmin()) { return $this->adminDashboard(); }
   return $this->studentDashboard();
   ```
5. I-update ang navigation logic (`isStudent()`/`isAdmin()` checks sa blade files) para icheck din yung effective view, hindi lang `role`. Gumawa ng helper `isEffectivelyStudent()` / `isEffectivelyAdmin()` sa `User.php`:
   - `isEffectivelyStudent()`: `role==='student' || ($role==='admin' && getActiveView()==='student')`
   - Gamitin ito sa navigation branching sa `navigation.blade.php` (huwag galawin ang `osas_admin` branch, hiwalay na case).
6. Idagdag ang switch buttons:
   - `admin/profile/show.blade.php`: button/form na `POST /view/switch`, "Switch to Student View" — makikita lang kung `auth()->user()->role === 'admin'`.
   - `layouts/navigation.blade.php` profile dropdown: button "Switch to Admin View" — makikita lang kung effective view = student AT `role === 'admin'` (hindi totoong `student` role).
7. Siguraduhin na yung mga route na naka-protect ng `role:admin` middleware ay hindi apektado — role mismo hindi nagbabago, `active_view` lang ang nagbabago sa UI/dashboard routing.

## Task Breakdown (test-driven, incremental)

### Task 1: Add `active_view` migration and User model helpers
- **Objective:** Idagdag ang column at helper methods, walang UI changes pa.
- **Implementation:** `php artisan make:migration add_active_view_to_users_table`, add nullable string column. Add `getActiveView()` at `canSwitchView()` methods sa `User.php`.
- **Test:** Unit test sa `UserTest` — verify `getActiveView()` returns `'admin'` by default kung `active_view` is null, at returns stored value kung set. Verify `canSwitchView()` true lang for `role='admin'`.
- **Demo:** Run `php artisan migrate`, tinggnan gamit tinker na bagong user may `active_view = null` at `getActiveView() === 'admin'`.

### Task 2: Build the view-switch endpoint (backend only)
- **Objective:** Route + controller na nag-toggle ng `active_view` at nag-redirect sa dashboard.
- **Implementation:** `routes/web.php` — `Route::post('/view/switch', [ViewSwitchController::class, 'switch'])->name('view.switch')->middleware('auth');`. Controller: abort 403 kung hindi `role==='admin'`; toggle value; save; redirect `route('dashboard')` na may flash message.
- **Test:** Feature test — admin user posts to `/view/switch`, assert `active_view` changed sa DB, assert redirect sa dashboard. Test na non-admin role (student, dean) posting dito ay 403.
- **Demo:** Gamit Tinker/Postman, mag-POST bilang admin user, tingnan sa DB na nag-toggle ang `active_view`.

### Task 3: Wire DashboardController to respect active_view
- **Objective:** `/dashboard` magbibigay ng tamang view base sa `getActiveView()`.
- **Implementation:** I-update `DashboardController::index()` gamit ang logic sa design.
- **Test:** Feature test — admin user na may `active_view='student'` na nag-visit ng `/dashboard` ay nakikita ang `student.dashboard` view; `active_view='admin'` (or null) ay `admin.dashboard`.
- **Demo:** Mag-login bilang admin, i-POST manually yung switch endpoint (curl/tinker), i-refresh ang `/dashboard` — makikita talaga ang student dashboard view.

### Task 4: Add "Switch to Student View" button sa admin profile page
- **Objective:** UI entry point mula admin side.
- **Implementation:** Idagdag button/form sa `admin/profile/show.blade.php` na naka-conditional sa `auth()->user()->role === 'admin'`, submit sa `route('view.switch')`.
- **Test:** Manual/browser test — button visible lang sa `role=admin`, hindi makikita sa `super_admin`/`osas_admin`/`dean`.
- **Demo:** Mag-login bilang org admin, pumunta sa Profile page, i-click ang button, dapat mailipat sa Student Dashboard.

### Task 5: Add effective-view helper at update navigation branching
- **Objective:** Ipakita ang tamang nav tabs (student-style) kapag naka-student view ang isang admin.
- **Implementation:** Idagdag `isEffectivelyStudent()`/`isEffectivelyAdmin()` helpers sa `User.php`. Palitan lahat ng nav branching checks (`isStudent()`, `isAdmin()`, at ilang `role === 'osas_admin'` guard) sa `navigation.blade.php` para gamitin ang effective helpers (huwag galawin ang `osas_admin` branch dahil hiwalay na case).
- **Test:** Manual browser check + optional Dusk/browser test — admin sa student view makikita student nav tabs (Announcements, Events, Payments, Elections) at hindi admin tabs.
- **Demo:** Mag-login bilang admin, switch to student view, makikita full student navbar/tabs, functional lahat ng links.

### Task 6: Add "Switch to Admin View" button sa student profile dropdown
- **Objective:** UI entry point pabalik sa admin, makikita lang kung `role==='admin'` habang naka-student view.
- **Implementation:** Idagdag item sa dropdown ng `navigation.blade.php` (kasunod ng existing "Profile" link), naka-conditional `auth()->user()->role === 'admin'`, submit sa `route('view.switch')`.
- **Test:** Manual — button visible lang sa admin-role user na naka-student view; hindi makikita sa totoong `student` role.
- **Demo:** Buong flow end-to-end: admin logs in → default admin view → switch to student view via profile page → gamitin student features → switch back via navbar dropdown → bumalik admin dashboard. Logout/login ulit — dapat naka-recall ang huling view na pinili.

### Task 7: Regression check sa access control at existing tests
- **Objective:** Siguraduhing walang na-break sa existing admin/student route protections.
- **Implementation:** Patakbuhin ang buong test suite (`php artisan test`), i-verify manually na yung `role:super_admin,admin,osas_admin` middleware sa `/admin/*` routes ay gumagana pa rin kahit `active_view='student'` (dahil `role` mismo hindi nagbago).
- **Test:** Full automated suite pass + manual check: admin na naka-student view, subukan direkta i-type ang `/admin/dashboard` URL — dapat pumasa pa rin ang middleware check (role-based, hindi apektado ng active_view).
- **Demo:** Patunayan na parehong daan (URL bar navigation papuntang `/admin/...` at ang switch button) ay gumagana nang tama, walang security gap.
