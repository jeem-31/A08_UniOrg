{{-- Smart Scheduler slide-over (weather-aware date suggestions, Open-Meteo).
     Include ONCE at page root. Open via window.openSmartScheduler('create' | 'edit'). --}}
<div id="smart-scheduler-root"
     data-suggest-url="{{ url('admin/events/suggest-dates') }}"
     data-geocode-url="{{ url('admin/events/geocode') }}"
     x-data="smartScheduler()"
     @smart-scheduler-open.window="openPanel($event.detail.mode)"
     @keydown.escape.window="close()">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[80]" style="display: none;">
            {{-- Backdrop: Apple-style fade + blur --}}
            <div @click="close()"
                 x-transition:enter="transition-opacity ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-black/40 ss-backdrop"></div>

            {{-- Panel: spring slide from right (Apple cubic-bezier) --}}
            <aside x-show="open"
                   x-transition:enter="transition duration-500"
                   x-transition:enter-start="translate-x-full"
                   x-transition:enter-end="translate-x-0"
                   x-transition:leave="transition duration-300"
                   x-transition:leave-start="translate-x-0"
                   x-transition:leave-end="translate-x-full"
                   style="transition-timing-function: cubic-bezier(0.32, 0.72, 0, 1);"
                   class="fixed right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl flex flex-col"
                   role="dialog" aria-label="Smart schedule suggestions">
                {{-- Header --}}
                <div class="flex items-start justify-between px-5 pt-5 pb-3 border-b border-gray-100">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wider" style="color: var(--primary-color);">Smart scheduler</p>
                        <div class="flex items-center gap-1">
                            <button type="button" @click="changeMonth(-1)" :disabled="!canPage(-1)"
                                    class="flex-shrink-0 h-7 w-7 rounded-full hover:bg-gray-100 flex items-center justify-center text-gray-600 transition active:scale-95 disabled:opacity-30 disabled:cursor-not-allowed">
                                <i class="ph ph-caret-left"></i>
                            </button>
                            <h3 class="text-lg font-bold text-gray-900 leading-tight truncate flex-1 text-center" x-text="monthLabel || 'Best dates'"></h3>
                            <button type="button" @click="changeMonth(1)" :disabled="!canPage(1)"
                                    class="flex-shrink-0 h-7 w-7 rounded-full hover:bg-gray-100 flex items-center justify-center text-gray-600 transition active:scale-95 disabled:opacity-30 disabled:cursor-not-allowed">
                                <i class="ph ph-caret-right"></i>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5 truncate text-center">
                            <i class="ph ph-map-pin mr-1"></i><span x-text="locationLabel"></span>
                        </p>
                    </div>
                    <button @click="close()" class="ml-3 flex-shrink-0 h-9 w-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-600 transition active:scale-95">
                        <i class="ph ph-x"></i>
                    </button>
                </div>

                {{-- Location search (Outside of School) / change location --}}
                <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/60">
                    <template x-if="searchMode || (needsLocationSearch && !locationChosen)">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Where is it held outside the school? <span class="text-red-500">*</span> <span class="font-normal text-gray-400">(Philippines only)</span></label>
                            <div class="flex gap-2">
                                <div class="relative flex-1 min-w-0">
                                    <input type="text" x-model="geoQuery" @input="onGeoInput()" @keydown.enter.prevent="searchPlace(true)"
                                           placeholder="e.g. Lumban, Laguna"
                                           class="w-full px-3 py-2 pr-8 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:outline-none" style="--tw-ring-color: var(--primary-color); border-color: var(--primary-color);">
                                    <span x-show="geoLoading" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400">
                                        <i class="ph ph-spinner ph-spin"></i>
                                    </span>
                                </div>
                                <button type="button" @click="searchPlace()" :disabled="geoLoading || geoQuery.trim().length < 3"
                                        class="px-3 py-2 text-sm font-semibold text-white rounded-lg transition active:scale-95 disabled:opacity-50" style="background-color: var(--primary-color);">
                                    <i class="ph" :class="geoLoading ? 'ph-spinner ph-spin' : 'ph-magnifying-glass'"></i>
                                </button>
                                <button type="button" x-show="locationChosen || !needsLocationSearch" @click="searchMode = false"
                                        class="px-3 py-2 text-sm font-semibold text-gray-600 border border-gray-300 rounded-lg hover:bg-white transition active:scale-95">
                                    Cancel
                                </button>
                            </div>
                            <div x-show="geoResults.length > 0" class="mt-2 space-y-1 max-h-32 overflow-y-auto">
                                <template x-for="r in geoResults" :key="r.latitude + ',' + r.longitude">
                                    <button type="button" @click="pickPlace(r)"
                                            class="w-full text-left px-3 py-1.5 text-sm rounded-lg hover:bg-white border border-transparent hover:border-gray-200 transition truncate">
                                        <i class="ph ph-map-pin text-gray-400 mr-1"></i><span x-text="r.name"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                    <template x-if="!searchMode && (!needsLocationSearch || locationChosen)">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs text-gray-600 truncate">
                                <i class="ph ph-cloud-sun text-gray-400 mr-1"></i>Weather: <span class="font-semibold" x-text="locationLabel"></span>
                            </p>
                            <button type="button" @click="searchMode = true" class="text-xs font-semibold flex-shrink-0 hover:opacity-70 active:scale-95" style="color: var(--primary-color);">Change</button>
                        </div>
                    </template>
                </div>

                {{-- Time pickers (user chooses the hours) --}}
                <div class="px-5 py-3 border-b border-gray-100">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Start time</label>
                            <input type="time" x-model="startTime" @change="refetch()"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:outline-none" style="--tw-ring-color: var(--primary-color);">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">End time</label>
                            <input type="time" x-model="endTime" @change="refetch()"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:outline-none" style="--tw-ring-color: var(--primary-color);">
                        </div>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1.5">You choose the time — the suggestion sets the day.</p>
                </div>

                {{-- Two-tap pick progress --}}
                <div class="px-5 py-2.5 border-b border-gray-100 bg-white flex items-center gap-2">
                    <span class="flex-shrink-0 text-[11px] font-bold px-2 py-0.5 rounded-full text-white" style="background-color: var(--primary-color);" x-text="pickStep === 'start' ? 'Step 1 of 2' : 'Step 2 of 2'"></span>
                    <p class="text-xs text-gray-600 flex-1 truncate" x-text="pickStep === 'start' ? 'Tap a day to set the start date' : ('Start: ' + pickedStart + ' — now tap the end date')"></p>
                    <button type="button" x-show="pickedStart" @click="resetPick()" class="flex-shrink-0 text-[11px] font-bold text-gray-400 hover:text-gray-600">Reset</button>
                </div>

                {{-- Body --}}
                <div class="flex-1 overflow-y-auto px-5 py-4">
                    {{-- Weather service down: venue availability still shown --}}
                    <template x-if="!loading && !error && !weatherAvailable && !fallbackLoading">
                        <div class="mb-3 rounded-xl border border-amber-200 bg-amber-50 p-3">
                            <div class="flex items-start gap-2">
                                <i class="ph ph-cloud-warning text-amber-500 mt-0.5"></i>
                                <p class="text-xs text-amber-800 flex-1" x-text="weatherMessage()"></p>
                            </div>
                            <button type="button" @click="refetch()"
                                    class="mt-2 w-full px-3 py-1.5 text-xs font-bold text-amber-800 border border-amber-300 rounded-lg hover:bg-amber-100 transition active:scale-[0.98]">
                                <i class="ph ph-arrow-clockwise mr-1"></i>Retry
                            </button>
                        </div>
                    </template>
                    {{-- Loading skeleton --}}
                    <template x-if="loading">
                        <div class="space-y-2">
                            <template x-for="i in 6" :key="i">
                                <div class="ss-shimmer flex items-center gap-3 p-3 rounded-xl border border-gray-100">
                                    <div class="h-10 w-10 rounded-lg bg-gray-200 flex-shrink-0"></div>
                                    <div class="flex-1 space-y-1.5">
                                        <div class="h-3 w-2/3 bg-gray-200 rounded"></div>
                                        <div class="h-2.5 w-1/2 bg-gray-100 rounded"></div>
                                    </div>
                                    <div class="h-6 w-14 bg-gray-200 rounded-full flex-shrink-0"></div>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Error / empty --}}
                    <template x-if="!loading && error">
                        <div class="text-center py-10">
                            <i class="ph ph-cloud-warning text-4xl text-gray-300"></i>
                            <p class="text-sm font-semibold text-gray-700 mt-2" x-text="error"></p>
                            <button type="button" @click="refetch()" class="mt-3 px-4 py-2 text-sm font-semibold border border-gray-300 rounded-lg hover:bg-gray-50 transition active:scale-95">Try again</button>
                        </div>
                    </template>
                    <template x-if="!loading && !error && suggestions.length === 0">
                        <div class="text-center py-10">
                            <i class="ph ph-calendar-x text-4xl text-gray-300"></i>
                            <p class="text-sm font-semibold text-gray-700 mt-2">No suggestible days left this month.</p>
                            <p class="text-xs text-gray-500 mt-1">Try picking a start date in a future month.</p>
                        </div>
                    </template>

                    {{-- Day cards --}}
                    <template x-if="!loading && !error && suggestions.length > 0">
                        <div class="space-y-2">
                            {{-- Suggested dates: good weather only, nearest first --}}
                            <div class="mb-1">
                                <p class="text-[11px] font-bold uppercase tracking-wider" style="color: var(--primary-color);">Suggested dates</p>
                                <template x-if="topPicks().length > 0">
                                    <div>
                                        <p class="text-[11px] text-gray-400 mb-2">Best days to hold your event:</p>
                                        <div class="grid grid-cols-3 gap-2">
                                            <template x-for="s in topPicks()" :key="'top-' + s.date">
                                                <button type="button" @click="pickDay(s)"
                                                        class="rounded-xl border p-2.5 text-center transition active:scale-95 ss-top-pick"
                                                        :class="pickedStart === s.date ? 'ss-picked' : 'border-gray-200 bg-white hover:shadow-md'">
                                                    <p class="text-[10px] font-bold uppercase text-gray-400" x-text="s.weekday"></p>
                                                    <p class="text-xl font-bold text-gray-900 leading-tight" x-text="s.day"></p>
                                                    <p class="mt-1 inline-block text-[10px] font-bold px-2 py-0.5 rounded-full" :class="badgeClass(s.label)" x-text="s.label"></p>
                                                    <p class="mt-1 text-[10px] text-gray-400" x-text="(s.temperature_max !== null ? Math.round(s.temperature_max) + '°C · ' : '') + (s.precipitation_probability !== null ? s.precipitation_probability + '% rain' : '')"></p>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="!loading && topPicks().length === 0">
                                    <p class="text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5">No good-weather days this month.</p>
                                </template>
                            </div>
                            {{-- Least rain this month --}}
                            <template x-if="driest().length > 0">
                                <div class="mb-1">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-yellow-600">Least rain this month</p>
                                    <div class="grid grid-cols-3 gap-2 mt-2">
                                        <template x-for="s in driest()" :key="'dry-' + s.date">
                                            <button type="button" @click="pickDay(s)" :disabled="!s.selectable"
                                                    class="rounded-xl border border-yellow-300 bg-yellow-50 p-2.5 text-center transition active:scale-95 disabled:opacity-60"
                                                    :class="pickedStart === s.date ? 'ss-picked' : 'hover:shadow-md'">
                                                <p class="text-[10px] font-bold uppercase text-yellow-600" x-text="s.weekday"></p>
                                                <p class="text-xl font-bold text-gray-900 leading-tight" x-text="s.day"></p>
                                                <p class="mt-1 text-[11px] font-bold text-yellow-700" x-text="s.precipitation_probability + '% rain'"></p>
                                                <p class="text-[10px] font-bold" :class="s.conflicted ? 'text-red-600' : 'text-green-700'" x-text="s.conflicted ? 'Taken' : s.label"></p>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400" x-text="'All days · ' + monthLabel"></p>
                            <template x-for="(s, idx) in suggestions" :key="s.date">
                                <button type="button" @click="pickDay(s)" :disabled="!s.selectable"
                                        :style="'animation-delay:' + Math.min(idx * 35, 400) + 'ms'"
                                        class="ss-card w-full text-left flex items-center gap-3 p-3 rounded-xl border transition active:scale-[0.98]"
                                        :class="[pickedStart === s.date ? 'ss-picked' : '', s.selectable
                                            ? 'border-gray-200 hover:border-transparent hover:shadow-lg bg-white cursor-pointer'
                                            : 'border-gray-100 bg-gray-50 opacity-60 cursor-not-allowed']">
                                    <div class="flex-shrink-0 h-11 w-11 rounded-xl flex flex-col items-center justify-center"
                                         :class="s.score === null ? 'bg-gray-100 text-gray-400' : tileClass(s.label)">
                                        <span class="text-base font-bold leading-none" x-text="s.day"></span>
                                        <span class="text-[10px] font-semibold leading-none mt-0.5" x-text="s.weekday"></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 flex items-center gap-1.5 truncate">
                                            <i class="ph" :class="s.icon"></i>
                                            <span x-text="s.label"></span>
                                            <span x-show="s.score !== null" class="text-xs font-bold text-gray-400" x-text="s.score"></span>
                                        </p>
                                        <p class="text-xs text-gray-500 truncate" x-text="s.details"></p>
                                        <p x-show="s.conflicted" class="text-[11px] text-red-600 font-medium truncate">
                                            <i class="ph ph-warning mr-0.5"></i>Taken: <span x-text="s.conflict_title || (s.conflict_count + ' event(s)')"></span>
                                        </p>
                                    </div>
                                    <span class="flex-shrink-0 text-[11px] font-bold px-2.5 py-1 rounded-full"
                                          :class="s.score === null ? 'bg-gray-100 text-gray-400' : badgeClass(s.label)"
                                          x-text="s.score === null ? 'N/A' : s.label"></span>
                                </button>
                            </template>
                            <p x-show="forecastNote" class="text-[11px] text-gray-400 pt-1" x-text="forecastNote"></p>
                        </div>
                    </template>
                </div>

                {{-- Footer --}}
                <div class="px-5 py-3 border-t border-gray-100 bg-white">
                    <p class="text-[11px] text-gray-400 text-center">Powered by Open-Meteo · <span x-text="venue || 'no venue selected'"></span></p>
                </div>
            </aside>
        </div>
    </template>
</div>

<script>
window.openSmartScheduler = function (mode) {
    window.dispatchEvent(new CustomEvent('smart-scheduler-open', { detail: { mode: mode } }));
};

function smartScheduler() {
    return {
        open: false,
        mode: 'create',
        loading: false,
        suggestions: [],
        monthLabel: '',
        monthValue: '',
        venue: '',
        excludeId: null,
        locationLabel: 'LSPU Sta. Cruz Campus',
        needsLocationSearch: false,
        locationChosen: false,
        searchMode: false,
        weatherAvailable: true,
        weatherError: null,
        fallbackLoading: false,
        pickStep: 'start',
        pickedStart: null,
        lat: null,
        lon: null,
        startTime: '09:00',
        endTime: '11:00',
        geoQuery: '',
        geoResults: [],
        geoLoading: false,
        geoTimer: null,
        geoAbort: null,
        error: '',
        forecastNote: '',

        // Green = good weather, yellow = fair / least rain, red = avoid.
        badgeClass(label) {
            if (label === 'Best' || label === 'Good') return 'bg-green-500 text-white';
            if (label === 'Fair') return 'bg-yellow-400 text-white';
            return 'bg-red-500 text-white';
        },
        tileClass(label) {
            if (label === 'Best' || label === 'Good') return 'bg-green-100 text-green-700';
            if (label === 'Fair') return 'bg-yellow-100 text-yellow-700';
            return 'bg-red-100 text-red-600';
        },

        form() {
            return document.querySelector(this.mode === 'edit' ? '#edit-event-form' : '#create-event-form');
        },
        val(name) {
            const f = this.form();
            return f ? (f.querySelector('[name="' + name + '"]') || {}).value || '' : '';
        },

        openPanel(mode) {
            this.mode = mode || 'create';
            this.error = '';
            this.suggestions = [];
            this.geoResults = [];
            this.locationChosen = false;
            this.searchMode = false;
            this.weatherAvailable = true;
            this.weatherError = null;
            this.fallbackLoading = false;
            this.pickStep = 'start';
            this.pickedStart = null;
            this.lat = null;
            this.lon = null;

            const startVal = this.val('start_date');
            const endVal = this.val('end_date');
            const timeOf = (v, fallback) => {
                const m = (v || '').match(/T(\d{2}:\d{2})/);
                return m ? m[1] : fallback;
            };
            this.startTime = timeOf(startVal, '09:00');
            this.endTime = timeOf(endVal, '11:00');

            this.venue = this.val('venue');
            this.needsLocationSearch = (this.venue === 'Outside of School');

            const f = this.form();
            this.excludeId = null;
            if (this.mode === 'edit' && f) {
                const m = (f.getAttribute('action') || '').match(/events\/(\d+)/);
                if (m) this.excludeId = parseInt(m[1], 10);
            }

            let base = startVal ? new Date(startVal) : new Date();
            if (isNaN(base.getTime())) base = new Date();
            const pad = (n) => String(n).padStart(2, '0');
            this.monthValue = base.getFullYear() + '-' + pad(base.getMonth() + 1);

            this.locationLabel = this.needsLocationSearch ? 'Choose a location below' : 'LSPU Sta. Cruz Campus';
            this.open = true;
            document.body.style.overflow = 'hidden';

            if (!this.needsLocationSearch) this.refetch();
        },
        close() {
            this.open = false;
            document.body.style.overflow = '';
            clearTimeout(this.geoTimer);
            if (this.geoAbort) this.geoAbort.abort();
        },

        currentMonthKey() {
            const n = new Date();
            return n.getFullYear() + '-' + String(n.getMonth() + 1).padStart(2, '0');
        },
        maxMonthKey() {
            const n = new Date();
            n.setMonth(n.getMonth() + 2);
            return n.getFullYear() + '-' + String(n.getMonth() + 1).padStart(2, '0');
        },
        canPage(d) {
            const [y, m] = (this.monthValue || '').split('-').map(Number);
            if (!y || !m) return false;
            const t = new Date(y, m - 1 + d, 1);
            const key = t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0');
            return key >= this.currentMonthKey() && key <= this.maxMonthKey();
        },
        changeMonth(d) {
            if (!this.canPage(d)) return;
            const [y, m] = this.monthValue.split('-').map(Number);
            const t = new Date(y, m - 1 + d, 1);
            this.monthValue = t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0');
            this.resetPick();
            this.refetch();
        },

        onGeoInput() {
            clearTimeout(this.geoTimer);
            if ((this.geoQuery || '').trim().length < 3) {
                this.geoResults = [];
                return;
            }
            this.geoTimer = setTimeout(() => this.searchPlace(false), 400);
        },
        searchPlace(manual) {
            const q = (this.geoQuery || '').trim();
            if (q.length < 3) return;
            clearTimeout(this.geoTimer);
            if (this.geoAbort) this.geoAbort.abort();
            this.geoAbort = new AbortController();
            this.geoLoading = true;
            this.geoResults = [];
            const root = document.getElementById('smart-scheduler-root');
            fetch(root.dataset.geocodeUrl + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json' },
                signal: this.geoAbort.signal,
            })
                .then((r) => r.json())
                .then((d) => {
                    const results = d.results || [];
                    if (results.length) {
                        this.geoResults = results;
                        this.geoLoading = false;
                    } else {
                        // Server quota exhausted — ask Open-Meteo directly from the browser.
                        this.searchPlaceDirect(q, this.geoAbort.signal);
                    }
                })
                .catch((e) => {
                    if (e && e.name === 'AbortError') return;
                    this.searchPlaceDirect(q, null);
                });
        },
        searchPlaceDirect(q, signal) {
            fetch('https://geocoding-api.open-meteo.com/v1/search?name=' + encodeURIComponent(q) + '&count=5&language=en&format=json&countryCode=PH', signal ? { signal: signal } : {})
                .then((r) => r.json())
                .then((d) => {
                    this.geoResults = (d.results || [])
                        .filter((r) => r.latitude !== undefined)
                        .map((r) => ({
                            name: r.name + (r.admin1 ? ', ' + r.admin1 : ''),
                            latitude: r.latitude,
                            longitude: r.longitude,
                            country: r.country || null,
                            admin1: r.admin1 || null,
                        }));
                    if (!this.geoResults.length) showToast('No places found in the Philippines. Try another keyword.', 'error');
                })
                .catch((e) => {
                    if (e && e.name === 'AbortError') return;
                    showToast('Location search failed. Try again.', 'error');
                })
                .finally(() => { this.geoLoading = false; });
        },
        pickPlace(r) {
            this.lat = r.latitude;
            this.lon = r.longitude;
            this.locationLabel = r.name;
            this.locationChosen = true;
            this.searchMode = false;
            this.setVenueOutside();
            this.refetch();
        },
        setVenueOutside() {
            // Picking a custom place means the event is outside school —
            // flip the main form's venue automatically.
            const f = this.form();
            if (f) {
                const scope = window.Alpine && window.Alpine.$data ? window.Alpine.$data(f) : null;
                if (scope && 'selectedVenue' in scope) {
                    scope.selectedVenue = 'Outside of School';
                    if ('isCustomVenue' in scope) scope.isCustomVenue = false;
                    if ('customVenue' in scope) scope.customVenue = '';
                    if ('venueConflictMessage' in scope) scope.venueConflictMessage = '';
                } else {
                    const sel = Array.from(f.querySelectorAll('select'))
                        .find((s) => s.querySelector('option[value="__other__"]'));
                    if (sel) {
                        sel.value = 'Outside of School';
                        sel.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            }
            this.venue = 'Outside of School';
            this.needsLocationSearch = true;
            showToast('Venue set to Outside of School.', 'success');
        },

        refetch() {
            if (this.needsLocationSearch && !this.locationChosen) return;
            if (this.startTime && this.endTime && this.endTime <= this.startTime) {
                const [h, m] = this.startTime.split(':').map(Number);
                const d = new Date(2000, 0, 1, h, m + 120);
                this.endTime = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
            }
            this.loading = true;
            this.error = '';
            const root = document.getElementById('smart-scheduler-root');
            const params = new URLSearchParams({
                month: this.monthValue,
                start_time: this.startTime || '09:00',
                end_time: this.endTime || '11:00',
            });
            if (this.venue) params.set('venue', this.venue);
            if (this.lat !== null) params.set('latitude', this.lat);
            if (this.lon !== null) params.set('longitude', this.lon);
            if (this.locationChosen) params.set('location_label', this.locationLabel);
            if (this.excludeId) params.set('exclude_event_id', this.excludeId);

            fetch(root.dataset.suggestUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
                .then((r) => {
                    if (!r.ok) throw new Error('Request failed');
                    return r.json();
                })
                .then((d) => {
                    if (!d.success) throw new Error('Request failed');
                    this.monthLabel = d.month_label || '';
                    if (d.location && d.location.label) this.locationLabel = d.location.label;
                    this.weatherAvailable = d.weather_available !== false;
                    this.weatherError = d.weather_error || null;
                    this.suggestions = (d.suggestions || []).map((s) => ({
                        ...s,
                        details: this.describe(s),
                    }));
                    this.forecastNote = d.forecast_available_until
                        ? 'Forecast available until ' + d.forecast_available_until + ' · days after that show “No forecast”.'
                        : '';
                    if (!this.suggestions.length) this.error = 'No suggestible days left this month.';
                    if (!this.weatherAvailable) this.tryBrowserFallback();
                })
                .catch(() => { this.error = 'Could not load weather suggestions. Check your connection.'; })
                .finally(() => { this.loading = false; });
        },

        weatherMessage() {
            if (this.weatherError === 'rate_limited') {
                return 'The weather service is busy (too many requests). Trying a direct connection instead — or press Retry in a bit.';
            }
            return 'Weather data is unavailable right now — days still show venue availability. Please try again later.';
        },

        // When the server quota is exhausted, fetch Open-Meteo straight from
        // the browser (each visitor has their own quota) and score locally.
        tryBrowserFallback() {
            if (this.fallbackLoading) return;
            if (this.needsLocationSearch && !this.locationChosen) return;
            const lat = this.lat !== null ? this.lat : 14.2628;
            const lon = this.lon !== null ? this.lon : 121.3974;
            const [y, m] = (this.monthValue || '').split('-').map(Number);
            if (!y || !m) return;

            const pad = (n) => String(n).padStart(2, '0');
            const fmt = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const first = new Date(y, m - 1, 1);
            const last = new Date(y, m, 0);
            const start = first > today ? first : today;
            const horizon = new Date(today);
            horizon.setDate(horizon.getDate() + 15);
            const end = last < horizon ? last : horizon;
            if (end < start) return;

            this.fallbackLoading = true;
            const url = 'https://api.open-meteo.com/v1/forecast?latitude=' + lat + '&longitude=' + lon
                + '&daily=weathercode,temperature_2m_max,precipitation_probability_max,wind_speed_10m_max'
                + '&hourly=temperature_2m,precipitation_probability,weathercode,wind_speed_10m'
                + '&timezone=Asia%2FManila&start_date=' + fmt(start) + '&end_date=' + fmt(end);

            fetch(url)
                .then((r) => {
                    if (!r.ok) throw new Error('Direct fetch failed');
                    return r.json();
                })
                .then((d) => {
                    const daily = d.daily || {};
                    const times = daily.time || [];
                    if (!times.length) throw new Error('No data');
                    const hourlyIndex = this.indexHourly(d.hourly || {});
                    const windowLabel = this.windowLabel(this.startTime || '09:00', this.endTime || '11:00');
                    let filled = 0;
                    this.suggestions = this.suggestions.map((s) => {
                        if (s.score !== null) return s;
                        const i = times.indexOf(s.date);
                        if (i === -1) return s;
                        let w = this.aggregateWindow(hourlyIndex, s.date, this.startTime || '09:00', this.endTime || '11:00');
                        let hourlyScored = true;
                        if (!w) {
                            hourlyScored = false;
                            w = {
                                temperature_max: daily.temperature_2m_max ? daily.temperature_2m_max[i] : null,
                                precipitation_probability: daily.precipitation_probability_max ? daily.precipitation_probability_max[i] : null,
                                wind_speed_max: daily.wind_speed_10m_max ? daily.wind_speed_10m_max[i] : null,
                                weathercode: daily.weathercode ? daily.weathercode[i] : null,
                            };
                        }
                        const scored = this.scoreDay(w);
                        filled++;
                        const merged = {
                            ...s,
                            score: scored.score,
                            label: scored.label,
                            reason: scored.reason,
                            temperature_max: w.temperature_max,
                            precipitation_probability: w.precipitation_probability,
                            wind_speed_max: w.wind_speed_max,
                            weathercode: w.weathercode,
                            icon: this.iconFor(w.weathercode),
                            hourly_scored: hourlyScored,
                            window_label: windowLabel,
                            selectable: !s.conflicted,
                        };
                        merged.details = this.describe(merged);
                        return merged;
                    });
                    if (filled > 0) {
                        this.suggestions.sort((a, b) => (a.date < b.date ? -1 : a.date > b.date ? 1 : 0));
                        this.weatherAvailable = true;
                        this.weatherError = null;
                        this.forecastNote = 'Forecast available until ' + fmt(end) + ' · days after that show “No forecast”.';
                    }
                })
                .catch(() => { /* warning banner stays visible */ })
                .finally(() => { this.fallbackLoading = false; });
        },

        indexHourly(hourly) {
            const out = {};
            const times = (hourly && hourly.time) || [];
            for (let i = 0; i < times.length; i++) {
                out[times[i]] = {
                    temperature: hourly.temperature_2m ? hourly.temperature_2m[i] : null,
                    precipitation_probability: hourly.precipitation_probability ? hourly.precipitation_probability[i] : null,
                    weathercode: hourly.weathercode ? hourly.weathercode[i] : null,
                    wind_speed: hourly.wind_speed_10m ? hourly.wind_speed_10m[i] : null,
                };
            }
            return out;
        },

        // Same rules as the server: rain = mean probability in the event
        // window; weathercode = worst in the window; temp/wind = max.
        aggregateWindow(index, date, startTime, endTime) {
            const probs = [];
            const temps = [];
            const winds = [];
            let worstCode = null;
            let worstRank = -1;
            for (let h = 0; h < 24; h++) {
                const hm = String(h).padStart(2, '0') + ':00';
                if (hm < startTime || hm >= endTime) continue;
                const row = index[date + 'T' + hm];
                if (!row) continue;
                if (row.precipitation_probability !== null && row.precipitation_probability !== undefined) probs.push(row.precipitation_probability);
                if (row.temperature !== null && row.temperature !== undefined) temps.push(row.temperature);
                if (row.wind_speed !== null && row.wind_speed !== undefined) winds.push(row.wind_speed);
                if (row.weathercode !== null && row.weathercode !== undefined) {
                    const rank = this.weathercodeSeverity(row.weathercode);
                    if (rank > worstRank) {
                        worstRank = rank;
                        worstCode = row.weathercode;
                    }
                }
            }
            if (!probs.length && !temps.length && !winds.length && worstCode === null) return null;
            const avg = (a) => a.reduce((x, y) => x + y, 0) / a.length;
            return {
                temperature_max: temps.length ? Math.max(...temps) : null,
                precipitation_probability: probs.length ? Math.round(avg(probs)) : null,
                wind_speed_max: winds.length ? Math.max(...winds) : null,
                weathercode: worstCode,
            };
        },

        weathercodeSeverity(code) {
            if ([95, 96, 99].includes(code)) return 3;
            if ([51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82].includes(code)) return 2;
            if ([3, 45, 48].includes(code)) return 1;
            if (code === 2) return 0;
            return -1;
        },

        windowLabel(startTime, endTime) {
            const fmtT = (t) => {
                const parts = (t || '').split(':').map(Number);
                if (parts.length < 2 || isNaN(parts[0])) return t;
                let h = parts[0];
                const suffix = h >= 12 ? 'PM' : 'AM';
                h = h % 12;
                if (h === 0) h = 12;
                return h + ' ' + suffix;
            };
            return fmtT(startTime) + ' - ' + fmtT(endTime);
        },

        scoreDay(w) {
            let score = 100;
            const notes = [];
            const rain = w.precipitation_probability || 0;
            if (rain > 0) {
                score -= Math.round(rain * 0.6);
                if (rain >= 60) notes.push(rain + '% chance of rain');
            }
            const code = w.weathercode ?? 0;
            if ([95, 96, 99].includes(code)) {
                score -= 30;
                notes.push('Thunderstorms likely');
            } else if ([51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82].includes(code)) {
                score -= 20;
                notes.push('Rain expected');
            } else if ([3, 45, 48].includes(code)) {
                score -= 8;
                notes.push('Overcast');
            } else if (code === 2) {
                score -= 3;
            }
            const wind = w.wind_speed_max || 0;
            if (wind >= 30) {
                score -= 15;
                notes.push('Strong winds');
            } else if (wind >= 20) {
                score -= 8;
                notes.push('Breezy');
            } else if (wind >= 12) {
                score -= 3;
            }
            const temp = w.temperature_max;
            if (temp !== null && temp !== undefined && (temp >= 36 || temp <= 17)) {
                score -= 5;
                notes.push(temp >= 36 ? 'Extreme heat' : 'Unusually cool');
            }
            score = Math.max(0, Math.min(100, score));
            const label = score >= 80 ? 'Best' : score >= 60 ? 'Good' : score >= 40 ? 'Fair' : 'Avoid';
            if (label === 'Best') notes.push('Ideal conditions');
            return { score: score, label: label, reason: [...new Set(notes)].join(' · ') || '—' };
        },

        iconFor(code) {
            if ([95, 96, 99].includes(code)) return 'ph-cloud-lightning';
            if ([51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82].includes(code)) return 'ph-cloud-rain';
            if ([3, 45, 48].includes(code)) return 'ph-cloud';
            if (code === 2) return 'ph-cloud-sun';
            return 'ph-sun';
        },

        describe(s) {
            if (s.score === null) return 'No forecast data for this day';
            const parts = [];
            if (s.window_label) parts.push(s.window_label);
            if (s.temperature_max !== null) parts.push(Math.round(s.temperature_max) + '°C');
            if (s.precipitation_probability !== null) parts.push(s.precipitation_probability + '% rain');
            if (s.wind_speed_max !== null) parts.push(Math.round(s.wind_speed_max) + ' km/h');
            if (s.reason && s.reason !== '—') parts.push(s.reason);
            return parts.join(' · ');
        },

        // Suggested = good weather only (Best/Good), nearest day first.
        topPicks() {
            return this.suggestions
                .filter((s) => s.score !== null && !s.conflicted && (s.label === 'Best' || s.label === 'Good'))
                .slice()
                .sort((a, b) => (a.date < b.date ? -1 : a.date > b.date ? 1 : 0))
                .slice(0, 3);
        },

        // Least rain this month, regardless of label.
        driest() {
            return this.suggestions
                .filter((s) => s.score !== null && s.precipitation_probability !== null)
                .slice()
                .sort((a, b) => a.precipitation_probability - b.precipitation_probability)
                .slice(0, 3);
        },

        resetPick() {
            this.pickedStart = null;
            this.pickStep = 'start';
        },

        setFormDate(name, date, time) {
            const f = this.form();
            if (!f) return;
            const input = f.querySelector('[name="' + name + '"]');
            if (!input) return;
            input.value = date + 'T' + time;
            if (name === 'start_date') {
                const endInput = f.querySelector('[name="end_date"]');
                if (endInput) endInput.setAttribute('min', input.value);
            }
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        },

        // Two-tap range pick: first tap sets the start date, second tap sets
        // the end date (tap the same day again for a single-day event).
        pickDay(s) {
            if (s.score === null) {
                showToast('No forecast data for this day.', 'error');
                return;
            }
            if (s.conflicted) {
                showToast('That day is already booked for this venue.', 'error');
                return;
            }
            if (this.pickStep === 'start' || !this.pickedStart) {
                this.pickedStart = s.date;
                this.setFormDate('start_date', s.date, this.startTime || '09:00');
                this.pickStep = 'end';
                showToast('Start set to ' + s.date + '. Now tap the end date (same day for a single-day event).', 'success');
                return;
            }
            let start = this.pickedStart;
            let end = s.date;
            if (end < start) {
                const tmp = start;
                start = end;
                end = tmp;
            }
            let endTime = this.endTime || '11:00';
            const startTime = this.startTime || '09:00';
            if (start === end && endTime <= startTime) {
                const parts = startTime.split(':').map(Number);
                const d = new Date(2000, 0, 1, parts[0], parts[1] + 120);
                endTime = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
                this.endTime = endTime;
            }
            this.setFormDate('start_date', start, startTime);
            this.setFormDate('end_date', end, endTime);
            this.resetPick();
            this.close();
            showToast('Dates set: ' + start + ' to ' + end + '. You can still adjust the time.', 'success');
        },
    };
}
</script>

<style>
.ss-backdrop { backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); }
.ss-picked { border-color: var(--primary-color) !important; box-shadow: 0 0 0 2px var(--primary-color); }
.ss-top-pick { animation: ss-card-in 0.5s cubic-bezier(0.32, 0.72, 0, 1) both; }
.ss-card { animation: ss-card-in 0.5s cubic-bezier(0.32, 0.72, 0, 1) both; }
@keyframes ss-card-in {
    from { opacity: 0; transform: translateY(14px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.ss-shimmer > div { animation: ss-pulse 1.4s ease-in-out infinite; }
@keyframes ss-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.45; }
}
</style>
