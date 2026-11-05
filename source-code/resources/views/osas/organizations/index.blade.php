@extends((request()->boolean('fragment') && request()->ajax()) ? 'layouts.fragment' : 'layouts.osas')

@section('title', 'Organizations Management')

@section('content')
<div class="container-fluid px-6 py-5">
    <!-- Breadcrumbs & Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-5 gap-2">
        <div>
            <nav class="flex text-gray-400 text-[10px] mb-1 uppercase tracking-widest font-black" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1">
                    <li class="inline-flex items-center">
                        <a href="{{ route('osas.dashboard') }}" class="hover:text-[#26488C] transition-colors">OSAS</a>
                    </li>
                    <li class="flex items-center">
                        <i class="ph ph-caret-right text-[8px] mx-2 text-gray-300"></i>
                        <span class="text-gray-300">Organizations</span>
                    </li>
                </ol>
            </nav>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight leading-none">
                Organization <span class="text-[#26488C]">Registry</span>
            </h1>
            <p class="text-gray-500 mt-1 text-xs font-medium">Centralized management for all accredited university organizations.</p>
        </div>

        <a href="{{ route('osas.reports.organizations.pdf') }}"
           class="inline-flex items-center gap-2 px-4 py-2 text-xs font-black uppercase tracking-widest text-white rounded-lg bg-[#26488C] hover:bg-[#1a3461] transition-colors shadow-sm">
            <i class="ph ph-file-pdf"></i> Export Registry PDF
        </a>
    </div>

    <!-- KPI Strip -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
        <div class="bg-white rounded-lg border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-blue-50 text-[#26488C] flex items-center justify-center flex-shrink-0">
                <i class="ph ph-buildings text-sm"></i>
            </div>
            <div class="min-w-0">
                <p class="text-lg font-black text-gray-900 leading-none">{{ $stats['total'] }}</p>
                <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-0.5 truncate">Total Listed</p>
            </div>
        </div>
        <div class="bg-white rounded-lg border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0">
                <i class="ph ph-graduation-cap text-sm"></i>
            </div>
            <div class="min-w-0">
                <p class="text-lg font-black text-gray-900 leading-none">{{ $stats['mother_org'] }}</p>
                <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-0.5 truncate">Mother Orgs</p>
            </div>
        </div>
        <div class="bg-white rounded-lg border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center flex-shrink-0">
                <i class="ph ph-tree-structure text-sm"></i>
            </div>
            <div class="min-w-0">
                <p class="text-lg font-black text-gray-900 leading-none">{{ $stats['sub_org'] }}</p>
                <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-0.5 truncate">Sub Orgs</p>
            </div>
        </div>
        <div class="bg-white rounded-lg border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-amber-50 text-[#C4973B] flex items-center justify-center flex-shrink-0">
                <i class="ph ph-users text-sm"></i>
            </div>
            <div class="min-w-0">
                <p class="text-lg font-black text-gray-900 leading-none">{{ $stats['non_academic'] }}</p>
                <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-0.5 truncate">Non-Academic</p>
            </div>
        </div>
        <div class="bg-white rounded-lg border border-gray-100 p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-green-50 text-green-600 flex items-center justify-center flex-shrink-0">
                <i class="ph ph-check-circle text-sm"></i>
            </div>
            <div class="min-w-0">
                <p class="text-lg font-black text-gray-900 leading-none">{{ $stats['active'] }}</p>
                <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-0.5 truncate">Active Status</p>
            </div>
        </div>
    </div>

    <!-- Unified Toolbar & Table Container -->
    <div class="bg-white rounded-lg border border-gray-100 overflow-hidden">
        <!-- Toolbar -->
        <div class="px-4 py-3 border-b border-gray-50 bg-gray-50/30">
            <form id="organization-filter-form" method="GET" action="{{ route('osas.organizations.index') }}" class="flex flex-col lg:flex-row justify-between items-center gap-2">
                <div class="relative w-full lg:w-80 group">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="ph ph-magnifying-glass text-gray-400 text-xs group-focus-within:text-[#26488C] transition-colors"></i>
                    </div>
                    <input id="organization-search" type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, acronym..."
                           class="block w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg text-xs placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#26488C]/10 focus:border-[#26488C] transition-all bg-white">
                </div>

                <div class="flex items-center gap-2 w-full lg:w-auto">
                    <div class="relative flex-1 lg:flex-none">
                        <select name="type" onchange="this.form.requestSubmit()"
                                class="w-full appearance-none pl-3 pr-8 py-2 border border-gray-200 rounded-lg text-[10px] font-black uppercase tracking-widest text-gray-600 focus:outline-none focus:ring-2 focus:ring-[#26488C]/10 bg-white cursor-pointer">
                            <option value="">Structure</option>
                            <option value="Mother Org" {{ request('type') == 'Mother Org' ? 'selected' : '' }}>Mother Org</option>
                            <option value="Sub Org" {{ request('type') == 'Sub Org' ? 'selected' : '' }}>Sub Org</option>
                            <option value="Non-Academic" {{ request('type') == 'Non-Academic' ? 'selected' : '' }}>Non-Academic</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-gray-400">
                            <i class="ph ph-caret-down text-[9px]"></i>
                        </div>
                    </div>

                    <div class="relative flex-1 lg:flex-none">
                        <select name="status" onchange="this.form.requestSubmit()"
                                class="w-full appearance-none pl-3 pr-8 py-2 border border-gray-200 rounded-lg text-[10px] font-black uppercase tracking-widest text-gray-600 focus:outline-none focus:ring-2 focus:ring-[#26488C]/10 bg-white cursor-pointer">
                            <option value="">Status</option>
                            <option value="Approved" {{ request('status') == 'Approved' ? 'selected' : '' }}>Active</option>
                            <option value="Inactive" {{ request('status') == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-gray-400">
                            <i class="ph ph-caret-down text-[9px]"></i>
                        </div>
                    </div>

                    <button type="submit" class="p-2.5 bg-[#26488C] text-white rounded-lg hover:bg-[#1a3461] transition-all">
                        <i class="ph ph-funnel text-[10px]"></i>
                    </button>
                </div>
            </form>
        </div>

        <div id="organization-results" aria-live="polite">
        <!-- Compact Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50/50 text-left border-b border-gray-50">
                        <th class="px-5 py-2.5 text-[9px] font-black text-gray-400 uppercase tracking-widest">Organization Details</th>
                        <th class="px-5 py-2.5 text-[9px] font-black text-gray-400 uppercase tracking-widest">Category</th>
                        <th class="px-5 py-2.5 text-[9px] font-black text-gray-400 uppercase tracking-widest text-center">Members</th>
                        <th class="px-5 py-2.5 text-[9px] font-black text-gray-400 uppercase tracking-widest">Status</th>
                        <th class="px-5 py-2.5 text-[9px] font-black text-gray-400 uppercase tracking-widest text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($organizations as $org)
                    <tr class="hover:bg-blue-50/30 transition-colors group">
                        <td class="px-5 py-2.5 whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                @if($org->settings && $org->settings->getRawOriginal('logo_url'))
                                    <div class="w-9 h-9 rounded-lg bg-white border border-gray-100 flex items-center justify-center overflow-hidden flex-shrink-0">
                                        <img src="{{ $org->settings->logo_url }}" alt="{{ $org->name }} Logo" class="w-full h-full object-cover">
                                    </div>
                                @else
                                    <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-[#26488C] to-[#1a3461] flex items-center justify-center text-white font-black text-xs flex-shrink-0">
                                        {{ substr($org->name, 0, 1) }}
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <div class="text-xs font-bold text-gray-900 leading-none mb-0.5 truncate max-w-[220px]">{{ $org->name }}</div>
                                    <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest">{{ $org->org_acronym ?? 'NO ACRONYM' }}</div>
                                    @if($org->accreditationPeriodLabel())
                                        <div class="text-[9px] font-semibold text-gray-400 mt-0.5 truncate max-w-[220px]">{{ $org->accreditationPeriodLabel() }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-2.5 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest {{ $org->type === 'Mother Org' ? 'bg-indigo-50 text-indigo-700' : ($org->type === 'Sub Org' ? 'bg-purple-50 text-purple-700' : 'bg-amber-50 text-[#C4973B]') }}">
                                {{ $org->type }}
                            </span>
                        </td>
                        <td class="px-5 py-2.5 whitespace-nowrap text-center">
                            <span class="text-xs font-black text-gray-900">{{ number_format($org->users_count) }}</span>
                        </td>
                        <td class="px-5 py-2.5 whitespace-nowrap">
                            @if($org->isArchived())
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 text-amber-700 text-[9px] font-black uppercase tracking-widest rounded">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Archived
                                </span>
                            @elseif($org->hasPendingRenewal())
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 text-amber-700 text-[9px] font-black uppercase tracking-widest rounded">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> For Renewal
                                </span>
                            @elseif($org->isExpired())
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-red-50 text-red-700 text-[9px] font-black uppercase tracking-widest rounded">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Expired
                                </span>
                            @elseif($org->status === 'Approved')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-50 text-green-700 text-[9px] font-black uppercase tracking-widest rounded">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Active
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-50 text-gray-700 text-[9px] font-black uppercase tracking-widest rounded">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-500"></span> {{ $org->status }}
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-2.5 whitespace-nowrap text-right">
                            <a href="{{ route('osas.organizations.show', $org) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-200 text-gray-700 text-[9px] font-black uppercase tracking-widest rounded-lg hover:bg-[#26488C] hover:text-white hover:border-[#26488C] transition-all">
                                <i class="ph ph-eye text-[10px]"></i> View
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-8 py-16 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                                    <i class="ph ph-bank text-2xl text-gray-200"></i>
                                </div>
                                <h4 class="text-sm font-black text-gray-900">No organizations found</h4>
                                <p class="text-xs text-gray-500 max-w-xs mx-auto mt-1">Adjust your search or category filters to find specific organizations.</p>
                                <a href="{{ route('osas.organizations.index') }}" class="mt-3 text-[#26488C] text-[10px] font-black uppercase tracking-widest hover:underline">Reset Registry</a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-5 py-3 bg-gray-50/30 border-t border-gray-50 premium-pagination">
            {{ $organizations->links() }}
        </div>
        </div>
    </div>
</div>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

    .container-fluid {
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    /* Premium Pagination Overrides */
    .premium-pagination nav > div:first-child {
        display: none !important;
    }
    .premium-pagination nav > div:last-child {
        justify-content: center !important;
        gap: 0.4rem !important;
    }
    .premium-pagination span[aria-current="page"] > span {
        background-color: #26488C !important;
        color: white !important;
        border: none !important;
        border-radius: 6px !important;
        padding: 6px 12px !important;
        font-weight: 800 !important;
    }
    .premium-pagination a, .premium-pagination span > span, .premium-pagination span > a {
        border-radius: 6px !important;
        border: 1px solid rgba(0,0,0,0.05) !important;
        padding: 6px 12px !important;
        font-size: 10px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        transition: all 0.2s ease !important;
    }
    .premium-pagination a:hover {
        background-color: #F8FAFC !important;
        border-color: #26488C !important;
        color: #26488C !important;
    }
</style>

@push('scripts')
<script>
    (() => {
        const form = document.getElementById('organization-filter-form');
        const searchInput = document.getElementById('organization-search');
        if (!form || !searchInput) return;

        let searchTimer;
        let activeRequest;
        const results = document.getElementById('organization-results');

        const refreshResults = async (targetUrl = null) => {
            const url = new URL(targetUrl || form.action, window.location.origin);
            if (!targetUrl) {
                new FormData(form).forEach((value, key) => {
                    if (value) url.searchParams.set(key, value);
                    else url.searchParams.delete(key);
                });
                url.searchParams.delete('page');
            }
            url.searchParams.delete('fragment');

            const requestUrl = new URL(url);
            requestUrl.searchParams.set('fragment', '1');
            activeRequest?.abort();
            const requestController = new AbortController();
            activeRequest = requestController;
            results.setAttribute('aria-busy', 'true');
            results.classList.add('opacity-60');

            try {
                const response = await fetch(requestUrl.href, {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html'
                    },
                    signal: requestController.signal
                });
                if (!response.ok) throw new Error(`Organization results request failed (${response.status})`);

                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                const updatedResults = page.querySelector('#organization-results');
                if (!updatedResults) throw new Error('Organization results were not found in the response');

                results.innerHTML = updatedResults.innerHTML;
                window.history.replaceState(window.history.state, '', url.href);
            } catch (error) {
                if (error.name !== 'AbortError') console.error('Could not refresh organization results:', error);
            } finally {
                if (activeRequest === requestController) {
                    activeRequest = null;
                    results.removeAttribute('aria-busy');
                    results.classList.remove('opacity-60');
                }
            }
        };

        form.addEventListener('submit', event => {
            event.preventDefault();
            refreshResults();
        });

        searchInput.addEventListener('input', () => {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(() => refreshResults(), 300);
        });

        results.addEventListener('click', event => {
            const link = event.target.closest('a[href]');
            if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

            const targetUrl = new URL(link.href, window.location.origin);
            const registryUrl = new URL(form.action, window.location.origin);
            if (targetUrl.pathname !== registryUrl.pathname) return;

            event.preventDefault();
            event.stopPropagation();
            refreshResults(targetUrl.href);
        });
    })();
</script>
@endpush
@endsection
