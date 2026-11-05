@extends((request()->boolean('fragment') && request()->ajax()) ? 'layouts.fragment' : 'layouts.osas')

@section('title', $organization->name . ' - Profile')

@php
    // Registry display order: Dean -> Advisers -> Officers -> COMELEC -> Members
    $rolePriority = static function (string $role): int {
        $role = strtolower(trim($role));
        if ($role === 'dean') {
            return 1;
        }
        if (str_contains($role, 'adviser')) {
            return 2;
        }
        if (in_array($role, ['comelec', 'comlec'])) {
            return 4;
        }
        if ($role === '' || $role === 'member') {
            return 5;
        }
        return 3;
    };

    // Officer roster rank: President first, down to Event Coordinator, representatives last.
    $officerRank = static function (string $role): int {
        $role = strtolower(trim($role));
        $ranks = [
            'president' => 1,
            'vice president' => 2,
            'secretary' => 3,
            'treasurer' => 4,
            'auditor' => 5,
            'business manager' => 6,
            'public information officer' => 7,
            'pio' => 7,
            'event coordinator' => 8,
        ];
        if (isset($ranks[$role])) {
            return $ranks[$role];
        }
        if (str_contains($role, 'representative')) {
            $year = (int) preg_replace('/[^0-9]/', '', $role);
            return 20 + ($year > 0 && $year <= 4 ? $year : 0);
        }
        return 15;
    };

    $members = $organization->users
        ->sortBy(fn ($user) => ($rolePriority((string) $user->pivot->role) * 100) + $officerRank((string) $user->pivot->role))
        ->values();
    $verifiedCount = $members->filter(fn ($user) => $user->pivot->membership_status === 'Active')->count();
    $pendingCount = $members->count() - $verifiedCount;
    $officerPool = $members->filter(function ($user) {
        if ($user->role !== 'admin') {
            return false;
        }
        $orgRole = strtolower(trim((string) $user->pivot->role));
        return ! in_array($orgRole, ['dean', 'comelec', 'comlec']);
    })
        ->sortBy(fn ($user) => $officerRank((string) $user->pivot->role))
        ->values();
    $officers = $officerPool->take(5);
    $department = $organization->loadMissing('department')->department;
    $accStatus = $organization->accreditationStatus(); // none | expired | expiring | active
    $days = $organization->daysUntilExpiration(); // ?int
    $hasLogo = $organization->settings && $organization->settings->getRawOriginal('logo_url');
@endphp

@section('content')
<div class="container-fluid px-4 lg:px-6 py-4">

    <!-- Compact Header -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-3 min-w-0">
            <a href="{{ route('osas.organizations.index') }}" data-osas-navigation
               class="h-9 w-9 shrink-0 rounded-lg bg-white border border-gray-100 shadow-sm flex items-center justify-center text-gray-400 hover:text-[#26488C] hover:border-[#26488C]/30 transition-all">
                <i class="ph ph-arrow-left"></i>
            </a>
            @if($hasLogo)
                <div class="h-11 w-11 shrink-0 rounded-lg bg-white border border-gray-100 flex items-center justify-center overflow-hidden shadow-sm">
                    <img src="{{ $organization->settings->logo_url }}" alt="{{ $organization->name }} Logo" class="w-full h-full object-cover">
                </div>
            @else
                <div class="h-11 w-11 shrink-0 rounded-lg bg-gradient-to-br from-[#26488C] to-[#1a3461] flex items-center justify-center text-white font-black text-base shadow-sm">
                    {{ substr($organization->name, 0, 1) }}
                </div>
            @endif
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-lg lg:text-xl font-extrabold text-gray-900 tracking-tight truncate">{{ $organization->name }}</h1>
                    @if($organization->isArchived())
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 text-amber-700 text-[9px] font-black uppercase tracking-widest rounded-full border border-amber-100">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> Archived
                        </span>
                    @elseif($organization->hasPendingRenewal())
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 text-amber-700 text-[9px] font-black uppercase tracking-widest rounded-full border border-amber-100">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> For Renewal
                        </span>
                    @elseif($organization->isExpired())
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-red-50 text-red-700 text-[9px] font-black uppercase tracking-widest rounded-full border border-red-100">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Expired
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-green-50 text-green-700 text-[9px] font-black uppercase tracking-widest rounded-full border border-green-100">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Active
                        </span>
                    @endif
                </div>
                <p class="text-[10px] font-black uppercase tracking-widest mt-0.5 truncate">
                    <span class="text-[#C4973B]">{{ $organization->org_acronym ?? 'Official Organization' }}</span>
                    <span class="text-gray-300 mx-1.5">•</span>
                    <span class="text-gray-400">{{ $organization->type }}</span>
                    @if($organization->accreditationPeriodLabel())
                        <span class="text-gray-300 mx-1.5">•</span>
                        <span class="text-gray-400">{{ $organization->accreditationPeriodLabel() }}</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('osas.reports.organization.pdf', $organization) }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-gray-200 text-gray-700 font-black text-[10px] uppercase tracking-widest rounded-lg hover:bg-gray-50 transition-all shadow-sm">
                <i class="ph ph-file-pdf text-red-500"></i> Export PDF
            </a>
            <button onclick="toggleStatus()"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 {{ $organization->isArchived() ? 'bg-amber-50 text-amber-600 border-amber-200' : 'bg-red-50 text-red-600 border-red-200' }} border font-black text-[10px] uppercase tracking-widest rounded-lg hover:scale-[1.03] transition-all shadow-sm">
                <i class="ph {{ $organization->isArchived() ? 'ph-archive-box' : 'ph-archive' }}"></i>
                {{ $organization->isArchived() ? 'Unarchive Org' : 'Archive Org' }}
            </button>
        </div>
    </div>

    <!-- Master-Detail Layout -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-start">

        <!-- Member Registry -->
        <div class="xl:col-span-8 bg-white rounded-lg border border-gray-100 overflow-hidden">
            <div class="px-4 py-2.5 border-b border-gray-50 bg-gray-50/40 flex flex-col sm:flex-row sm:items-center gap-2 sm:justify-between">
                <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest flex items-center gap-2">
                    <i class="ph ph-users text-[#26488C]"></i> Member Registry
                    <span class="text-[10px] font-black text-gray-400">{{ $members->count() }} records</span>
                </h3>
                <div class="flex items-center gap-2 flex-wrap">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                            <i class="ph ph-magnifying-glass text-gray-400 text-[10px]"></i>
                        </div>
                        <input type="text" id="member-search" placeholder="Search member..."
                               class="block w-40 pl-7 pr-2 py-1.5 border border-gray-200 rounded-md text-[10px] placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all bg-white">
                    </div>
                    <div class="flex items-center gap-1" data-member-filters>
                        <button type="button" data-member-filter="all" class="member-filter-btn px-2.5 py-1.5 rounded-md text-[9px] font-black uppercase tracking-widest bg-[#26488C] text-white transition-all">All {{ $members->count() }}</button>
                        <button type="button" data-member-filter="verified" class="member-filter-btn px-2.5 py-1.5 rounded-md text-[9px] font-black uppercase tracking-widest bg-white border border-gray-200 text-gray-500 hover:border-[#26488C]/30 transition-all">Verified {{ $verifiedCount }}</button>
                        <button type="button" data-member-filter="pending" class="member-filter-btn px-2.5 py-1.5 rounded-md text-[9px] font-black uppercase tracking-widest bg-white border border-gray-200 text-gray-500 hover:border-[#26488C]/30 transition-all">Pending {{ $pendingCount }}</button>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50/50 text-left border-b border-gray-50">
                            <th class="px-4 py-2.5 text-[9px] font-black text-gray-400 uppercase tracking-widest">Member</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-gray-400 uppercase tracking-widest">Org Role</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-gray-400 uppercase tracking-widest">Course & Year</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-gray-400 uppercase tracking-widest">System Access</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-gray-400 uppercase tracking-widest">Joined</th>
                            <th class="px-4 py-2.5 text-[9px] font-black text-gray-400 uppercase tracking-widest text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($members as $user)
                        <tr class="hover:bg-blue-50/30 transition-colors"
                            data-member-row
                            data-member-status="{{ $user->pivot->membership_status === 'Active' ? 'verified' : 'pending' }}"
                            data-member-name="{{ strtolower($user->full_name) }}">
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-gray-100 text-gray-500 flex items-center justify-center font-black text-[9px] flex-shrink-0">
                                        {{ substr($user->first_name, 0, 1) }}{{ substr($user->last_name, 0, 1) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-xs font-bold text-gray-900 leading-none mb-0.5 truncate max-w-[200px]">{{ $user->full_name }}</div>
                                        <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest">{{ $user->student_id ?? 'GUEST' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                @php
                                    $role = \App\Models\OrganizationUser::formatDisplayLabel($user->pivot->role ?? 'Member');
                                    $roleLower = strtolower(trim($role));
                                    $isOfficer = $user->role === 'admin'
                                        && ! in_array($roleLower, ['dean', 'comelec', 'comlec'])
                                        && $roleLower !== 'member';
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest {{ $isOfficer ? 'bg-blue-50 text-[#26488C]' : 'bg-gray-50 text-gray-500' }}">
                                    {{ $role }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <div class="text-[10px] font-bold text-gray-700">{{ course_acronym($user->course) ?: '—' }}</div>
                                <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest">{{ $user->year_level ? preg_replace('/[^0-9]/', '', $user->year_level) : '' }}</div>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $user->role === 'admin' ? 'bg-[#C4973B]' : 'bg-gray-300' }}"></span>
                                    <span class="text-[10px] font-black uppercase text-gray-500">{{ ucfirst($user->role) }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-[10px] font-bold text-gray-500">
                                {{ $user->pivot->created_at?->format('M Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-right">
                                @if($user->pivot->membership_status === 'Active')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest bg-green-50 text-green-700">Verified</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest bg-gray-50 text-gray-400 italic">Pending</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-4 py-14 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                                        <i class="ph ph-users-three text-xl text-gray-200"></i>
                                    </div>
                                    <h4 class="text-sm font-black text-gray-900">No members in registry</h4>
                                    <p class="text-[11px] text-gray-500 max-w-xs mt-1">This organization has no member records yet.</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-2 bg-gray-50/30 border-t border-gray-50 flex items-center justify-between">
                <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Registry Records Found</span>
                <span class="text-[10px] font-black text-gray-900">{{ $members->count() }} member{{ $members->count() !== 1 ? 's' : '' }}</span>
            </div>
        </div>

        <!-- Side Panel -->
        <div class="xl:col-span-4 space-y-4">

            <!-- Quick Stats -->
            <div class="grid grid-cols-4 gap-2">
                <div class="bg-white border border-gray-100 rounded-lg px-3 py-2.5">
                    <p class="text-lg font-black text-gray-900 leading-none">{{ $members->count() }}</p>
                    <p class="text-[8px] font-black text-gray-400 uppercase tracking-widest mt-1">Members</p>
                </div>
                <div class="bg-white border border-gray-100 rounded-lg px-3 py-2.5">
                    <p class="text-lg font-black text-green-600 leading-none">{{ $verifiedCount }}</p>
                    <p class="text-[8px] font-black text-gray-400 uppercase tracking-widest mt-1">Verified</p>
                </div>
                <div class="bg-white border border-gray-100 rounded-lg px-3 py-2.5">
                    <p class="text-lg font-black text-amber-500 leading-none">{{ $pendingCount }}</p>
                    <p class="text-[8px] font-black text-gray-400 uppercase tracking-widest mt-1">Pending</p>
                </div>
                <div class="bg-white border border-gray-100 rounded-lg px-3 py-2.5">
                    <p class="text-lg font-black text-[#C4973B] leading-none">{{ $officerPool->count() }}</p>
                    <p class="text-[8px] font-black text-gray-400 uppercase tracking-widest mt-1">Officers</p>
                </div>
            </div>

            <!-- Organization Details -->
            <div class="bg-white rounded-lg border border-gray-100 overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-50 bg-gray-50/40 flex items-center gap-2">
                    <i class="ph ph-buildings text-[#26488C] text-sm"></i>
                    <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest">Organization Details</h3>
                </div>
                <dl class="divide-y divide-gray-50 text-xs">
                    <div class="flex items-center justify-between px-4 py-2.5">
                        <dt class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Structure</dt>
                        <dd class="text-[11px] font-bold text-gray-800">{{ $organization->type }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-4 py-2.5">
                        <dt class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Department</dt>
                        <dd class="text-[11px] font-bold text-gray-800">
                            @if($department)
                                {{ $department->department_name }}
                                @if($department->department_acronym)
                                    <span class="text-gray-400">({{ $department->department_acronym }})</span>
                                @endif
                            @else
                                {{ $organization->isNonAcademic() ? 'Campus-wide' : '—' }}
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between px-4 py-2.5">
                        <dt class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Established</dt>
                        <dd class="text-[11px] font-bold text-gray-800">{{ $organization->created_at->format('M Y') }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-4 py-2.5">
                        <dt class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Accreditation</dt>
                        <dd class="text-[11px] font-bold text-gray-800 text-right">{{ $organization->accreditationPeriodLabel() ?? 'Not set' }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-4 py-2.5">
                        <dt class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Expires In</dt>
                        <dd class="text-[11px] font-bold flex items-center gap-1.5">
                            @if($days === null)
                                <span class="text-gray-400">—</span>
                            @elseif($days < 0)
                                <span class="text-red-600">{{ abs($days) }}d overdue</span>
                            @elseif($days <= 30)
                                <span class="text-amber-600">{{ $days }}d left</span>
                            @else
                                <span class="text-green-600">{{ $days }}d left</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between px-4 py-2.5">
                        <dt class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Linked System</dt>
                        <dd class="text-[11px] font-bold">
                            @if($organization->has_system)
                                <span class="inline-flex items-center gap-1 text-green-600"><i class="ph ph-check-circle"></i> Enabled</span>
                            @else
                                <span class="text-gray-400">Not linked</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Renewal Status (inside View Details) -->
            @php
                $latestRenew = \App\Models\ApplyOrg::where('organization_id', $organization->id)
                    ->where('application_type', 'renew')
                    ->latest()->first();
                $renewStatus = $latestRenew?->status;
                $renewDisplay = $renewStatus === 'For-Checking' ? 'For Checking' : $renewStatus;
            @endphp
            <div class="bg-white rounded-lg border border-gray-100 overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-50 bg-gray-50/40 flex items-center justify-between">
                    <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest flex items-center gap-2">
                        <i class="ph ph-arrow-clockwise text-[#C4973B]"></i> Renewal
                    </h3>
                    @if($latestRenew)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest {{ $renewStatus==='For-Checking' ? 'bg-blue-50 text-blue-700 border border-blue-100' : ($renewStatus==='Approved' ? 'bg-green-50 text-green-700 border border-green-100' : ($renewStatus==='Rejected' ? 'bg-red-50 text-red-700 border border-red-100' : 'bg-amber-50 text-amber-700 border border-amber-100')) }}">{{ $renewDisplay ?? '—' }}</span>
                    @else
                        <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest">No renewal</span>
                    @endif
                </div>
                <div class="p-4">
                    @if(!$latestRenew)
                        <p class="text-xs text-gray-500">No renewal application yet.</p>
                        @if($organization->isExpired())
                            <a href="{{ route('admin.renew.index', ['org' => $organization->id]) }}" class="mt-3 inline-flex items-center gap-1.5 px-3.5 py-2 bg-[#26488C] text-white font-black text-[10px] uppercase tracking-widest rounded-lg hover:bg-[#1a3461]">Start Renewal</a>
                        @endif
                    @elseif($renewStatus === 'For-Checking')
                        <p class="text-xs font-bold text-blue-700">For Checking — waiting for OSAS review.</p>
                        <p class="text-[11px] text-gray-500 mt-1">Submitted {{ $latestRenew->created_at->format('M d, Y') }} by {{ $latestRenew->requester_first_name }} {{ $latestRenew->requester_last_name }} ({{ $latestRenew->requester_email }})</p>
                        <a href="{{ route('osas.apply-org.show', $latestRenew) }}" class="mt-3 inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-gray-200 text-gray-700 font-black text-[10px] uppercase tracking-widest rounded-lg hover:bg-gray-50">View Details → /osas/apply-org/{{ $latestRenew->id }}</a>
                    @elseif($renewStatus === 'Pending')
                        <p class="text-xs font-bold text-amber-700">Pending — awaiting org upload (auto-generated).</p>
                        <p class="text-[11px] text-gray-500 mt-1">OSAS started renewal on {{ $latestRenew->created_at->format('M d, Y') }}. Org needs to submit renewal docs.</p>
                    @elseif($renewStatus === 'Rejected')
                        <p class="text-xs font-bold text-red-700">Rejected — {{ $latestRenew->admin_notes ?? 'Needs correction' }}</p>
                        <p class="text-[11px] text-gray-500 mt-1">Reviewed {{ $latestRenew->reviewed_at?->format('M d, Y') }}</p>
                        <a href="{{ route('admin.renew.index', ['org' => $organization->id]) }}" class="mt-3 inline-flex items-center gap-1.5 px-3.5 py-2 bg-[#26488C] text-white font-black text-[10px] uppercase tracking-widest rounded-lg hover:bg-[#1a3461]"><i class="ph ph-arrow-clockwise"></i> Resubmit Renewal</a>
                    @elseif($renewStatus === 'Approved')
                        <p class="text-xs font-bold text-green-700">Approved — active until {{ $organization->accreditation_expires_at?->format('M d, Y') }}</p>
                    @endif
                </div>
            </div>

            <!-- Officers -->
            <div class="bg-white rounded-lg border border-gray-100 overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-50 bg-gray-50/40 flex items-center justify-between">
                    <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest flex items-center gap-2">
                        <i class="ph ph-identification-card text-[#26488C] text-sm"></i> Officer Roster
                    </h3>
                    <span class="text-[10px] font-black text-gray-400">{{ $officerPool->count() }} assigned</span>
                </div>
                <ul class="divide-y divide-gray-50">
                    @forelse($officers as $user)
                    <li class="flex items-center gap-3 px-4 py-2.5">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 text-[#26488C] flex items-center justify-center font-black text-[9px] flex-shrink-0">
                            {{ substr($user->first_name, 0, 1) }}{{ substr($user->last_name, 0, 1) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-[11px] font-bold text-gray-900 leading-none truncate">{{ $user->full_name }}</div>
                            <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-0.5 truncate">{{ $user->student_id ?? 'GUEST' }}</div>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest bg-blue-50 text-[#26488C]">
                            {{ \App\Models\OrganizationUser::formatDisplayLabel($user->pivot->role) }}
                        </span>
                    </li>
                    @empty
                    <li class="px-4 py-8 text-center">
                        <div class="flex flex-col items-center">
                            <i class="ph ph-identification-badge text-xl text-gray-200 mb-2"></i>
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">No officers assigned</p>
                        </div>
                    </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

    .container-fluid {
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
</style>

<script>
(function () {
    const searchInput = document.getElementById('member-search');
    const filterButtons = Array.from(document.querySelectorAll('[data-member-filter]'));
    let activeFilter = 'all';

    function applyMemberFilters() {
        const query = (searchInput?.value || '').trim().toLowerCase();
        document.querySelectorAll('[data-member-row]').forEach(row => {
            const matchesStatus = activeFilter === 'all' || row.dataset.memberStatus === activeFilter;
            const matchesQuery = !query || (row.dataset.memberName || '').includes(query);
            row.style.display = matchesStatus && matchesQuery ? '' : 'none';
        });
    }

    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            activeFilter = button.dataset.memberFilter;
            filterButtons.forEach(btn => {
                const isActive = btn === button;
                btn.className = 'member-filter-btn px-2.5 py-1.5 rounded-md text-[9px] font-black uppercase tracking-widest transition-all' +
                    (isActive
                        ? ' bg-[#26488C] text-white'
                        : ' bg-white border border-gray-200 text-gray-500 hover:border-[#26488C]/30');
            });
            applyMemberFilters();
        });
    });

    if (searchInput) searchInput.addEventListener('input', applyMemberFilters);
})();
</script>

<script>
async function toggleStatus() {
    const isArchived = {{ $organization->isArchived() ? 'true' : 'false' }};
    const message = isArchived
        ? 'This will restore access for officers and students to this organization.'
        : 'This will restrict access for officers and students until accreditation is renewed.';
    const title = isArchived ? 'Unarchive Organization?' : 'Archive Organization?';

    const confirmed = await window.confirmAction(message, title);
    if (!confirmed) return;

    try {
        const response = await fetch('{{ route("osas.organizations.toggle-status", $organization) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json();
        if (data.success) {
            window.showToast(data.message, 'success', 'Registry Updated');
            await window.refreshCurrentOsasPage?.();
        } else {
            window.showToast(data.message || 'Unknown error', 'error', 'Operation Failed');
        }
    } catch (error) {
        console.error(error);
        window.showToast('An error occurred during communication with the server.', 'error', 'System Error');
    }
}
</script>
@endsection
