@extends(request()->boolean('fragment') ? 'layouts.fragment' : 'layouts.admin')

@php
    $primaryColor = $organization->settings?->primary_color ?? '#8238B3';
    $orgTypeNormalized = $organization->type === 'Sub-Org' ? 'Sub Org' : $organization->type;
    $needsMembers = in_array($orgTypeNormalized, ['Mother Org', 'Non-Academic'], true);
    $renewBlockedReason = $renewBlockedReason ?? null;
    $renewBlockedTitle = $renewBlockedTitle ?? null;
    $renewBlockedMsg = $renewBlockedMsg ?? null;
    $daysUntil = $daysUntil ?? null;
    $accreditationStatus = $accreditationStatus ?? 'none';
    $currentPeriod = $currentPeriod ?? null;
    $existingDean = $existingDean ?? null;
    $renewerPosition = $renewerPosition ?? null;
    $hasMotherOrg = $hasMotherOrg ?? false;
    $departments = $departments ?? collect();
    $dbDeanSnapshot = $existingDean ? [
        'first_name' => strtolower(trim($existingDean->first_name ?? '')),
        'middle_name' => strtolower(trim($existingDean->middle_name ?? '')),
        'last_name' => strtolower(trim($existingDean->last_name ?? '')),
        'suffix' => strtolower(trim($existingDean->suffix ?? '')),
    ] : null;
@endphp

@section('title', 'Renewal of Accreditation - ' . $organization->name)

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" id="renew-root" x-data="{}">
    @php
        $isRenewBlocked = !empty($renewBlockedReason);
        // Prefer controller-provided title/msg (from renewBlockInfo), fallback to legacy — includes global + per-org Start Renewal gates.
        $isStartGate = in_array($renewBlockedReason, ['not_started_global','not_started_org'], true);
        if ($isRenewBlocked && empty($renewBlockedTitle)) {
            if ($renewBlockedReason === 'no_period') $renewBlockedTitle = 'Renewal Period Not Set';
            elseif ($isStartGate) $renewBlockedTitle = $renewBlockedReason === 'not_started_global' ? 'Renewal Not Yet Started' : 'Renewal Not Yet Opened For Your Organization';
            else $renewBlockedTitle = 'Accreditation Still Active';
        }
        if ($isRenewBlocked && empty($renewBlockedMsg)) {
            if ($renewBlockedReason === 'no_period') $renewBlockedMsg = 'The accreditation renewal period has not been set or opened yet by the OSAS. Please wait for an official announcement or ask OSAS to click Apply + Start Renewal.';
            elseif ($renewBlockedReason === 'not_started_global') $renewBlockedMsg = 'OSAS hasn\'t clicked Start Renewal yet. Please wait for OSAS to generate the renewal queue before you can renew.';
            elseif ($renewBlockedReason === 'not_started_org') $renewBlockedMsg = 'Your organization\'s renewal queue hasn\'t been opened by OSAS yet. Please ask OSAS to generate renewals for expired organizations.';
            else $renewBlockedMsg = 'Renewal opens after '.($organization->accreditation_expires_at?->format('M d, Y') ?? 'expiry').' ('.($daysUntil !== null ? $daysUntil.' day'.($daysUntil===1?'':'s').' left' : 'still valid').').';
        }
        $renewBlockedTitle = $renewBlockedTitle ?? ($renewBlockedReason === 'no_period' ? 'Renewal Period Not Set' : ($isStartGate ? ($renewBlockedReason === 'not_started_global' ? 'Renewal Not Yet Started' : 'Renewal Not Yet Opened For Your Organization') : 'Accreditation Still Active'));
        $renewBlockedMsg = $renewBlockedMsg ?? ($renewBlockedReason === 'no_period'
            ? 'The accreditation renewal period has not been set or opened yet by the OSAS. Please wait for an official announcement or ask OSAS to click Apply + Start Renewal.'
            : ($renewBlockedReason === 'not_started_global' ? 'OSAS hasn\'t clicked Start Renewal yet. Please wait for OSAS to generate the renewal queue before you can renew.'
            : ($renewBlockedReason === 'not_started_org' ? 'Your organization\'s renewal queue hasn\'t been opened by OSAS yet. Please ask OSAS to generate renewals for expired organizations.'
            : 'Renewal opens after '.($organization->accreditation_expires_at?->format('M d, Y') ?? 'expiry').' ('.($daysUntil !== null ? $daysUntil.' day'.($daysUntil===1?'':'s').' left' : 'still valid').').')));
        $renewExpiryLabel = $organization->accreditation_expires_at?->format('M d, Y') ?? 'Not set';
        $renewStatusKey = $accreditationStatus ?? 'none';
        $renewStatusLabel = $renewStatusKey === 'expired' ? 'Expired' : ($renewStatusKey === 'expiring' ? 'Expiring soon' : ($renewStatusKey === 'active' ? 'Active' : 'No period'));
    @endphp
    {{-- Blocked state reuses the global renew-blocked modal in layouts.admin (single source).
         Fired on next tick so it also works when this fragment is swapped in via SPA navigation. --}}
    @if($isRenewBlocked)
        <div x-init="$nextTick(() => window.dispatchEvent(new CustomEvent('open-renew-blocked-modal', { detail: { title: @js($renewBlockedTitle), message: @js($renewBlockedMsg) } })))"></div>
        <noscript>
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                <p class="text-sm font-semibold text-amber-800">{{ $renewBlockedTitle }}</p>
                <p class="text-xs text-amber-700 mt-0.5">{{ $renewBlockedMsg }}</p>
                <a href="{{ route('dashboard') }}" data-admin-navigation-loading class="mt-2 inline-block text-xs font-bold" style="color: var(--primary-color)">Back to Dashboard</a>
            </div>
        </noscript>
    @endif

    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 4000)"
             class="fixed top-4 right-4 z-50 w-[calc(100%-2rem)] max-w-sm bg-white border-l-4 border-green-500 rounded-md shadow-lg px-4 py-3 flex items-start gap-3" style="display: none;">
            <i class="ph ph-check-circle text-green-600 mt-0.5"></i>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-gray-900">Success</p>
                <p class="text-sm text-gray-600 break-words">{{ session('success') }}</p>
            </div>
            <button @click="show = false" class="text-gray-400 hover:text-gray-600" aria-label="Dismiss notification"><i class="ph ph-x"></i></button>
        </div>
    @endif

    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between mb-4">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wider" style="color: var(--primary-color);">Operations / Accreditation</p>
            <h1 class="text-2xl font-bold text-gray-900">Renew Accreditation</h1>
            <p class="text-sm text-gray-500 mt-1 truncate">{{ $organization->name }} &middot; {{ $organization->type }} &middot; Expires {{ $renewExpiryLabel }}</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $renewStatusKey === 'expired' ? 'bg-red-100 text-red-700' : ($renewStatusKey === 'expiring' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600') }}">
                <i class="ph {{ $renewStatusKey === 'expired' ? 'ph-x-circle' : ($renewStatusKey === 'expiring' ? 'ph-warning-circle' : 'ph-seal-check') }}"></i>{{ $renewStatusLabel }}
            </span>
            <a href="{{ route('dashboard') }}" data-admin-navigation-loading class="text-xs font-semibold text-gray-500 hover:text-gray-700 flex items-center gap-1.5">
                <i class="ph ph-arrow-left text-[10px]"></i> Dashboard
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 border border-gray-200 bg-white rounded-lg divide-x divide-gray-200 mb-4">
        <div class="px-4 py-3 sm:px-5">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Organization</p>
                <i class="ph ph-buildings text-sm" style="color: var(--primary-color);"></i>
            </div>
            <p class="text-sm font-semibold text-gray-900 mt-1 truncate" title="{{ $organization->name }}">{{ $organization->org_acronym ?: $organization->name }}</p>
        </div>
        <div class="px-4 py-3 sm:px-5">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Type / Dept</p>
                <i class="ph ph-tag text-sm text-blue-600"></i>
            </div>
            <p class="text-sm font-semibold text-gray-900 mt-1 truncate" title="{{ $organization->type }} — {{ $organization->department?->department_name ?? 'N/A' }}">{{ $organization->type }}</p>
        </div>
        <div class="px-4 py-3 sm:px-5 border-t border-gray-200 lg:border-t-0">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Expires</p>
                <i class="ph ph-calendar-blank text-sm text-amber-600"></i>
            </div>
            <p class="text-sm font-semibold text-gray-900 mt-1">{{ $renewExpiryLabel }}{{ $daysUntil !== null ? ' · ' . $daysUntil . 'd left' : '' }}</p>
        </div>
        <div class="px-4 py-3 sm:px-5 border-t border-gray-200 lg:border-t-0">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Progress</p>
                <i class="ph ph-arrows-clockwise text-sm text-green-600"></i>
            </div>
            <p class="text-sm font-semibold text-gray-900 mt-1"><span id="renew-progress-label">Step 1 of 6</span></p>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg px-4 py-3 mb-4">
        <div class="flex items-center">
            <div class="flex items-center flex-1">
                <div id="renew-step1-circle" class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-[11px] shrink-0" style="background-color: var(--primary-color); color:white">1</div>
                <div class="flex-1 h-1 mx-1 rounded" id="renew-progress1" style="background-color: var(--primary-color)"></div>
            </div>
            <div class="flex items-center flex-1">
                <div id="renew-step2-circle" class="w-7 h-7 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-bold text-[11px] shrink-0">2</div>
                <div class="flex-1 h-1 bg-gray-200 mx-1 rounded" id="renew-progress2"></div>
            </div>
            <div class="flex items-center flex-1">
                <div id="renew-step3-circle" class="w-7 h-7 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-bold text-[11px] shrink-0">2.1</div>
                <div class="flex-1 h-1 bg-gray-200 mx-1 rounded" id="renew-progress3"></div>
            </div>
            <div class="flex items-center flex-1">
                <div id="renew-step4-circle" class="w-7 h-7 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-bold text-[11px] shrink-0">2.2</div>
                <div class="flex-1 h-1 bg-gray-200 mx-1 rounded" id="renew-progress4"></div>
            </div>
            <div class="flex items-center flex-1">
                <div id="renew-step5-circle" class="w-7 h-7 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-bold text-[11px] shrink-0">2.3</div>
                <div class="flex-1 h-1 bg-gray-200 mx-1 rounded" id="renew-progress5"></div>
            </div>
            <div id="renew-step6-circle" class="w-7 h-7 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-bold text-[11px] shrink-0">3</div>
        </div>
        <div class="flex items-center mt-1.5 text-[11px]">
            <span class="font-semibold" style="color: var(--primary-color)">Info</span>
            <span class="flex-1 text-center text-gray-500">Documents</span>
            <span class="w-14 text-center text-gray-500">Dean</span>
            <span class="w-14 text-center text-gray-500">Officers</span>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_280px] gap-4 items-start">
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden min-w-0">
            <form id="renewForm" enctype="multipart/form-data" novalidate>
                @csrf
                <input type="hidden" name="current_step" value="1">

                <!-- STEP 1: Renewer Info -->
                <div id="renew-step1" class="renew-step">
                    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                        <i class="ph ph-user text-xs" style="color: var(--primary-color)"></i>
                        <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wide">Authorized Requester (Prefilled)</h3>
                    </div>
                    <div class="p-5 space-y-4">
                        <p class="text-xs text-gray-500">Prefilled from your account. Update if the renewer changed, otherwise Next. Enter ID to auto-fill.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1 md:col-span-2">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Student / Employee ID</label>
                                <input type="text" name="renewer_student_id" id="renew_student_id" value="{{ auth()->user()->student_id }}" placeholder="2021-0001 or 00-0000-0000" maxlength="12" pattern="^(\d{4}-\d{4}|\d{2}-\d{4}-\d{2,4})$" title="Student ID (0000-0000) or Employee/Adviser ID (00-0000-0000)"
                                       class="w-full px-3 py-2 bg-white border border-gray-200 rounded-md text-sm font-medium focus:ring-2 transition-all"
                                       oninput="formatRenewRequesterID(this)" onblur="fetchRenewRequesterData(this.value)">
                                <p class="text-[10px] text-gray-500">Prefilled from your account — change the ID to renew for someone else (auto-fills name &amp; email, email editable)</p>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">First Name *</label>
                                <input type="text" name="renewer_first_name" id="renew_first_name" value="{{ auth()->user()->first_name }}" required
                                       class="w-full px-3 py-2 bg-white border border-gray-200 rounded-md text-sm font-medium focus:ring-2 transition-all">
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Last Name *</label>
                                <input type="text" name="renewer_last_name" id="renew_last_name" value="{{ auth()->user()->last_name }}" required
                                       class="w-full px-3 py-2 bg-white border border-gray-200 rounded-md text-sm font-medium focus:ring-2 transition-all">
                            </div>
                            <div class="space-y-1 md:col-span-2">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Official Email *</label>
                                <input type="email" name="renewer_email" id="renew_email" value="{{ auth()->user()->email }}" required
                                       class="w-full px-3 py-2 bg-white border border-gray-200 rounded-md text-sm font-medium focus:ring-2 transition-all">
                            </div>
                            <div class="space-y-1 md:col-span-2">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Position</label>
                                <div id="renew_position" data-initial="{{ $renewerPosition ?? '' }}" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-sm font-semibold text-gray-700" aria-live="polite">{{ ($renewerPosition ?? '') !== '' ? $renewerPosition : 'Type your ID above — position is detected automatically' }}</div>
                                <p class="text-[10px] text-gray-500">0000-0000 → Organization President · 00-0000-0000 → Organization Adviser</p>
                            </div>
                        </div>
                    </div>
                    <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-end">
                        <button type="button" onclick="renewNext(2)" style="background-color: var(--primary-color)" class="px-5 py-2 text-white font-bold text-xs uppercase tracking-wide rounded-md hover:opacity-90 transition-all flex items-center gap-2">Continue <i class="ph ph-arrow-right text-[10px]"></i></button>
                    </div>
                </div>

                <!-- STEP 2: Documents Part 1 -->
                <div id="renew-step2" class="renew-step hidden">
                    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                        <i class="ph ph-file-text text-xs" style="color: var(--primary-color)"></i>
                        <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wide">Document Requirements (Part 1 of 2)</h3>
                    </div>
                    <div class="p-5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach([
                                ['name' => 'adviser_commitment', 'label' => 'Adviser Commitment Form'],
                                ['name' => 'application_form', 'label' => 'Application letter for recognition/renewal'],
                                ['name' => 'certification_of_officers', 'label' => 'Certification of Officers'],
                                ['name' => 'plan_of_activities', 'label' => 'Plan of Activities']
                            ] as $doc)
                                <div class="space-y-1">
                                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">{{ $doc['label'] }} <span class="text-red-500">*</span></label>
                                    <input type="file" name="{{ $doc['name'] }}" id="renew_{{ $doc['name'] }}" required accept=".pdf,.jpg,.jpeg,.png"
                                           class="w-full text-xs file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-bold file:bg-gray-100 file:text-gray-600 hover:file:bg-gray-200 border border-gray-200 rounded-md">
                                    @if($doc['name']==='certification_of_officers')
                                        <p class="text-[10px] text-blue-500">Uploading this will auto-fill Dean & Officers (Step 5-6). Email stays editable.</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-between">
                        <button type="button" onclick="renewPrev(1)" class="px-4 py-2 bg-white border border-gray-200 text-gray-500 font-bold text-xs uppercase tracking-wide rounded-md hover:bg-gray-100 transition-all"><i class="ph ph-arrow-left text-[10px]"></i> Previous</button>
                        <button type="button" onclick="renewNext(3)" style="background-color: var(--primary-color)" class="px-5 py-2 text-white font-bold text-xs uppercase tracking-wide rounded-md hover:opacity-90 transition-all flex items-center gap-2">Next <i class="ph ph-arrow-right text-[10px]"></i></button>
                    </div>
                </div>

                <!-- STEP 3: Documents Part 2 -->
                <div id="renew-step3" class="renew-step hidden">
                    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                        <i class="ph ph-file-text text-xs" style="color: var(--primary-color)"></i>
                        <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wide">Document Requirements (Part 2 of 2)</h3>
                    </div>
                    <div class="p-5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Constitution and By-laws <span class="text-red-500">*</span></label>
                                <input type="file" name="constitution_bylaws" required accept=".pdf,.jpg,.jpeg,.png" class="w-full text-xs file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-bold file:bg-gray-100 file:text-gray-600 hover:file:bg-gray-200 border border-gray-200 rounded-md">
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Renewal Form <span class="text-red-500">*</span></label>
                                <input type="file" name="renewal_form" required accept=".pdf,.jpg,.jpeg,.png" class="w-full text-xs file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-bold file:bg-gray-100 file:text-gray-600 hover:file:bg-gray-200 border border-gray-200 rounded-md">
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Accomplishment Reports <span class="text-gray-400">(Optional)</span></label>
                                <input type="file" name="accomplishment_reports" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-xs file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-bold file:bg-gray-100 file:text-gray-600 hover:file:bg-gray-200 border border-gray-200 rounded-md">
                                <p class="text-[10px] text-gray-400">Optional</p>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Certification from Dean</label>
                                <input type="file" name="certification_dean" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-xs file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-bold file:bg-gray-100 file:text-gray-600 hover:file:bg-gray-200 border border-gray-200 rounded-md">
                                <p class="text-[10px] text-gray-400">Optional</p>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Financial Report</label>
                                <input type="file" name="financial_report" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-xs file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-bold file:bg-gray-100 file:text-gray-600 hover:file:bg-gray-200 border border-gray-200 rounded-md">
                                <p class="text-[10px] text-gray-400">Optional</p>
                            </div>
                        </div>
                    </div>
                    <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-between">
                        <button type="button" onclick="renewPrev(2)" class="px-4 py-2 bg-white border border-gray-200 text-gray-500 font-bold text-xs uppercase tracking-wide rounded-md hover:bg-gray-100 transition-all"><i class="ph ph-arrow-left text-[10px]"></i> Previous</button>
                        <button type="button" onclick="renewNext(4)" style="background-color: var(--primary-color)" class="px-5 py-2 text-white font-bold text-xs uppercase tracking-wide rounded-md hover:opacity-90 transition-all flex items-center gap-2">Next <i class="ph ph-arrow-right text-[10px]"></i></button>
                    </div>
                </div>

                <!-- STEP 4: Members -->
                <div id="renew-step4" class="renew-step hidden">
                    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                        <i class="ph ph-users text-xs" style="color: var(--primary-color)"></i>
                        <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wide">List of Members</h3>
                    </div>
                    <div class="p-5">
                        <div id="renew-mother-exists-notice" class="hidden">
                            <div class="flex flex-col items-center justify-center py-10 text-center">
                                <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mb-4"><i class="ph ph-check-circle text-green-500 text-3xl"></i></div>
                                <h3 class="text-lg font-bold text-gray-800 mb-2">No Upload Needed!</h3>
                                <p class="text-sm text-gray-500 max-w-xs">Your Mother Organization has already submitted the List of Members for this department. You can proceed to the next step.</p>
                            </div>
                        </div>
                        <div id="renew-member-upload">
                            <div class="border-2 border-dashed rounded-lg p-6" style="border-color: var(--primary-color)">
                                <div class="text-center mb-4">
                                    <i class="ph ph-users text-4xl mb-2" style="color: var(--primary-color)"></i>
                                    <p class="text-sm font-semibold text-gray-700">Click or drag files to upload</p>
                                    <p class="text-xs text-gray-500">PDF, JPG, PNG - Multiple files allowed (max 20)</p>
                                </div>
                                <input type="file" id="renewListMembersInput" multiple accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                                <button type="button" id="renewAddMembersBtn" class="w-full px-4 py-2 text-white rounded-lg hover:opacity-90 text-sm font-bold" style="background-color: var(--primary-color)"><i class="ph ph-plus mr-2"></i> Add Files</button>
                                <div id="renewMemberPreview" class="mt-4 space-y-2"></div>
                            </div>
                            <p class="text-[11px] text-gray-400 mt-2" id="renew-members-hint"></p>
                        </div>

                        <!-- Additional Documents (Other Files) -->
                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <div class="flex items-center gap-2 mb-3">
                                <i class="ph ph-folder-plus text-xs" style="color: var(--primary-color)"></i>
                                <h4 class="text-[11px] font-bold text-gray-700 uppercase tracking-wide">Additional Documents</h4>
                                <span class="text-[10px] text-gray-400 font-medium">Optional — for any other files</span>
                            </div>
                            <div class="border border-dashed rounded-lg p-4 bg-gray-50/50">
                                <div class="flex items-center justify-between mb-3">
                                    <p class="text-[11px] text-gray-500">Upload other supporting files (max 20, 10MB each)</p>
                                    <button type="button" id="renewAddOtherBtn" class="px-3 py-1.5 bg-white border border-gray-200 text-[10px] font-bold uppercase tracking-wide rounded-md hover:border-gray-300" style="color: var(--primary-color)"><i class="ph ph-plus mr-1"></i> Add Files</button>
                                </div>
                                <input type="file" id="renewOtherFilesInput" multiple accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                                <div id="renewOtherPreview" class="space-y-2"></div>
                                <p class="text-[10px] text-gray-400 mt-2">Accepted: PDF, JPG, PNG</p>
                            </div>
                        </div>
                    </div>
                    <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-between">
                        <button type="button" onclick="renewPrev(3)" class="px-4 py-2 bg-white border border-gray-200 text-gray-500 font-bold text-xs uppercase tracking-wide rounded-md hover:bg-gray-100 transition-all"><i class="ph ph-arrow-left text-[10px]"></i> Previous</button>
                        <button type="button" onclick="renewNext(5)" style="background-color: var(--primary-color)" class="px-5 py-2 text-white font-bold text-xs uppercase tracking-wide rounded-md hover:opacity-90 transition-all flex items-center gap-2">Next <i class="ph ph-arrow-right text-[10px]"></i></button>
                    </div>
                </div>

                <!-- STEP 5: Dean (OSAS-assigned, autofilled) -->
                <div id="renew-step5" class="renew-step hidden">
                    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                        <i class="ph ph-graduation-cap text-xs" style="color: var(--primary-color)"></i>
                        <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wide">College Dean</h3>
                    </div>
                    <div class="p-5">
                        <p class="text-xs text-gray-500 mb-4">The Dean is set by OSAS for each college and attached automatically. No action needed.</p>
                        <div class="border border-green-200 rounded-xl p-5 bg-green-50 space-y-2">
                            <p class="text-xs font-semibold text-green-800"><i class="ph ph-check-circle mr-1"></i> Auto-assigned by OSAS</p>
                            @if(isset($existingDean) && $existingDean)
                                <p class="text-xs text-green-700">Assigned Dean: <strong>{{ $existingDean->first_name }} {{ $existingDean->last_name }}</strong>{{ $existingDean->email ? ' ('.$existingDean->email.')' : '' }}</p>
                            @else
                                <p class="text-[11px] text-green-700">The assigned Dean for your college will be attached upon submission. If none is assigned yet, please contact OSAS.</p>
                            @endif
                        </div>
                    </div>
                    <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-between">
                        <button type="button" onclick="renewPrev(4)" class="px-4 py-2 bg-white border border-gray-200 text-gray-500 font-bold text-xs uppercase tracking-wide rounded-md hover:bg-gray-100 transition-all"><i class="ph ph-arrow-left text-[10px]"></i> Previous</button>
                        <button type="button" onclick="renewNext(6)" style="background-color: var(--primary-color)" class="px-5 py-2 text-white font-bold text-xs uppercase tracking-wide rounded-md hover:opacity-90 transition-all flex items-center gap-2">Next <i class="ph ph-arrow-right text-[10px]"></i></button>
                    </div>
                </div>

                <!-- STEP 6: Officers -->
                <div id="renew-step6" class="renew-step hidden">
                    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                        <i class="ph ph-users-three text-xs" style="color: var(--primary-color)"></i>
                        <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wide">Incoming Officers</h3>
                        <span class="text-[10px] text-gray-400 ml-2">Auto-filled from Certification of Officers</span>
                    </div>
                    <div class="p-5">
                        <div id="renewOfficersContainer" class="space-y-3 max-h-[60vh] overflow-y-auto pr-1 custom-scrollbar"></div>
                        <button type="button" onclick="addRenewCustomOfficer()" class="mt-4 px-4 py-2 border border-dashed border-gray-300 rounded-lg text-xs font-bold text-gray-600 hover:bg-gray-50 w-full"><i class="ph ph-plus mr-1"></i> Add Other Position</button>
                    </div>
                    <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-between">
                        <button type="button" onclick="renewPrev(5)" class="px-4 py-2 bg-white border border-gray-200 text-gray-500 font-bold text-xs uppercase tracking-wide rounded-md hover:bg-gray-100 transition-all"><i class="ph ph-arrow-left text-[10px]"></i> Previous</button>
                        <button type="submit" id="renewSubmitBtn" style="background-color: var(--primary-color)" class="px-6 py-2 text-white font-bold text-xs uppercase tracking-wide rounded-md hover:opacity-90 transition-all flex items-center gap-2 disabled:opacity-60"><i class="ph ph-paper-plane-tilt text-[10px]"></i> Final Submission</button>
                    </div>
                </div>
            </form>
        </div>

        <aside class="space-y-3 xl:sticky xl:top-20 min-w-0">
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h3 class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-2">Requirements</h3>
                <ul class="space-y-1.5 text-[11px] text-gray-600 leading-relaxed">
                    <li class="flex gap-1.5"><i class="ph ph-check-circle mt-0.5 shrink-0" style="color: var(--primary-color)"></i>Current, signed, legible documents</li>
                    <li class="flex gap-1.5"><i class="ph ph-check-circle mt-0.5 shrink-0" style="color: var(--primary-color)"></i>PDF, JPG, PNG (max 10MB each)</li>
                    <li class="flex gap-1.5"><i class="ph ph-check-circle mt-0.5 shrink-0" style="color: var(--primary-color)"></i>Re-upload all files for renewal</li>
                </ul>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h3 class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-2">Steps</h3>
                <ol class="space-y-1.5 text-[11px] text-gray-600">
                    <li><span class="font-bold text-gray-800">1 Info</span> — requester details</li>
                    <li><span class="font-bold text-gray-800">2 Docs</span> — requirements 1 &amp; 2</li>
                    <li><span class="font-bold text-gray-800">2.2 Members</span> — list upload</li>
                    <li><span class="font-bold text-gray-800">2.3 Dean</span> — dean details</li>
                    <li><span class="font-bold text-gray-800">3 Officers</span> — incoming officers</li>
                </ol>
            </div>
            <div id="renew-cert-prefill-status" class="hidden bg-blue-50 border border-blue-100 rounded-lg p-3">
                <p class="text-[11px] font-bold text-blue-700">Auto-fill</p>
                <p class="text-[11px] text-blue-600" id="renew-cert-prefill-text">Upload Certification of Officers to auto-fill Dean &amp; Officers.</p>
            </div>
        </aside>
    </div>
</div>

<!-- Floating ID lookup progress card (ported from apply-org; bar/dot themed via --primary-color) -->
<div id="renew-lookup-card" class="hidden fixed top-20 right-4 z-50 bg-white rounded-lg shadow-xl border border-gray-200 px-4 py-3" role="status" aria-live="polite">
    <div class="flex items-center gap-2">
        <span id="renew-lookup-dot" class="w-3 h-3 rounded-full flex-shrink-0" style="background-color:var(--primary-color);"></span>
        <span id="renew-lookup-title" class="font-bold text-gray-900 text-sm flex-1">Matching Officer IDs</span>
        <button type="button" onclick="renewHideLookupProgress()" class="text-gray-400 hover:text-gray-600 text-sm leading-none px-1" aria-label="Dismiss">&times;</button>
    </div>
    <div class="flex items-center gap-2 mt-2">
        <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
            <div id="renew-lookup-fill" class="h-full rounded-full" style="width:0%;background-color:var(--primary-color);"></div>
        </div>
        <span id="renew-lookup-pct" class="text-xs font-bold text-gray-700 w-10 text-right tabular-nums">0%</span>
    </div>
    <p id="renew-lookup-line1" class="text-xs text-gray-600 mt-2 truncate">Preparing…</p>
    <p id="renew-lookup-line2" class="text-xs text-gray-500"></p>
</div>

<style>
    :root { --primary-color: {{ $primaryColor }}; --org-primary: {{ $primaryColor }}; --org-primary-light: {{ $primaryColor }}15; }
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 10px; }
    input:focus, select:focus { outline: none !important; box-shadow: 0 0 0 3px var(--org-primary-light) !important; border-color: var(--primary-color) !important; }

    /* Floating ID lookup progress card (ported from apply-org; bar/dot use org theme via --primary-color) */
    #renew-lookup-card { width: 340px; max-width: calc(100vw - 32px); animation: renewLookupSlideIn 0.3s ease-out; }
    #renew-lookup-card.hiding { animation: renewLookupSlideOut 0.3s ease-out; }
    #renew-lookup-fill { transition: width 0.25s ease-out; }
    #renew-lookup-card.is-loading #renew-lookup-fill {
        background-image: linear-gradient(
            45deg,
            rgba(255, 255, 255, 0.35) 25%,
            transparent 25%,
            transparent 50%,
            rgba(255, 255, 255, 0.35) 50%,
            rgba(255, 255, 255, 0.35) 75%,
            transparent 75%
        );
        background-size: 16px 16px;
        animation: renewLookupStripes 0.8s linear infinite;
    }
    @keyframes renewLookupStripes {
        from { background-position: 0 0; }
        to { background-position: 16px 0; }
    }
    #renew-lookup-card.is-loading #renew-lookup-dot { animation: renewLookupPulse 1.2s ease-in-out infinite; }
    @keyframes renewLookupPulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.45; transform: scale(0.85); }
    }
    @keyframes renewLookupSlideIn {
        from { opacity: 0; transform: translateX(24px); }
        to { opacity: 1; transform: none; }
    }
    @keyframes renewLookupSlideOut {
        from { opacity: 1; transform: none; }
        to { opacity: 0; transform: translateX(24px); }
    }
</style>

<script>
    // SPA-safe state: this <script> is re-executed on every fragment swap
    // (see executeAdminPageScripts in layouts.admin), so `const` re-declaration
    // would throw and wipe in-progress files. All state lives on window.
    var renewPrimary = "{{ $primaryColor }}";
    var renewOrgType = @json($orgTypeNormalized);
    var renewHasMotherOrg = @json($hasMotherOrg);
    var renewOrgDeptId = @json($organization->department_id);
    var renewDepartments = @json($departments);
    var officerPositions = @json(\App\Services\CertificationOfOfficersRoleSlotRegistry::browserPositions());
    window.__renewFileState = window.__renewFileState || null;
    // Fresh fragment render = fresh File lists (same as a full page reload).
    window.__renewFileState = { members: [], others: [], customSlots: [] };
    var customOfficerSlots = window.__renewFileState.customSlots;
    var renewMemberFiles = window.__renewFileState.members;
    var renewOtherFiles = window.__renewFileState.others;

    // NOTE: `var` (not const/let) — this script re-executes on every SPA
    // fragment swap and top-level const/let re-declaration would throw.
    var courseMapping = {
        "1": ["Bachelor of Science in Civil Engineering","Bachelor of Science in Computer Engineering","Bachelor of Science in Electrical Engineering","Bachelor of Science in Electronics Engineering","Bachelor of Science in Mechanical Engineering"],
        "2": ["Bachelor of Science in Hospitality Management","Bachelor of Science in Tourism Management"],
        "3": ["Bachelor of Science in Nursing"],
        "4": ["Bachelor of Public Administration","Bachelor of Science in Accountancy","Bachelor of Science in Business Administration Major in Financial Management","Bachelor of Science in Business Administration Major in Marketing Management","Bachelor of Science in Entrepreneurship","Bachelor of Science in Office Administration"],
        "5": ["Bachelor of Industrial Technology Major in Architectural Drafting Technology","Bachelor of Industrial Technology Major in Automotive Technology","Bachelor of Industrial Technology Major in Culinary Technology","Bachelor of Industrial Technology Major in Electrical Technology","Bachelor of Industrial Technology Major in Electronics Technology","Bachelor of Industrial Technology Major in Heating, Ventilation, Air-Conditioning and Refrigeration","Bachelor of Science in Industrial Technology Major in Architectural Drafting","Bachelor of Science in Industrial Technology Major in Automotive Technology","Bachelor of Science in Industrial Technology Major in Electrical Technology","Bachelor of Science in Industrial Technology Major in Electronics Technology","Bachelor of Science in Industrial Technology Major in Food & Beverage Preparation Service Mgt.","Bachelor of Science in Industrial Technology Major in Heating, Ventilation, Air-Conditioning and Refrigeration"],
        "6": ["Bachelor of Elementary Education","Bachelor of Physical Education","Bachelor of Secondary Education Major in English","Bachelor of Secondary Education Major in Filipino","Bachelor of Secondary Education Major in Mathematics","Bachelor of Secondary Education Major in Science","Bachelor of Secondary Education Major in Social Studies","Bachelor of Technical-Vocational Teacher Education Major in Computer Programming","Bachelor of Technical-Vocational Teacher Education Major in Food and Service Management","Bachelor of Technology and Livelihood Education Major in Home Economics","Bachelor of Technology and Livelihood Education Major in Industrial Arts"],
        "7": ["Bachelor of Science in Computer Science","Bachelor of Science in Information Technology"],
        "8": ["Bachelor of Arts in Broadcasting","Bachelor of Science in Biology","Bachelor of Science in Biology Major in Animal Biology","Bachelor of Science in Biology Major in Microbiology","Bachelor of Science in Chemistry","Bachelor of Science in Mathematics","Bachelor of Science in Psychology"],
        "9": ["Bachelor of Science in Criminology"],
        "11": ["Bachelor of Science in Civil Engineering","Bachelor of Science in Computer Engineering","Bachelor of Science in Electrical Engineering","Bachelor of Science in Electronics Engineering","Bachelor of Science in Mechanical Engineering","Bachelor of Science in Hospitality Management","Bachelor of Science in Tourism Management","Bachelor of Science in Nursing","Bachelor of Public Administration","Bachelor of Science in Accountancy","Bachelor of Science in Business Administration Major in Financial Management","Bachelor of Science in Business Administration Major in Marketing Management","Bachelor of Science in Entrepreneurship","Bachelor of Science in Office Administration","Bachelor of Industrial Technology Major in Architectural Drafting Technology","Bachelor of Industrial Technology Major in Automotive Technology","Bachelor of Industrial Technology Major in Culinary Technology","Bachelor of Industrial Technology Major in Electrical Technology","Bachelor of Industrial Technology Major in Electronics Technology","Bachelor of Industrial Technology Major in Heating, Ventilation, Air-Conditioning and Refrigeration","Bachelor of Science in Industrial Technology Major in Architectural Drafting","Bachelor of Science in Industrial Technology Major in Automotive Technology","Bachelor of Science in Industrial Technology Major in Electrical Technology","Bachelor of Science in Industrial Technology Major in Electronics Technology","Bachelor of Science in Industrial Technology Major in Food & Beverage Preparation Service Mgt.","Bachelor of Science in Industrial Technology Major in Heating, Ventilation, Air-Conditioning and Refrigeration","Bachelor of Elementary Education","Bachelor of Physical Education","Bachelor of Secondary Education Major in English","Bachelor of Secondary Education Major in Filipino","Bachelor of Secondary Education Major in Mathematics","Bachelor of Secondary Education Major in Science","Bachelor of Secondary Education Major in Social Studies","Bachelor of Technical-Vocational Teacher Education Major in Computer Programming","Bachelor of Technical-Vocational Teacher Education Major in Food and Service Management","Bachelor of Technology and Livelihood Education Major in Home Economics","Bachelor of Technology and Livelihood Education Major in Industrial Arts","Bachelor of Science in Computer Science","Bachelor of Science in Information Technology","Bachelor of Arts in Broadcasting","Bachelor of Science in Biology","Bachelor of Science in Biology Major in Animal Biology","Bachelor of Science in Biology Major in Microbiology","Bachelor of Science in Chemistry","Bachelor of Science in Mathematics","Bachelor of Science in Psychology","Bachelor of Science in Criminology"]
    };
    function updateCourseOptions(selectId, deptId){
        const s=document.getElementById(selectId); if(!s) return;
        const cur=s.dataset.pendingValue||s.value; s.innerHTML='<option value="">Select Course</option>';
        (courseMapping[deptId]||[]).forEach(c=>{ const o=document.createElement('option'); o.value=c; o.textContent=c; s.appendChild(o);});
        if(cur){ s.value=cur; delete s.dataset.pendingValue; }
    }
    function formatStudentID(input){
        // STRICT Student 0000-0000 (8 digits, 4-4), digits.slice 0,8 -> 4-4, maxlength 9
        const raw=input.value.replace(/\D/g,'');
        if(raw.length===10 || raw.length>8){
            if(typeof window.showToast==='function') window.showToast('Student ID must be 0000-0000 (8 digits). Dean uses 00-0000-0000.', 'error', 'Invalid Student ID');
            input.classList.add('border-red-500');
            // slice to 8 but keep error
        }
        let v=raw.substring(0,8);
        let fmt='';
        if(v.length<=4) fmt=v;
        else fmt=v.substring(0,4)+'-'+v.substring(4);
        input.value=fmt;
        if(raw.length===10 || raw.length>8) return;
        const digits=v.length;
        if(digits===8){
            input.classList.remove('border-red-500');
            const idEl=input.id||''; const m=idEl.match(/^officers_(.+)_student_id$/);
            if(m) fetchRenewStudent('officers', m[1], fmt);
            else if(idEl==='renew_student_id') fetchRenewRequesterData(fmt);
        } else if(digits>0 && digits<8){
            input.classList.remove('border-red-500');
        }
    }
    function formatAdviserID(input){
        // STRICT Adviser/Dean 00-0000-0000 (10 digits, 2-4-4), digits.slice 0,10 -> 2-4-4, maxlength 12
        const rawFull=input.value.replace(/\D/g,'');
        if(rawFull.length===8){
            if(typeof window.showToast==='function') window.showToast('Adviser/Dean ID must be 00-0000-0000 (10 digits).', 'error', 'Invalid Adviser ID');
            const el=input; if(el) el.classList.add('border-red-500');
        }
        const raw=rawFull.substring(0,10);
        let v=raw;
        let fmt='';
        if(v.length<=2) fmt=v;
        else if(v.length<=6) fmt=v.substring(0,2)+'-'+v.substring(2,6);
        else fmt=v.substring(0,2)+'-'+v.substring(2,6)+'-'+v.substring(6,10);
        input.value=fmt;
        if(v.length===10) input.classList.remove('border-red-500');
        else if(v.length>0 && v.length<10 && rawFull.length!==8) input.classList.remove('border-red-500');
    }
    var renewDebounceTimers = {};
    function debounceRenewFetch(key, fn, delay=2000) {
        if (renewDebounceTimers[key]) clearTimeout(renewDebounceTimers[key]);
        renewDebounceTimers[key] = setTimeout(fn, delay);
    }
    // --- autofill helpers for Renew (student_id -> /api/student, email remains editable) - debounced 2s on input, instant on blur ---
    async function fetchRenewStudent(namePrefix, positionKey, studentId){
        if(!studentId) return;
        try{
            const res=await fetch(`/api/student/${encodeURIComponent(studentId.trim())}`, { headers:{'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', 'Accept':'application/json'}});
            const data=await res.json();
            if(data.success && data.student){
                const s=data.student;
                const mi = s.middle_initial || (s.middle_name ? s.middle_name.trim().charAt(0).toUpperCase() : '');
                const srcData = { ...s, middle_initial: mi, middle_name: mi };
                const map={first_name:`${namePrefix}_${positionKey}_first_name`, last_name:`${namePrefix}_${positionKey}_last_name`, middle_name:`${namePrefix}_${positionKey}_middle_name`, middle_initial:`${namePrefix}_${positionKey}_middle_name`, suffix:`${namePrefix}_${positionKey}_suffix`, email:`${namePrefix}_${positionKey}_email`, course:`${namePrefix}_${positionKey}_course`, year_level:`${namePrefix}_${positionKey}_year_level`, section:`${namePrefix}_${positionKey}_section`};
                Object.entries(map).forEach(([src,id])=>{
                    const el=document.getElementById(id);
                    if(el && srcData[src]!==undefined && srcData[src]!==null){
                        let val = srcData[src];
                        if(src==='middle_initial' || src==='middle_name') val = val.toString().trim().charAt(0).toUpperCase();
                        // Always overwrite all fields when student_id changes (user request: lahat mag-update, di lang email)
                        if(src==='course' && el.tagName==='SELECT'){
                            if(el.options.length>1) el.value=val; else el.dataset.pendingValue=val;
                        } else if(src==='year_level'){
                            el.value=formatYearLevel(val);
                        } else el.value=val;
                        el.dataset.studentAutoFilled = 'true';
                    }
                });
            }
        }catch(e){ console.error('Renew student fetch error',e); }
    }
    function autoFetchRenewAdviser(namePrefix, positionKey, value, immediate=false){
        const key = `${namePrefix}_${positionKey}_adviser`;
        const doFetch = () => {
            const digits=(value||'').replace(/\D/g,'');
            if(digits.length===8){
                if(typeof window.showToast==='function') window.showToast('Adviser/Dean ID must be 00-0000-0000 (10 digits).', 'error', 'Invalid Adviser ID');
                const el=document.getElementById(`${namePrefix}_${positionKey}_id_number`); if(el) el.classList.add('border-red-500');
                return;
            }
            if(digits.length===10){
                const el=document.getElementById(`${namePrefix}_${positionKey}_id_number`); if(el) el.classList.remove('border-red-500');
                fetchRenewStudent(namePrefix, positionKey, value);
            }
        };
        if(immediate) {
            if(renewDebounceTimers[key]) clearTimeout(renewDebounceTimers[key]);
            doFetch();
        } else {
            debounceRenewFetch(key, doFetch, 2000);
        }
    }
    function autoFetchRenewDean(value, immediate=false){
        const key = 'renew_dean_id';
        const doFetch = () => {
            const digits=(value||'').replace(/\D/g,'');
            if(digits.length===8){
                if(typeof window.showToast==='function') window.showToast('Dean ID must be 00-0000-0000 (10 digits). Student 0000-0000 not allowed here.', 'error', 'Invalid Dean ID');
                const el=document.getElementById('renew_dean_id_number'); if(el) el.classList.add('border-red-500');
                return;
            }
            if(digits.length===10){
                const el=document.getElementById('renew_dean_id_number'); if(el) el.classList.remove('border-red-500');
                fetchRenewDeanData(value);
            }
        };
        if(immediate) {
            if(renewDebounceTimers[key]) clearTimeout(renewDebounceTimers[key]);
            doFetch();
        } else {
            debounceRenewFetch(key, doFetch, 2000);
        }
    }
    async function fetchRenewDeanData(studentId){
        if(!studentId) return;
        const digits=(studentId||'').replace(/\D/g,'');
        if(digits.length!==10){
            if(digits.length===8 && typeof window.showToast==='function') window.showToast('Dean ID must be 00-0000-0000 (10 digits). Student 0000-0000 not allowed here.', 'error', 'Invalid Dean ID');
            else if(digits.length>0 && typeof window.showToast==='function') window.showToast('Dean ID must be 00-0000-0000 (10 digits).', 'error', 'Invalid Dean ID');
            const el=document.getElementById('renew_dean_id_number'); if(el && digits.length===8) el.classList.add('border-red-500');
            return;
        }
        try{
            const res=await fetch(`/api/student/${encodeURIComponent(studentId.trim())}`, { headers:{'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', 'Accept':'application/json'}});
            const data=await res.json();
            if(data.success && data.student){
                const s=data.student;
                const mi = s.middle_initial || (s.middle_name ? s.middle_name.trim().charAt(0).toUpperCase() : '');
                const srcData = { ...s, middle_initial: mi };
                const map={first_name:'renew_dean_first_name', middle_initial:'renew_dean_middle_name', last_name:'renew_dean_last_name', suffix:'renew_dean_suffix', email:'renew_dean_email'};
                Object.entries(map).forEach(([src,id])=>{
                    const el=document.getElementById(id);
                    if(el && srcData[src]){
                        const isName=['first_name','last_name','middle_initial'].includes(src);
                        if(isName && el.value && el.dataset.autoFilled) return;
                        let val = srcData[src] || '';
                        if(src==='middle_initial') val = val.toString().trim().charAt(0).toUpperCase();
                        el.value=val;
                    }
                });
            }
        }catch(e){ console.error('Renew dean fetch error',e); }
    }
    // Position is derived from the renewer ID format — never picked manually.
    // 8 digits (0000-0000) => Organization President, 10 digits (00-0000-0000) => Organization Adviser.
    // Falls back to the logged-in user's organization position while the ID is empty.
    function detectRenewPosition(){
        const raw = (document.getElementById('renew_student_id')?.value || '').replace(/\D/g,'');
        if(raw.length === 8) return 'Organization President';
        if(raw.length === 10) return 'Organization Adviser';
        return document.getElementById('renew_position')?.dataset?.initial || '';
    }
    function renderRenewPosition(){
        const pos = detectRenewPosition();
        const badge = document.getElementById('renew_position');
        if(badge) badge.textContent = pos || 'Type your ID above — position is detected automatically';
        return pos;
    }
    function formatRenewRequesterID(input){
        if(!input) return;
        renderRenewPosition();
        // Never shrink maxLength while typing — the browser would swallow the
        // keystroke that crosses formats (8 -> 10 digits). Mask follows digit
        // count; final format is enforced by pattern + backend derivation.
        const raw = input.value.replace(/\D/g,'');
        input.maxLength = 12;
        const isAdviser = raw.length > 8;

        let fmt = '';
        if (isAdviser) {
            // 10 digits: 00-0000-0000 (2-4-4)
            let v = raw.substring(0, 10);
            if (v.length <= 2) fmt = v;
            else if (v.length <= 6) fmt = v.substring(0, 2) + '-' + v.substring(2);
            else fmt = v.substring(0, 2) + '-' + v.substring(2, 6) + '-' + v.substring(6);
            input.value = fmt;

            input.classList.remove('border-red-500');
            if (v.length === 10) {
                fetchRenewRequesterData(fmt);
            }
        } else {
            // 8 digits: 0000-0000 (4-4)
            let v = raw.substring(0, 8);
            if (v.length <= 4) fmt = v;
            else fmt = v.substring(0, 4) + '-' + v.substring(4);
            input.value = fmt;

            input.classList.remove('border-red-500');
            if (v.length === 8) {
                fetchRenewRequesterData(fmt);
            }
        }
    }
    async function fetchRenewRequesterData(studentId){
        if(!studentId) return;
        const digits = (studentId||'').replace(/\D/g,'');
        if(digits.length !== 8 && digits.length !== 10){
            return;
        }
        try{
            const res = await fetch(`/api/student/${encodeURIComponent(studentId.trim())}`, { headers:{'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', 'Accept':'application/json'}});
            const data = await res.json();
            if(data.success && data.student){
                const s = data.student;
                const fn = document.getElementById('renew_first_name'), ln = document.getElementById('renew_last_name'), em = document.getElementById('renew_email');
                if(fn && s.first_name) fn.value = s.first_name;
                if(ln && s.last_name) ln.value = s.last_name;
                if(em && s.email) em.value = s.email;
                renderRenewPosition();
            }
        }catch(e){ console.error('Renew requester fetch error',e); }
    }

    function formatYearLevel(raw) {
        if (raw === undefined || raw === null || raw === '') return raw;
        const text = String(raw).trim();
        let digits = null;
        if (/^\d+$/.test(text)) {
            digits = parseInt(text, 10);
        } else {
            const alreadyFormatted = text.match(/^(\d+)(st|nd|rd|th)\b/i);
            if (alreadyFormatted) digits = parseInt(alreadyFormatted[1], 10);
        }
        if (digits === null || digits < 1) return raw;
        const last = digits % 10;
        const lastTwo = digits % 100;
        let suffix = 'th';
        if (lastTwo < 11 || lastTwo > 13) {
            if (last === 1) suffix = 'st';
            else if (last === 2) suffix = 'nd';
            else if (last === 3) suffix = 'rd';
        }
        return `${digits}${suffix} Year`;
    }

    function departmentIdForCollege(collegeName) {
        if (!collegeName) return null;
        const normalize = value => String(value ?? '')
            .replace(/\([^)]*\)/g, ' ')
            .toLowerCase()
            .replace(/[^a-z0-9\s]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
        const target = normalize(collegeName);
        if (!target) return null;
        const select = document.getElementById('officers_president_department_id')
            || document.querySelector('select[id$="_department_id"]');
        if (!select || !select.options) return null;
        for (const option of select.options) {
            const text = normalize(option.textContent ?? String(option));
            if (text && (text.includes(target) || target.includes(text))) {
                return String(option.value ?? option);
            }
        }
        return null;
    }

    function autoLinkOfficerDepartment(namePrefix, positionKey, preferredDeptId = null) {
        const orgType = renewOrgType;
        const globalDeptId = renewOrgDeptId;

        const officerDeptSelect = document.getElementById(`${namePrefix}_${positionKey}_department_id`);
        if (!officerDeptSelect) return;

        if (orgType === 'Non-Academic') {
            const targetDeptId = preferredDeptId;
            const matchedOption = targetDeptId && Array.from(officerDeptSelect.options).some(option =>
                String(option.value ?? option) === String(targetDeptId));
            if (matchedOption) {
                officerDeptSelect.value = String(targetDeptId);
                officerDeptSelect.dataset.deptLinked = 'true';

                // Load course options automatically
                updateCourseOptions(`${namePrefix}_${positionKey}_course`, officerDeptSelect.value);
            }
        } else if (globalDeptId) {
            officerDeptSelect.value = String(globalDeptId);
            officerDeptSelect.disabled = true;
            officerDeptSelect.classList.add('bg-gray-100', 'cursor-not-allowed');
            officerDeptSelect.dataset.deptLinked = 'true';

            // Ensure hidden input exists so department_id is submitted when disabled
            let hiddenDeptInput = officerDeptSelect.parentNode.querySelector(`input[type="hidden"][name="${namePrefix}[${positionKey}][department_id]"]`);
            if (!hiddenDeptInput) {
                hiddenDeptInput = document.createElement('input');
                hiddenDeptInput.type = 'hidden';
                hiddenDeptInput.name = `${namePrefix}[${positionKey}][department_id]`;
                officerDeptSelect.parentNode.appendChild(hiddenDeptInput);
            }
            hiddenDeptInput.value = String(globalDeptId);

            // Load course options automatically
            updateCourseOptions(`${namePrefix}_${positionKey}_course`, globalDeptId);
        }
    }

    function matchAndSetCourse(selectId, courseName) {
        const select = document.getElementById(selectId);
        if (!select || !courseName) return;
        const norm = s => String(s || '').toLowerCase().replace(/[^a-z0-9]/g, '');
        const target = norm(courseName);
        if (!target) return;

        let matchedVal = null;
        for (const opt of select.options) {
            if (!opt.value) continue;
            const optNorm = norm(opt.value);
            if (optNorm === target || optNorm.includes(target) || target.includes(optNorm)) {
                matchedVal = opt.value;
                break;
            }
        }
        if (matchedVal) {
            select.value = matchedVal;
        }
    }

    var renewCurrent = window.__renewCurrentStep || 1;
    function renewShow(step){
        var target = document.getElementById('renew-step'+step);
        if(!target) return;
        document.querySelectorAll('.renew-step').forEach(el=> el.classList.add('hidden'));
        target.classList.remove('hidden');
        var progressLabel = document.getElementById('renew-progress-label');
        if(progressLabel) progressLabel.textContent = 'Step ' + step + ' of 6';
        for(let i=1;i<=6;i++){
            const c=document.getElementById('renew-step'+i+'-circle'); const p=document.getElementById('renew-progress'+i);
            if(!c) continue;
            if(i<step){ c.style.backgroundColor='#22c55e'; c.style.color='white'; c.textContent='✓'; if(p) p.style.backgroundColor='#22c55e'; }
            else if(i===step){ c.style.backgroundColor='var(--primary-color)'; c.style.color='white'; c.textContent= i===3?'2.1':i===4?'2.2':i===5?'2.3':i; if(p) p.style.backgroundColor='var(--primary-color)'; }
            else { c.style.backgroundColor='#e5e7eb'; c.style.color='#6b7280'; c.textContent= i===3?'2.1':i===4?'2.2':i===5?'2.3':i; if(p) p.style.backgroundColor='#e5e7eb'; }
        }
        renewCurrent=step; window.__renewCurrentStep=step;
        window.scrollTo({top:0,behavior:'smooth'});
        if(step===4) refreshRenewMembers();
        if(step===5 || step===6) applyRenewVisiblePrefill();
    }
    function renewValidate(step){
        const container=document.getElementById('renew-step'+step);
        const fields=container.querySelectorAll('input,select,textarea');
        for(const f of fields){ if(f.type==='hidden'||f.disabled||f.offsetParent===null) continue; if(!f.checkValidity()){ f.reportValidity(); return false; } }
        if(step===4){
            const needs = (renewOrgType==='Mother Org'||renewOrgType==='Non-Academic') && !(renewOrgType==='Sub Org' && renewHasMotherOrg);
            const hasMotherSkip = renewOrgType==='Sub Org' && renewHasMotherOrg;
            if(needs && !hasMotherSkip && renewMemberFiles.length===0){
                if(window.showToast) window.showToast('Please add at least one membership list file.', 'error', 'Missing Members');
                else alert('Please add at least one membership list file.');
                return false;
            }
        }
        return true;
    }
    function renewNext(target){ if(!renewValidate(renewCurrent)) return; renewShow(target); }
    function renewPrev(target){ renewShow(target); }

    function refreshRenewMembers(){
        const needs = (renewOrgType==='Mother Org'||renewOrgType==='Non-Academic');
        const isSubWithMother = renewOrgType==='Sub Org' && renewHasMotherOrg;
        const notice=document.getElementById('renew-mother-exists-notice');
        const upload=document.getElementById('renew-member-upload');
        const hint=document.getElementById('renew-members-hint');
        const btn=document.getElementById('renewAddMembersBtn');
        if(isSubWithMother){
            notice.classList.remove('hidden'); upload.classList.add('hidden');
            hint.textContent='Sub Org — Mother Org already submitted members, no upload needed.';
            renewMemberFiles.length=0;
            const preview=document.getElementById('renewMemberPreview');
            if(preview) preview.innerHTML='';
            if(btn) btn.disabled=true;
            resetRenewIdLookupCache();
        } else {
            notice.classList.add('hidden'); upload.classList.remove('hidden');
            hint.textContent = needs ? 'Required for '+renewOrgType+' (max 20 files)' : 'Optional for Sub Org without Mother Org';
            if(btn) btn.disabled=false;
        }
    }

    // Officers generation (mirrors Apply) — all required except suffix/middle_name + mamula + requester sync (Adviser > PDF)
    function generateRenewOfficerForms(){
        const container=document.getElementById('renewOfficersContainer'); if(!container) return;
        container.innerHTML='';
        // Requester info (higher priority than certification autofill, like Apply)
        const reqFirstName = document.getElementById('renew_first_name')?.value?.trim() || '';
        const reqLastName = document.getElementById('renew_last_name')?.value?.trim() || '';
        const reqEmail = document.getElementById('renew_email')?.value?.trim() || '';
        const reqPosition = (typeof detectRenewPosition === 'function') ? detectRenewPosition() : '';
        officerPositions.forEach(pos=>{
            const key=pos.key, label=pos.label, isAdviser=pos.is_adviser;
            // Requester -> officer mapping (Organization Adviser generic fills Senior only, to maintain Junior; legacy Senior/Junior 1:1, President -> president)
            const isRequesterAdviser = (key==='senior_adviser' && reqPosition==='Organization Adviser');
            const isLegacySenior = (key==='senior_adviser' && reqPosition==='Organization Senior Adviser');
            const isLegacyJunior = (key==='junior_adviser' && reqPosition==='Organization Junior Adviser');
            const isRequesterPresident = (key==='president' && reqPosition==='Organization President');
            const isThisRequesterSlot = isRequesterAdviser || isLegacySenior || isLegacyJunior || isRequesterPresident;
            const seedFirstName = isThisRequesterSlot ? reqFirstName : '';
            const seedLastName = isThisRequesterSlot ? reqLastName : '';
            const seedEmail = isThisRequesterSlot ? reqEmail : '';
            const div=document.createElement('div'); div.className='border border-gray-300 rounded-lg p-3 bg-gray-50';
            if(isAdviser){
                div.innerHTML=`<h4 class="font-bold text-sm text-gray-800 mb-2">${label}</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div><label class="block text-xs font-semibold mb-1">ID Number *</label><input type="text" name="officers[${key}][id_number]" required id="officers_${key}_id_number" class="w-full px-2 py-1 text-xs border border-gray-300 rounded" placeholder="00-0000-0000" maxlength="12" pattern="^\\d{2}-\\d{4}-\\d{4}$" title="Adviser ID must be 00-0000-0000" oninput="formatAdviserID(this); autoFetchRenewAdviser('officers','${key}', this.value)" onblur="autoFetchRenewAdviser('officers','${key}', this.value)"></div>
                    <div><label class="block text-xs font-semibold mb-1">Email *</label><input type="email" name="officers[${key}][email]" required id="officers_${key}_email" class="w-full px-2 py-1 text-xs border border-gray-300 rounded" placeholder="email@lspu.edu.ph" value="${seedEmail}"></div>
                    <div><label class="block text-xs font-semibold mb-1">First Name *</label><input type="text" name="officers[${key}][first_name]" required id="officers_${key}_first_name" class="w-full px-2 py-1 text-xs border border-gray-300 rounded" value="${seedFirstName}"></div>
                    <div><label class="block text-xs font-semibold mb-1">Middle Initial</label><input type="text" name="officers[${key}][middle_initial]" id="officers_${key}_middle_name" maxlength="1" pattern="[A-Za-z]" class="w-full px-2 py-1 text-xs border border-gray-300 rounded uppercase" placeholder="M.I."></div>
                    <div><label class="block text-xs font-semibold mb-1">Last Name *</label><input type="text" name="officers[${key}][last_name]" required id="officers_${key}_last_name" class="w-full px-2 py-1 text-xs border border-gray-300 rounded" value="${seedLastName}"></div>
                    <div><label class="block text-xs font-semibold mb-1">Suffix</label><input type="text" name="officers[${key}][suffix]" id="officers_${key}_suffix" class="w-full px-2 py-1 text-xs border border-gray-300 rounded" placeholder="Jr., Sr., III"></div>
                </div><input type="hidden" name="officers[${key}][position]" value="${key}">`;
            } else if(key==='comelec'){
                // COMELEC as ID Number with autofill - STRICT 00-0000-0000
                div.innerHTML=`<h4 class="font-bold text-sm text-gray-800 mb-2">${label}</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div><label class="block text-xs font-semibold mb-1">ID Number *</label><input type="text" name="officers[${key}][id_number]" required id="officers_${key}_id_number" class="w-full px-2 py-1 text-xs border border-gray-300 rounded" placeholder="00-0000-0000" maxlength="12" pattern="^\\d{2}-\\d{4}-\\d{4}$" title="Adviser ID must be 00-0000-0000" oninput="formatAdviserID(this); autoFetchRenewAdviser('officers','${key}', this.value)" onblur="autoFetchRenewAdviser('officers','${key}', this.value)"></div>
                    <div><label class="block text-xs font-semibold mb-1">Email *</label><input type="email" name="officers[${key}][email]" required id="officers_${key}_email" class="w-full px-2 py-1 text-xs border border-gray-300 rounded" placeholder="email@lspu.edu.ph"></div>
                    <div><label class="block text-xs font-semibold mb-1">First Name *</label><input type="text" name="officers[${key}][first_name]" required id="officers_${key}_first_name" class="w-full px-2 py-1 text-xs border border-gray-300 rounded"></div>
                    <div><label class="block text-xs font-semibold mb-1">Middle Initial</label><input type="text" name="officers[${key}][middle_initial]" id="officers_${key}_middle_name" maxlength="1" pattern="[A-Za-z]" class="w-full px-2 py-1 text-xs border border-gray-300 rounded uppercase" placeholder="M.I."></div>
                    <div><label class="block text-xs font-semibold mb-1">Last Name *</label><input type="text" name="officers[${key}][last_name]" required id="officers_${key}_last_name" class="w-full px-2 py-1 text-xs border border-gray-300 rounded"></div>
                    <div><label class="block text-xs font-semibold mb-1">Suffix</label><input type="text" name="officers[${key}][suffix]" id="officers_${key}_suffix" class="w-full px-2 py-1 text-xs border border-gray-300 rounded" placeholder="Jr., Sr., III"></div>
                </div><input type="hidden" name="officers[${key}][position]" value="${key}">`;
            } else {
                div.innerHTML=`<h4 class="font-bold text-sm text-gray-800 mb-2">${label}</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div class="col-span-2"><label class="block text-xs font-semibold mb-1">Student ID *</label><input type="text" name="officers[${key}][student_id]" required id="officers_${key}_student_id" class="w-full px-2 py-1 text-xs border border-gray-300 rounded" placeholder="2021-0001" maxlength="9" pattern="^\\d{4}-\\d{4}$" title="Student ID must be 0000-0000" oninput="formatStudentID(this)"></div>
                    <div><label class="block text-xs font-semibold mb-1">First Name *</label><input type="text" name="officers[${key}][first_name]" required id="officers_${key}_first_name" class="w-full px-2 py-1 text-xs border border-gray-300 rounded" value="${seedFirstName}"></div>
                    <div><label class="block text-xs font-semibold mb-1">Last Name *</label><input type="text" name="officers[${key}][last_name]" required id="officers_${key}_last_name" class="w-full px-2 py-1 text-xs border border-gray-300 rounded" value="${seedLastName}"></div>
                    <div><label class="block text-xs font-semibold mb-1">Middle Initial</label><input type="text" name="officers[${key}][middle_initial]" id="officers_${key}_middle_name" maxlength="1" pattern="[A-Za-z]" class="w-full px-2 py-1 text-xs border border-gray-300 rounded uppercase" placeholder="M.I."></div>
                    <div><label class="block text-xs font-semibold mb-1">Email *</label><input type="email" name="officers[${key}][email]" required id="officers_${key}_email" class="w-full px-2 py-1 text-xs border border-gray-300 rounded" value="${seedEmail}"></div>
                    <div><label class="block text-xs font-semibold mb-1">Department *</label><select name="officers[${key}][department_id]" required id="officers_${key}_department_id" class="w-full px-2 py-1 text-xs border border-gray-300 rounded" onchange="updateCourseOptions('officers_${key}_course', this.value)"><option value="">Select Department</option>${renewDepartments.map(d=>`<option value="${d.id}">${d.department_name} (${d.department_acronym})</option>`).join('')}</select></div>
                    <div><label class="block text-xs font-semibold mb-1">Course *</label><select name="officers[${key}][course]" required id="officers_${key}_course" class="w-full px-2 py-1 text-xs border border-gray-300 rounded"><option value="">Select Department First</option></select></div>
                    <div><label class="block text-xs font-semibold mb-1">Year Level *</label><input type="text" name="officers[${key}][year_level]" required id="officers_${key}_year_level" class="w-full px-2 py-1 text-xs border border-gray-300 rounded"></div>
                    <div><label class="block text-xs font-semibold mb-1">Section *</label><input type="text" name="officers[${key}][section]" required id="officers_${key}_section" class="w-full px-2 py-1 text-xs border border-gray-300 rounded"></div>
                    <div><label class="block text-xs font-semibold mb-1">Suffix</label><input type="text" name="officers[${key}][suffix]" id="officers_${key}_suffix" class="w-full px-2 py-1 text-xs border border-gray-300 rounded"></div>
                </div><input type="hidden" name="officers[${key}][position]" value="${key}">`;
            }
            container.appendChild(div);
            if(!isAdviser && key!=='comelec'){
                autoLinkOfficerDepartment('officers', key);
            }
        });
        customOfficerSlots.forEach(s=> {
            ensureRenewCustomCard(s.key, s.label);
        });
    }
    // Sync requester info to officer card (Adviser > PDF, like Apply) — renew only, single Senior for generic Adviser to maintain Junior
    function syncRenewRequesterToOfficer(){
        const reqFirstName = document.getElementById('renew_first_name')?.value?.trim() || '';
        const reqLastName = document.getElementById('renew_last_name')?.value?.trim() || '';
        const reqEmail = document.getElementById('renew_email')?.value?.trim() || '';
        const reqPosition = (typeof detectRenewPosition === 'function') ? detectRenewPosition() : '';
        if(!reqEmail && !reqFirstName && !reqLastName) return;
        let targetKey = null;
        if(reqPosition === 'Organization President') targetKey = 'president';
        else if(reqPosition === 'Organization Adviser') targetKey = 'senior_adviser';
        else if(reqPosition === 'Organization Senior Adviser') targetKey = 'senior_adviser';
        else if(reqPosition === 'Organization Junior Adviser') targetKey = 'junior_adviser';
        if(!targetKey) return;
        const firstEl = document.getElementById(`officers_${targetKey}_first_name`);
        const lastEl = document.getElementById(`officers_${targetKey}_last_name`);
        const emailEl = document.getElementById(`officers_${targetKey}_email`);
        if(firstEl && reqFirstName){ firstEl.value = reqFirstName; firstEl.dataset.autoFillDirty = 'true'; }
        if(lastEl && reqLastName){ lastEl.value = reqLastName; lastEl.dataset.autoFillDirty = 'true'; }
        if(emailEl && reqEmail){ emailEl.value = reqEmail; emailEl.dataset.autoFillDirty = 'true'; }
    }

    function removeRenewCustomCard(key){
        const el=document.getElementById(`officers_${key}_position_name`);
        const card=el ? el.closest('div.border') : null;
        if(card) card.remove();
        const idx = customOfficerSlots.findIndex(s=> s.key === key);
        if(idx !== -1) customOfficerSlots.splice(idx, 1);
    }

    function ensureRenewCustomCard(key, label){
        const c = document.getElementById('renewOfficersContainer');
        if(!c) return false;
        if(document.getElementById(`officers_${key}_first_name`)) return false;
        const div = document.createElement('div');
        div.className = 'border border-dashed border-gray-300 rounded-lg p-3 bg-yellow-50';
        div.innerHTML = `<div class="flex justify-between items-center mb-2"><h4 class="font-bold text-sm text-gray-800">${label} <span class="text-xs font-normal text-gray-500">(Other Position)</span></h4><button type="button" onclick="removeRenewCustomCard('${key}')" class="text-xs text-red-500 hover:text-red-700"><i class="ph ph-x"></i> Remove</button></div>
        <div class="mb-2">
            <label class="block text-xs font-semibold mb-1">Position Name *</label>
            <input type="text" name="officers[${key}][position_name]" required id="officers_${key}_position_name" value="${label}" class="w-full px-2 py-1 text-xs border border-amber-300 rounded focus:outline-none focus:border-amber-500" placeholder="e.g. Business Manager 2">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <div class="col-span-2"><label class="block text-xs font-semibold mb-1">Student ID *</label><input type="text" name="officers[${key}][student_id]" required id="officers_${key}_student_id" class="w-full px-2 py-1 text-xs border rounded" placeholder="2021-0001" maxlength="9" pattern="^\\d{4}-\\d{4}$" title="Student ID must be 0000-0000" oninput="formatStudentID(this)"></div>
            <div><label class="block text-xs font-semibold mb-1">First Name *</label><input type="text" name="officers[${key}][first_name]" required id="officers_${key}_first_name" class="w-full px-2 py-1 text-xs border rounded"></div>
            <div><label class="block text-xs font-semibold mb-1">Last Name *</label><input type="text" name="officers[${key}][last_name]" required id="officers_${key}_last_name" class="w-full px-2 py-1 text-xs border rounded"></div>
            <div><label class="block text-xs font-semibold mb-1">Middle Initial</label><input type="text" name="officers[${key}][middle_initial]" id="officers_${key}_middle_name" maxlength="1" pattern="[A-Za-z]" class="w-full px-2 py-1 text-xs border rounded uppercase" placeholder="M.I."></div>
            <div><label class="block text-xs font-semibold mb-1">Email *</label><input type="email" name="officers[${key}][email]" required id="officers_${key}_email" class="w-full px-2 py-1 text-xs border rounded"></div>
            <div><label class="block text-xs font-semibold mb-1">Department *</label><select name="officers[${key}][department_id]" required id="officers_${key}_department_id" class="w-full px-2 py-1 text-xs border rounded" onchange="updateCourseOptions('officers_${key}_course', this.value)"><option value="">Select Department</option>${renewDepartments.map(d=>`<option value="${d.id}">${d.department_name} (${d.department_acronym})</option>`).join('')}</select></div>
            <div><label class="block text-xs font-semibold mb-1">Course *</label><select name="officers[${key}][course]" required id="officers_${key}_course" class="w-full px-2 py-1 text-xs border rounded"><option value="">Select Course</option></select></div>
            <div><label class="block text-xs font-semibold mb-1">Year Level *</label><input type="text" name="officers[${key}][year_level]" required id="officers_${key}_year_level" class="w-full px-2 py-1 text-xs border rounded"></div>
            <div><label class="block text-xs font-semibold mb-1">Section *</label><input type="text" name="officers[${key}][section]" required id="officers_${key}_section" class="w-full px-2 py-1 text-xs border rounded"></div>
            <div><label class="block text-xs font-semibold mb-1">Suffix</label><input type="text" name="officers[${key}][suffix]" id="officers_${key}_suffix" class="w-full px-2 py-1 text-xs border rounded" placeholder="Jr., Sr., III"></div>
        </div><input type="hidden" name="officers[${key}][position]" value="${key}">`;
        c.appendChild(div);
        autoLinkOfficerDepartment('officers', key);
        return true;
    }

    var renewCustomIdSeq = 0;
    function refreshRenewCustomIdSeq(){
        customOfficerSlots.forEach(s=>{ const m=String(s.key).match(/^custom_(\d+)$/); if(m) renewCustomIdSeq=Math.max(renewCustomIdSeq, parseInt(m[1],10)); });
    }
    function addRenewCustomOfficer(){
        const label=prompt('Enter position name (e.g. Business Manager 2):'); if(!label) return;
        refreshRenewCustomIdSeq(); renewCustomIdSeq+=1;
        const key='custom_'+renewCustomIdSeq;
        customOfficerSlots.push({key,label});
        ensureRenewCustomCard(key,label);
        bindRenewPrefillDestinationTracking();
    }

    // --- Certification PDF auto-fill (matches Apply Org architecture) ---
    var renewOfficersPrefillState = {
        generation: 0,
        abortController: null,
        status: 'manual',
        data: null,
        warnings: [],
        file: null,
        manualValues: {},
    };
    var renewCertifierPrefillFields = ['first_name', 'middle_name', 'last_name', 'suffix'];
    var renewOfficerPrefillFields = [...renewCertifierPrefillFields, 'year_level', 'section'];

    function isRenewCertificationPdf(file) {
        return Boolean(file) && (file.type === 'application/pdf' || /\.pdf$/i.test(file.name || ''));
    }

    function currentRenewOfficerSlots() {
        return typeof officerPositions === 'undefined' ? [] : officerPositions;
    }

    function isDefaultRenewPositionKey(key) {
        return currentRenewOfficerSlots().some(position => position.key === key);
    }

    function isCustomRenewPositionKey(key) {
        return String(key).startsWith('custom_');
    }

    function customRenewPrefillIds(includeIdentityFields) {
        const fields = includeIdentityFields
            ? ['student_id', 'email', 'department_id', 'course', ...renewOfficerPrefillFields]
            : [...renewOfficerPrefillFields];
        return customOfficerSlots.flatMap(position =>
            fields.map(field => `officers_${position.key}_${field}`)
        );
    }

    function renewPrefillDestinationIds() {
        const deanIds = renewCertifierPrefillFields.map(field => `renew_dean_${field}`);
        const officerIds = currentRenewOfficerSlots().flatMap(position => {
            const fields = position.is_adviser ? renewCertifierPrefillFields : renewOfficerPrefillFields;
            return fields.map(field => `officers_${position.key}_${field}`);
        });
        return [...deanIds, ...officerIds, ...customRenewPrefillIds(false)];
    }

    function renewPrefillTrackedIds() {
        const deanIds = ['id_number', 'email', ...renewCertifierPrefillFields].map(field => `renew_dean_${field}`);
        const officerIds = currentRenewOfficerSlots().flatMap(position => {
            const identityFields = position.is_adviser
                ? ['id_number', 'email', ...renewCertifierPrefillFields]
                : ['student_id', 'email', 'department_id', 'course', ...renewOfficerPrefillFields];
            return identityFields.map(field => `officers_${position.key}_${field}`);
        });
        return [...deanIds, ...officerIds, ...customRenewPrefillIds(true)];
    }

    function bindRenewPrefillDestinationTracking() {
        renewPrefillTrackedIds().forEach(id => {
            const input = document.getElementById(id);
            if (!input || input.dataset.autoFillDirtyBound === 'true') return;
            const markDirty = () => {
                if (input.dataset.autoFillWriting !== 'true') {
                    input.dataset.autoFillDirty = 'true';
                    renewOfficersPrefillState.manualValues[id] = input.value;
                }
            };
            input.addEventListener('input', markDirty);
            input.addEventListener('change', markDirty);
            input.dataset.autoFillDirtyBound = 'true';
        });
    }

    function rememberRenewManualPrefillValues() {
        renewPrefillTrackedIds().forEach(id => {
            const input = document.getElementById(id);
            if (input?.dataset.autoFillDirty === 'true') {
                renewOfficersPrefillState.manualValues[id] = input.value;
            }
        });
    }

    function clearPriorRenewAutomaticValues() {
        renewPrefillDestinationIds().forEach(id => {
            const input = document.getElementById(id);
            if (!input) return;
            const wasManuallyChanged = input.dataset.autoFillDirty === 'true';
            const wasAutoFilled = Boolean(input.dataset.autoFillToken);
            if (wasAutoFilled && !wasManuallyChanged) {
                input.value = '';
            }
            delete input.dataset.autoFillToken;
            delete input.dataset.autoFillValue;
            if (wasManuallyChanged) {
                input.dataset.autoFillDirty = 'true';
            } else if (wasAutoFilled) {
                delete input.dataset.autoFillDirty;
            }
        });
    }

    function assignRenewAutomaticValue(input, value, requestToken) {
        if (!input || value === undefined || value === null || value === '') return;
        const isAutoOwned = Boolean(input.dataset.autoFillToken)
            && input.dataset.autoFillDirty !== 'true';
        if (input.dataset.autoFillDirty === 'true' || (input.value !== '' && !isAutoOwned)) return;

        input.dataset.autoFillWriting = 'true';
        input.value = value;
        input.dataset.autoFillToken = String(requestToken);
        input.dataset.autoFillValue = String(value);
        input.dataset.autoFillDirty = 'false';
        delete input.dataset.autoFillWriting;
    }

    function applyRenewPrefillFields(namePrefix, positionKey, data, fields, requestToken) {
        fields.forEach(field => {
            const id = (namePrefix === 'dean')
                ? `renew_dean_${field}`
                : (positionKey ? `${namePrefix}_${positionKey}_${field}` : `${namePrefix}_${field}`);
            let value = data?.[field];
            if (field === 'year_level') value = formatYearLevel(value);
            if (field === 'middle_name' || field === 'middle_initial') {
                const rawM = (data?.middle_initial || data?.middle_name || '').toString().trim();
                value = rawM ? rawM.charAt(0).toUpperCase() : '';
            }
            assignRenewAutomaticValue(document.getElementById(id), value, requestToken);
        });
    }

    function applyRenewDeanPrefill() {
        if (!renewOfficersPrefillState.data?.dean) return;
        applyRenewPrefillFields('dean', '', renewOfficersPrefillState.data.dean, renewCertifierPrefillFields, renewOfficersPrefillState.generation);
    }

    function applyRenewOfficerPrefill(namePrefix, positionKey) {
        const isCustom = isCustomRenewPositionKey(positionKey);
        const data = isCustom
            ? renewOfficersPrefillState.data?.officers?.[positionKey]
            : (positionKey === 'senior_adviser' || positionKey === 'junior_adviser')
                ? renewOfficersPrefillState.data?.[positionKey]
                : renewOfficersPrefillState.data?.officers?.[positionKey];
        if (!data) return;
        const fields = isCustom
            ? renewOfficerPrefillFields
            : (positionKey === 'senior_adviser' || positionKey === 'junior_adviser')
                ? renewCertifierPrefillFields
                : renewOfficerPrefillFields;
        applyRenewPrefillFields(namePrefix, positionKey, data, fields, renewOfficersPrefillState.generation);

        // Course & Department if available in officer data from PDF
        if (data.department_id) {
            autoLinkOfficerDepartment(namePrefix, positionKey, data.department_id);
        }
        if (data.course) {
            matchAndSetCourse(`${namePrefix}_${positionKey}_course`, data.course);
        }
    }

    function applyRenewVisibleOfficersPrefill() {
        currentRenewOfficerSlots().forEach(position => applyRenewOfficerPrefill('officers', position.key));
        if (renewCurrent !== 6) return;
        Object.entries(renewOfficersPrefillState.data?.officers || {}).forEach(([key, officer]) => {
            if (isDefaultRenewPositionKey(key)) return;
            const label = officer?.position_name || key.replace(/^custom_\d+_?/i, '').replace(/_/g, ' ');
            // Register cert-driven customs like the manual add flow does —
            // without this, lookup/tracking/linkage skip them silently.
            if (!customOfficerSlots.some(s => s.key === key)) customOfficerSlots.push({ key, label });
            if (ensureRenewCustomCard(key, label)) {
                bindRenewPrefillDestinationTracking();
            }
            applyRenewOfficerPrefill('officers', key);
        });

        const preferredDeptId = (renewOrgType === 'Non-Academic')
            ? departmentIdForCollege(renewOfficersPrefillState.data?.college)
            : null;
        [...currentRenewOfficerSlots(), ...customOfficerSlots].forEach(slot => {
            autoLinkOfficerDepartment('officers', slot.key, preferredDeptId);
        });

        // Course options load during department linking above — match the
        // prefilled courses only now that options exist (covers customs too).
        Object.entries(renewOfficersPrefillState.data?.officers || {}).forEach(([key, officer]) => {
            if (officer?.course) matchAndSetCourse(`officers_${key}_course`, officer.course);
        });

        // Re-assert requester over President or Senior Adviser
        syncRenewRequesterToOfficer();
        // Officer names are settled here — kick off the List of Members /
        // Mother Org roster ID lookup when possible.
        maybeRenewLookupOfficerIds();
    }

    function applyRenewVisiblePrefill() {
        bindRenewPrefillDestinationTracking();
        applyRenewDeanPrefill();
        if (renewCurrent === 6) applyRenewVisibleOfficersPrefill();
    }

    async function requestRenewOfficersPrefill(file, generation) {
        const controller = renewOfficersPrefillState.abortController;
        const formData = new FormData();
        formData.append('certification_of_officers', file);
        formData.append('request_token', String(generation));
        formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}');

        const statusEl = document.getElementById('renew-cert-prefill-status');
        const txt = document.getElementById('renew-cert-prefill-text');
        if (statusEl) statusEl.classList.remove('hidden');
        if (txt) txt.textContent = 'Reading Certification of Officers...';
        if (typeof renderRenewIdLookupProgress === 'function') renderRenewIdLookupProgress({ title: 'Matching Officer IDs', detail: 'Reading ' + (file?.name || 'certification file') + '…', stats: '', percent: null, tone: 'amber' });

        try {
            const response = await fetch('{{ route('apply-org.prefill-from-officers') }}', {
                method: 'POST', body: formData, signal: controller.signal,
            });
            const result = await response.json();
            const isCurrent = renewOfficersPrefillState.generation === generation
                && renewOfficersPrefillState.file === file;
            if (!isCurrent) return;

            renewOfficersPrefillState.status = result.success ? (result.outcome || 'completed') : 'manual_required';
            renewOfficersPrefillState.data = result.success ? (result.data || null) : null;
            renewOfficersPrefillState.warnings = result.warnings || (result.message ? [result.message] : []);
            applyRenewVisiblePrefill();

            if (renewOfficersPrefillState.data?.officers && Object.keys(renewOfficersPrefillState.data.officers).length > 0) {
                if (txt) txt.textContent = 'Auto-filled from Certification PDF. Please verify.';
                if (typeof renewCompleteLookupProgress === 'function') renewCompleteLookupProgress('Officer IDs matched — review and edit below.');
                if (window.showToast) window.showToast('Certification of Officers auto-filled successfully.', 'success', 'Autofill Successful');
            } else {
                if (txt) txt.textContent = result.message || 'Could not auto-fill. Please fill manually.';
                if (typeof renderRenewIdLookupProgress === 'function') renderRenewIdLookupProgress({ title: 'Matching Officer IDs', detail: renewOfficersPrefillState.warnings?.[0] || 'Certification details need manual entry.', stats: 'Please enter IDs manually.', percent: 100, tone: 'red' });
                if (window.showToast) window.showToast(renewOfficersPrefillState.warnings[0] || result.message || 'Certification details need manual entry.', 'warning', 'Autofill Failed');
            }
        } catch (error) {
            if (error.name === 'AbortError' || renewOfficersPrefillState.generation !== generation) return;
            renewOfficersPrefillState.status = 'manual_required';
            renewOfficersPrefillState.data = null;
            renewOfficersPrefillState.warnings = ['Certification details could not be read. Please complete them manually.'];
            if (txt) txt.textContent = 'Auto-fill failed. Please fill manually.';
            if (typeof renderRenewIdLookupProgress === 'function') renderRenewIdLookupProgress({ title: 'Member ID Lookup', detail: 'Lookup failed', stats: 'Please enter IDs manually.', percent: 100, tone: 'red' });
            if (window.showToast) window.showToast('Certification details need manual entry.', 'warning', 'Autofill Failed');
        }
    }

    function transitionRenewOfficersPrefill(file) {
        if (file && file !== renewOfficersPrefillState.file) {
            renewOfficersPrefillState.manualValues = {};
            renewPrefillTrackedIds().forEach(id => {
                const input = document.getElementById(id);
                if (input) delete input.dataset.autoFillDirty;
            });
        } else {
            rememberRenewManualPrefillValues();
        }
        renewOfficersPrefillState.generation += 1;
        renewOfficersPrefillState.abortController?.abort();
        clearPriorRenewAutomaticValues();
        renewOfficersPrefillState.abortController = null;
        renewOfficersPrefillState.status = 'manual';
        renewOfficersPrefillState.data = null;
        renewOfficersPrefillState.warnings = [];
        renewOfficersPrefillState.file = file || null;

        if (!isRenewCertificationPdf(file)) return;
        renewOfficersPrefillState.status = 'loading';
        renewOfficersPrefillState.abortController = new AbortController();
        void requestRenewOfficersPrefill(file, renewOfficersPrefillState.generation);
    }

    // Officer ID lookup from List of Members / Mother Org roster (Step 6).
    // Mirrors the apply flow. SPA-safe: no top-level const/let (this script
    // re-executes on fragment swaps); all state lives on window.
    window.__renewIdLookup = window.__renewIdLookup || { status: 'idle', cacheKey: null, abortController: null, results: {} };
    var RENEW_ID_LOOKUP_PAGE_SIZE = 25;
    var RENEW_ID_LOOKUP_TOKEN = 'member-lookup';

    function renewOfficerLookupSlots() {
        var slots = currentRenewOfficerSlots()
            .filter(function (position) { return !position.is_adviser; })
            .map(function (position) { return position.key; });
        customOfficerSlots.forEach(function (slot) {
            if (slots.indexOf(slot.key) === -1) slots.push(slot.key);
        });
        return slots;
    }

    function renewIdLookupSlotLabel(slot) {
        var defaults = (typeof officerPositions === 'undefined') ? [] : officerPositions;
        var found = null;
        for (var i = 0; i < defaults.length; i++) { if (defaults[i].key === slot) { found = defaults[i]; break; } }
        if (!found && typeof customOfficerSlots !== 'undefined') {
            for (var j = 0; j < customOfficerSlots.length; j++) { if (customOfficerSlots[j].key === slot) { found = customOfficerSlots[j]; break; } }
        }
        if (found && found.label) return found.label;
        return String(slot).replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    function readRenewOfficerName(slot) {
        var get = function (field) { var el = document.getElementById('officers_' + slot + '_' + field); return el ? (el.value || '').trim() : ''; };
        var first = get('first_name');
        var last = get('last_name');
        if (!first && !last) return null;
        return { first_name: first, middle_name: get('middle_name'), last_name: last, suffix: get('suffix') };
    }

    function renewShortHash(text) {
        var hash = 5381;
        for (var i = 0; i < text.length; i++) hash = ((hash << 5) + hash + text.charCodeAt(i)) >>> 0;
        return hash.toString(16);
    }

    function renewOfficerLookupCacheKey(officers, extraSig) {
        var fileSig = renewMemberFiles
            .map(function (f) { return f.name + '|' + f.size + '|' + f.lastModified; }).join(';');
        return renewShortHash(fileSig + '::' + JSON.stringify(officers) + '::' + (extraSig || ''));
    }

    function clearRenewIdLookupWidgets(slot) {
        document.querySelectorAll('[data-renew-idlookup-slot="' + slot + '"]').forEach(function (el) { el.remove(); });
    }

    function renderRenewIdBadge(slot, memberName, studentId, rawLine, sourceLabel) {
        var input = document.getElementById('officers_' + slot + '_student_id');
        if (!input || !input.parentElement || !memberName || !studentId) return;
        var badge = document.createElement('div');
        badge.className = 'text-[11px] text-green-700 mt-0.5';
        badge.setAttribute('data-renew-idlookup-slot', slot);
        if (rawLine) badge.title = sourceLabel + ' row: ' + rawLine;
        badge.textContent = '✓ From ' + sourceLabel + ': ' + memberName + ' (' + studentId + ')';
        input.parentElement.appendChild(badge);
    }

    function renderRenewIdPicker(slot, candidates, sourceLabel) {
        var input = document.getElementById('officers_' + slot + '_student_id');
        if (!input || !input.parentElement || !Array.isArray(candidates) || candidates.length === 0) return;
        var wrap = document.createElement('div');
        wrap.className = 'mt-1';
        wrap.setAttribute('data-renew-idlookup-slot', slot);
        var label = document.createElement('label');
        label.className = 'block text-[11px] font-semibold text-amber-700 mb-0.5';
        label.textContent = candidates.length + ' possible matches in ' + sourceLabel + ' — piliin ang tama:';
        var select = document.createElement('select');
        select.className = 'w-full px-2 py-1 text-xs border border-amber-400 rounded focus:outline-none focus:border-amber-600 bg-amber-50';
        select.setAttribute('data-renew-idlookup-slot', slot);
        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Select matching ID…';
        select.appendChild(placeholder);
        candidates.forEach(function (candidate) {
            if (!candidate || !candidate.student_id) return;
            var option = document.createElement('option');
            option.value = candidate.student_id;
            option.textContent = (candidate.member_name || candidate.student_id) + ' — ' + candidate.student_id;
            option.dataset.rawLine = candidate.raw_line || '';
            option.dataset.memberName = candidate.member_name || '';
            select.appendChild(option);
        });
        select.addEventListener('change', function () {
            var picked = select.selectedOptions[0];
            if (!picked || !picked.value) return;
            input.value = picked.value;
            input.dataset.autoFillDirty = 'true';
            delete input.dataset.autoFillToken;
            delete input.dataset.autoFillValue;
            try { renewOfficersPrefillState.manualValues[input.id] = picked.value; } catch (e) {}
            input.classList.remove('border-red-500');
            clearRenewIdLookupWidgets(slot);
            renderRenewIdBadge(slot, picked.dataset.memberName, picked.value, picked.dataset.rawLine, sourceLabel);
            fetchRenewStudent('officers', slot, picked.value);
        });
        wrap.appendChild(label);
        wrap.appendChild(select);
        input.parentElement.appendChild(wrap);
    }

    function applyRenewIdLookupResults(results, sourceLabel, announce, rowsRead) {
        if (!results || typeof results !== 'object') return;
        rowsRead = rowsRead || 0;
        var filled = 0;
        var pickers = 0;
        Object.entries(results).forEach(function ([slot, result]) {
            if (!result || typeof result !== 'object') return;
            clearRenewIdLookupWidgets(slot);
            var input = document.getElementById('officers_' + slot + '_student_id');
            if (!input) return;
            if (result.match && result.match.student_id) {
                var hadManualValue = input.dataset.autoFillDirty === 'true'
                    || (input.value !== '' && !input.dataset.autoFillToken);
                assignRenewAutomaticValue(input, result.match.student_id, RENEW_ID_LOOKUP_TOKEN);
                if (input.value === result.match.student_id) {
                    renderRenewIdBadge(slot, result.match.member_name, result.match.student_id, result.match.raw_line, sourceLabel);
                    if (!hadManualValue) {
                        filled++;
                        fetchRenewStudent('officers', slot, result.match.student_id);
                    }
                }
            } else if (Array.isArray(result.candidates) && result.candidates.length > 0) {
                renderRenewIdPicker(slot, result.candidates, sourceLabel);
                pickers++;
            }
        });
        if (!announce || typeof window.showToast !== 'function') return;
        if (filled > 0 || pickers > 0) {
            var parts = [];
            if (filled > 0) parts.push(filled + ' ID' + (filled > 1 ? 's' : '') + ' auto-matched');
            if (pickers > 0) parts.push(pickers + ' need' + (pickers > 1 ? '' : 's') + ' your pick');
            window.showToast('Matched from ' + sourceLabel + ': ' + parts.join(', ') + '.', 'success', 'Member ID Lookup');
            var silent = Object.entries(results).filter(function (entry) {
                var result = entry[1];
                return result && typeof result === 'object' && !(result.match && result.match.student_id)
                    && !(Array.isArray(result.candidates) && result.candidates.length > 0);
            }).map(function (entry) { return renewIdLookupSlotLabel(entry[0]); });
            if (silent.length > 0) {
                window.showToast('Still need manual IDs: ' + silent.join(', ') + '. Enter them manually.', 'info', 'Manual IDs Needed');
            }
        } else if (rowsRead === 0) {
            window.showToast('No member rows found — hindi mabasa ang format ng file. Please check the file or enter IDs manually.', 'warning', 'No Rows Found');
        } else {
            window.showToast('No matching IDs found in ' + sourceLabel + ' (' + rowsRead + ' rows read). Check name spelling or enter IDs manually.', 'info', 'No Matches');
        }
    }

    function renewLookupElapsed(startedAt) {
        return Math.max(0, Math.round((Date.now() - startedAt) / 1000)) + 's';
    }

    // Floating ID lookup progress card (ported from apply-org; UI-only).
    // Loading bar/dot use the org theme (var(--primary-color)); green = done, red = failed.
    // NOTE: `var` (not const/let) — this script re-executes on every SPA fragment swap.
    var renewLookupProgress = window.__renewLookupProgress || (window.__renewLookupProgress = { tick: null, hideTimer: null, pct: 0, title: '' });

    function renewSetLookupBar(pct) {
        var fill = document.getElementById('renew-lookup-fill');
        var label = document.getElementById('renew-lookup-pct');
        var clamped = Math.max(0, Math.min(100, Math.floor(pct)));
        if (fill) fill.style.width = clamped + '%';
        if (label) label.textContent = clamped + '%';
        renewLookupProgress.pct = clamped;
    }

    function renewLookupTheme(color) {
        var fill = document.getElementById('renew-lookup-fill');
        var dot = document.getElementById('renew-lookup-dot');
        if (fill) fill.style.backgroundColor = color;
        if (dot) dot.style.backgroundColor = color;
    }

    function renewStopLookupTick() {
        if (renewLookupProgress.tick) { clearInterval(renewLookupProgress.tick); renewLookupProgress.tick = null; }
    }

    function renderRenewIdLookupProgress(opts) {
        opts = opts || {};
        var card = document.getElementById('renew-lookup-card');
        if (!card) return;
        var tone = opts.tone || 'amber';
        var title = opts.title !== undefined ? opts.title : 'Matching Officer IDs';
        if (renewLookupProgress.hideTimer) { clearTimeout(renewLookupProgress.hideTimer); renewLookupProgress.hideTimer = null; }
        var wasHidden = card.classList.contains('hidden');
        card.classList.remove('hidden', 'hiding');
        if (wasHidden) { renewSetLookupBar(0); renewLookupProgress.title = ''; }
        var titleEl = document.getElementById('renew-lookup-title');
        var line1 = document.getElementById('renew-lookup-line1');
        var line2 = document.getElementById('renew-lookup-line2');
        if (titleEl) titleEl.textContent = title;
        if (line1 && opts.detail !== undefined) { line1.textContent = opts.detail; line1.title = opts.detail; }
        if (line2 && opts.stats !== undefined) line2.textContent = opts.stats;
        if (tone === 'red') {
            renewStopLookupTick();
            card.classList.remove('is-loading');
            renewLookupTheme('#ef4444');
            renewSetLookupBar(opts.percent === null || opts.percent === undefined ? 100 : opts.percent);
            return;
        }
        if (tone === 'green') {
            renewCompleteLookupProgress(opts.detail || '');
            return;
        }
        // amber / blue = working: theme-colored bar with shimmer
        card.classList.add('is-loading');
        renewLookupTheme('var(--primary-color)');
        if (title !== renewLookupProgress.title) { renewLookupProgress.title = title; renewStopLookupTick(); }
        if (opts.percent === null || opts.percent === undefined) {
            // Ease toward 90% while the request is pending — never jump straight to full.
            if (!renewLookupProgress.tick) {
                var runTitle = title;
                renewLookupProgress.tick = setInterval(function () {
                    if (runTitle !== renewLookupProgress.title) { renewStopLookupTick(); return; }
                    var next = renewLookupProgress.pct + Math.max(0.6, (90 - renewLookupProgress.pct) * 0.055);
                    renewSetLookupBar(Math.min(90, next));
                }, 200);
            }
        } else {
            renewStopLookupTick();
            renewSetLookupBar(opts.percent);
        }
    }

    function renewCompleteLookupProgress(doneText) {
        var card = document.getElementById('renew-lookup-card');
        if (!card || card.classList.contains('hidden')) return;
        renewStopLookupTick();
        card.classList.remove('is-loading');
        renewLookupTheme('#22c55e');
        if (doneText) { var line1 = document.getElementById('renew-lookup-line1'); if (line1) line1.textContent = doneText; }
        renewSetLookupBar(100);
        if (renewLookupProgress.hideTimer) clearTimeout(renewLookupProgress.hideTimer);
        renewLookupProgress.hideTimer = setTimeout(function () { renewHideLookupProgress(); }, 1200);
    }

    function renewHideLookupProgress() {
        var card = document.getElementById('renew-lookup-card');
        if (!card || card.classList.contains('hidden')) return;
        renewStopLookupTick();
        if (renewLookupProgress.hideTimer) { clearTimeout(renewLookupProgress.hideTimer); renewLookupProgress.hideTimer = null; }
        renewLookupProgress.title = '';
        card.classList.add('hiding');
        setTimeout(function () { card.classList.add('hidden'); }, 280);
        setTimeout(function () { card.classList.remove('hiding'); }, 320);
    }

    function clearRenewIdLookupStatus() {
        renewCompleteLookupProgress('');
    }

    async function runRenewMotherOrgLookup(officers, key, departmentId, controller, csrf, startedAt, rowsCounter) {
        renderRenewIdLookupProgress({ title: 'Matching Officer IDs', detail: 'Reading Mother Org list…', stats: '', percent: null, tone: 'amber' });
        var meta = null;
        try {
            var metaResponse = await fetch('/apply-org/mother-org-member-files/' + encodeURIComponent(departmentId), {
                headers: { 'Accept': 'application/json' }, signal: controller.signal,
            });
            meta = await metaResponse.json();
        } catch (error) {
            if (error.name === 'AbortError') return null;
            throw error;
        }
        var files = meta && Array.isArray(meta.files) ? meta.files : [];
        if (!meta || !meta.has_mother_org || files.length === 0) return { empty: true };
        var partial = false;
        var failReason = '';
        for (var i = 0; i < files.length; i++) {
            if (controller.signal.aborted) return null;
            var f = files[i];
            var pageFrom = 1;
            var knownTotal = 0;
            for (;;) {
                if (controller.signal.aborted) return null;
                renderRenewIdLookupProgress({
                    title: 'Matching Officer IDs',
                    detail: 'Reading Mother Org list (' + (i + 1) + '/' + files.length + '): ' + (f.name || 'file'),
                    stats: knownTotal > 0
                        ? 'Pages ' + pageFrom + ' of ' + knownTotal + ' · ' + renewLookupElapsed(startedAt) + ' elapsed · ' + rowsCounter.count + ' rows found'
                        : 'Pages ' + pageFrom + '… · ' + renewLookupElapsed(startedAt) + ' elapsed',
                    percent: knownTotal > 0 ? ((pageFrom - 1) / knownTotal) * 100 : null,
                    tone: 'amber',
                });
                var formData = new FormData();
                formData.append('department_id', String(departmentId));
                formData.append('file_index', String(f.index));
                formData.append('page_from', String(pageFrom));
                formData.append('page_size', String(RENEW_ID_LOOKUP_PAGE_SIZE));
                formData.append('officers', JSON.stringify(officers));
                formData.append('request_key', key);
                formData.append('_token', csrf);
                var chunk = null;
                try {
                    var response = await fetch('/apply-org/match-mother-org-ids', {
                        method: 'POST', body: formData, signal: controller.signal,
                    });
                    chunk = await response.json();
                } catch (error) {
                    if (error.name === 'AbortError') return null;
                    throw error;
                }
                if (!chunk || !chunk.success) {
                    partial = true;
                    if (!failReason && chunk && chunk.message) failReason = String(chunk.message);
                    break;
                }
                knownTotal = Number(chunk.pages_total) || knownTotal;
                rowsCounter.count = Number(chunk.rows_total) || rowsCounter.count;
                if (window.console && window.console.info) window.console.info('[Member ID Lookup] Mother Org chunk parsed:', { file: f.name, pages_done: chunk.pages_done, pages_total: chunk.pages_total, rows_total: chunk.rows_total });
                if (chunk.complete) break;
                pageFrom = (Number(chunk.pages_done) || pageFrom) + 1;
                if (knownTotal > 0 && pageFrom > knownTotal) break;
            }
        }
        if (controller.signal.aborted) return null;
        renderRenewIdLookupProgress({ title: 'Matching Officer IDs', detail: 'Matching officer IDs…', stats: renewLookupElapsed(startedAt) + ' elapsed', percent: 100, tone: 'blue' });
        var finishData = new FormData();
        finishData.append('department_id', String(departmentId));
        finishData.append('officers', JSON.stringify(officers));
        finishData.append('request_key', key);
        finishData.append('finish', '1');
        finishData.append('_token', csrf);
        var fin = null;
        try {
            var finishResponse = await fetch('/apply-org/match-mother-org-ids', {
                method: 'POST', body: finishData, signal: controller.signal,
            });
            fin = await finishResponse.json();
        } catch (error) {
            if (error.name === 'AbortError') return null;
            throw error;
        }
        if (!fin || !fin.success || !fin.results) return { empty: true, partial: partial, failReason: (fin && fin.message) || failReason };
        return { results: fin.results, rowsRead: Number(fin.rows_received) || 0, partial: partial, failReason: failReason };
    }

    function resetRenewIdLookupCache() {
        if (window.__renewIdLookup) {
            window.__renewIdLookup.cacheKey = null;
            window.__renewIdLookup.results = {};
        }
    }

    async function maybeRenewLookupOfficerIds() {
        if (renewCurrent !== 6) return;
        var useMotherOrg = renewOrgType === 'Sub Org' && renewHasMotherOrg && renewMemberFiles.length === 0;
        var hasUploads = renewMemberFiles.length > 0;
        if (!useMotherOrg && !hasUploads) return;
        var officers = {};
        renewOfficerLookupSlots().forEach(function (slot) {
            var name = readRenewOfficerName(slot);
            if (name) officers[slot] = name;
        });
        if (Object.keys(officers).length === 0) return;
        var key = renewOfficerLookupCacheKey(officers, useMotherOrg ? 'mother-' + renewOrgDeptId : '');
        var sourceLabel = useMotherOrg ? 'Mother Org list' : 'List of Members';
        var st = window.__renewIdLookup;
        if (st.cacheKey === key) {
            applyRenewIdLookupResults(st.results, sourceLabel, false);
            return;
        }
        if (st.abortController) { try { st.abortController.abort(); } catch (e) {} }
        var controller = new AbortController();
        st.abortController = controller;
        st.status = 'loading';
        st.cacheKey = key;
        st.results = {};

        var csrf = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : '';
        if (!csrf) { try { csrf = '{{ csrf_token() }}'; } catch (e) {} }

        try {
            var matchResult = null;
            var partialMessage = '';
            var failReason = '';
            var lookupStartedAt = Date.now();
            var rowsCounter = { count: 0 };
            if (useMotherOrg) {
                var mother = await runRenewMotherOrgLookup(officers, key, renewOrgDeptId, controller, csrf, lookupStartedAt, rowsCounter);
                if (mother === null) return;
                if (!mother.empty && mother.results) {
                    matchResult = { success: true, results: mother.results, rows_received: mother.rowsRead || 0 };
                    if (mother.partial) partialMessage = 'Mother Org list could not be fully read. Unmatched IDs may need manual entry.';
                } else if (!mother.empty) {
                    failReason = mother.failReason || failReason;
                }
            } else {
                var files = renewMemberFiles.slice();
                var allRows = [];
                var filesPartial = 0;
                for (var i = 0; i < files.length; i++) {
                    if (controller.signal.aborted) return;
                    var entry = files[i];
                    var pageFrom = 1;
                    var knownTotal = 0;
                    var fileOk = true;
                    for (;;) {
                        if (controller.signal.aborted) return;
                        renderRenewIdLookupProgress({
                            title: 'Matching Officer IDs',
                            detail: 'Reading file ' + (i + 1) + '/' + files.length + ': ' + entry.name,
                            stats: knownTotal > 0
                                ? 'Pages ' + pageFrom + ' of ' + knownTotal + ' · ' + renewLookupElapsed(lookupStartedAt) + ' elapsed · ' + allRows.length + ' rows found'
                                : 'Pages ' + pageFrom + '… · ' + renewLookupElapsed(lookupStartedAt) + ' elapsed',
                            percent: knownTotal > 0 ? ((pageFrom - 1) / knownTotal) * 100 : null,
                            tone: 'amber',
                        });
                        var formData = new FormData();
                        formData.append('member_file', entry, entry.name);
                        formData.append('page_from', String(pageFrom));
                        formData.append('page_size', String(RENEW_ID_LOOKUP_PAGE_SIZE));
                        formData.append('request_key', key + '-f' + i);
                        formData.append('_token', csrf);
                        var result = null;
                        try {
                            var response = await fetch('/apply-org/parse-member-rows', {
                                method: 'POST', body: formData, signal: controller.signal,
                            });
                            result = await response.json();
                        } catch (error) {
                            if (error.name === 'AbortError') return;
                            throw error;
                        }
                        if (!result || !result.success || !Array.isArray(result.rows)) {
                            fileOk = false;
                            if (!failReason && result && result.message) failReason = String(result.message);
                            break;
                        }
                        knownTotal = Number(result.pages_total) || knownTotal;
                        allRows.push.apply(allRows, result.rows);
                        if (window.console && window.console.info) window.console.info('[Member ID Lookup] Parsed chunk:', { file: entry.name, pages_done: result.pages_done, pages_total: result.pages_total, rows_in_chunk: result.rows.length, rows_total: allRows.length });
                        if (result.complete) break;
                        pageFrom = (Number(result.pages_done) || pageFrom) + 1;
                        if (knownTotal > 0 && pageFrom > knownTotal) break;
                    }
                    if (!fileOk) filesPartial++;
                }
                if (controller.signal.aborted) return;
                if (st.abortController !== controller) return;
                if (st.cacheKey !== key) return;

                renderRenewIdLookupProgress({ title: 'Matching Officer IDs', detail: 'Matching officer IDs…', stats: renewLookupElapsed(lookupStartedAt) + ' elapsed · ' + allRows.length + ' rows read', percent: 100, tone: 'blue' });
                var matchData = new FormData();
                matchData.append('officers', JSON.stringify(officers));
                matchData.append('rows', JSON.stringify(allRows));
                matchData.append('request_key', key);
                matchData.append('_token', csrf);
                try {
                    var matchResponse = await fetch('/apply-org/match-officer-ids', {
                        method: 'POST', body: matchData, signal: controller.signal,
                    });
                    matchResult = await matchResponse.json();
                } catch (error) {
                    if (error.name === 'AbortError') return;
                    throw error;
                }
                if (!matchResult || !matchResult.success) {
                    if (!failReason && matchResult && matchResult.message) failReason = String(matchResult.message);
                }
                if (filesPartial > 0) partialMessage = filesPartial + ' member file(s) could not be fully read. Unmatched IDs may need manual entry.';
            }
            if (controller.signal.aborted) return;
            if (st.abortController !== controller) return;
            if (st.cacheKey !== key) return;
            st.status = 'done';
            clearRenewIdLookupStatus();
            if (matchResult && matchResult.success && matchResult.results) {
                st.results = matchResult.results;
                applyRenewIdLookupResults(matchResult.results, sourceLabel, true, Number(matchResult.rows_received) || 0);
                if (partialMessage !== '' && typeof window.showToast === 'function') {
                    window.showToast(partialMessage, 'warning', 'Partial Read');
                }
            } else {
                st.results = {};
                st.cacheKey = null;
                renderRenewIdLookupProgress({ title: 'Member ID Lookup', detail: 'Lookup failed', stats: failReason || 'Please enter IDs manually.', percent: 100, tone: 'red' });
                if (typeof window.showToast === 'function') {
                    window.showToast(failReason || 'Member ID lookup failed. Please enter IDs manually.', 'warning', 'Lookup Failed');
                }
            }
        } catch (error) {
            if (error.name === 'AbortError') return;
            if (st.abortController !== controller) return;
            st.status = 'idle';
            st.cacheKey = null;
            renderRenewIdLookupProgress({ title: 'Member ID Lookup', detail: 'Lookup failed', stats: 'Please try again or enter IDs manually.', percent: 100, tone: 'red' });
            if (typeof window.showToast === 'function') {
                window.showToast('Member ID lookup failed. Please try again or enter IDs manually.', 'warning', 'Lookup Failed');
            }
        }
        if (data.course) {
            matchAndSetCourse(`${namePrefix}_${positionKey}_course`, data.course);
        }
    }

    function applyRenewVisibleOfficersPrefill() {
        currentRenewOfficerSlots().forEach(position => applyRenewOfficerPrefill('officers', position.key));
        if (renewCurrent !== 6) return;
        Object.entries(renewOfficersPrefillState.data?.officers || {}).forEach(([key, officer]) => {
            if (isDefaultRenewPositionKey(key)) return;
            const label = officer?.position_name || key.replace(/^custom_\d+_?/i, '').replace(/_/g, ' ');
            if (ensureRenewCustomCard(key, label)) {
                bindRenewPrefillDestinationTracking();
            }
            applyRenewOfficerPrefill('officers', key);
        });

        const preferredDeptId = (renewOrgType === 'Non-Academic')
            ? departmentIdForCollege(renewOfficersPrefillState.data?.college)
            : null;
        [...currentRenewOfficerSlots(), ...customOfficerSlots].forEach(slot => {
            autoLinkOfficerDepartment('officers', slot.key, preferredDeptId);
        });

        // Re-assert requester over President or Senior Adviser
        syncRenewRequesterToOfficer();
    }

    function applyRenewVisiblePrefill() {
        bindRenewPrefillDestinationTracking();
        applyRenewDeanPrefill();
        if (renewCurrent === 6) applyRenewVisibleOfficersPrefill();
    }

    async function requestRenewOfficersPrefill(file, generation) {
        const controller = renewOfficersPrefillState.abortController;
        const formData = new FormData();
        formData.append('certification_of_officers', file);
        formData.append('request_token', String(generation));
        formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}');

        const statusEl = document.getElementById('renew-cert-prefill-status');
        const txt = document.getElementById('renew-cert-prefill-text');
        if (statusEl) statusEl.classList.remove('hidden');
        if (txt) txt.textContent = 'Reading Certification of Officers...';

        try {
            const response = await fetch('{{ route('apply-org.prefill-from-officers') }}', {
                method: 'POST', body: formData, signal: controller.signal,
            });
            const result = await response.json();
            const isCurrent = renewOfficersPrefillState.generation === generation
                && renewOfficersPrefillState.file === file;
            if (!isCurrent) return;

            renewOfficersPrefillState.status = result.success ? (result.outcome || 'completed') : 'manual_required';
            renewOfficersPrefillState.data = result.success ? (result.data || null) : null;
            renewOfficersPrefillState.warnings = result.warnings || (result.message ? [result.message] : []);
            applyRenewVisiblePrefill();

            if (result.success) {
                if (txt) txt.textContent = 'Auto-filled from Certification PDF. Please verify.';
                if (window.showToast) window.showToast('Officers and Dean auto-filled from Certification PDF.', 'success', 'Auto-fill Ready');
            } else {
                if (txt) txt.textContent = result.message || 'Could not auto-fill. Please fill manually.';
                if (window.showToast) window.showToast(result.message || 'Could not auto-fill. Please fill manually.', 'warning', 'Auto-fill');
            }
        } catch (error) {
            if (error.name === 'AbortError' || renewOfficersPrefillState.generation !== generation) return;
            renewOfficersPrefillState.status = 'manual_required';
            renewOfficersPrefillState.data = null;
            renewOfficersPrefillState.warnings = ['Certification details could not be read. Please complete them manually.'];
            if (txt) txt.textContent = 'Auto-fill failed. Please fill manually.';
            if (window.showToast) window.showToast('Auto-fill failed. Please fill manually.', 'error', 'Error');
        }
    }

    function transitionRenewOfficersPrefill(file) {
        if (file && file !== renewOfficersPrefillState.file) {
            renewOfficersPrefillState.manualValues = {};
            renewPrefillTrackedIds().forEach(id => {
                const input = document.getElementById(id);
                if (input) delete input.dataset.autoFillDirty;
            });
        } else {
            rememberRenewManualPrefillValues();
        }
        renewOfficersPrefillState.generation += 1;
        renewOfficersPrefillState.abortController?.abort();
        clearPriorRenewAutomaticValues();
        renewOfficersPrefillState.abortController = null;
        renewOfficersPrefillState.status = 'manual';
        renewOfficersPrefillState.data = null;
        renewOfficersPrefillState.warnings = [];
        renewOfficersPrefillState.file = file || null;

        if (!isRenewCertificationPdf(file)) return;
        renewOfficersPrefillState.status = 'loading';
        renewOfficersPrefillState.abortController = new AbortController();
        void requestRenewOfficersPrefill(file, renewOfficersPrefillState.generation);
    }

    function renderRenewMembers(){
        const preview=document.getElementById('renewMemberPreview');
        if(!preview) return;
        preview.innerHTML='';
        renewMemberFiles.forEach((f,i)=>{
            const row=document.createElement('div'); row.className='flex items-center justify-between px-3 py-2 bg-white border rounded';
            const sizeKb=(f.size/1024).toFixed(1);
            const icon=f.type.includes('pdf')?'ph-file-pdf':'ph-file-image';
            row.innerHTML=`<div class="flex items-center gap-2 overflow-hidden"><i class="ph ${icon} text-xs" style="color:var(--primary-color)"></i><span class="text-xs truncate font-medium">${f.name}</span><span class="text-[10px] text-gray-400 shrink-0">${sizeKb}KB</span></div><button type="button" class="text-red-500 text-xs shrink-0 hover:text-red-700">${f.name===renewMemberFiles[i]?.name ? 'Remove' : 'Remove'}</button>`;
            row.querySelector('button').onclick=()=>{ renewMemberFiles.splice(i,1); renderRenewMembers(); resetRenewIdLookupCache(); };
            preview.appendChild(row);
        });
    }
    function bindRenewMemberInput(){
        const memberInput=document.getElementById('renewListMembersInput');
        const addBtn=document.getElementById('renewAddMembersBtn');
        if(memberInput && !memberInput.dataset.bound){
            memberInput.addEventListener('change', (e)=>{
                Array.from(e.target.files).forEach(f=> renewMemberFiles.push(f));
                e.target.value=''; renderRenewMembers(); resetRenewIdLookupCache();
            });
            memberInput.dataset.bound='true';
        }
        if(addBtn && !addBtn.dataset.bound){
            addBtn.addEventListener('click', ()=> document.getElementById('renewListMembersInput')?.click());
            addBtn.dataset.bound='true';
        }
    }
    function renderRenewOtherFiles(){
        const preview=document.getElementById('renewOtherPreview');
        if(!preview) return;
        preview.innerHTML='';
        renewOtherFiles.forEach((f,i)=>{
            const row=document.createElement('div'); row.className='flex items-center justify-between px-3 py-2 bg-white border rounded';
            const sizeKb=(f.size/1024).toFixed(1);
            const icon=f.type.includes('pdf')?'ph-file-pdf':'ph-file-image';
            row.innerHTML=`<div class="flex items-center gap-2 overflow-hidden"><i class="ph ${icon} text-xs" style="color:var(--primary-color)"></i><span class="text-xs truncate font-medium">${f.name}</span><span class="text-[10px] text-gray-400 shrink-0">${sizeKb}KB</span></div><button type="button" class="text-red-500 text-xs shrink-0 hover:text-red-700">Remove</button>`;
            row.querySelector('button').onclick=()=>{ renewOtherFiles.splice(i,1); renderRenewOtherFiles(); };
            preview.appendChild(row);
        });
    }
    function bindRenewOtherInput(){
        const otherInput=document.getElementById('renewOtherFilesInput');
        const addBtn=document.getElementById('renewAddOtherBtn');
        if(otherInput && !otherInput.dataset.bound){
            otherInput.addEventListener('change', (e)=>{
                Array.from(e.target.files).forEach(f=> renewOtherFiles.push(f));
                e.target.value=''; renderRenewOtherFiles();
            });
            otherInput.dataset.bound='true';
        }
        if(addBtn && !addBtn.dataset.bound){
            addBtn.addEventListener('click', ()=> document.getElementById('renewOtherFilesInput')?.click());
            addBtn.dataset.bound='true';
        }
    }
    function initRenewPage(){
        // Idempotent per DOM instance: SPA fragment swaps re-execute this whole
        // script on a fresh DOM, while bfcache/Alpine re-inits reuse this DOM.
        var renewFormEl = document.getElementById('renewForm');
        if(renewFormEl && renewFormEl.dataset.renewInit === 'true') {
            var keepStep = window.__renewCurrentStep || 1;
            renewShow(keepStep);
            return;
        }
        window.__renewCurrentStep = 1; renewCurrent = 1;
        generateRenewOfficerForms();
        refreshRenewMembers();
        bindRenewMemberInput();
        bindRenewOtherInput();
        bindRenewPrefillDestinationTracking();

        const certInput=document.getElementById('renew_certification_of_officers');
        if(certInput && !certInput.dataset.bound){
            certInput.addEventListener('change', (e)=>{ const f=e.target.files[0]; if(f) transitionRenewOfficersPrefill(f); });
            certInput.dataset.bound='true';
        }
        // Live sync requester -> officer (Adviser > PDF priority, like Apply)
        ['renew_first_name','renew_last_name','renew_email','renew_student_id'].forEach(id=>{
            const el=document.getElementById(id);
            if(el && !el.dataset.requesterBound){
                el.addEventListener('input', syncRenewRequesterToOfficer);
                el.dataset.requesterBound='true';
            }
        });
        const posEl=document.getElementById('renew_student_id');
        if(posEl && !posEl.dataset.requesterBound){
            posEl.addEventListener('input', ()=>{ renderRenewPosition(); syncRenewRequesterToOfficer(); });
            posEl.dataset.requesterBound='true';
        }
        // also support click on the visual dashed area
        const memberDashed=document.querySelector('#renew-member-upload .border-dashed');
        if(memberDashed && !memberDashed.dataset.bound){
            memberDashed.addEventListener('click', (e)=>{ if(e.target.closest('button')) return; document.getElementById('renewListMembersInput')?.click(); });
            memberDashed.style.cursor='pointer';
            memberDashed.dataset.bound='true';
        }
        renewShow(1);
        // Prefilled renewer ID (logged-in account): render its position and
        // sync name/email from the directory on init. Type-to-override stays.
        try {
            const prefilledId = document.getElementById('renew_student_id');
            if (prefilledId) {
                formatRenewRequesterID(prefilledId);
                if ((prefilledId.value || '').replace(/\D/g,'').length >= 8) {
                    fetchRenewRequesterData(prefilledId.value);
                }
            } else {
                renderRenewPosition();
            }
        } catch (e) { console.error('Renew prefill init error', e); }
        var doneForm = document.getElementById('renewForm');
        if(doneForm) doneForm.dataset.renewInit = 'true';
        var doneSubmit = document.getElementById('renewForm');
        if(doneSubmit && !doneSubmit.dataset.submitBound){
            doneSubmit.addEventListener('submit', submitRenew);
            doneSubmit.dataset.submitBound = 'true';
        }
    }
    // NOTE: no turbo:load/pageshow listeners — this script re-executes on every
    // SPA fragment swap (fresh DOM), and those events would re-run init on
    // bfcache restore and wipe an in-progress form.
    if(document.readyState==='loading'){
        document.addEventListener('DOMContentLoaded', initRenewPage, { once: true });
    } else {
        initRenewPage();
    }

    async function submitRenew(e){
        e.preventDefault();
        console.log('renewForm submit fired', {memberFiles: renewMemberFiles.length, otherFiles: renewOtherFiles.length});
        // Instant required check with toast + red highlight (like Apply)
        let instantValid = true;
        let firstInvalid = null;
        for(let s=1;s<=6;s++){
            const el=document.getElementById('renew-step'+s); if(!el) continue;
            const req=el.querySelectorAll('input[required],select[required],textarea[required]');
            for(const f of req){
                if(f.type==='hidden'||f.disabled) continue;
                // For file inputs, check files length
                const isFile = f.type==='file';
                const hasValue = isFile ? (f.files && f.files.length>0) : !!f.value.trim();
                // Skip hidden containers (offsetParent null) but still check if in current flow
                if(!hasValue){
                    instantValid = false;
                    f.classList.add('border-red-500','bg-red-50');
                    f.addEventListener('input', function handler(){ f.classList.remove('border-red-500','bg-red-50'); f.removeEventListener('input', handler); }, {once:true});
                    f.addEventListener('change', function handler(){ f.classList.remove('border-red-500','bg-red-50'); f.removeEventListener('change', handler); }, {once:true});
                    if(!firstInvalid) firstInvalid = {step:s, field:f};
                } else {
                    f.classList.remove('border-red-500','bg-red-50');
                }
            }
        }
        if(!instantValid){
            const stepToShow = firstInvalid ? firstInvalid.step : renewCurrent;
            renewShow(stepToShow);
            if(firstInvalid && firstInvalid.field) firstInvalid.field.focus();
            if(window.showToast) window.showToast('Please fill all required fields before submitting. Check highlighted fields.', 'error');
            else alert('Please fill all required fields');
            return;
        }
        if(!renewValidate(renewCurrent)){ return; }
        // also validate earlier steps via renewValidate
        for(let s=1;s<=6;s++){ const el=document.getElementById('renew-step'+s); if(!el) continue; const req=el.querySelectorAll('input[required],select[required]'); for(const f of req){ if(f.type==='hidden'||f.disabled||f.offsetParent===null) continue; if(!f.value.trim()){ renewShow(s); if(window.showToast) window.showToast('Please fill required fields in step '+s,'error'); f.classList.add('border-red-500'); return; } } }
        // members conditional required
        if(!renewValidate(4)) { renewShow(4); return; }
        const btn=document.getElementById('renewSubmitBtn'); btn.disabled=true; btn.innerHTML='<i class="ph ph-spinner ph-spin text-[10px]"></i> Processing...';
        const form=document.getElementById('renewForm'); const fd=new FormData(form);
        // list_of_members[] must use array notation like apply (not indexed) and avoid ghost empty file part
        fd.delete('list_of_members[]');
        // renewMemberFiles is array of File objects (from input change) - handle both File and {file} shapes
        renewMemberFiles.forEach((entry)=>{ const file = entry && entry.file ? entry.file : entry; if(file) fd.append('list_of_members[]', file); });
        fd.delete('other_files[]');
        renewOtherFiles.forEach((entry)=>{ const file = entry && entry.file ? entry.file : entry; if(file) fd.append('other_files[]', file); });
        // Delete ghost for renewal/accomplishment if needed (no ghost for single file, but safe)
        try{
            const res=await fetch('{{ route('admin.renew.submit') }}',{method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','X-Requested-With':'XMLHttpRequest','Accept':'application/json'}, body:fd});
            const text = await res.text();
            let result;
            try{ result = JSON.parse(text); } catch(e){ const m = text.match(/\{[\s\S]*\}/); result = m ? JSON.parse(m[0]) : {success:false, message: text.slice(0,500)}; }
            if(result.success){
                if(window.showToast) window.showToast(result.message||'Renewal submitted successfully! Please wait for OSAS approval through email.','success','Renewal Submitted');
                else alert(result.message);
                setTimeout(()=>{
                    var dashUrl = '{{ route('dashboard') }}';
                    // Stay inside the admin SPA shell so the toast remains visible.
                    if(typeof window.navigateAdminPageInPlace === 'function') window.navigateAdminPageInPlace(dashUrl, false);
                    else window.location.href = dashUrl;
                }, 2000);
            } else {
                let msg = result.message || 'Validation failed';
                if(result.errors){
                    const all = Object.entries(result.errors).map(([k,v])=> `${k}: ${Array.isArray(v)?v[0]:v}`).join('\n');
                    msg = all || msg;
                    Object.entries(result.errors).forEach(([k, msgs])=>{
                        const m = Array.isArray(msgs)? msgs[0] : msgs;
                        if(window.showToast) window.showToast(m, 'error', k);
                        // Highlight matching inputs
                        document.querySelectorAll(`[name="${k}"], [name*="${k.split('.').pop()}"]`).forEach(el=>{
                            el.classList.add('border-red-500','bg-red-50');
                            el.addEventListener('input', ()=> el.classList.remove('border-red-500','bg-red-50'), {once:true});
                        });
                    });
                }
                if(!result.errors && window.showToast) window.showToast(msg,'error','Submission Error');
                else if(!result.errors) alert(msg);
                btn.disabled=false; btn.innerHTML='<i class="ph ph-paper-plane-tilt text-[10px]"></i> Final Submission';
            }
        }catch(err){
            console.error(err); 
            if(window.showToast) window.showToast(err.message||'Unexpected error','error','System Error'); 
            else alert('Unexpected error: '+(err.message||''));
            btn.disabled=false; btn.innerHTML='<i class="ph ph-paper-plane-tilt text-[10px]"></i> Final Submission';
        }
    }
    // Submit binding happens inside initRenewPage (guarded) so SPA fragment
    // re-execution never double-binds.
</script>
@endsection
