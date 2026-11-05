@extends('layouts.osas')

@section('title', 'Account Management - OSAS')

@section('content')
<div class="container-fluid px-6 py-5">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-5 gap-2">
        <div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight leading-none">
                Account <span class="text-[#26488C]">Management</span>
            </h1>
            <p class="text-gray-500 mt-1 text-xs font-medium">Deactivate or reactivate accounts. OSAS can create OSAS admin and Dean (per college) accounts.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('osas.accounts.create', ['role' => 'super_admin']) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 text-white text-xs font-bold rounded-lg hover:bg-amber-700 transition-colors shadow-sm">
                <i class="ph ph-graduation-cap"></i>
                New Dean
            </a>
            <a href="{{ route('osas.accounts.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-[#26488C] text-white text-xs font-bold rounded-lg hover:bg-[#1a3461] transition-colors shadow-sm">
                <i class="ph ph-plus"></i>
                New OSAS Admin
            </a>
        </div>
    </div>

    <!-- Validity Summary -->
    @if(($validitySummary['expiring'] ?? 0) > 0 || ($validitySummary['expired'] ?? 0) > 0)
        <div class="flex flex-wrap items-center gap-2 mb-4">
            @if(($validitySummary['expiring'] ?? 0) > 0)
                <a href="{{ route('osas.accounts.index', array_merge(request()->except(['page', 'validity']), ['validity' => 'expiring'])) }}" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 text-[10px] font-black uppercase tracking-wider hover:bg-amber-100 transition-colors">
                    <i class="ph ph-hourglass text-[10px]"></i> {{ $validitySummary['expiring'] }} expiring
                </a>
            @endif
            @if(($validitySummary['expired'] ?? 0) > 0)
                <a href="{{ route('osas.accounts.index', array_merge(request()->except(['page', 'validity']), ['validity' => 'expired'])) }}" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-red-50 border border-red-200 text-red-600 text-[10px] font-black uppercase tracking-wider hover:bg-red-100 transition-colors">
                    <i class="ph ph-user-x text-[10px]"></i> {{ $validitySummary['expired'] }} expired
                </a>
            @endif
        </div>
    @endif

    <!-- Filters Section -->
    <div class="bg-white rounded-t-lg border border-gray-100 p-4 border-b-0 flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('osas.accounts.index') }}" id="account-filter-form" class="flex-1 flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-[200px] max-w-sm">
                <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by name, email, ID..." aria-label="Search accounts" oninput="debouncedFilter()" class="w-full pl-9 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-[#26488C]/20 focus:border-[#26488C] transition-all">
            </div>
            <select name="role" aria-label="Filter by role" onchange="submitFilterForm()" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-700 focus:ring-2 focus:ring-[#26488C]/20 focus:border-[#26488C] transition-all min-w-[120px]">
                <option value="">All Roles</option>
                <option value="student" {{ request('role') === 'student' ? 'selected' : '' }}>Student</option>
                <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                <option value="osas_admin" {{ request('role') === 'osas_admin' ? 'selected' : '' }}>OSAS</option>
                <option value="super_admin" {{ request('role') === 'super_admin' ? 'selected' : '' }}>Dean</option>
            </select>
            <select name="state" aria-label="Filter by status" onchange="submitFilterForm()" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-700 focus:ring-2 focus:ring-[#26488C]/20 focus:border-[#26488C] transition-all min-w-[120px]">
                <option value="">All Statuses</option>
                <option value="active" {{ request('state') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('state') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
            <select name="validity" aria-label="Filter by validity" onchange="submitFilterForm()" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-700 focus:ring-2 focus:ring-[#26488C]/20 focus:border-[#26488C] transition-all min-w-[120px]">
                <option value="">All Validity</option>
                <option value="active" {{ request('validity') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="expiring" {{ request('validity') === 'expiring' ? 'selected' : '' }}>Expiring</option>
                <option value="expired" {{ request('validity') === 'expired' ? 'selected' : '' }}>Expired</option>
            </select>
            <select name="organization" aria-label="Filter by organization" onchange="submitFilterForm()" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-700 focus:ring-2 focus:ring-[#26488C]/20 focus:border-[#26488C] transition-all min-w-[160px]">
                <option value="">All Organizations</option>
                @foreach($organizations as $org)
                    <option value="{{ $org->id }}" {{ (string) request('organization') === (string) $org->id ? 'selected' : '' }}>{{ $org->org_acronym ? $org->org_acronym.' - ' : '' }}{{ $org->name }}</option>
                @endforeach
            </select>
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
                                @php $orgLabels = $account->activeOrganizationAssignments->map(fn($a) => $a->organization ? ($a->organization->org_acronym ? $a->organization->org_acronym.' - '.$a->organization->name : $a->organization->name).' ('.$a->role.')' : $a->role)->take(2); @endphp
                                @if($orgLabels->isNotEmpty())
                                    <div class="flex flex-col gap-1">
                                        @foreach($orgLabels as $label)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-blue-50 text-[#26488C] max-w-[220px] truncate" title="{{ $label }}">{{ $label }}</span>
                                        @endforeach
                                        @if($account->activeOrganizationAssignments->count() > 2)
                                            <span class="text-[9px] text-gray-400 font-bold">+{{ $account->activeOrganizationAssignments->count() - 2 }} more</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-[10px] text-gray-400 font-bold">— No org</span>
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
                                    <a href="{{ route('osas.accounts.show', $account) }}" class="inline-flex items-center justify-center px-3 py-1.5 rounded bg-gray-50 text-gray-600 hover:bg-[#26488C] hover:text-white transition-colors text-[10px] font-bold" title="View &amp; deactivate">
                                        <i class="ph ph-eye mr-1"></i> View
                                    </a>
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
            <div class="px-5 py-3 border-t border-gray-50 bg-gray-50/30 premium-pagination">
                {{ $accounts->links() }}
            </div>
        @endif
    </div>
</div>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    
    .container-fluid {
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
    
    .premium-pagination nav > div:first-child {
        display: none !important;
    }
    @media (max-width: 639px) {
        .premium-pagination nav > div:first-child {
            display: flex !important;
        }
        .premium-pagination nav > div:last-child {
            display: none !important;
        }
    }
    .premium-pagination nav > div:last-child {
        justify-content: center !important;
        gap: 0.4rem !important;
    }
    .premium-pagination span[aria-current="page"] > span {
        background-color: rgb(31 41 55 / var(--tw-bg-opacity, 1)) !important;
        color: #fff !important;
        border: 1px solid rgb(31 41 55 / var(--tw-bg-opacity, 1)) !important;
        border-radius: 6px !important;
        padding: 6px 12px !important;
        font-weight: 800 !important;
    }
    .premium-pagination a, .premium-pagination span > span, .premium-pagination span > a {
        border-radius: 6px !important;
        border: 1px solid #F1F5F9 !important;
        padding: 6px 12px !important;
        font-size: 10px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        color: #64748B !important;
        transition: all 0.2s ease !important;
    }
    .premium-pagination a:hover {
        background-color: #F1F5F9 !important;
        border-color: #D1D5DB !important;
        color: #374151 !important;
    }
</style>

<script>
    let filterTimer;
    function debouncedFilter() {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(() => {
            submitFilterForm();
        }, 300);
    }
    function submitFilterForm() {
        const form = document.getElementById('account-filter-form');
        form.querySelectorAll('input, select').forEach(el => {
            el.disabled = !el.value;
        });
        form.submit();
    }
</script>
@endsection
