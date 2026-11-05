# Spec — Login Routing for Organizations Without a System (Systemless Org Login Fallback)

**Status:** Draft (pending implementation)
**Date:** August 18, 2026
**Branch context:** `Modification_based_Mia`
**Related docs:** `docs/context-switching-plan.md` (Admin/Student view switching)

---

## 1. Problem Statement

Ang mga organization na naka-`has_system = false` (lalo na ang **Non-Academic** orgs, at gayundin ang mga academic **Sub-Orgs** na hindi pa na-activate ang system) ay hindi dapat maging dead-end para sa mga member nito pagdating sa login.

Sa kasalukuyan:
- Login (`OrgAuthController::login`) ay nire-require na may ≥1 **Approved** org ang user — pumapasa ito kahit na `has_system = false` ang org.
- Kapag nag-login, naka-set ang `current_organization_id` sa **first active org** ng user. Kapag `has_system = false` ang org na iyon, walang `organization_settings` (walang modules, walang theme) kaya sirang-buo/broken at walang laman ang kanyang dashboard/experience.
- Ang org na walang system ay hindi dinisenyo para i-login nang direkta.

**Gustong behavior:** Kapag ang org ng user ay walang system, i-ruta siya sa **Mother Org ng kanyang department** (na may system) — **Student page lang**, kahit na `admin` ang account role niya. Ang mga **Adviser / Dean / COMELEC** na membership ay hindi dapat makakuha ng student-page fallback na ito. Lahat ng "bawal" na kaso ay dapat may **malinis at malinaw na error handling** sa login.

---

## 2. Goals / Non-Goals

### Goals
1. Makapag-login ang members ng org na `has_system = false` (Non-Academic at academic Sub-Orgs) papunta sa **department Mother Org** na may system.
2. Force **Student View** para sa mga fallback users — kahit `admin` ang account role (officers).
3. I-block ang **Adviser / Dean / COMELEC** memberships mula sa student-page fallback, na may malinaw na error.
4. I-block lahat ng kaso kung saan **walang eligible Mother Org** na mahanap, na may malinaw na error.
5. Friendly, consistent error handling sa login (existing pattern: toast + red field, may non-JS fallback).
6. Session guards habang naka-login: hidden switch-to-admin button, hidden org switcher, blocked view/org switching.

### Non-Goals (hindi kasama sa task na ito)
- **Registration flow** (`OrgAuthController::register`) — hindi gagalawin.
- **Non-Academic post visibility settings** (per-post: member-only vs public) — **hiwalay na follow-up task** (tingnan ang §10).
- Pag-aayos ng mga existing session na naka-login na bago ma-deploy ang feature (login-time ang enforcement; see §8.4).
- Hindi binabago ang admin routes / `role:admin` middleware semantics — role mismo ay hindi nagbabago.

---

## 3. Current Behavior (Code References)

| Item | Location |
|---|---|
| Org login flow | `app/Http/Controllers/Auth/OrgAuthController.php` → `login()` |
| Legacy login flow (`/login`) | `app/Http/Controllers/Auth/AuthController.php` → `login()`, `setOrganizationContext()` |
| Dashboard routing | `app/Http/Controllers/DashboardController.php` → `index()` / `studentDashboard()` |
| View switch endpoint | `app/Http/Controllers/ViewSwitchController.php` |
| Org switch endpoint | `app/Http/Controllers/Auth/AuthController.php` → `switchOrganization()` |
| `has_system` column | `app/Models/Organization.php` (cast `boolean`), migration `2026_05_01_102857_*` |
| Active-view helpers | `app/Models/User.php` → `getActiveView()`, `canSwitchView()`, `isEffectivelyStudent()`, `isEffectivelyAdmin()`, `isAdviser()`, `isComelec()` |
| Course→department mapping | `OrgAuthController::courseToDepartmentId()` / `courseDepartmentMap()` |
| Current org context (session) | `session('current_organization_id')` — ginagamit ng `helpers.php` (`module_enabled`, `organization_*`), `ThemeService`, `PermissionHelper`, middleware |
| Switch buttons (UI) | `resources/views/admin/profile/show.blade.php` ("Switch to Student View"), `resources/views/layouts/navigation.blade.php` (line ~394, "Switch to Admin View") |
| Org switcher (UI) | `resources/views/components/organization-switcher.blade.php` |
| Error display (login) | `public/js/auth.js` → `validateLoginForm()` (AJAX toast + red field) at `resources/views/auth/org_auth.blade.php` (server-side toast) |

### Existing relevant logic sa login
```php
// OrgAuthController::login()
// 1. validate student_id + password
// 2. user exists && Hash::check
// 3. is_active check
// 4. expiredAccountError check
// 5. mobileAdminLoginError check (desktop-only admin view)
// 6. hasActiveOrg check (non super_admin/osas_admin must belong to ≥1 Approved org)
// 7. Auth::login, session regenerate
// 8. current_organization_id = first active org (non super_admin/osas_admin)
// 9. redirect: super_admin → /dean/dashboard; others → /dashboard
```

---

## 4. Terminology

| Term | Meaning |
|---|---|
| **Systemless org** | Org na `has_system = false` (at wala pang `organization_settings`). |
| **Mother Org** | Org na `type = 'Mother Org'`. |
| **Eligible Mother Org** | Mother Org na `type = 'Mother Org'` **AND** `status = 'Approved'` **AND** `has_system = true`. |
| **Fallback user** | User na ang LAHAT ng active orgs ay systemless → iruruta sa department Mother Org student page. |
| **Adviser / Dean / COMELEC membership** | Pivot role (`organization_user.role`) na may laman na: contains `'adviser'` (case-insensitive), contains `'dean'` (case-insensitive), o katumbas ng `'COMELEC'`/`'COMELEC'` variants (see `User::isAdviser()`, `User::isComelec()`, at `SettingsController` filters). |
| **Officer** | User na may posisyon sa org (e.g., President, VP, Secretary, Treasurer, Auditor, PIO, Event Coordinator, atbp.) — account role `admin`. Hindi kasama ang plain `Member`. |

---

## 5. Login Routing Algorithm (Proposed)

Ang bagong logic ay ida-dagdag sa `OrgAuthController::login()` (at i-consist din sa legacy `AuthController::login()` para sa `setOrganizationContext`), **pagkatapos** ng existing checks (credentials → is_active → expired → mobile-admin → hasActiveOrg) at **bago** i-commit ang `Auth::login` / `current_organization_id`:

```
function resolveSystemlessFallback(User $user): array|null
    # Returns null (no fallback) OR ['motherOrg' => Organization]

    # 1. Kung may kahit isang active org ang user na has_system = true → WALANG fallback.
    #    (Officer ay isang org lang, pero ang member ay maaaring nasa ilang orgs.)
    if user has any active org where has_system = true:
        return null

    # 2. Determine target department:
    deptId = user.department_id
    if deptId is null/empty:
        deptId = OrgAuthController::courseToDepartmentId(user.course)
    if deptId is null/empty:
        → block (Error A: "cannot determine department")

    # 3. Find eligible Mother Org for deptId
    motherOrg = Organization where department_id = deptId
                              and type = 'Mother Org'
                              and status = 'Approved'
                              and has_system = true
    if motherOrg is null:
        → block (Error B: "no system yet" — walang eligible mother org)

    return ['motherOrg' => motherOrg]
```

### Sa login flow mismo (pagkatapos ma-verify credentials):

```
fallback = resolveSystemlessFallback(user)

if fallback is not null:
    # 4. I-block ang staff-only memberships (walang student view sila)
    if user.isAdviser() or user.isComelec() or user.hasDeanMembership():
        → block (Error C: "admin/staff account; org has no system yet")

    # 5. Login + force Student View
    Auth::login(user)
    session->regenerate()
    user.active_view = 'student'          # persisted (DB)
    user.save()
    session['current_organization_id'] = fallback.motherOrg.id
    session['systemless_fallback'] = true # marker para sa session guards (see §8)
    redirect → /dashboard                  # studentDashboard() ang mapupunta
else:
    ... existing behavior unchanged ...
```

> **Tandaan:** Sa step 4, ang `hasDeanMembership()` ay bagong helper (o gamitin ang pattern ng `User::isComelec()`: `whereRaw('LOWER(organization_user.role) LIKE ?', ['%dean%'])`). Kasama rin dito ang mga variant tulad ng "Assoc. Dean" / "Dean/Assoc. Dean" (see `ApplyOrgController` normalization).

---

## 6. Error Cases & Messages (English, per interview)

Display pattern: **existing** — AJAX → toast (`showToast`) + red border sa `student_id` field; non-JS → `back()->withErrors(['student_id' => $msg])`. HTTP 422 sa JSON path (consistent sa existing errors).

| # | Condition | Message (English) |
|---|---|---|
| A | Walang ma-resolve na department (null `department_id` at hindi ma-match ang course) | `Unable to determine your department. Please contact OSAS to update your account before logging in.` |
| B | May department pero walang eligible Mother Org (wala, hindi Approved, o `has_system = false`) | `Your organization does not have a system yet. Please wait until your organization's system has been created before logging in.` |
| C | Adviser / Dean / COMELEC membership sa systemless org | `Your account is linked to an organization that does not have a system yet. Once your organization's system is created, you can log in. Please contact OSAS if you need access.` |

> Wording ay pwedeng i-fine-tune sa implementation; **English** ang language per interview. Consistent sa existing registration message: *"Your organization does not have a system yet. Please wait until your organization's system has been created before registering."*

---

## 7. Session Behavior (While Logged In)

Ang fallback user ay naka-mark sa session (`systemless_fallback = true`) at may mga sumusunod na guards:

1. **Forced Student View**
   - `active_view` ay naka-persist na `'student'` sa DB sa login.
   - `DashboardController::index()` — para sa fallback users, laging `studentDashboard()` ang ibabalik (kahit na nagbago ang `active_view` sa ibang paraan).
   - `getActiveView()` — dapat i-override/force sa `'student'` para sa fallback users (tulad ng existing comelec special-case).

2. **"Switch to Admin View" button — HIDDEN**
   - `resources/views/layouts/navigation.blade.php` (~line 394) at `resources/views/admin/profile/show.blade.php` (~line 25).
   - Condition: hidden kapag nasa fallback mode (wala pang system ang org ng user); **lalabas muli** kapag nagkaroon na ng system ang org (i.e., kapag hindi na systemless ang org).
   - `ViewSwitchController::switch()` — dapat mag-abort (403 / flash error) kapag nasa fallback mode.

3. **Org switcher — HIDDEN**
   - `resources/views/components/organization-switcher.blade.php` — hindi ire-render para sa fallback users; **lalabas muli** kapag may system na ang org.
   - `AuthController::switchOrganization()` — i-block (flash error / 403) ang pag-switch para sa fallback users (o sa kahit anong org na `has_system = false`).

4. **Notice/Banner — admin-role lang**
   - Magpakita ng maliit na notice sa student page **kapag `admin` ang account role** ng fallback user (hindi sa plain students), dahil wala siyang admin page para sa org niya.
   - Proposed copy: `You are viewing {Mother Org} because your organization does not have a system yet. You can manage your organization once its system is created.`
   - Placement: dashboard area / navigation — to be finalized sa implementation.

5. **`isEffectivelyStudent()` / nav branching** — para sa fallback users, dapat palaging `true` (student-style nav) ang effective view.

> **Pagka-wala na ang fallback (nagkaroon ng system ang org):** ang user ay babalik sa normal na flow — makikita na ang switch button at org switcher, at igagalang ang huling iniwang view (per interview: *"kapag meron na, kung saan last iniwan ng user ung view"*).

---

## 8. Design Decisions (from Interview)

| # | Decision | Notes |
|---|---|---|
| 1 | **Coverage:** LAHAT ng org na `has_system = false` | Non-Academic orgs + academic Sub-Orgs. Mother Orgs mismo ay laging may system. |
| 2 | **Department source:** stored `department_id` muna; kung null/empty, **fallback sa course mapping** (`courseToDepartmentId`). | Per interview round 1 (Q2) at round 2 (Q4). |
| 3 | **Priority:** may-system org ang manalo | Kung may kahit isang active org ang user na may system → normal login, walang fallback. (Konsistente sa "officer ay isang org lang" na rule.) |
| 4 | **Adviser/Dean/COMELEC:** i-BLOCK sa login with clear error | Hindi sila makakakuha ng student-page fallback (admin-only sila, walang switch to student view). |
| 5 | **View switching:** force student view; hidden switch button | Lalabas ang button kapag nagkaroon na ng system ang org. |
| 6 | **Org context:** `current_organization_id` = Mother Org habang walang system | Kapag may system na ang org, normal flow + last remembered view. |
| 7 | **No eligible mother org:** i-block LAHAT (students + admins) | Per interview round 3 (Q1). |
| 8 | **Students:** same fallback sa mother org; kung walang system ang mother org → error handling | Per interview round 3 (Q2). |
| 9 | **Error display:** follow existing pattern | AJAX toast + red field; non-JS fallback. |
| 10 | **Scope:** login flow lang; registration hindi galawin | Per interview round 3 (Q4). |
| 11 | **Session guards:** kasama sa scope | Switch button hidden, org switcher hidden, block sa view/org switch endpoints. |
| 12 | **Message language:** English | Per interview round 4 (Q4). |
| 13 | **Test data:** may existing Non-Academic org + members sa DB | Gagamitin sa pag-verify. |
| 14 | **Non-acad visibility feature:** HINDI kasama sa task na ito | Follow-up pagkatapos ma-verify ang login fallback (see §10). |

---

## 9. Files Likely Affected (Implementation Guide)

- `app/Http/Controllers/Auth/OrgAuthController.php` — main login routing changes.
- `app/Http/Controllers/Auth/AuthController.php` — consistent changes sa legacy login + `switchOrganization()` guard.
- `app/Http/Controllers/DashboardController.php` — force `studentDashboard()` para sa fallback users.
- `app/Http/Controllers/ViewSwitchController.php` — block ang switch habang fallback mode.
- `app/Models/User.php` — bagong helpers: `isSystemlessFallback()` (or similar), `hasDeanMembership()`; i-update ang `getActiveView()` / `canSwitchView()` para sa fallback users.
- `app/Support/` or `app/Services/` — (optional) dedicated service/helper para sa fallback resolution (e.g., `SystemlessOrgFallbackResolver`) para reusable sa login at middleware.
- `app/Http/Middleware/` — (optional) bagong middleware o pag-update ng `CheckOrganizationAccess`/`active.account` para sa session guard.
- `resources/views/layouts/navigation.blade.php` — hide "Switch to Admin View" + notice placement.
- `resources/views/admin/profile/show.blade.php` — hide "Switch to Student View".
- `resources/views/components/organization-switcher.blade.php` — hide para sa fallback users.
- `public/js/auth.js` / `resources/views/auth/org_auth.blade.php` — walang major changes (existing error display pattern ang gagamitin), maliban kung may bagong field-level errors.
- Tests: `tests/Feature/` — bagong feature tests (see §11).

---

## 10. Out of Scope — Follow-up Feature: Non-Academic Post Visibility

Per interview (round 3, Q5): kailangan din ng Non-Academic orgs ang kakayahang **i-setup kung sino-sino lang ang makakakita ng isang post** (announcements/etc.) — e.g., member-only vs public ("kahit anong org ung pede ipakita e, kung member or pang public"). 

**PRIORITY:** Huling gagawin — pagkatapos ma-test at ma-verify ang login fallback flow na ito (part ng admin side ng Non-Academic). Hindi ito i-implement sa task na ito; hiwalay na spec/task ang kailangan.

---

## 11. Testing Plan

### Feature tests (PHPUnit — `tests/Feature/`)
1. **Systemless org member (student, BSIT)** → login succeeds; redirect `/dashboard`; `current_organization_id` = CCS Mother Org (or department mother org); `active_view` = `'student'`; student dashboard view rendered.
2. **Systemless org member (admin/officer)** → login succeeds; forced student view; `isEffectivelyStudent()` = true; switch button hidden.
3. **Adviser member ng systemless org** → login blocked; error message (Error C); HTTP 422 sa JSON.
4. **COMELEC member ng systemless org** → login blocked (Error C).
5. **Dean/Assoc. Dean member** → login blocked (Error C).
6. **User na walang department_id at hindi ma-match ang course** → login blocked (Error A).
7. **Department na walang eligible Mother Org** (walang mother org / hindi Approved / `has_system=false`) → login blocked (Error B), students kasama.
8. **User na may kahit isang org na may system** → walang fallback; normal login behavior (regression).
9. **Fallback user tries `POST /view/switch`** → 403 / blocked.
10. **Fallback user tries `POST /organization/switch`** → blocked.
11. **Org na nagkaroon ng system (has_system false→true)** → hindi na fallback; switch button at org switcher lalabas muli; last-view respected.
12. **Non-JS login path** → `back()->withErrors()` na may tamang message.

### Manual verification (browser)
- Gamit ang existing Non-Academic org + member accounts sa DB (per interview, may existing test data na).
- Kaso: non-acad member na BSIT course → dapat mapunta sa CCS Mother Org student page, walang admin access, walang switch button, walang org switcher, may notice kung admin ang role.
- Kaso: adviser ng non-acad → blocked sa login na may malinaw na toast.
- Kaso: officer ng SOCCS (may system) na member din ng non-acad → normal SOCCS login (no fallback).

---

## 12. Assumptions & Open Items

1. **`department_id` availability:** Inaasahan na may laman ang `department_id` ng karamihan sa mga non-acad members (tinatrack ng OSAS ang academic department nila). Kung hindi, course fallback ang sasalo. — *I-verify sa test data.*
2. **Dean detection:** May account-level `dean`/`super_admin` roles at org-level `Dean` pivot roles. Sa fallback, ang org-level (pivot) Dean membership ang magti-trigger ng Error C; ang account-level `super_admin`/`dean` ay hindi aabot sa fallback logic dahil may sarili silang dashboard routing (dean dashboard).
3. **`active_view` persistence:** Ipo-persist ang `'student'` sa DB para sa fallback users. Kapag may system na ang org, magsisimula sila sa student view at pwede nang mag-switch papuntang admin (consistent sa interview).
4. **Session marker:** Kailangan ng malinaw na marker (`session('systemless_fallback')`) para sa UI guards; i-clear kapag hindi na applicable (e.g., pag-alis sa session, pag-update ng org status).
5. **`hasActiveOrg` check:** Mananatili; ang fallback ay ida-dagdag pagkatapos nito (ang systemless org ay Approved pa rin kaya pumapasa).
6. **Open:** Kung saan eksaktong ilalagay ang admin notice/banner (dashboard vs navigation) — i-finalize sa implementation.
