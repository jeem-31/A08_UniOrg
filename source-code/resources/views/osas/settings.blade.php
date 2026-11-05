@extends((request()->boolean('fragment') && request()->ajax()) ? 'layouts.fragment' : 'layouts.osas')

@section('title', 'OSAS Settings - UniOrg')

@php
    $requestedTab = request('tab');
    // Legacy 'account-management' URLs map to the Manage Accounts tab.
    if ($requestedTab === 'account-management') {
        $requestedTab = 'manage-accounts';
    }
    if ($requestedTab && in_array($requestedTab, ['profile', 'audit-logs', 'manage-accounts', 'dean-setup', 'accreditation'], true)) {
        $activeTab = $requestedTab;
    } elseif (request()->hasAny(['page', 'q', 'role', 'state', 'validity']) && !request()->has('tab')) {
        $activeTab = 'manage-accounts';
    } else {
        $activeTab = 'profile';
    }
@endphp

@section('content')
<div class="container-fluid px-6 py-5">
    <div class="mb-4">
        <h1 class="text-2xl font-black text-gray-900 tracking-tight leading-none">
            <span class="text-[#26488C]">Settings</span>
        </h1>
        <p class="text-gray-500 mt-1 text-xs font-medium">Manage your profile and system preferences.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
        <!-- Sidebar Navigation -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg border border-gray-100 p-2">
                <nav class="space-y-1">
                    <a href="#profile" onclick="showTab('profile', event)" id="tab-profile"
                       class="settings-tab {{ $activeTab === 'profile' ? 'active' : '' }} flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition text-xs font-bold">
                        <i class="ph ph-user text-sm w-4 text-center"></i>
                        Profile Information
                    </a>
                    <a href="#audit-logs" onclick="showTab('audit-logs', event)" id="tab-audit-logs"
                       class="settings-tab {{ $activeTab === 'audit-logs' ? 'active' : '' }} flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition text-xs font-bold">
                        <i class="ph ph-clock-counter-clockwise text-sm w-4 text-center"></i>
                        Audit Logs
                    </a>
                    <a href="#manage-accounts" onclick="showTab('manage-accounts', event)" id="tab-manage-accounts"
                       class="settings-tab {{ $activeTab === 'manage-accounts' ? 'active' : '' }} flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition text-xs font-bold">
                        <i class="ph ph-users text-sm w-4 text-center"></i>
                        Manage Accounts
                    </a>
                    <a href="#dean-setup" onclick="showTab('dean-setup', event)" id="tab-dean-setup"
                       class="settings-tab {{ $activeTab === 'dean-setup' ? 'active' : '' }} flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition text-xs font-bold">
                        <i class="ph ph-graduation-cap text-sm w-4 text-center"></i>
                        Dean Setup
                    </a>
                    <a href="#accreditation" onclick="showTab('accreditation', event)" id="tab-accreditation"
                       class="settings-tab {{ $activeTab === 'accreditation' ? 'active' : '' }} flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition text-xs font-bold">
                        <i class="ph ph-calendar-x text-sm w-4 text-center"></i>
                        Accreditation Expiration
                    </a>
                </nav>
            </div>
        </div>

        <!-- Content Area -->
        <div class="lg:col-span-4 min-w-0">
            <!-- Profile Information -->
            <div id="content-profile" class="settings-content {{ $activeTab === 'profile' ? '' : 'hidden' }}">
                <div class="bg-white rounded-lg border border-gray-100">
                    <div class="h-20 sm:h-24 rounded-t-lg bg-gradient-to-r from-[#26488C] to-[#1a3461]"></div>
                    <div class="px-4 sm:px-6 pb-5 sm:pb-6">
                        <div class="flex flex-col sm:flex-row sm:items-end -mt-12 sm:-mt-[3.5rem] mb-4 mobile-profile-row">
                            <div class="relative flex-shrink-0">
                                <div id="avatar-wrapper" role="button" tabindex="0" aria-label="Change profile photo" onclick="document.getElementById('photo-input').click()" onkeydown="if(event.key === 'Enter' || event.key === ' ') { event.preventDefault(); document.getElementById('photo-input').click(); }" class="cursor-pointer group relative">
                                    @if($user->photo)
                                        <img id="profile-avatar" src="{{ storage_url($user->photo) }}" alt="{{ $user->full_name }}" class="w-20 h-20 sm:w-24 sm:h-24 rounded-lg border-4 border-white shadow-md object-cover">
                                    @else
                                        <div id="profile-avatar" class="w-20 h-20 sm:w-24 sm:h-24 rounded-lg border-4 border-white shadow-md flex items-center justify-center bg-blue-50">
                                            <i class="ph ph-user text-3xl sm:text-4xl text-[#26488C]"></i>
                                        </div>
                                    @endif
                                    <div class="absolute inset-0 rounded-lg bg-black bg-opacity-0 group-hover:bg-opacity-40 transition-all flex items-center justify-center">
                                        <i class="ph ph-camera text-white text-lg opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                    </div>
                                </div>
                                <form id="photo-form" method="POST" action="{{ route('osas.settings.photo.update') }}" enctype="multipart/form-data">
                                    @csrf
                                    <input type="file" id="photo-input" name="photo" accept="image/*" class="hidden" onchange="onPhotoSelect(event)">
                                </form>
                                <div id="photo-actions" class="hidden absolute left-full ml-3 sm:right-full sm:mr-3 sm:left-auto sm:ml-0 top-1/2 -translate-y-1/2 flex gap-2 bg-white rounded-lg shadow-lg border border-gray-200 px-2.5 py-2 z-10">
                                    <button type="button" onclick="savePhoto()" class="px-2.5 py-1.5 text-[10px] font-black uppercase tracking-widest text-white rounded-lg bg-[#26488C] hover:bg-[#1a3461] transition whitespace-nowrap">
                                        <i class="ph ph-floppy-disk mr-1"></i>Save
                                    </button>
                                    <button type="button" onclick="cancelPhoto()" class="px-2.5 py-1.5 text-[10px] font-black uppercase tracking-widest text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition whitespace-nowrap">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                            <div class="sm:ml-4 mt-2 sm:mt-0 sm:mb-2 min-w-0 overflow-visible">
                                <h2 id="profile-name" class="text-lg sm:text-xl font-black text-white whitespace-nowrap relative -top-3">
                                    <span class="hidden sm:inline">{{ $user->full_name }}</span>
                                    <span class="sm:hidden">{{ $user->first_name }} {{ $user->last_name }}</span>
                                </h2>
                                <p class="text-[10px] sm:text-xs font-black text-[#C4973B] uppercase tracking-widest whitespace-nowrap relative -top-3">OSAS Administrator</p>
                            </div>
                        </div>

                        <div class="flex justify-end mb-4">
                            <button onclick="openEditProfileModal()" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-black uppercase tracking-widest text-white rounded-lg bg-[#26488C] hover:bg-[#1a3461] transition-colors">
                                <i class="ph ph-pencil-simple"></i> Edit Profile
                            </button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="bg-gray-50 rounded-lg border border-gray-100 p-4">
                                <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                                    <i class="ph ph-user-circle text-[#C4973B]"></i>
                                    Personal Information
                                </h3>
                                <div>
                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-1">Email Address</p>
                                    <p class="font-bold text-gray-900 text-sm break-words">{{ $user->email }}</p>
                                </div>
                            </div>

                            <div class="bg-gray-50 rounded-lg border border-gray-100 p-4">
                                <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                                    <i class="ph ph-gear text-[#C4973B]"></i>
                                    Account Settings
                                </h3>
                                <div>
                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-1">Password</p>
                                    <p class="font-bold text-gray-900 text-sm" id="last-changed-time" data-last-changed="{{ $user->password_changed_at ? $user->password_changed_at->toISOString() : '' }}">Last changed: Never</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="bg-white rounded-lg border border-gray-100 p-5 mt-3">
                    <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                        <i class="ph ph-lock text-[#C4973B]"></i>
                        Change Password
                    </h3>

                    <form id="passwordForm" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @csrf
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Current Password</label>
                            <input type="password" name="current_password" required autocomplete="current-password"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">New Password</label>
                            <input type="password" name="new_password" required minlength="8" autocomplete="new-password"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all">
                            <p class="text-[10px] text-gray-400 font-medium mt-1">Minimum 8 characters</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Confirm New Password</label>
                            <input type="password" name="new_password_confirmation" required minlength="8" autocomplete="new-password"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all">
                        </div>

                        <div class="md:col-span-2 p-3 bg-amber-50 border-l-4 border-amber-400 rounded">
                            <p class="text-xs text-amber-800">
                                <i class="ph ph-warning mr-1.5"></i>
                                <strong>Security Tip:</strong> Use a strong password with a mix of letters, numbers, and symbols.
                            </p>
                        </div>

                        <div class="md:col-span-2 flex justify-end">
                            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 text-white rounded-lg font-black text-xs uppercase tracking-widest bg-[#26488C] hover:bg-[#1a3461] transition-all">
                                <i class="ph ph-floppy-disk"></i> Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Audit Logs -->
            <div id="content-audit-logs" class="settings-content {{ $activeTab === 'audit-logs' ? '' : 'hidden' }}">
                <div class="bg-white rounded-lg border border-gray-100 overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-50 bg-gray-50/30 flex items-center justify-between">
                        <div>
                            <h2 class="text-xs font-black text-gray-900 uppercase tracking-widest flex items-center gap-2">
                                <i class="ph ph-clock-counter-clockwise text-[#26488C]"></i>
                                Recent Activities
                            </h2>
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-0.5">Last 50 administrative actions</p>
                        </div>
                        <div class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">
                            <i class="ph ph-clock mr-1 text-[#C4973B]"></i>
                            {{ now()->format('M d, Y h:i A') }}
                        </div>
                    </div>

                    <div class="divide-y divide-gray-50">
                        @forelse($recentActions as $action)
                            <div class="px-5 py-3 hover:bg-gray-50/50 transition-colors">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 {{ $action['type'] === 'success' ? 'bg-green-50' : ($action['type'] === 'info' ? 'bg-blue-50' : 'bg-red-50') }}">
                                        <i class="ph {{ $action['type'] === 'success' ? 'ph-check text-green-600' : ($action['type'] === 'info' ? 'ph-calendar-blank text-blue-600' : 'ph-x text-red-600') }} text-xs"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <h3 class="text-xs font-bold text-gray-900">{{ $action['action'] }}</h3>
                                                <p class="text-xs text-gray-500 mt-0.5">{{ $action['description'] }}</p>
                                                <div class="flex items-center gap-2 mt-1.5 text-[9px] font-black text-gray-400 uppercase tracking-widest">
                                                    <i class="ph ph-user text-[#C4973B]"></i>
                                                    <span>{{ $action['user'] }}</span>
                                                    <span>•</span>
                                                    <span>{{ $action['timestamp']->format('M d, Y h:i A') }}</span>
                                                    <span>•</span>
                                                    <span>{{ $action['timestamp']->diffForHumans() }}</span>
                                                </div>
                                            </div>
                                            <span class="px-2 py-0.5 text-[9px] font-black uppercase tracking-widest rounded flex-shrink-0 {{ $action['type'] === 'success' ? 'bg-green-50 text-green-700' : ($action['type'] === 'info' ? 'bg-blue-50 text-blue-700' : 'bg-red-50 text-red-700') }}">
                                                {{ $action['type'] === 'success' ? 'Approved' : ($action['type'] === 'info' ? 'Period Set' : 'Rejected') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="px-5 py-16 text-center">
                                <div class="w-14 h-14 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="ph ph-clock-counter-clockwise text-xl text-gray-200"></i>
                                </div>
                                <p class="text-sm font-black text-gray-900">No audit logs yet</p>
                                <p class="text-xs text-gray-400 mt-1">Administrative actions will appear here</p>
                            </div>
                        @endforelse
                    </div>
                </div>
                @include('osas.partials.authentication-activity')
            </div>

            <!-- Accreditation Expiration -->
            <div id="content-accreditation" class="settings-content {{ $activeTab === 'accreditation' ? '' : 'hidden' }}">
                <div class="bg-white rounded-lg border border-gray-100 p-5">
                    <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                        <i class="ph ph-calendar-x text-[#C4973B]"></i>
                        Accreditation Expiration
                    </h3>
                    <p class="text-xs text-gray-500 mb-4 leading-relaxed">Set the school year accreditation period (start and end date) that applies to all approved organizations. Once the end date passes, affected organizations are locked until their accreditation is renewed.</p>

                    <div class="bg-gray-50 rounded-lg border border-gray-100 p-4 mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">Current Accreditation Period</p>
                            <p class="font-black text-gray-900 text-sm" id="current-period-label">
                                {{ $currentPeriod && $currentPeriod->started_at ? $currentPeriod->started_at->format('M d, Y').' - '.$currentPeriod->expires_at->format('M d, Y') : 'Not set' }}
                            </p>
                            <p class="text-[10px] text-gray-500 font-bold mt-1" id="current-period-count">
                                @if($currentPeriod)
                                    @if($currentPeriod->expires_at->isPast())
                                        Expired &mdash; approved organizations are currently locked.
                                     @else
                                        Expires {{ $currentPeriod->expires_at->diffForHumans() }} &middot; {{ $approvedOrgCount }} approved org(s)
                                    @endif
                                @else
                                    No period set for {{ $approvedOrgCount }} approved org(s)
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest {{ $currentPeriod && $currentPeriod->expires_at && $currentPeriod->expires_at->isPast() ? 'bg-red-100 text-red-700' : ($currentPeriod ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600') }}">
                                <span class="h-2 w-2 rounded-full {{ $currentPeriod && $currentPeriod->expires_at && $currentPeriod->expires_at->isPast() ? 'bg-red-600' : ($currentPeriod ? 'bg-green-600' : 'bg-gray-400') }}"></span>
                                {{ $currentPeriod && $currentPeriod->expires_at && $currentPeriod->expires_at->isPast() ? 'Expired' : ($currentPeriod ? 'Active' : 'Not Set') }}
                            </span>
                            <span class="hidden sm:inline-flex items-center px-2.5 py-1 rounded bg-white border border-gray-200 text-[10px] font-bold text-gray-600">{{ $approvedOrgCount }} orgs</span>
                        </div>
                    </div>                    <form id="accreditationPeriodForm" class="flex flex-col gap-4">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="accreditation_start_date" class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">School Year Start</label>
                                <input type="date" name="accreditation_start_date" id="accreditation_start_date" required
                                       value="{{ $currentPeriod && $currentPeriod->started_at ? $currentPeriod->started_at->format('Y-m-d') : '' }}"
                                       class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all">
                            </div>
                            <div>
                                <label for="accreditation_end_date" class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">School Year End</label>
                                <input type="date" name="accreditation_end_date" id="accreditation_end_date" required
                                       value="{{ $currentPeriod && $currentPeriod->expires_at ? $currentPeriod->expires_at->format('Y-m-d') : '' }}"
                                       class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all">
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <button type="button" onclick="openApplyAccreditationModal()" class="flex-1 inline-flex items-center justify-center gap-2 px-5 py-2.5 text-white rounded-lg font-black text-xs uppercase tracking-widest bg-[#26488C] hover:bg-[#1a3461] transition-all">
                                <i class="ph ph-calendar-check"></i> Apply to All Approved Organizations
                            </button>
                            <button type="button" id="start-renewal-accreditation-btn" onclick="openStartRenewalModal()"
                                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-white rounded-lg font-black text-xs uppercase tracking-widest bg-[#C4973B] hover:bg-[#a67d2e] transition-all whitespace-nowrap">
                                <i class="ph ph-arrows-clockwise"></i> Start Renewal
                            </button>
                        </div>
                    </form>
                    <p class="text-[10px] text-gray-400 font-medium mt-2 flex items-center gap-1">
                        <i class="ph ph-warning"></i>
                        This overwrites the accreditation period of every approved organization.
                    </p>
                </div>
                <!-- Apply Accreditation Confirm Modal -->
                <div id="apply-accreditation-confirm-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 transition-opacity duration-300 opacity-0 p-4">
                    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 text-center">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-50 mb-4">
                            <i class="ph ph-warning text-xl text-[#26488C]"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Apply to All Approved?</h3>
                        <p class="text-sm text-gray-500 mb-2">This will overwrite the accreditation period for <span class="font-bold text-gray-900">{{ $approvedOrgCount }} approved organization(s)</span> to the dates above.</p>
                        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded p-2 mb-6">This action is irreversible and will affect all active organizations. Please confirm.</p>
                        <div class="flex justify-center gap-3">
                            <button type="button" onclick="closeApplyAccreditationModal()"
                                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium">Cancel</button>
                            <button type="button" id="apply-accreditation-confirm-btn" onclick="closeApplyAccreditationModal(); confirmApplyAccreditationPeriod();"
                                    class="px-6 py-2 bg-[#26488C] text-white rounded-lg hover:bg-[#1a3461] font-medium">Confirm Overwrite</button>
                        </div>
                    </div>
                </div>
                <!-- Start Renewal Confirm Modal (like logout) -->
                <div id="start-renewal-confirm-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 transition-opacity duration-300 opacity-0 p-4">
                    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 text-center">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-50 mb-4">
                            <i class="ph ph-arrows-clockwise text-xl text-[#C4973B]"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Start Renewal?</h3>
                        <p class="text-sm text-gray-500 mb-6">This will generate a pending renewal record for every approved organization whose accreditation has expired. Do you want to continue?</p>
                        <div class="flex justify-center gap-3">
                            <button type="button" onclick="closeStartRenewalModal()"
                                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium">Cancel</button>
                            <button type="button" id="start-renewal-confirm-btn" onclick="closeStartRenewalModal(); startRenewalFromAccreditation();"
                                    class="px-6 py-2 bg-[#C4973B] text-white rounded-lg hover:bg-[#a67d2e] font-medium">Confirm</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Management -->
            <div id="content-manage-accounts" class="settings-content {{ $activeTab === 'manage-accounts' ? '' : 'hidden' }}">
                <!-- Validity Summary -->
                @if(($validitySummary['expiring'] ?? 0) > 0 || ($validitySummary['expired'] ?? 0) > 0)
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        @if(($validitySummary['expiring'] ?? 0) > 0)
                            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 text-[10px] font-black uppercase tracking-wider">
                                <i class="ph ph-hourglass text-[10px]"></i> {{ $validitySummary['expiring'] }} expiring
                            </span>
                        @endif
                        @if(($validitySummary['expired'] ?? 0) > 0)
                            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-red-50 border border-red-200 text-red-600 text-[10px] font-black uppercase tracking-wider">
                                <i class="ph ph-user-x text-[10px]"></i> {{ $validitySummary['expired'] }} expired
                            </span>
                        @endif
                    </div>
                @endif

                <!-- Filters -->
                <div class="bg-white rounded-t-lg border border-gray-100 p-4 border-b-0">
                    <form method="GET" action="{{ route('osas.settings') }}" id="account-filter-form" class="flex flex-col gap-3">
                        <input type="hidden" name="tab" value="manage-accounts">
                        <div class="flex flex-wrap items-center gap-3">
                            <select name="role" onchange="submitAccountFilterForm()" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-700 focus:ring-2 focus:ring-[#26488C]/20 focus:border-[#26488C] transition-all min-w-[120px] flex-1 sm:flex-none">
                                <option value="">All Roles</option>
                                <option value="student" {{ request('role') === 'student' ? 'selected' : '' }}>Student</option>
                                <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                                <option value="osas_admin" {{ request('role') === 'osas_admin' ? 'selected' : '' }}>OSAS</option>
                            </select>
                            <select name="state" onchange="submitAccountFilterForm()" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-700 focus:ring-2 focus:ring-[#26488C]/20 focus:border-[#26488C] transition-all min-w-[120px] flex-1 sm:flex-none">
                                <option value="">All Statuses</option>
                                <option value="active" {{ request('state') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ request('state') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            <select name="validity" onchange="submitAccountFilterForm()" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-700 focus:ring-2 focus:ring-[#26488C]/20 focus:border-[#26488C] transition-all min-w-[120px] flex-1 sm:flex-none">
                                <option value="">All Validity</option>
                                <option value="active" {{ request('validity') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="expiring" {{ request('validity') === 'expiring' ? 'selected' : '' }}>Expiring</option>
                                <option value="expired" {{ request('validity') === 'expired' ? 'selected' : '' }}>Expired</option>
                            </select>
                            <select name="organization" onchange="submitAccountFilterForm()" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-700 focus:ring-2 focus:ring-[#26488C]/20 focus:border-[#26488C] transition-all min-w-[160px] flex-1 sm:flex-none">
                                <option value="">All Organizations</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}" {{ (string) request('organization') === (string) $org->id ? 'selected' : '' }}>{{ $org->org_acronym ?: $org->name }}</option>
                                @endforeach
                            </select>
                            <button type="button" onclick="openCreateAccountModal('osas_admin')" class="inline-flex items-center gap-2 px-4 py-2 bg-[#26488C] text-white text-xs font-bold rounded-lg hover:bg-[#1a3461] transition-colors shadow-sm ml-auto">
                                <i class="ph ph-plus"></i> New OSAS Admin
                            </button>
                        </div>
                        <div class="relative w-full">
                            <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by name, email, ID..." oninput="debouncedAccountFilter()" class="w-full pl-9 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-[#26488C]/20 focus:border-[#26488C] transition-all">
                        </div>
                    </form>
                </div>

                <!-- Data Table -->
                <div class="bg-white rounded-b-lg border border-gray-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50/50 border-b border-gray-50">
                                    <th class="px-5 py-3 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">Name &amp; ID</th>
                                    <th class="px-5 py-3 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">Contact</th>
                                    <th class="px-5 py-3 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">Organization</th>
                                    <th class="px-5 py-3 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">Role</th>
                                    <th class="px-5 py-3 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">Status</th>
                                    <th class="px-5 py-3 text-right text-[9px] font-black text-gray-400 uppercase tracking-widest">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @forelse($accounts as $account)
                                    <tr class="hover:bg-gray-50/50 transition-colors group">
                                        <td class="px-5 py-3">
                                            <div class="flex items-center gap-3">
                                                <div class="w-9 h-9 rounded-lg bg-blue-50 text-[#26488C] flex items-center justify-center font-black text-sm flex-shrink-0">
                                                    {{ substr($account->first_name, 0, 1) }}{{ substr($account->last_name, 0, 1) }}
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="text-xs font-bold text-gray-900 truncate">{{ $account->full_name }}</div>
                                                    <div class="text-[10px] text-gray-400 uppercase tracking-widest font-black">{{ $account->student_id ?? 'No ID' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3">
                                            <div class="text-xs text-gray-600 truncate">{{ $account->email }}</div>
                                        </td>
                                        <td class="px-5 py-3">
                                            @php $orgLabels = $account->activeOrganizationAssignments->map(fn($a) => $a->organization?->org_acronym ?: ($a->organization?->name ? \Illuminate\Support\Str::limit($a->organization->name, 12) : $a->role))->take(2); @endphp
                                            @if($orgLabels->isNotEmpty())
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach($orgLabels as $label)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-blue-50 text-[#26488C] truncate" title="{{ $label }}">{{ $label }}</span>
                                                    @endforeach
                                                    @if($account->activeOrganizationAssignments->count() > 2)
                                                        <span class="text-[9px] text-gray-400 font-bold">+{{ $account->activeOrganizationAssignments->count() - 2 }}</span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-[10px] text-gray-400 font-bold">—</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest bg-gray-100 text-gray-600">
                                                {{ \App\Services\AccountManagement\SystemAccessRole::tryFrom($account->role)?->label() ?? str_replace('_', ' ', $account->role) }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3">
                                            @php
                                                $rowStatus = $accountStatus[$account->id] ?? ['state' => $account->is_active ? 'active' : 'inactive', 'expiresAt' => null, 'daysLeft' => null];
                                            @endphp
                                            <div class="flex flex-col items-start gap-1">
                                                @if($rowStatus['state'] === 'expired')
                                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest bg-red-50 text-red-600">
                                                        <i class="ph ph-user-x text-[9px]"></i> Expired
                                                    </span>
                                                @elseif($rowStatus['state'] === 'expiring')
                                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest bg-amber-50 text-amber-700">
                                                        <i class="ph ph-hourglass text-[9px]"></i> Will be inactive in {{ $rowStatus['daysLeft'] }} day{{ $rowStatus['daysLeft'] === 1 ? '' : 's' }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest {{ $rowStatus['state'] === 'active' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600' }}">
                                                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span> {{ $rowStatus['state'] === 'active' ? 'Active' : 'Inactive' }}
                                                    </span>
                                                @endif
                                                @if($rowStatus['expiresAt'])
                                                    <span class="text-[9px] text-gray-400 font-bold uppercase tracking-wider">Expires {{ $rowStatus['expiresAt']->format('M d, Y') }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                @if((int) $account->id !== (int) auth()->id())
                                                    <form method="POST" action="{{ route('osas.accounts.lifecycle', $account) }}" class="inline">
                                                        @csrf
                                                        @method('PUT')
                                                        <input type="hidden" name="action" value="{{ $account->is_active ? 'deactivate' : 'activate' }}">
                                                        <input type="hidden" name="freshness" value="{{ $account->management_version }}">
                                                        <button type="submit" class="inline-flex items-center justify-center px-3 py-1.5 rounded text-[10px] font-bold {{ $account->is_active ? 'bg-amber-50 text-amber-700 hover:bg-amber-100' : 'bg-green-50 text-green-700 hover:bg-green-100' }}" title="{{ $account->is_active ? 'Deactivate' : 'Reactivate' }}">
                                                            <i class="ph {{ $account->is_active ? 'ph-pause-circle' : 'ph-power' }} mr-1"></i> {{ $account->is_active ? 'Deactivate' : 'Activate' }}
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="text-[10px] text-gray-400 font-bold">—</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-5 py-12 text-center text-gray-400">
                                            <div class="flex flex-col items-center justify-center">
                                                <i class="ph ph-user-x text-3xl mb-3 text-gray-300"></i>
                                                <p class="text-[11px] font-black uppercase tracking-widest">No accounts found</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($accounts->hasPages())
                        <div class="px-5 py-3 border-t border-gray-50 bg-gray-50/30">
                            {{ $accounts->appends(array_merge(request()->except(['fragment']), ['tab' => 'manage-accounts']))->links() }}
                        </div>
                    @endif
                </div>
            </div>

            <!-- Dean Setup: one Dean per college -->
            <div id="content-dean-setup" class="settings-content {{ $activeTab === 'dean-setup' ? '' : 'hidden' }}">
                <div class="bg-white rounded-lg border border-gray-100 p-4 mb-3 flex flex-col sm:flex-row sm:items-center gap-2">
                    <div class="flex-1">
                        <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest flex items-center gap-2">
                            <i class="ph ph-graduation-cap text-amber-600"></i> Deans per College
                        </h3>
                        <p class="text-[11px] text-gray-500 mt-1">Each college has exactly one Dean. Apply/Renew forms autofill the assigned Dean — no Dean input needed there. To replace a Dean, edit their name and details in place, or activate an existing inactive Dean.</p>
                    </div>
                </div>

                <div class="bg-white rounded-lg border border-gray-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm table-fixed">
                            <thead>
                                <tr class="bg-gray-50/50 border-b border-gray-50">
                                    <th class="px-3 py-2 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest w-[26%]">College</th>
                                    <th class="px-3 py-2 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest w-[38%]">Assigned Dean</th>
                                    <th class="px-3 py-2 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest w-[12%]">Status</th>
                                    <th class="px-3 py-2 text-right text-[9px] font-black text-gray-400 uppercase tracking-widest w-[24%]">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($departments as $dept)
                                    @php $dean = ($collegeDeans ?? collect())->get($dept->id); @endphp
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-3 py-2">
                                            <div class="text-xs font-bold text-gray-900 truncate" title="{{ $dept->department_name }}">{{ $dept->department_acronym ? $dept->department_acronym.' — ' : '' }}{{ $dept->department_name }}</div>
                                        </td>
                                        <td class="px-3 py-2">
                                            @if($dean)
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center font-black text-xs flex-shrink-0">
                                                        {{ substr($dean->first_name, 0, 1) }}{{ substr($dean->last_name, 0, 1) }}
                                                    </div>
                                                    <div class="min-w-0">
                                                        <div class="text-xs font-bold text-gray-900 truncate">{{ $dean->full_name }} <span class="font-black text-gray-400">· {{ $dean->student_id ?? 'No ID' }}</span></div>
                                                        <div class="text-[11px] text-gray-500 truncate">{{ $dean->email }}</div>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-[10px] text-gray-400 font-bold">— No Dean assigned</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            @if($dean)
                                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest {{ $dean->is_active ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600' }}">
                                                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span> {{ $dean->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest bg-amber-50 text-amber-700">
                                                    Vacant
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                @if($dean)
                                                    <a href="{{ route('osas.accounts.edit', $dean) }}" class="inline-flex items-center justify-center px-3 py-1.5 rounded bg-gray-50 text-gray-600 hover:bg-[#26488C] hover:text-white transition-colors text-[10px] font-bold whitespace-nowrap" title="Update name and details">
                                                        <i class="ph ph-pencil-simple mr-1"></i> Edit
                                                    </a>
                                                    <form method="POST" action="{{ route('osas.accounts.lifecycle', $dean) }}" class="inline">
                                                        @csrf
                                                        @method('PUT')
                                                        <input type="hidden" name="action" value="{{ $dean->is_active ? 'deactivate' : 'activate' }}">
                                                        <input type="hidden" name="freshness" value="{{ $dean->management_version }}">
                                                        <button type="submit" class="inline-flex items-center justify-center px-3 py-1.5 rounded text-[10px] font-bold whitespace-nowrap {{ $dean->is_active ? 'bg-amber-50 text-amber-700 hover:bg-amber-100' : 'bg-green-50 text-green-700 hover:bg-green-100' }}" title="{{ $dean->is_active ? 'Deactivate' : 'Reactivate' }}">
                                                            {{ $dean->is_active ? 'Deactivate' : 'Activate' }}
                                                        </button>
                                                    </form>
                                                @else
                                                    <button type="button" onclick="openCreateAccountModal('super_admin', {{ $dept->id }})" class="inline-flex items-center justify-center px-3 py-1.5 rounded bg-amber-600 text-white hover:bg-amber-700 transition-colors text-[10px] font-bold whitespace-nowrap" title="Assign the Dean of {{ $dept->department_name }}">
                                                        <i class="ph ph-plus mr-1"></i> Set Dean
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div id="edit-profile-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 modal-backdrop flex items-start sm:items-center justify-center z-50 overflow-y-auto transition-opacity duration-300 opacity-0 p-3 sm:p-4">
    <div id="edit-profile-modal-content" class="bg-white rounded-lg shadow-xl p-5 sm:p-6 max-w-lg w-full my-4 sm:my-8 transform transition-all duration-300 scale-95">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-black text-gray-900">Edit Profile</h2>
            <button onclick="closeEditProfileModal()" class="text-gray-400 hover:text-gray-600">
                <i class="ph ph-x text-lg"></i>
            </button>
        </div>

        <form id="editProfileForm">
            @csrf
            <input type="hidden" name="email" value="{{ $user->email }}">

            <div class="mb-4">
                <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                    <i class="ph ph-user-circle text-[#C4973B]"></i>
                    Personal Information
                </h3>
                <div class="bg-gray-50 rounded-lg border border-gray-100 p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="modal_first_name" class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">First Name</label>
                            <input type="text" name="first_name" id="modal_first_name" value="{{ old('first_name', $user->first_name ?? '') }}" required
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all">
                        </div>
                        <div>
                            <label for="modal_last_name" class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Last Name</label>
                            <input type="text" name="last_name" id="modal_last_name" value="{{ old('last_name', $user->last_name ?? '') }}" required
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="closeEditProfileModal()"
                    class="flex-1 px-4 py-2.5 border border-gray-200 text-gray-700 font-black text-xs uppercase tracking-widest rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" id="modalSubmitBtn" class="flex-1 px-4 py-2.5 text-white font-black text-xs uppercase tracking-widest rounded-lg bg-[#26488C] hover:bg-[#1a3461] transition">
                    <i class="ph ph-floppy-disk mr-1.5"></i>Update Profile
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Create Account Modal -->
<div id="create-account-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 modal-backdrop flex items-start sm:items-center justify-center z-50 overflow-y-auto transition-opacity duration-300 opacity-0 p-3 sm:p-4">
    <div id="create-account-modal-content" class="bg-white rounded-lg shadow-xl p-5 sm:p-6 max-w-2xl w-full my-4 sm:my-8 transform transition-all duration-300 scale-95">
        <div class="flex justify-between items-center mb-4">
            <div>
                <h2 id="create-account-title" class="text-lg font-black text-gray-900">New OSAS Admin</h2>
                <p id="create-account-subtitle" class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Add another OSAS admin, or set the Dean of a college</p>
            </div>
            <button onclick="closeCreateAccountModal()" class="text-gray-400 hover:text-gray-600">
                <i class="ph ph-x text-lg"></i>
            </button>
        </div>

        <form id="createAccountForm">
            @csrf

            <!-- Name Fields -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" required class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" required class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                <div class="sm:col-span-2">
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Middle Name</label>
                    <input type="text" name="middle_name" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Suffix</label>
                    <input type="text" name="suffix" placeholder="e.g. Jr, III" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all">
                </div>
            </div>

            <div class="h-px bg-gray-100 my-4"></div>

            <!-- Credentials -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" required autocomplete="off" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Student/Employee ID</label>
                    <input type="text" name="student_id" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">System Role <span class="text-red-500">*</span></label>
                    <select name="role" id="create_account_role" required onchange="toggleCreateAccountCollege()" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all">
                        <option value="osas_admin">OSAS Admin</option>
                        <option value="super_admin">Dean (per College)</option>
                    </select>
                    <p class="mt-1 text-[9px] text-gray-500 font-bold uppercase tracking-wider">Dean accounts are tied to one college and autofill Apply/Renew.</p>
                </div>
                <div id="create-account-college-field" style="display: none;">
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">College <span class="text-red-500">*</span></label>
                    <select name="department_id" id="create_account_department" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all">
                        <option value="">Select college...</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->department_name }}{{ $dept->department_acronym ? ' ('.$dept->department_acronym.')' : '' }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password" required autocomplete="new-password" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Confirm Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password_confirmation" required autocomplete="new-password" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all">
                </div>
            </div>

            <div id="create-account-errors" class="hidden mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700"></div>

            <div class="flex gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeCreateAccountModal()"
                    class="flex-1 px-4 py-2.5 border border-gray-200 text-gray-700 font-black text-xs uppercase tracking-widest rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" id="createAccountSubmitBtn" class="flex-1 px-4 py-2.5 text-white font-black text-xs uppercase tracking-widest rounded-lg bg-[#26488C] hover:bg-[#1a3461] transition">
                    <i class="ph ph-floppy-disk mr-1.5"></i>Create OSAS Admin
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.settings-tab {
    color: #6b7280;
}
.settings-tab:hover {
    background-color: rgba(38, 72, 140, 0.05);
    color: #26488C;
}
.settings-tab.active {
    background-color: rgba(38, 72, 140, 0.08);
    color: #26488C;
    font-weight: 800;
}
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}
.shake { animation: shake 0.4s; }
@media (max-width: 639px) {
    .mobile-profile-row {
        flex-direction: row !important;
        align-items: flex-start !important;
        margin-top: -1.5rem !important;
    }
    .mobile-profile-row .min-w-0 {
        margin-left: 0.75rem !important;
        margin-top: 0 !important;
        overflow: visible !important;
    }
    .mobile-profile-row .min-w-0 #profile-name {
        font-size: 1.125rem !important;
        white-space: normal !important;
    }
    .mobile-profile-row .min-w-0 p {
        font-size: 0.75rem !important;
    }
}
</style>

<script>
function showTab(tab, ev) {
    if (ev && typeof ev.preventDefault === 'function') {
        ev.preventDefault();
    } else if (typeof window.event !== 'undefined' && window.event && typeof window.event.preventDefault === 'function') {
        window.event.preventDefault();
    }

    document.querySelectorAll('.settings-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.settings-tab').forEach(el => el.classList.remove('active'));

    const content = document.getElementById('content-' + tab);
    const tabEl = document.getElementById('tab-' + tab);
    if (content) content.classList.remove('hidden');
    if (tabEl) tabEl.classList.add('active');

    // Update URL query / hash without page reload
    if (history.replaceState) {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        if (tab !== 'manage-accounts' && tab !== 'dean-setup') {
            url.searchParams.delete('page');
            url.searchParams.delete('q');
            url.searchParams.delete('role');
            url.searchParams.delete('state');
            url.searchParams.delete('validity');
        }
        url.hash = tab;
        history.replaceState(null, null, url.toString());
    }
}
window.showTab = showTab;

function timeAgo(date) {
    if (!date) return 'Never';
    const now = new Date();
    const pastDate = new Date(date);
    const diffInSeconds = Math.floor((now - pastDate) / 1000);
    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) {
        const minutes = Math.floor(diffInSeconds / 60);
        return minutes === 1 ? '1 minute ago' : `${minutes} minutes ago`;
    }
    if (diffInSeconds < 86400) {
        const hours = Math.floor(diffInSeconds / 3600);
        return hours === 1 ? '1 hour ago' : `${hours} hours ago`;
    }
    if (diffInSeconds < 2592000) {
        const days = Math.floor(diffInSeconds / 86400);
        return days === 1 ? '1 day ago' : `${days} days ago`;
    }
    if (diffInSeconds < 31536000) {
        const months = Math.floor(diffInSeconds / 2592000);
        return months === 1 ? '1 month ago' : `${months} months ago`;
    }
    const years = Math.floor(diffInSeconds / 31536000);
    return years === 1 ? '1 year ago' : `${years} years ago`;
}
window.timeAgo = timeAgo;

function initSettingsTabs() {
    // Clear any legacy localStorage tab persistence so settings always defaults to the first tab (Profile)
    try {
        localStorage.removeItem('osasSettingsTab');
    } catch (e) {}

    const nameEl = document.getElementById('profile-name');
    if (nameEl) {
        nameEl.style.color = '#ffffff';
        nameEl.style.textShadow = '0 2px 4px rgba(0,0,0,0.5)';
    }
    const lastChangedElement = document.getElementById('last-changed-time');
    if (lastChangedElement) {
        const lastChangedDate = lastChangedElement.dataset.lastChanged || null;
        lastChangedElement.textContent = 'Last changed: ' + timeAgo(lastChangedDate);
    }

    const urlParams = new URLSearchParams(window.location.search);
    const tabFromUrl = urlParams.get('tab');
    const tabFromHash = window.location.hash.replace('#', '');
    let targetTab = 'profile';

    if (tabFromHash && ['profile', 'audit-logs', 'manage-accounts', 'dean-setup', 'accreditation'].includes(tabFromHash) && document.getElementById('content-' + tabFromHash)) {
        targetTab = tabFromHash;
    } else if (tabFromUrl && ['profile', 'audit-logs', 'manage-accounts', 'dean-setup', 'account-management', 'accreditation'].includes(tabFromUrl)) {
        // Legacy 'account-management' maps to Manage Accounts.
        targetTab = tabFromUrl === 'account-management' ? 'manage-accounts' : tabFromUrl;
        if (!document.getElementById('content-' + targetTab)) targetTab = 'profile';
    } else if (!tabFromUrl && (urlParams.has('page') || urlParams.has('q') || urlParams.has('role') || urlParams.has('state') || urlParams.has('validity'))) {
        targetTab = 'manage-accounts';
    } else {
        targetTab = 'profile';
    }

    if (targetTab && document.getElementById('content-' + targetTab)) {
        showTab(targetTab);
    }
}
window.initSettingsTabs = initSettingsTabs;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSettingsTabs);
} else {
    initSettingsTabs();
}

function openEditProfileModal() {
    const modal = document.getElementById('edit-profile-modal');
    const modalContent = document.getElementById('edit-profile-modal-content');
    if (!modal) return;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modal.classList.add('opacity-100');
        if (modalContent) {
            modalContent.classList.remove('scale-95');
            modalContent.classList.add('scale-100');
        }
    }, 10);
}
window.openEditProfileModal = openEditProfileModal;

function closeEditProfileModal() {
    const modal = document.getElementById('edit-profile-modal');
    const modalContent = document.getElementById('edit-profile-modal-content');
    if (!modal) return;
    modal.classList.remove('opacity-100');
    modal.classList.add('opacity-0');
    if (modalContent) {
        modalContent.classList.remove('scale-100');
        modalContent.classList.add('scale-95');
    }
    document.body.style.overflow = '';
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}
window.closeEditProfileModal = closeEditProfileModal;

function openStartRenewalModal() {
    const modal = document.getElementById('start-renewal-confirm-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modal.classList.add('opacity-100');
    }, 10);
}
window.openStartRenewalModal = openStartRenewalModal;

function closeStartRenewalModal() {
    const modal = document.getElementById('start-renewal-confirm-modal');
    if (!modal) return;
    modal.classList.remove('opacity-100');
    modal.classList.add('opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}
window.closeStartRenewalModal = closeStartRenewalModal;

function openApplyAccreditationModal() {
    const modal = document.getElementById('apply-accreditation-confirm-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modal.classList.add('opacity-100');
    }, 10);
}
window.openApplyAccreditationModal = openApplyAccreditationModal;

function closeApplyAccreditationModal() {
    const modal = document.getElementById('apply-accreditation-confirm-modal');
    if (!modal) return;
    modal.classList.remove('opacity-100');
    modal.classList.add('opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}
window.closeApplyAccreditationModal = closeApplyAccreditationModal;

async function confirmApplyAccreditationPeriod() {
    const form = document.getElementById('accreditationPeriodForm');
    const btn = document.getElementById('apply-accreditation-confirm-btn');
    const orig = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i> Applying...'; }
    const formData = new FormData(form);
    try {
        const response = await fetch('{{ route("osas.settings.accreditation.update") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }
        });
        const data = await response.json();
        if (data.success) {
            showToast(data.message, 'success');
            const label = document.getElementById('current-period-label');
            const count = document.getElementById('current-period-count');
            if (label && data.start_date && data.end_date) {
                const start = new Date(data.start_date + 'T00:00:00');
                const end = new Date(data.end_date + 'T00:00:00');
                const fmt = (d) => d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
                label.textContent = `${fmt(start)} - ${fmt(end)}`;
            }
            if (count) {
                count.textContent = `Applies to ${data.affected} approved organization(s).`;
            }
        } else {
            const errors = data.errors ? Object.values(data.errors).flat().join(' ') : '';
            showToast(errors || data.message || 'Failed to set accreditation period', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('An error occurred. Please try again.', 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
    }
}
window.confirmApplyAccreditationPeriod = confirmApplyAccreditationPeriod;

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && document.getElementById('edit-profile-modal') && !document.getElementById('edit-profile-modal').classList.contains('hidden')) {
        closeEditProfileModal();
    }
    if (e.key === 'Escape' && document.getElementById('start-renewal-confirm-modal') && !document.getElementById('start-renewal-confirm-modal').classList.contains('hidden')) {
        closeStartRenewalModal();
    }
    if (e.key === 'Escape' && document.getElementById('apply-accreditation-confirm-modal') && !document.getElementById('apply-accreditation-confirm-modal').classList.contains('hidden')) {
        closeApplyAccreditationModal();
    }
});

// Edit Profile Form
const editProfileForm = document.getElementById('editProfileForm');
if (editProfileForm) {
    editProfileForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const submitBtn = document.getElementById('modalSubmitBtn');
        const originalText = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i>Updating...';
        }

        try {
            const response = await fetch('{{ route("osas.settings.profile.update") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                showToast(data.message, 'success');
                closeEditProfileModal();
            } else {
                showToast(data.message || 'Failed to update profile', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('An error occurred. Please try again.', 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
    });
}

// Password Form
const passwordForm = document.getElementById('passwordForm');
if (passwordForm) {
    passwordForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const button = e.target.querySelector('button[type="submit"]');
        const originalText = button ? button.innerHTML : '';
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i> Updating...';
        }

        try {
            const response = await fetch('{{ route("osas.settings.password.update") }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await response.json();
            if (data.success) {
                showToast(data.message, 'success');
                e.target.reset();
            } else {
                showToast(data.message || 'Failed to update password', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('An error occurred. Please try again.', 'error');
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalText;
            }
        }
    });
}

async function startRenewalFromAccreditation() {
    const btn = document.getElementById('start-renewal-accreditation-btn');
    const confirmBtn = document.getElementById('start-renewal-confirm-btn');
    // Debounce: block double-click / two-tab race before button paints
    if ((btn && btn.disabled) || (confirmBtn && confirmBtn.disabled)) return;
    const orig = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i> Starting...'; }
    if (confirmBtn) { confirmBtn.disabled = true; confirmBtn.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i> Starting...'; }
    try {
        const res = await fetch('{{ route("osas.apply-org.generate-renewals") }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });
        let data = null;
        const ct = res.headers.get('content-type') || '';
        if (ct.includes('application/json')) {
            data = await res.json();
        } else {
            const txt = await res.text();
            try { data = JSON.parse(txt); } catch(e){ data = { success: res.ok, message: txt.slice(0,200) }; }
        }
        if (!res.ok || !data.success) {
            const msg = (data && data.message) ? data.message : 'Failed to start renewal.';
            if (typeof window.showToast === 'function') window.showToast(msg, 'error'); else alert(msg);
        } else {
            if (typeof window.showToast === 'function') window.showToast(data.message, 'success'); else alert(data.message);
            // reload after toast visible
            setTimeout(() => window.location.reload(), 800);
        }
    } catch (err) {
        console.error(err);
        const msg = 'Could not start renewal. Please try again.';
        if (typeof window.showToast === 'function') window.showToast(msg, 'error'); else alert(msg);
    }
    finally {
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
        if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.innerHTML = 'Confirm'; }
    }
}
window.startRenewalFromAccreditation = startRenewalFromAccreditation;

// Accreditation Period Form — destructive: require confirmation modal
const accreditationPeriodForm = document.getElementById('accreditationPeriodForm');
if (accreditationPeriodForm) {
    accreditationPeriodForm.addEventListener('submit', (e) => {
        e.preventDefault();
        openApplyAccreditationModal();
    });
}

// Photo upload
let selectedPhotoFile = null;
const defaultPhotoUrl = '{{ $user->photo ? storage_url($user->photo) : "" }}';

function onPhotoSelect(event) {
    const file = event.target.files[0];
    if (!file) return;
    selectedPhotoFile = file;
    const avatar = document.getElementById('profile-avatar');
    const reader = new FileReader();
    reader.onload = function(e) {
        if (avatar.tagName === 'IMG') {
            avatar.src = e.target.result;
        } else {
            const newImg = document.createElement('img');
            newImg.id = 'profile-avatar';
            newImg.src = e.target.result;
            newImg.alt = 'Preview';
            newImg.className = avatar.className;
            avatar.parentNode.replaceChild(newImg, avatar);
        }
    };
    reader.readAsDataURL(file);
    const photoActions = document.getElementById('photo-actions');
    if (photoActions) photoActions.classList.remove('hidden');
}
window.onPhotoSelect = onPhotoSelect;

function savePhoto() {
    if (!selectedPhotoFile) return;
    const form = document.getElementById('photo-form');
    if (!form) return;
    const formData = new FormData(form);
    formData.set('photo', selectedPhotoFile);
    const saveBtn = document.querySelector('#photo-actions button:first-child');
    const originalText = saveBtn ? saveBtn.innerHTML : '';
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i>Saving...';
    }
    fetch('{{ route("osas.settings.photo.update") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const photoActions = document.getElementById('photo-actions');
            if (photoActions) photoActions.classList.add('hidden');
            selectedPhotoFile = null;
            const photoInput = document.getElementById('photo-input');
            if (photoInput) photoInput.value = '';
            const avatar = document.getElementById('profile-avatar');
            if (avatar && avatar.tagName === 'IMG') {
                avatar.src = data.photo_url + '?t=' + Date.now();
            }
            const navWrappers = document.querySelectorAll('.nav-avatar-wrapper');
            navWrappers.forEach(wrapper => {
                const newUrl = data.photo_url + '?t=' + Date.now();
                wrapper.innerHTML = `<img src="${newUrl}" alt="" class="h-10 w-10 rounded-lg object-cover border-2 border-white shadow-sm">`;
            });
            showToast(data.message || 'Profile photo updated!', 'success');
        } else {
            showToast(data.message || 'Failed to update photo.', 'error');
        }
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        }
    })
    .catch(() => {
        showToast('An error occurred. Please try again.', 'error');
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        }
    });
}
window.savePhoto = savePhoto;

function cancelPhoto() {
    selectedPhotoFile = null;
    const photoInput = document.getElementById('photo-input');
    if (photoInput) photoInput.value = '';
    const photoActions = document.getElementById('photo-actions');
    if (photoActions) photoActions.classList.add('hidden');
    const avatar = document.getElementById('profile-avatar');
    if (!avatar) return;
    if (defaultPhotoUrl) {
        avatar.src = defaultPhotoUrl + '?t=' + Date.now();
    } else {
        const wrapper = document.createElement('div');
        wrapper.id = 'profile-avatar';
        wrapper.className = 'w-20 h-20 sm:w-24 sm:h-24 rounded-lg border-4 border-white shadow-md flex items-center justify-center bg-blue-50';
        wrapper.innerHTML = '<i class="ph ph-user text-3xl sm:text-4xl text-[#26488C]"></i>';
        avatar.parentNode.replaceChild(wrapper, avatar);
    }
}
window.cancelPhoto = cancelPhoto;

// Account Management functions
let accountFilterTimer;
function debouncedAccountFilter() {
    clearTimeout(accountFilterTimer);
    accountFilterTimer = setTimeout(() => submitAccountFilterForm(), 300);
}
window.debouncedAccountFilter = debouncedAccountFilter;

function submitAccountFilterForm() {
    const form = document.getElementById('account-filter-form');
    if (!form) return;
    form.action = '{{ route("osas.settings") }}';
    form.querySelectorAll('input, select').forEach(el => {
        if (!el.value && el.name !== 'tab') el.disabled = true;
    });
    form.submit();
}
window.submitAccountFilterForm = submitAccountFilterForm;

function openCreateAccountModal(role = 'osas_admin', departmentId = null) {
    const modal = document.getElementById('create-account-modal');
    const modalContent = document.getElementById('create-account-modal-content');
    if (!modal) return;
    const roleSelect = document.getElementById('create_account_role');
    if (roleSelect && (role === 'osas_admin' || role === 'super_admin')) {
        roleSelect.value = role;
    }
    const deptSelect = document.getElementById('create_account_department');
    if (deptSelect && departmentId) {
        deptSelect.value = String(departmentId);
    }
    toggleCreateAccountCollege();
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modal.classList.add('opacity-100');
        if (modalContent) {
            modalContent.classList.remove('scale-95');
            modalContent.classList.add('scale-100');
        }
    }, 10);
}
window.openCreateAccountModal = openCreateAccountModal;

function toggleCreateAccountCollege() {
    const role = document.getElementById('create_account_role')?.value || 'osas_admin';
    const isDean = role === 'super_admin';
    const collegeField = document.getElementById('create-account-college-field');
    const dept = document.getElementById('create_account_department');
    const title = document.getElementById('create-account-title');
    const submitBtn = document.getElementById('createAccountSubmitBtn');
    if (collegeField) collegeField.style.display = isDean ? '' : 'none';
    if (dept) dept.required = isDean;
    if (title) title.textContent = isDean ? 'New Dean' : 'New OSAS Admin';
    if (submitBtn) submitBtn.innerHTML = isDean
        ? '<i class="ph ph-graduation-cap mr-1.5"></i>Create Dean'
        : '<i class="ph ph-floppy-disk mr-1.5"></i>Create OSAS Admin';
}
window.toggleCreateAccountCollege = toggleCreateAccountCollege;

function closeCreateAccountModal() {
    const modal = document.getElementById('create-account-modal');
    const modalContent = document.getElementById('create-account-modal-content');
    if (!modal) return;
    modal.classList.remove('opacity-100');
    modal.classList.add('opacity-0');
    if (modalContent) {
        modalContent.classList.remove('scale-100');
        modalContent.classList.add('scale-95');
    }
    document.body.style.overflow = '';
    setTimeout(() => {
        modal.classList.add('hidden');
        const form = document.getElementById('createAccountForm');
        if (form) form.reset();
        toggleCreateAccountCollege();
        const errEl = document.getElementById('create-account-errors');
        if (errEl) errEl.classList.add('hidden');
    }, 300);
}
window.closeCreateAccountModal = closeCreateAccountModal;

// Create Account Form AJAX
const createAccountForm = document.getElementById('createAccountForm');
if (createAccountForm) {
    createAccountForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const submitBtn = document.getElementById('createAccountSubmitBtn');
        const errorsEl = document.getElementById('create-account-errors');
        const originalText = submitBtn ? submitBtn.innerHTML : '';

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i>Creating...';
        }
        if (errorsEl) errorsEl.classList.add('hidden');

        try {
            const response = await fetch('{{ route("osas.accounts.store") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: formData
            });

            const data = await response.json();

            if (response.ok && data.success !== false) {
                closeCreateAccountModal();
                showToast(data.message || 'Account created successfully!', 'success');
                setTimeout(() => window.location.reload(), 500);
            } else {
                let errorHtml = '';
                if (data.errors) {
                    errorHtml = Object.values(data.errors).flat().map(err => `<p>${err}</p>`).join('');
                } else {
                    errorHtml = `<p>${data.message || 'Failed to create account.'}</p>`;
                }
                if (errorsEl) {
                    errorsEl.innerHTML = errorHtml;
                    errorsEl.classList.remove('hidden');
                }
            }
        } catch (error) {
            console.error('Error:', error);
            if (errorsEl) {
                errorsEl.innerHTML = '<p>An error occurred. Please try again.</p>';
                errorsEl.classList.remove('hidden');
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
    });
}

// Destructive: Deactivate/Activate requires confirmation (uses global confirmAction modal)
document.querySelectorAll('form[action*="accounts/lifecycle"]').forEach(form => {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const actionInput = form.querySelector('input[name="action"]');
        const isDeactivate = actionInput && actionInput.value === 'deactivate';
        const title = isDeactivate ? 'Deactivate Account?' : 'Reactivate Account?';
        const message = isDeactivate
            ? 'This will deactivate the account and revoke its access. The user will be unable to log in until reactivated.'
            : 'This will reactivate the account and restore access.';
        const confirmed = await window.confirmAction(message, title);
        if (!confirmed) return;
        // Prevent double-submit
        const btn = form.querySelector('button[type="submit"]');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i> Processing...'; }
        form.submit();
    });
});

// Close create account modal on ESC
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('create-account-modal');
        if (modal && !modal.classList.contains('hidden')) {
            closeCreateAccountModal();
        }
    }
});
</script>
@endsection
