@extends((request()->boolean('fragment') && request()->ajax()) ? 'layouts.fragment' : 'layouts.osas')

@section('title', 'Accreditation Workbench - UniOrg')

@section('content')
@php
    $activeType = request('type');
    $baseTabParams = request()->except(['type', 'page', 'fragment', 'table_fragment']);
    $recognitionParams = array_merge($baseTabParams, ['type' => 'apply']);
    $renewalParams = array_merge($baseTabParams, ['type' => 'request']);
@endphp

<div class="osas-accreditation w-full max-w-[1400px] mx-auto space-y-4"
     x-data="accreditationWorkbench()"
     data-initial-type="{{ $activeType ?? '' }}"
     data-initial-total="{{ $requests->total() }}">
    <header class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <nav class="mb-1.5 flex items-center gap-2 text-[11px] font-semibold text-slate-400" aria-label="Breadcrumb">
                <a href="{{ route('osas.dashboard') }}" data-osas-navigation class="text-[#26488C] hover:text-[#1a3461]">OSAS</a>
                <i class="ph ph-caret-right text-[10px] text-slate-300"></i>
                <span>Accreditation</span>
            </nav>
            <h1 class="text-xl font-bold tracking-tight text-slate-950 sm:text-2xl">Accreditation <span class="text-[#26488C]">Workbench</span></h1>
            <p class="mt-1 text-xs font-medium leading-relaxed text-slate-500">Review organization recognition and renewal requests from one focused queue.</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <button type="button"
                    id="generate-renewals-btn"
                    onclick="document.getElementById('workbench-start-renewal-modal').classList.remove('hidden'); document.getElementById('workbench-start-renewal-modal').classList.remove('opacity-0'); document.getElementById('workbench-start-renewal-modal').classList.add('opacity-100');"
                    class="inline-flex items-center justify-center gap-2 rounded-md bg-indigo-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                <i class="ph ph-arrows-clockwise text-sm"></i>
                Start Renewal
            </button>
        </div>
        <!-- Start Renewal Confirm Modal (like logout) -->
        <div id="workbench-start-renewal-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 transition-opacity duration-300 opacity-0 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-indigo-50 mb-4">
                    <i class="ph ph-arrows-clockwise text-xl text-indigo-600"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Start Renewal?</h3>
                <p class="text-sm text-gray-500 mb-6">This will generate a pending renewal record for every approved organization whose accreditation has expired. Do you want to continue?</p>
                <div class="flex justify-center gap-3">
                    <button type="button" onclick="document.getElementById('workbench-start-renewal-modal').classList.add('hidden'); document.getElementById('workbench-start-renewal-modal').classList.add('opacity-0'); document.getElementById('workbench-start-renewal-modal').classList.remove('opacity-100');"
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium">Cancel</button>
                    <button type="button" id="workbench-start-renewal-confirm-btn" onclick="document.getElementById('workbench-start-renewal-modal').classList.add('hidden'); generateExpiredRenewals();"
                            class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">Confirm</button>
                </div>
            </div>
        </div>
    </header>

    <section aria-label="Accreditation summary" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-3.5">
            <div class="flex min-w-0 items-center gap-2.5">
                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-500"><i class="ph ph-squares-four text-sm"></i></span>
                <span class="min-w-0">
                    <span class="block truncate text-xs font-semibold text-slate-600">All requests</span>
                    <span class="block text-[11px] font-medium text-slate-400">Total submissions</span>
                </span>
            </div>
            <p class="text-xl font-bold leading-none text-slate-950">{{ number_format($totalRequests) }}</p>
        </div>
        <div class="flex items-center justify-between gap-3 rounded-lg border border-blue-100 bg-blue-50/50 p-3.5">
            <div class="flex min-w-0 items-center gap-2.5">
                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-white text-[#26488C]"><i class="ph ph-certificate text-sm"></i></span>
                <span class="min-w-0">
                    <span class="block truncate text-xs font-semibold text-[#26488C]">Recognition pending</span>
                    <span class="block text-[11px] font-medium text-[#26488C]/60">New organization reviews</span>
                </span>
            </div>
            <p class="text-xl font-bold leading-none text-slate-950">{{ number_format($pendingRecognition) }}</p>
        </div>
        <div class="flex items-center justify-between gap-3 rounded-lg border border-indigo-100 bg-indigo-50/50 p-3.5">
            <div class="flex min-w-0 items-center gap-2.5">
                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-white text-indigo-600"><i class="ph ph-arrow-clockwise text-sm"></i></span>
                <span class="min-w-0">
                    <span class="block truncate text-xs font-semibold text-indigo-600">Renewal pending</span>
                    <span class="block text-[11px] font-medium text-indigo-600/60">Existing organization renewals</span>
                </span>
            </div>
            <p class="text-xl font-bold leading-none text-slate-950">{{ number_format($pendingRenewal) }}</p>
        </div>
    </section>

    <section class="dashboard-panel overflow-hidden">
        <div class="border-b border-slate-100 bg-slate-50/40 px-4 py-3 sm:px-5">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <nav class="flex w-full items-center gap-1 overflow-x-auto rounded-lg border border-slate-200 bg-white p-1 xl:w-auto" aria-label="Accreditation request type">
                    <a href="{{ route('osas.apply-org.index', $baseTabParams) }}"
                       data-accreditation-tab
                       data-tab-type=""
                       @click.prevent="loadTab($event, '')"
                       :aria-current="isActive('') ? 'page' : 'false'"
                       :class="isActive('') ? 'bg-[#26488C] text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                       class="inline-flex flex-shrink-0 items-center gap-2 rounded-md px-3 py-1.5 text-[11px] font-semibold transition">
                        All
                        <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold" :class="isActive('') ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-500'">{{ $totalRequests }}</span>
                    </a>
                    <a href="{{ route('osas.apply-org.index', $recognitionParams) }}"
                       data-accreditation-tab
                       data-tab-type="apply"
                       @click.prevent="loadTab($event, 'apply')"
                       :aria-current="isActive('apply') ? 'page' : 'false'"
                       :class="isActive('apply') ? 'bg-[#26488C] text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                       class="inline-flex flex-shrink-0 items-center gap-2 rounded-md px-3 py-1.5 text-[11px] font-semibold transition">
                        Recognition
                        @if($pendingRecognition > 0)<span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold" :class="isActive('apply') ? 'bg-white/15 text-white' : 'bg-blue-50 text-[#26488C]'">{{ $pendingRecognition }}</span>@endif
                    </a>
                    <a href="{{ route('osas.apply-org.index', $renewalParams) }}"
                       data-accreditation-tab
                       data-tab-type="request"
                       @click.prevent="loadTab($event, 'request')"
                       :aria-current="isActive('request') ? 'page' : 'false'"
                       :class="isActive('request') ? 'bg-[#26488C] text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                       class="inline-flex flex-shrink-0 items-center gap-2 rounded-md px-3 py-1.5 text-[11px] font-semibold transition">
                        Renewal
                        @if($pendingRenewal > 0)<span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold" :class="isActive('request') ? 'bg-white/15 text-white' : 'bg-indigo-50 text-indigo-600'">{{ $pendingRenewal }}</span>@endif
                    </a>
                </nav>

                <form action="{{ route('osas.apply-org.index') }}" method="GET" @submit.prevent="submitFilters($event.target)" class="flex w-full flex-col gap-2 sm:flex-row xl:w-auto">
                    @if($activeType)<input type="hidden" name="type" value="{{ $activeType }}">@endif
                    <label class="relative min-w-0 flex-1 sm:min-w-[250px]">
                        <span class="sr-only">Search accreditation requests</span>
                        <i class="ph ph-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i>
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search organization or requester" class="w-full rounded-md border border-slate-200 bg-white py-2 pl-9 pr-3 text-xs font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-[#26488C] focus:ring-2 focus:ring-[#26488C]/10">
                    </label>
                    <div class="relative">
                        <label for="status-filter" class="sr-only">Filter by status</label>
                        <select id="status-filter" name="status" @change="submitFilters($event.target.form)" class="w-full appearance-none rounded-md border border-slate-200 bg-white py-2 pl-3 pr-9 text-xs font-semibold text-slate-600 outline-none transition focus:border-[#26488C] focus:ring-2 focus:ring-[#26488C]/10 sm:w-[140px]">
                            <option value="">All status</option>
                            <option value="Pending" {{ request('status') === 'Pending' ? 'selected' : '' }}>Pending</option>
                            <option value="For-Checking" {{ request('status') === 'For-Checking' ? 'selected' : '' }}>For Checking</option>
                            <option value="Approved" {{ request('status') === 'Approved' ? 'selected' : '' }}>Approved</option>
                            <option value="Rejected" {{ request('status') === 'Rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                        <i class="ph ph-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-slate-400"></i>
                    </div>
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-[#26488C]">Search</button>
                    @if(request('q') || request('status') || $activeType)
                        <a href="{{ route('osas.apply-org.index') }}" @click.prevent="clearFilters($event)" class="inline-flex items-center justify-center rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-500 transition hover:border-rose-200 hover:text-rose-600" title="Clear filters"><i class="ph ph-arrow-counter-clockwise mr-1.5 text-sm"></i>Clear</a>
                    @endif
                </form>
            </div>
        </div>

        <div class="relative min-h-[240px]">
            <div x-ref="tableRegion" @click="handleTableClick($event)">
                @include('osas.apply-org.partials.table', ['requests' => $requests])
            </div>

            <div x-show="isLoading" x-cloak class="absolute inset-0 z-10 bg-white/90 px-5 py-4" aria-live="polite" aria-label="Loading accreditation table">
                <div class="space-y-3">
                    <div class="h-7 w-full animate-pulse rounded-md bg-slate-100"></div>
                    <div class="h-12 w-full animate-pulse rounded-md bg-slate-100"></div>
                    <div class="h-12 w-full animate-pulse rounded-md bg-slate-100"></div>
                    <div class="h-12 w-full animate-pulse rounded-md bg-slate-100"></div>
                    <div class="h-12 w-full animate-pulse rounded-md bg-slate-100"></div>
                    <div class="h-9 w-full animate-pulse rounded-md bg-slate-50"></div>
                </div>
            </div>
        </div>
    </section>
</div>

@push('scripts')
<script>
    window.accreditationWorkbench = function () {
        return {
            activeType: '',
            resultCount: '0',
            isLoading: false,
            errorMessage: '',
            requestId: 0,
            abortController: null,
            popstateHandler: null,
            tableCache: new Map(),
            tableCacheLimit: 12,
            tableCacheTtl: 60000,
            clearCacheHandler: null,

            init() {
                this.activeType = this.$root.dataset.initialType || '';
                this.resultCount = this.$root.dataset.initialTotal || '0';

                const initialTable = this.$refs.tableRegion.querySelector('[data-accreditation-table]');
                if (initialTable) {
                    this.storeTable(this.cacheKey(window.location.href), initialTable.innerHTML, initialTable.dataset.total);
                }

                this.clearCacheHandler = () => this.tableCache.clear();
                window.clearOsasAccreditationCache = this.clearCacheHandler;

                this.popstateHandler = (url) => {
                    const targetUrl = new URL(url.href || url, window.location.href);
                    return this.fetchTable(targetUrl, false, targetUrl.searchParams.get('type') || '', true);
                };

                window.__osasAccreditationPopstate = this.popstateHandler;
                window.__osasAccreditationNavigate = (url, pushHistory = true) => {
                    const targetUrl = new URL(url.href || url, window.location.href);
                    return this.fetchTable(targetUrl, pushHistory, targetUrl.searchParams.get('type') || '', false);
                };
            },

            cacheKey(url) {
                const cacheUrl = new URL(url.href || url, window.location.href);
                cacheUrl.searchParams.delete('fragment');
                cacheUrl.searchParams.delete('table_fragment');

                const entries = Array.from(cacheUrl.searchParams.entries()).sort((left, right) => {
                    const keyOrder = left[0].localeCompare(right[0]);
                    return keyOrder || left[1].localeCompare(right[1]);
                });

                return `${cacheUrl.pathname}?${new URLSearchParams(entries).toString()}`;
            },

            trimTableCache() {
                while (this.tableCache.size > this.tableCacheLimit) {
                    this.tableCache.delete(this.tableCache.keys().next().value);
                }
            },

            storeTable(key, html, total) {
                this.tableCache.delete(key);
                this.tableCache.set(key, {
                    html,
                    total: total || '0',
                    cachedAt: Date.now()
                });
                this.trimTableCache();
            },

            getCachedTable(key) {
                const cached = this.tableCache.get(key);
                if (!cached) return null;

                if (Date.now() - cached.cachedAt > this.tableCacheTtl) {
                    this.tableCache.delete(key);
                    return null;
                }

                // Refresh the entry's position so recently used states remain available.
                this.tableCache.delete(key);
                this.tableCache.set(key, cached);
                return cached;
            },

            applyTable(html, total, cleanUrl, type, pushHistory) {
                this.$refs.tableRegion.innerHTML = html;
                this.activeType = type || cleanUrl.searchParams.get('type') || '';
                this.resultCount = total || '0';
                this.syncFilters(cleanUrl);

                if (pushHistory) {
                    window.history.pushState({ osasAccreditationTable: true }, '', cleanUrl.href);
                }

                if (typeof window.setOsasActiveNavigation === 'function') {
                    window.setOsasActiveNavigation(cleanUrl.href);
                }
            },

            destroy() {
                if (this.abortController) this.abortController.abort();
                if (window.clearOsasAccreditationCache === this.clearCacheHandler) window.clearOsasAccreditationCache = null;
                if (window.__osasAccreditationPopstate === this.popstateHandler) window.__osasAccreditationPopstate = null;
                window.__osasAccreditationNavigate = null;
            },

            isActive(type) {
                return this.activeType === type;
            },

            loadTab(event, type) {
                const targetUrl = new URL(window.location.href);
                const previousUrl = new URL(window.location.href);
                const previousType = this.activeType;
                targetUrl.searchParams.delete('fragment');
                targetUrl.searchParams.delete('table_fragment');
                targetUrl.searchParams.delete('page');

                if (type) targetUrl.searchParams.set('type', type);
                else targetUrl.searchParams.delete('type');

                // Update the visual state before the request starts so the click feels immediate.
                this.activeType = type;
                this.syncFilters(targetUrl);
                return this.fetchTable(targetUrl, true, type, false, previousType, previousUrl);
            },

            submitFilters(form) {
                const targetUrl = new URL(form.action, window.location.href);
                const formData = new FormData(form);

                targetUrl.search = '';
                formData.forEach((value, key) => {
                    if (value !== '') targetUrl.searchParams.set(key, value);
                });

                return this.fetchTable(targetUrl, true, targetUrl.searchParams.get('type') || '', false);
            },

            clearFilters(event) {
                const targetUrl = new URL(event.currentTarget.href, window.location.href);
                return this.fetchTable(targetUrl, true, '', false);
            },

            syncFilters(url) {
                const form = this.$root.querySelector('form');
                if (!form) return;

                const searchInput = form.querySelector('[name="q"]');
                const statusInput = form.querySelector('[name="status"]');
                const typeValue = url.searchParams.get('type') || '';
                let typeInput = form.querySelector('[name="type"]');

                if (searchInput) searchInput.value = url.searchParams.get('q') || '';
                if (statusInput) statusInput.value = url.searchParams.get('status') || '';

                if (typeValue) {
                    if (!typeInput) {
                        typeInput = document.createElement('input');
                        typeInput.type = 'hidden';
                        typeInput.name = 'type';
                        form.prepend(typeInput);
                    }
                    typeInput.value = typeValue;
                } else if (typeInput) {
                    typeInput.remove();
                }
            },

            handleTableClick(event) {
                const link = event.target.closest('a');
                if (!link || !link.closest('[data-accreditation-pagination]')) return;

                event.preventDefault();
                return this.fetchTable(new URL(link.href, window.location.href), true, this.activeType, true);
            },

            async fetchTable(url, pushHistory, type, preservePage, rollbackType = null, rollbackUrl = null) {
                const cleanUrl = new URL(url.href || url, window.location.href);
                cleanUrl.searchParams.delete('fragment');
                cleanUrl.searchParams.delete('table_fragment');
                if (!preservePage) cleanUrl.searchParams.delete('page');

                if (typeof window.setOsasActiveNavigation === 'function') {
                    window.setOsasActiveNavigation(cleanUrl.href);
                }

                const tableUrl = new URL(cleanUrl.href);
                tableUrl.searchParams.set('table_fragment', '1');
                const cacheKey = this.cacheKey(cleanUrl);
                const currentRequestId = ++this.requestId;
                const cachedTable = this.getCachedTable(cacheKey);

                if (this.abortController) this.abortController.abort();
                this.abortController = null;
                this.errorMessage = '';

                if (cachedTable) {
                    this.isLoading = false;
                    this.applyTable(cachedTable.html, cachedTable.total, cleanUrl, type, pushHistory);
                    return true;
                }

                this.abortController = new AbortController();
                this.isLoading = true;

                try {
                    const response = await fetch(tableUrl.href, {
                        credentials: 'same-origin',
                        signal: this.abortController.signal,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html'
                        }
                    });

                    if (!response.ok) throw new Error(`Unable to load accreditation requests (${response.status})`);

                    const html = await response.text();
                    const parsed = new DOMParser().parseFromString(html, 'text/html');
                    const nextTable = parsed.querySelector('[data-accreditation-table]');
                    if (!nextTable) throw new Error('Accreditation table content was not returned');
                    if (currentRequestId !== this.requestId) return false;

                    const tableHtml = nextTable.innerHTML;
                    this.storeTable(cacheKey, tableHtml, nextTable.dataset.total);
                    this.applyTable(tableHtml, nextTable.dataset.total, cleanUrl, type, pushHistory);

                    return true;
                } catch (error) {
                    if (error.name === 'AbortError' || currentRequestId !== this.requestId) return false;
                    if (rollbackType !== null) {
                        this.activeType = rollbackType;
                        if (rollbackUrl) {
                            this.syncFilters(rollbackUrl);
                            if (typeof window.setOsasActiveNavigation === 'function') {
                                window.setOsasActiveNavigation(rollbackUrl.href);
                            }
                        }
                    }
                    this.errorMessage = 'The request list could not be refreshed. Please try again.';
                    if (typeof window.showToast === 'function') window.showToast(this.errorMessage, 'error');
                    return false;
                } finally {
                    if (currentRequestId === this.requestId) {
                        this.isLoading = false;
                        this.abortController = null;
                    }
                }
            }
        };
    };
</script>
<script>
    async function generateExpiredRenewals() {
        const button = document.getElementById('generate-renewals-btn');
        const confirmBtn = document.getElementById('workbench-start-renewal-confirm-btn');
        // Debounce: block double-click / two-tab race before button paints
        if ((button && button.disabled) || (confirmBtn && confirmBtn.disabled)) return;
        if (button) { button.disabled = true; }
        const original = button ? button.innerHTML : '';
        if (button) button.innerHTML = '<i class="ph ph-spinner ph-spin text-sm"></i> Generating...';
        if (confirmBtn) { confirmBtn.disabled = true; confirmBtn.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i> Generating...'; }
        try {
            const response = await fetch('{{ route("osas.apply-org.generate-renewals") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                }
            });
            let data = null;
            const ct = response.headers.get('content-type') || '';
            if (ct.includes('application/json')) {
                data = await response.json();
            } else {
                const txt = await response.text();
                try { data = JSON.parse(txt); } catch(e){ data = { success: response.ok, message: txt.slice(0,200) }; }
            }
            if (!response.ok || !data.success) {
                const msg = (data && data.message) ? data.message : 'Failed to generate renewals.';
                if (typeof window.showToast === 'function') window.showToast(msg, 'error'); else alert(msg);
            } else {
                if (typeof window.showToast === 'function') window.showToast(data.message, 'success'); else alert(data.message);
                setTimeout(() => window.location.reload(), 800);
            }
        } catch (error) {
            const msg = 'Could not generate renewals. Please try again.';
            if (typeof window.showToast === 'function') window.showToast(msg, 'error'); else alert(msg);
        } finally {
            if (button) { button.disabled = false; button.innerHTML = original; }
            if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.innerHTML = 'Confirm'; }
        }
    }
    window.generateExpiredRenewals = generateExpiredRenewals;
</script>
@endpush

<style>
    [x-cloak] { display: none !important; }
    .osas-accreditation { font-family: 'Plus Jakarta Sans', sans-serif; }
    .dashboard-panel { background: #fff; border: 1px solid #E2E8F0; border-radius: 0.625rem; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02); }
    .premium-pagination nav > div:first-child { display: none !important; }
    .premium-pagination nav > div:last-child { display: flex; align-items: center; justify-content: flex-end; gap: 0.35rem; }
    .premium-pagination a, .premium-pagination span > span, .premium-pagination span > a { border-radius: 0.375rem !important; border: 1px solid #E2E8F0 !important; padding: 0.35rem 0.65rem !important; font-size: 0.625rem !important; font-weight: 800 !important; }
    .premium-pagination span[aria-current="page"] > span { background: #26488C !important; color: #fff !important; border-color: #26488C !important; }
    .premium-pagination a:hover { border-color: #26488C !important; color: #26488C !important; background: #F8FAFC !important; }
    @media (max-width: 640px) {
        .premium-pagination nav > div:last-child { justify-content: center; }
    }
</style>
@endsection