@extends(request()->boolean('fragment') ? 'layouts.fragment' : 'layouts.admin')

@section('title', 'Settings - Admin')

@push('styles')
<style>
    .color-picker-button { position: relative; overflow: hidden; transition: all 0.3s ease; }
    .color-picker-button:hover { transform: translateY(-2px); }
    input[type="color"] { position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none; }
    button { transition: all 0.3s ease; }
    .color-display-box { box-shadow: 0 2px 8px rgba(0,0,0,.1); transition: all 0.3s ease; }
    .color-display-box:hover { box-shadow: 0 4px 12px rgba(var(--primary-color-rgb,130,56,179),.3); transform: translateY(-2px); }
    .logo-file-input::file-selector-button,
    .logo-file-input::-webkit-file-upload-button {
        margin-right: 1rem;
        background-color: rgba(var(--complementary-color-rgb,215,172,40),.1);
        color: var(--complementary-color,#D7AC28); transition: all 0.3s ease;
    }
    .logo-file-input:hover::file-selector-button,
    .logo-file-input:hover::-webkit-file-upload-button {
        background-color: rgba(var(--complementary-color-rgb,215,172,40),.2);
    }
    /* ── Design tokens ─────────────────────────────────────────────────── */
    .settings-shell { --border: #e5e7eb; --border-soft: #eef0f2; --ink: #0f1115; --ink-muted: #6b7280;
        --ink-faint: #9aa1ac; --surface: #ffffff; --surface-sunken: #fafafa; }

    /* ── Segmented tab control (Linear-style) ─────────────────────────── */
    .tab-segment { display: inline-flex; align-items: center; gap: 2px; padding: 3px; background: #f3f4f6;
        border-radius: 8px; }
    .settings-tab { cursor: pointer; padding: 0.4375rem 0.875rem; font-weight: 500; font-size: 0.8125rem;
        border-radius: 6px; transition: color .15s ease, background-color .15s ease; color: #6b7280;
        line-height: 1.25rem; letter-spacing: -0.006em; }
    .settings-tab.active { color: #0f1115; background: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,.06), 0 0 0 1px rgba(0,0,0,.04); }
    .settings-tab:hover:not(.active) { color: #111827; }

    /* ── Switch (Stripe/GitHub-scale, smaller and quieter than a chunky pill) ── */
    .switch { width: 34px; height: 20px; border-radius: 999px; position: relative; flex-shrink: 0;
        transition: background-color .15s ease; cursor: pointer; border: none; padding: 0; }
    .switch-knob { width: 16px; height: 16px; border-radius: 999px; background: #fff; position: absolute;
        top: 2px; left: 2px; transition: transform .15s ease; box-shadow: 0 1px 2px rgba(0,0,0,.25); }
    .switch[data-on="true"] { background-color: var(--primary-color, #6238C4); }
    .switch[data-on="false"] { background-color: #d8dbe0; }
    .switch[data-on="true"] .switch-knob { transform: translateX(14px); }
    .switch:disabled { opacity: .55; cursor: default; }
    .switch:focus-visible { outline: 2px solid var(--primary-color, #6238C4); outline-offset: 2px; }

    /* ── Row list (fine-grained permission list, GitHub Settings-style) ── */
    .admin-row { border-top: 1px solid #eef0f2; }
    .admin-row:first-child { border-top: none; }
    .avatar-stack img, .avatar-stack .avatar-fallback { box-shadow: 0 0 0 2px #fff; }
    .perm-row + .perm-row { border-top: 1px solid #f3f4f6; }
    .disclosure-icon { transition: transform .15s ease; }
    .disclosure-open .disclosure-icon { transform: rotate(90deg); }

    /* Subtle scale-in for expanding panels (no bounce, no drama) */
    .panel-enter { transition: opacity .12s ease; }

    /* ── Manage Admins: master-detail layout ─────────────────────────── */
    /* Exactly 9 role rows visible (each row ≈ 53px incl. padding + border); beyond that, scroll. */
    .role-list { max-height: 477px; overflow-y: auto; }
    .role-list-group-label { padding: 0.625rem 0.875rem 0.25rem; font-size: 0.625rem;
        font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #9aa1ac;
        background: #fcfcfd; }
    .role-list-group-label + .role-list-item { border-top: none; }
    .role-list-item { border-top: 1px solid #f1f2f4; cursor: pointer; transition: background-color .1s ease; }
    .role-list-item:first-child { border-top: none; }
    .role-list-item:hover { background: #fafafa; }
    .role-list-item.active { background: #f5f3ff; }
    .role-list-item.active .role-list-bar { opacity: 1; }
    .role-list-bar { position: absolute; left: 0; top: 0; bottom: 0; width: 2.5px; opacity: 0;
        background-color: var(--primary-color, #6238C4); }
    .detail-panel { max-height: 477px; overflow-y: auto; }
    .perm-row { min-height: 52px; }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"
     x-data="settingsPage(@js($zeroAccessCount), @js($isAdviser))"
     x-on:show-success.window="successMessage=$event.detail"
     x-on:show-error.window="errorMessage=$event.detail">

    {{-- ── Toast Notifications ─────────────────────────────────────────── --}}
    {{-- Session success --}}
    @if(session('success'))
    <div x-data="{show:true}" x-show="show" x-init="setTimeout(()=>show=false,5000)"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-8"
         x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-8"
         class="fixed top-6 right-6 z-50 bg-white border-l-4 border-green-500 rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md" style="display:none;">
        <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
            <i class="ph ph-check-circle text-xl text-green-600"></i></div>
        <div class="flex-1"><p class="text-sm font-semibold text-gray-900">Success!</p>
            <p class="text-sm text-gray-600">{{ session('success') }}</p></div>
        <button @click="show=false" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x"></i></button>
    </div>
    @endif

    {{-- Alpine dynamic success --}}
    <div x-show="successMessage" x-init="$watch('successMessage',v=>{if(v){clearTimeout(window._st);window._st=setTimeout(()=>successMessage='',6000)}})"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-8"
         x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-8"
         class="fixed top-6 right-6 z-50 bg-white border-l-4 border-green-500 rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md" style="display:none;">
        <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
            <i class="ph ph-check-circle text-xl text-green-600"></i></div>
        <div class="flex-1"><p class="text-sm font-semibold text-gray-900">Success!</p>
            <p class="text-sm text-gray-600" x-text="successMessage"></p></div>
        <button @click="successMessage=''" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x"></i></button>
    </div>

    {{-- Session/Alpine error --}}
    @if(session('error'))
    <div x-data="{show:true}" x-show="show" x-init="setTimeout(()=>show=false,5000)"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-8"
         x-transition:enter-end="opacity-100 translate-x-0"
         class="fixed top-6 right-6 z-50 bg-white border-l-4 border-red-500 rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md" style="display:none;">
        <div class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
            <i class="ph ph-warning-circle text-xl text-red-600"></i></div>
        <div class="flex-1"><p class="text-sm font-semibold text-gray-900">Error!</p>
            <p class="text-sm text-gray-600">{{ session('error') }}</p></div>
        <button @click="show=false" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x"></i></button>
    </div>
    @endif

    <div x-show="errorMessage" x-init="$watch('errorMessage',v=>{if(v){clearTimeout(window._et);window._et=setTimeout(()=>errorMessage='',6000)}})"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-8"
         x-transition:enter-end="opacity-100 translate-x-0"
         class="fixed top-6 right-6 z-50 bg-white border-l-4 border-red-500 rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md" style="display:none;">
        <div class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
            <i class="ph ph-warning-circle text-xl text-red-600"></i></div>
        <div class="flex-1"><p class="text-sm font-semibold text-gray-900">Error!</p>
            <p class="text-sm text-gray-600" x-text="errorMessage"></p></div>
        <button @click="errorMessage=''" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x"></i></button>
    </div>

    {{-- ── Page header ──────────────────────────────────────────────────── --}}
    <div class="flex items-end justify-between mb-3">
        <div>
            <h1 class="text-[1.25rem] font-semibold text-gray-900 tracking-[-0.01em] leading-tight">Settings</h1>
            <p class="text-[0.8125rem] text-gray-500">Manage your organization's branding and admin access</p>
        </div>
        <div class="tab-segment">
            <button type="button" @click="activeTab='theme'"
                :class="activeTab==='theme' ? 'active' : ''"
                class="settings-tab">
                Theme
            </button>
            <button type="button" @click="activeTab='admins'"
                :class="activeTab==='admins' ? 'active' : ''"
                class="settings-tab relative">
                Admins
                <span x-show="adminsBadgeCount > 0" x-cloak x-text="adminsBadgeCount"
                      class="absolute -top-1 -right-1 min-w-[16px] h-4 px-1 rounded-full text-[10px] font-bold text-white flex items-center justify-center"
                      style="background-color:var(--complementary-color);box-shadow:0 0 0 2px #fff"
                      title="Roles without module access"></span>
            </button>
            <button type="button" @click="activeTab='positions'"
                :class="activeTab==='positions' ? 'active' : ''"
                class="settings-tab">
                Add Positions
            </button>
            @if($isAdviser)
            <button type="button" @click="activeTab='accounts'"
                :class="activeTab==='accounts' ? 'active' : ''"
                class="settings-tab">
                Manage Student Accounts
            </button>
            @endif
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- TAB 1 — THEME SETTINGS                                           --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div x-show="activeTab==='theme'" x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         class="bg-white rounded-xl border border-gray-200"
         x-data="settingsManager()" @submit-settings-form.window="submitForm($event.detail.event)" @settings-changed.window="markAsChanged()">

        <div class="p-5">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5"
                 x-data="previewRenderer({ primary_color: '{{ $theme['primary_color'] ?? 'var(--primary-color)' }}', complementary_color: '{{ $theme['complementary_color'] ?? 'var(--complementary-color)' }}', logo_url: '{{ $theme['logo_url'] ?? asset('images/soccsyncLogo-removebg-preview.png') }}' })">

                {{-- Left: Form --}}
                <div class="bg-gray-50 rounded-xl border border-gray-200 p-5">
                    <form id="settings-form" @submit.prevent="$dispatch('submit-settings-form',{event:$event})"
                          @change="$dispatch('settings-changed')" @input="$dispatch('settings-changed')"
                          enctype="multipart/form-data" method="POST" action="{{ route('admin.settings.update') }}">
                        @csrf

                        {{-- Primary Color --}}
                        <div class="mb-4" x-data="colorPicker('{{ $theme['primary_color'] ?? 'var(--primary-color)' }}','primary')">
                            <h3 class="text-sm font-semibold text-gray-900 flex items-center">
                                <i style="color:var(--primary-color)" class="ph ph-palette mr-2"></i>Primary Color</h3>
                            <p class="text-xs text-gray-500 mb-2.5">Main brand color for navigation and buttons</p>
                            <div class="flex items-start space-x-3">
                                <div class="relative flex-shrink-0">
                                    <div class="w-11 h-11 rounded-lg border-2 border-gray-300 cursor-pointer color-display-box"
                                         :style="`background-color:${color}`" @click="$refs.pcInput.click()"></div>
                                    <input type="color" x-ref="pcInput" x-model="color" name="primary_color" class="absolute opacity-0 w-0 h-0">
                                </div>
                                <div class="flex-1">
                                    <div class="flex space-x-2">
                                        <input type="text" x-model="hexInput" @input="updateFromHex()" placeholder="#RRGGBB"
                                               class="flex-1 px-3 py-1.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2"
                                               style="border-color:var(--primary-color);--tw-ring-color:var(--primary-color)">
                                        <button type="button" @click="generateRandomColor()" style="background-color:var(--primary-color)"
                                                class="px-3 py-1.5 rounded-lg text-white text-sm font-semibold flex items-center shadow hover:opacity-90">
                                            <i class="ph ph-shuffle mr-1" style="color:var(--complementary-color)"></i>Random</button>
                                    </div>
                                    <div x-show="error" class="mt-1 text-xs text-red-600" x-text="error"></div>
                                </div>
                            </div>
                        </div>
                        <hr class="my-3.5 border-gray-200">

                        {{-- Complementary Color --}}
                        <div class="mb-4" x-data="colorPicker('{{ $theme['complementary_color'] ?? 'var(--complementary-color)' }}','complementary')">
                            <h3 class="text-sm font-semibold text-gray-900 flex items-center">
                                <i style="color:var(--complementary-color)" class="ph ph-star mr-2"></i>Complementary Color
                                <span x-show="!manualMode" class="ml-2 text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded">Auto</span>
                                <span x-show="manualMode"  class="ml-2 text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">Manual</span>
                            </h3>
                            <p class="text-xs text-gray-500 mb-2.5">Accent color for icons and highlights</p>
                            <div class="flex items-start space-x-3">
                                <div class="relative flex-shrink-0">
                                    <div class="w-11 h-11 rounded-lg border-2 cursor-pointer transition-all"
                                         :class="manualMode?'border-gray-300 color-display-box':'border-gray-200 opacity-50 cursor-not-allowed'"
                                         :style="`background-color:${color}`" @click="manualMode&&$refs.ccInput.click()"></div>
                                    <input type="color" x-ref="ccInput" x-model="color" class="absolute opacity-0 w-0 h-0">
                                    <input type="hidden" name="complementary_color" :value="color">
                                </div>
                                <div class="flex-1">
                                    <div class="flex space-x-2">
                                        <input type="text" x-model="hexInput" @input="manualMode&&updateFromHex()" :readonly="!manualMode"
                                               :class="manualMode?'':'bg-gray-50 cursor-not-allowed'"
                                               class="flex-1 px-3 py-1.5 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2"
                                               style="border-color:var(--primary-color);--tw-ring-color:var(--primary-color)">
                                        <button type="button" @click="toggleManualMode()"
                                                class="px-3 py-1.5 bg-gray-600 hover:bg-gray-700 text-white rounded-lg text-sm font-semibold flex items-center shadow whitespace-nowrap">
                                            <i class="ph mr-1" :class="manualMode?'ph-magic-wand':'ph-hand-pointing'"></i>
                                            <span x-text="manualMode?'Auto':'Manual'"></span></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr class="my-3.5 border-gray-200">

                        {{-- Logo Upload --}}
                        <div x-data="logoUploader('{{ $theme['logo_url'] ?? asset('images/soccsyncLogo-removebg-preview.png') }}')">
                            <h3 class="text-sm font-semibold text-gray-900 flex items-center">
                                <i style="color:var(--primary-color)" class="ph ph-image mr-2"></i>Organization Logo</h3>
                            <p class="text-xs text-gray-500 mb-2.5">Upload PNG logo (Max 2MB)</p>
                            <div class="flex items-center space-x-3">
                                <img :src="logoPreview" alt="Logo" class="h-14 w-14 object-contain border-2 border-gray-200 rounded-lg p-1.5 bg-white flex-shrink-0">
                                <input type="file" name="logo" accept="image/png,image/jpeg,image/jpg,image/svg+xml,image/webp"
                                       @change="handleFileSelect($event)"
                                       style="color:var(--primary-color)" class="block w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:text-gray-700 cursor-pointer logo-file-input">
                            </div>
                            <div x-show="error" class="mt-2 text-xs text-red-600" x-text="error"></div>
                        </div>
                    </form>
                </div>

                {{-- Right: Live Preview --}}
                <div class="lg:sticky lg:top-5 lg:self-start">
                    <div class="bg-gray-50 rounded-xl border border-gray-200 p-5">
                        <h3 class="text-sm font-semibold text-gray-900 flex items-center">
                            <i style="color:var(--primary-color)" class="ph ph-eye mr-2"></i>Live Preview</h3>
                        <p class="text-xs text-gray-500 mb-3">Changes update instantly as you adjust colors</p>

                        <div class="border-2 border-gray-200 rounded-lg overflow-hidden shadow-sm bg-white mb-3">
                            {{-- Mini nav bar (mimics student navigation) --}}
                            <div class="bg-white shadow px-3 py-2">
                                <div class="flex items-center space-x-3">
                                    <div class="flex items-center space-x-2 flex-shrink-0">
                                        <img :src="logoUrl" alt="Logo" class="object-contain" style="width:28px;height:28px">
                                        <span class="text-base font-bold" :style="`color:${primaryColor}`">{{ organization_acronym() }}</span>
                                    </div>
                                    <div class="flex items-center space-x-1 overflow-x-auto flex-1">
                                        <div class="flex items-center px-2 py-1 text-xs text-gray-600">
                                            <i class="ph ph-house text-xs mr-1" :style="`color:${complementaryColor}`"></i>Home</div>
                                        <div class="flex items-center px-2 py-1 text-xs font-medium border-b-2 whitespace-nowrap transition-all"
                                             :style="`color:${primaryColor};border-color:${complementaryColor}`">
                                            <i class="ph ph-calendar-blank text-xs mr-1" :style="`color:${primaryColor}`"></i>Events</div>
                                        <div class="flex items-center px-2 py-1 text-xs text-gray-600">
                                            <i class="ph ph-credit-card text-xs mr-1" :style="`color:${complementaryColor}`"></i>Payments</div>
                                    </div>
                                </div>
                            </div>

                            {{-- Events page mock — mirrors resources/views/student/events/index.blade.php --}}
                            <div class="p-3 bg-gray-50">
                                <div class="flex justify-between items-center mb-2">
                                    <div>
                                        <h1 class="text-base font-bold text-gray-900">Events</h1>
                                        <p class="text-gray-500" style="font-size:10px">Upcoming activities</p>
                                    </div>
                                    <button @click="viewMode = viewMode === 'calendar' ? 'list' : 'calendar'"
                                            class="text-white px-2 py-1 rounded text-xs flex items-center transition-all duration-300"
                                            :style="`background-color:${primaryColor}`">
                                        <i class="ph text-xs mr-1 transition-colors duration-300" :class="viewMode === 'calendar' ? 'ph-list' : 'ph-calendar-blank'" :style="`color:${complementaryColor}`"></i>
                                        <span x-text="viewMode === 'calendar' ? 'List View' : 'Calendar View'"></span>
                                    </button>
                                </div>

                                {{-- Calendar View (default) --}}
                                <div x-show="viewMode === 'calendar'" class="bg-white rounded-lg shadow-sm p-2">
                                    <div class="flex justify-between items-center mb-2">
                                        <div class="flex items-center space-x-1">
                                            <button class="w-5 h-5 rounded flex items-center justify-center text-white transition-all duration-300" :style="`background-color:${primaryColor}`">
                                                <i class="ph ph-caret-left" style="font-size:8px"></i></button>
                                            <button class="w-5 h-5 rounded flex items-center justify-center text-white transition-all duration-300" :style="`background-color:${primaryColor}`">
                                                <i class="ph ph-caret-right" style="font-size:8px"></i></button>
                                        </div>
                                        <h2 class="text-xs font-bold text-gray-900">May 2026</h2>
                                        <button class="px-2 py-0.5 rounded text-white text-xs transition-all duration-300" :style="`background-color:${primaryColor}`">month</button>
                                    </div>
                                    <div class="grid grid-cols-7 gap-px bg-gray-200 border border-gray-200" style="font-size:9px">
                                        <div class="bg-gray-50 font-semibold text-gray-700 py-1 text-center">S</div>
                                        <div class="bg-gray-50 font-semibold text-gray-700 py-1 text-center">M</div>
                                        <div class="bg-gray-50 font-semibold text-gray-700 py-1 text-center">T</div>
                                        <div class="bg-gray-50 font-semibold text-gray-700 py-1 text-center">W</div>
                                        <div class="bg-gray-50 font-semibold text-gray-700 py-1 text-center">T</div>
                                        <div class="bg-gray-50 font-semibold text-gray-700 py-1 text-center">F</div>
                                        <div class="bg-gray-50 font-semibold text-gray-700 py-1 text-center">S</div>
                                        <div class="bg-white p-1 h-8 text-gray-400"></div>
                                        <div class="bg-white p-1 h-8 text-gray-400"></div>
                                        <div class="bg-white p-1 h-8 text-gray-400"></div>
                                        <div class="bg-white p-1 h-8 text-gray-400"></div>
                                        <div class="bg-white p-1 h-8 text-gray-400"></div>
                                        <div class="p-1 h-8 text-gray-900 font-semibold rounded transition-all duration-300" :style="`background-color:${complementaryColor}20`">1</div>
                                        <div class="bg-white p-1 h-8 text-gray-900">2</div>
                                        <template x-for="d in [3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31]" :key="d">
                                            <div class="bg-white p-1 h-8 text-gray-900" x-text="d"></div>
                                        </template>
                                    </div>
                                </div>

                                {{-- List View (3 cards) --}}
                                <div x-show="viewMode === 'list'" class="grid grid-cols-3 gap-2">
                                    <template x-for="card in [{t:'Tech Summit',d:'May 15'},{t:'Leadership',d:'May 1'},{t:'Bootcamp',d:'May 20'}]" :key="card.t">
                                        <div class="bg-white rounded-lg shadow-sm overflow-hidden hover:shadow transition-shadow">
                                            <div class="relative h-16 flex items-center justify-center transition-all duration-300" :style="`background:linear-gradient(to bottom right,${primaryColor},${primaryColor}dd)`">
                                                <i class="ph ph-calendar-blank text-2xl transition-colors duration-300" :style="`color:${complementaryColor}`"></i></div>
                                            <div class="p-1.5">
                                                <p class="text-xs font-bold text-gray-900 truncate" x-text="card.t"></p>
                                                <p class="text-gray-600 mb-1" style="font-size:9px" x-text="card.d"></p>
                                                <button class="w-full text-white px-2 py-1 rounded transition-all duration-300 hover:opacity-90 font-semibold flex items-center justify-center" style="font-size:9px" :style="`background-color:${primaryColor}`">
                                                    <i class="ph ph-eye mr-1 transition-colors duration-300" :style="`color:${complementaryColor};font-size:8px`"></i>View</button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <button type="submit" form="settings-form" id="settings-save-btn" disabled
                                class="text-white w-full px-6 py-3 rounded-lg font-semibold flex items-center justify-center shadow-lg opacity-60 cursor-not-allowed"
                                :style="`background-color:${primaryColor}`">
                            <i id="settings-save-icon" class="ph ph-floppy-disk mr-2" :style="`color:${complementaryColor}`"></i>
                            <span id="settings-save-text">Save Changes</span>
                        </button>
                    </div>
                </div>

            </div>{{-- /grid --}}
        </div>{{-- /p-6 --}}
    </div>{{-- /theme tab --}}

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- TAB 2 — MANAGE ADMINS (master-detail layout)                     --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    @php
        $initialPermsByRole = [];
        foreach ($adminsByRole as $role => $members) {
            $initialPermsByRole[$role] = $rolePermissions[$role] ?? [];
        }
        $firstRole = $adminsByRole->keys()->first();
    @endphp
    <div x-show="activeTab==='admins'" x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         class="bg-white rounded-xl border border-gray-200"
         x-data="adminsPanel(@js($initialPermsByRole), '{{ $firstRole }}', '{{ route('admin.settings.role-permissions.update') }}', '{{ csrf_token() }}')">

        @if($isAdviser && isset($pendingTransition) && $pendingTransition)
        <div id="officer-transition" class="mx-5 mt-4 rounded-xl border-2" style="border-color: var(--primary-color); background: linear-gradient(135deg, rgba(var(--primary-color-rgb),0.06), rgba(var(--complementary-color-rgb,215,172,40),0.04));">
            <div class="px-5 py-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center text-white" style="background-color: var(--primary-color);">
                            <i class="ph ph-trophy text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900">Officer transition awaiting approval</h3>
                            <p class="text-xs text-gray-500">{{ $pendingTransition->election->title ?? 'Election' }} • {{ $pendingTransition->election->end_date ? $pendingTransition->election->end_date->format('M j, Y') : '' }}</p>
                        </div>
                    </div>
                    @if($pendingTransition->status === 'pending')
                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-amber-700">Pending</span>
                    @else
                        <span class="rounded-full bg-blue-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-blue-700">Scheduled</span>
                    @endif
                </div>

                @if($pendingTransition->status === 'scheduled' && $pendingTransition->scheduled_at)
                <div class="mt-3 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 flex items-center gap-2 text-xs text-blue-800">
                    <i class="ph ph-clock text-sm"></i>
                    <span>Scheduled for <strong>{{ $pendingTransition->scheduled_at->format('M j, Y g:i A') }}</strong> — will execute automatically.</span>
                </div>
                @endif

                <div class="mt-4">
                    <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2">Winners ({{ count($officerTransitionWinners) }} positions)</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-64 overflow-y-auto pr-1">
                        @foreach($officerTransitionWinners as $w)
                        <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2">
                            @if($w['photo_url'])
                                <img src="{{ $w['photo_url'] }}" alt="" class="w-8 h-8 rounded-full object-cover">
                            @else
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold" style="background-color: var(--primary-color);">{{ strtoupper(substr($w['candidate_name'],0,1)) }}</div>
                            @endif
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-900 truncate">{{ $w['candidate_name'] }}</p>
                                <p class="text-[11px] text-gray-500 truncate">{{ $w['officer_role'] }} • {{ $w['vote_count'] }} votes</p>
                                <p class="text-[11px] text-gray-400 truncate">{{ $w['student_id'] }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4 rounded-lg bg-white border border-gray-200 p-3">
                    @if($pendingTransition->status === 'pending')
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Effective date (optional — leave blank to approve immediately)</label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <input type="datetime-local" id="transitionScheduledAt" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2" style="--tw-ring-color: var(--primary-color);" min="{{ now()->format('Y-m-d\TH:i') }}">
                            <button type="button" onclick="approveOfficerTransition({{ $pendingTransition->id }})" id="approveTransitionBtn" class="rounded-lg px-4 py-2 text-sm font-semibold text-white whitespace-nowrap" style="background-color: var(--primary-color);">
                                <i class="ph ph-check-circle mr-1.5"></i>Approve officers
                            </button>
                        </div>
                        <p class="mt-1.5 text-[11px] text-gray-400">Previous officers will become <span class="font-medium text-gray-600">Member/Student</span> and lose Admin access; re-elected officers are retained.</p>
                    @else
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Reschedule effective date</label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <input type="datetime-local" id="rescheduleScheduledAt" value="{{ $pendingTransition->scheduled_at ? $pendingTransition->scheduled_at->format('Y-m-d\TH:i') : '' }}" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2" style="--tw-ring-color: var(--primary-color);" min="{{ now()->format('Y-m-d\TH:i') }}">
                            <button type="button" onclick="rescheduleOfficerTransition({{ $pendingTransition->id }})" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reschedule</button>
                            <button type="button" onclick="cancelOfficerTransition({{ $pendingTransition->id }})" class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">Cancel schedule</button>
                        </div>
                    @endif
                    <div id="transitionFeedback" class="mt-2 hidden rounded-md px-3 py-2 text-xs"></div>
                </div>
            </div>
        </div>
        <script>
        function showTransitionFeedback(msg, isError) {
            const el = document.getElementById('transitionFeedback');
            if (!el) return;
            el.textContent = msg;
            el.className = 'mt-2 rounded-md px-3 py-2 text-xs ' + (isError ? 'border border-red-200 bg-red-50 text-red-700' : 'border border-green-200 bg-green-50 text-green-700');
            el.classList.remove('hidden');
        }
        function approveOfficerTransition(transitionId) {
            const input = document.getElementById('transitionScheduledAt');
            const scheduledAt = input && input.value ? input.value : null;
            const btn = document.getElementById('approveTransitionBtn');
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="ph ph-spinner ph-spin mr-1.5"></i>Processing...'; }
            fetch('{{ route('admin.settings.officer-transition.approve') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify({ transition_id: transitionId, scheduled_at: scheduledAt })
            })
            .then(async r => { const d = await r.json(); if (!r.ok) throw new Error(d.message || 'Failed'); return d; })
            .then(d => { showTransitionFeedback(d.message, false); setTimeout(() => location.reload(), 1500); })
            .catch(e => { showTransitionFeedback(e.message, true); if (btn) { btn.disabled = false; btn.innerHTML = '<i class="ph ph-check-circle mr-1.5"></i>Approve officers'; } });
        }
        function rescheduleOfficerTransition(transitionId) {
            const input = document.getElementById('rescheduleScheduledAt');
            const scheduledAt = input.value;
            if (!scheduledAt) { showTransitionFeedback('Pick a new effective date.', true); return; }
            fetch('{{ route('admin.settings.officer-transition.reschedule') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify({ transition_id: transitionId, scheduled_at: scheduledAt })
            })
            .then(async r => { const d = await r.json(); if (!r.ok) throw new Error(d.message || 'Failed'); return d; })
            .then(d => { showTransitionFeedback(d.message, false); setTimeout(() => location.reload(), 1200); })
            .catch(e => showTransitionFeedback(e.message, true));
        }
        function cancelOfficerTransition(transitionId) {
            if (!confirm('Cancel the scheduled transition? It will return to pending.')) return;
            fetch('{{ route('admin.settings.officer-transition.cancel') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify({ transition_id: transitionId })
            })
            .then(async r => { const d = await r.json(); if (!r.ok) throw new Error(d.message || 'Failed'); return d; })
            .then(d => { showTransitionFeedback(d.message, false); setTimeout(() => location.reload(), 1200); })
            .catch(e => showTransitionFeedback(e.message, true));
        }
        // Auto-open admins tab if transition exists and hash not set
        document.addEventListener('DOMContentLoaded', function() {
            if (window.location.search.includes('tab=admins') || document.getElementById('officer-transition')) {
                // Alpine will handle activeTab via query param on page load, but ensure scroll
                const el = document.getElementById('officer-transition');
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
        </script>
        @endif

        {{-- Compact header: title/desc left, legend right, all on one line --}}
        <div class="flex items-center justify-between gap-4 px-5 py-3 border-b border-gray-100">
            <div class="flex items-baseline gap-2.5 min-w-0">
                <h2 class="text-base font-semibold text-gray-900 flex-shrink-0">Officers &amp; access</h2>
                <p class="text-sm text-gray-500 truncate hidden md:block">
                    Switches grant additional access on top of role defaults — defaults can't be removed here.
                </p>
            </div>
            @if(!$adminsByRole->isEmpty())
            <div class="flex items-center gap-3 text-sm text-gray-400 flex-shrink-0">
                <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-gray-900"></span>Default</span>
                <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full" style="background-color:var(--primary-color)"></span>Granted</span>
            </div>
            @endif
        </div>

        @if($adminsByRole->isEmpty())
            <div class="flex flex-col items-center justify-center text-center py-10 px-6">
                <div class="w-8 h-8 rounded-full bg-gray-50 border border-gray-200 flex items-center justify-center mb-2">
                    <i class="ph ph-users text-gray-300 text-sm"></i>
                </div>
                <p class="text-sm font-medium text-gray-700">No officers yet</p>
                <p class="text-sm text-gray-400 mt-0.5">Assigned officers for this organization will appear here.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-[15rem_1fr]">

                {{-- ── Left: role list (master) ─────────────────────────────── --}}
                <div class="role-list border-b md:border-b-0 md:border-r border-gray-100">
                    @php $defaultGroupShown = false; $otherGroupShown = false; @endphp
                    @foreach($adminsByRole as $role => $members)
                        @if($isDefaultRole[$role] && !$defaultGroupShown)
                            <div class="role-list-group-label">Officers</div>
                            @php $defaultGroupShown = true; @endphp
                        @elseif(!$isDefaultRole[$role] && !$otherGroupShown)
                            <div class="role-list-group-label">Other positions</div>
                            @php $otherGroupShown = true; @endphp
                        @endif
                        <div class="role-list-item relative flex items-center gap-2.5 px-3.5 py-2.5"
                             :class="{ active: selectedRole === '{{ $role }}' }"
                             @click="selectedRole = '{{ $role }}'">
                            <span class="role-list-bar"></span>
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-semibold flex-shrink-0"
                                 style="background-color:var(--primary-color)">
                                {{ strtoupper(substr(str_replace('_', ' ', $role), 0, 1)) }}
                            </div>
                            <span x-show="activeCount('{{ $role }}') === 0" x-cloak
                                  class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                                  style="background-color:var(--complementary-color);box-shadow:0 0 0 2px #fff"
                                  title="No module access yet"></span>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-medium text-gray-900 truncate">{{ str_replace('_', ' ', $role) }}</div>
                                <div class="text-xs text-gray-400">
                                    {{ $members->count() }} {{ Str::plural('person', $members->count()) }} &middot;
                                    <span x-text="activeCount('{{ $role }}') + '/{{ count($initialPermsByRole[$role]) }} modules'"></span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- ── Right: detail panel ──────────────────────────────────── --}}
                <div class="detail-panel">
                    @foreach($adminsByRole as $role => $members)
                        <div x-show="selectedRole === '{{ $role }}'" x-cloak>
                            {{-- Detail header: role name + assigned members inline --}}
                            <div class="flex items-center justify-between gap-3 px-5 py-3 border-b border-gray-100">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="text-base font-semibold text-gray-900 flex-shrink-0">{{ str_replace('_', ' ', $role) }}</span>
                                    <span class="text-gray-300 flex-shrink-0">&middot;</span>
                                    <div class="flex items-center gap-1.5 min-w-0 overflow-x-auto">
                                        @foreach($members as $member)
                                            <span class="inline-flex items-center gap-1.5 pl-1 pr-2 py-1 rounded-full border border-gray-200 bg-gray-50 flex-shrink-0">
                                                @if($member->photo)
                                                    <img src="{{ storage_url($member->photo) }}" alt=""
                                                         class="w-5 h-5 rounded-full object-cover"
                                                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                                    <span class="w-5 h-5 rounded-full items-center justify-center text-white text-[0.625rem] font-medium"
                                                          style="display:none;background-color:var(--primary-color)">{{ strtoupper(substr($member->first_name, 0, 1)) }}</span>
                                                @else
                                                    <span class="w-5 h-5 rounded-full flex items-center justify-center text-white text-[0.625rem] font-medium"
                                                          style="background-color:var(--primary-color)">{{ strtoupper(substr($member->first_name, 0, 1)) }}</span>
                                                @endif
                                                <span class="text-sm text-gray-600 whitespace-nowrap">{{ name_with_suffix($member->first_name, null, $member->last_name, $member->suffix ?? '') }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                                <span class="text-sm text-gray-400 flex-shrink-0" x-text="activeCount('{{ $role }}') + ' of {{ count($initialPermsByRole[$role]) }} modules active'"></span>
                            </div>

                            @if(!$isDefaultRole[$role])
                                <div class="px-5 pt-3">
                                    <p class="text-xs leading-5 rounded-lg bg-amber-50/70 border border-amber-100 text-amber-800 px-3 py-2">
                                        Other position — no default access. Grant modules below to give this role access.
                                    </p>
                                </div>
                            @endif

                            {{-- Module permission table --}}
                            <div class="px-5 py-4">
                                @if(empty($enabledModules))
                                    <p class="text-sm text-gray-400">No modules are enabled for this organization.</p>
                                @else
                                    @php
                                        $perms       = $initialPermsByRole[$role] ?? [];
                                        $moduleList  = array_values($enabledModules);
                                        $moduleHalf  = (int) ceil(count($moduleList) / 2);
                                        $moduleCols  = [array_slice($moduleList, 0, $moduleHalf), array_slice($moduleList, $moduleHalf)];
                                    @endphp
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        @foreach($moduleCols as $moduleColumn)
                                            @if(!empty($moduleColumn))
                                            <div class="rounded-lg border border-gray-200 overflow-hidden self-start">
                                                @foreach($moduleColumn as $module)
                                                    @php
                                                        $perm      = $perms[$module] ?? ['default'=>false,'granted'=>false,'active'=>false];
                                                        $isDefault = $perm['default'];
                                                    @endphp
                                                    <div class="perm-row flex items-center justify-between gap-2 px-4 py-4 bg-white">
                                                        <div class="flex items-center gap-3 min-w-0">
                                                            <i class="ph {{ $moduleIcons[$module] ?? 'ph-puzzle-piece' }} text-base w-5 text-center flex-shrink-0"
                                                               :style="perm('{{ $role }}','{{ $module }}').active ? 'color:var(--primary-color)' : 'color:#c9ccd1'"></i>
                                                            <span class="text-sm font-medium text-gray-700 truncate">{{ $moduleLabels[$module] ?? ucfirst($module) }}</span>
                                                        </div>

                                                        <div class="flex items-center gap-3 flex-shrink-0">
                                                            @if($isDefault)
                                                                <span class="text-xs font-medium text-gray-400">Default</span>
                                                            @elseif($role === 'COMELEC')
                                                                {{-- COMELEC is elections-only: lock all non-elections toggles --}}
                                                                <span class="text-xs text-gray-300 w-12 text-right">Locked</span>
                                                                <button type="button"
                                                                        disabled
                                                                        data-on="false"
                                                                        class="switch"
                                                                        aria-pressed="false"
                                                                        title="COMELEC can only access Elections">
                                                                    <span class="switch-knob"></span>
                                                                </button>
                                                            @else
                                                                <span class="text-xs text-gray-400 w-12 text-right"
                                                                      x-text="perm('{{ $role }}','{{ $module }}').active ? 'Granted' : 'Off'"
                                                                      :class="perm('{{ $role }}','{{ $module }}').active ? 'text-gray-600 font-medium' : 'text-gray-400'"></span>
                                                                <button type="button"
                                                                        @click="toggleModule('{{ $role }}','{{ $module }}')"
                                                                        :disabled="saving==='{{ $role }}:{{ $module }}'"
                                                                        :data-on="perm('{{ $role }}','{{ $module }}').active ? 'true' : 'false'"
                                                                        class="switch"
                                                                        :aria-pressed="perm('{{ $role }}','{{ $module }}').active"
                                                                        :title="perm('{{ $role }}','{{ $module }}').active ? 'Revoke access' : 'Grant access'">
                                                                    <span class="switch-knob"></span>
                                                                </button>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>{{-- /grid --}}
        @endif
    </div>{{-- /admins tab --}}

    {{-- TAB 3 — ADD POSITIONS --}}
    <div x-show="activeTab==='positions'" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         class="space-y-4"
         x-data="{ removeModalOpen: false, removeAction: '', removeMemberName: '', removePositionName: '', deleteModalOpen: false, deleteAction: '', deletePositionName: '', deleteMemberCount: 0 }"
         @keydown.escape.window="removeModalOpen = false; deleteModalOpen = false">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Create a position</h2>
                    <p class="text-sm text-gray-500 mt-1">Define a position name and choose the organization modules it can access.</p>
                </div>
                <i class="ph ph-shield-check text-lg" style="color:var(--primary-color)"></i>
            </div>

            @if($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.settings.positions.store') }}" class="space-y-4" data-admin-inplace-form
                  x-data="{ submitting: false }"
                  @submit="submitting = true">
                @csrf
                <div>
                    <label for="position-name" class="block text-sm font-medium text-gray-700 mb-1">Position name</label>
                    <input id="position-name" name="name" value="{{ old('name') }}" required maxlength="100"
                           placeholder="e.g. Social Media Manager"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-transparent focus:outline-none focus:ring-2"
                           style="--tw-ring-color:var(--primary-color)">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700">Module access</label>
                        <span class="text-xs text-gray-400">Organization-disabled modules remain unavailable.</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        @foreach($allModules as $module)
                            @php $moduleEnabled = in_array($module, $enabledModules, true); @endphp
                            <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2.5 {{ $moduleEnabled ? 'cursor-pointer hover:bg-gray-50' : 'bg-gray-50 opacity-60' }}">
                                <input type="checkbox" name="modules[]" value="{{ $module }}"
                                       {{ in_array($module, old('modules', []), true) ? 'checked' : '' }}
                                       class="rounded border-gray-300" style="accent-color:var(--primary-color)">
                                <i class="ph {{ $moduleIcons[$module] ?? 'ph-puzzle-piece' }} w-4 text-center text-gray-400"></i>
                                <span class="text-sm text-gray-700">{{ $moduleLabels[$module] ?? ucfirst($module) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="rounded-lg px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                            style="background-color:var(--primary-color)"
                            :disabled="submitting"
                            :class="submitting ? 'cursor-not-allowed opacity-70' : ''">
                        <i :class="submitting ? 'ph ph-spinner ph-spin mr-1.5' : 'ph ph-plus mr-1.5'" style="color:var(--complementary-color)"></i><span x-text="submitting ? 'Creating…' : 'Create Position'">Create Position</span>
                    </button>
                </div>
            </form>
        </div>

        @if($positions->isEmpty())
            <div class="bg-white rounded-xl border border-dashed border-gray-300 px-6 py-10 text-center">
                <i class="ph ph-identification-badge text-2xl text-gray-300 mb-2"></i>
                <p class="text-sm font-medium text-gray-700">No custom positions yet</p>
                <p class="text-sm text-gray-400 mt-1">Create a position above, then assign active organization members to it.</p>
            </div>
        @else
            @php
                $memberPickerOptions = $assignableMembers->map(function ($assignableMember) {
                    $memberName = name_with_suffix($assignableMember->first_name, null, $assignableMember->last_name, $assignableMember->suffix ?? '');

                    return [
                        'id' => (int) $assignableMember->id,
                        'name' => $memberName,
                        'student_id' => $assignableMember->student_id,
                        'photo_url' => $assignableMember->photo ? storage_url($assignableMember->photo) : null,
                        'label' => $memberName . ($assignableMember->student_id ? ' · ' . $assignableMember->student_id : ''),
                    ];
                })->values()->all();
            @endphp
            <div class="space-y-3">
                @foreach($positions as $position)
                    @php
                        $members = $positionMembers->get($position->name, collect());
                        $selectedPositionModules = $positionPermissions[$position->name] ?? [];
                    @endphp
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 border-b border-gray-100 px-5 py-4">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">{{ $position->name }}</h3>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $members->count() }} {{ Str::plural('assigned member', $members->count()) }}</p>
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
                            <form method="POST" action="{{ route('admin.settings.positions.assign', $position) }}" data-admin-inplace-form
                                  class="flex flex-col sm:flex-row gap-2"
                                  x-data="memberPicker(@js($memberPickerOptions))"
                                  @submit="if (!selectedId) { $event.preventDefault(); open = true; $nextTick(() => $refs.search.focus()); } else { submitting = true; }">
                                @csrf
                                <label class="sr-only" for="member-search-{{ $position->id }}">Search member to assign</label>
                                <div class="relative min-w-56 sm:min-w-72">
                                    <button type="button"
                                            class="flex w-full items-center justify-between gap-3 rounded-lg border border-gray-300 bg-white px-3 py-2 text-left text-sm focus:outline-none focus:ring-2"
                                            style="--tw-ring-color:var(--primary-color)"
                                            @click="open = !open; if (open) $nextTick(() => $refs.search.focus())"
                                            :aria-expanded="open.toString()"
                                            aria-haspopup="listbox">
                                        <span class="min-w-0 truncate" :class="selectedMember ? 'text-gray-700' : 'text-gray-400'" x-text="selectedMember ? selectedMember.label : 'Search active members…'"></span>
                                        <i class="ph ph-caret-down flex-shrink-0 text-xs text-gray-400"></i>
                                    </button>
                                    <div x-show="open" x-cloak x-transition @click.outside="open = false"
                                         class="absolute left-0 right-0 top-full z-30 mt-1 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl">
                                        <div class="border-b border-gray-100 p-2">
                                            <div class="relative">
                                                <i class="ph ph-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400"></i>
                                                <input x-ref="search" id="member-search-{{ $position->id }}" type="search" x-model="query"
                                                       placeholder="Search name or student ID…"
                                                       class="w-full rounded-md border border-gray-200 py-2 pl-8 pr-3 text-sm focus:border-transparent focus:outline-none focus:ring-2"
                                                       style="--tw-ring-color:var(--primary-color)"
                                                       @keydown.escape="open = false">
                                            </div>
                                        </div>
                                        <div class="max-h-56 overflow-y-auto p-1" role="listbox">
                                            <template x-for="member in filteredMembers()" :key="member.id">
                                                <button type="button" class="flex w-full items-start gap-2 rounded-md px-3 py-2 text-left hover:bg-gray-50" x-data="{ imageFailed: false }" @click="choose(member)">
                                                    <img x-show="member.photo_url && !imageFailed"
                                                         :src="member.photo_url"
                                                         :alt="member.name"
                                                         class="mt-0.5 h-7 w-7 flex-shrink-0 rounded-full object-cover"
                                                         x-on:error="imageFailed = true">
                                                    <span x-show="!member.photo_url || imageFailed"
                                                          class="mt-0.5 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white"
                                                          style="background-color:var(--primary-color)"
                                                          x-text="member.name.charAt(0).toUpperCase()"></span>
                                                    <span class="min-w-0">
                                                        <span class="block truncate text-sm text-gray-700" x-text="member.name"></span>
                                                        <span class="block truncate text-xs text-gray-400" x-text="member.student_id || 'Organization member'"></span>
                                                    </span>
                                                </button>
                                            </template>
                                            <p x-show="filteredMembers().length === 0" class="px-3 py-4 text-center text-xs text-gray-400">No matching active members.</p>
                                        </div>
                                    </div>
                                </div>
                                <select id="member-{{ $position->id }}" name="user_id" x-model="selectedId" class="hidden" aria-hidden="true" tabindex="-1">
                                    <option value="">Assign an active member…</option>
                                    @foreach($assignableMembers as $member)
                                        <option value="{{ $member->id }}">
                                            {{ name_with_suffix($member->first_name, null, $member->last_name, $member->suffix ?? '') }}{{ $member->student_id ? ' · ' . $member->student_id : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit"
                                        class="rounded-lg px-3 py-2 text-sm font-semibold text-white hover:opacity-90"
                                        style="background-color:var(--primary-color)"
                                        :disabled="submitting"
                                        :class="submitting ? 'cursor-not-allowed opacity-70' : ''">
                                    <i :class="submitting ? 'ph ph-spinner ph-spin mr-1' : 'ph ph-user-plus mr-1'"></i><span x-text="submitting ? 'Assigning…' : 'Assign'">Assign</span>
                                </button>
                            </form>
                                <button type="button"
                                        class="rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50"
                                        data-delete-action="{{ route('admin.settings.positions.destroy', $position) }}"
                                        data-position-name="{{ $position->name }}"
                                        data-member-count="{{ $members->count() }}"
                                        @click="deleteAction = $el.dataset.deleteAction; deletePositionName = $el.dataset.positionName; deleteMemberCount = Number($el.dataset.memberCount); deleteModalOpen = true">
                                    <i class="ph ph-trash mr-1"></i>Delete
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 p-5">
                            <form method="POST" action="{{ route('admin.settings.positions.update', $position) }}" class="space-y-3" data-admin-inplace-form>
                                @csrf
                                @method('PUT')
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-semibold text-gray-800">Module access</h4>
                                    <button type="submit" class="text-xs font-semibold hover:underline" style="color:var(--primary-color)">Save access</button>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @foreach($allModules as $module)
                                        @php $moduleEnabled = in_array($module, $enabledModules, true); @endphp
                                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 {{ $moduleEnabled ? 'cursor-pointer hover:bg-gray-50' : 'bg-gray-50 opacity-60' }}">
                                            <input type="checkbox" name="modules[]" value="{{ $module }}" {{ in_array($module, $selectedPositionModules, true) ? 'checked' : '' }}
                                                   class="rounded border-gray-300" style="accent-color:var(--primary-color)">
                                            <i class="ph {{ $moduleIcons[$module] ?? 'ph-puzzle-piece' }} w-4 text-center text-gray-400"></i>
                                            <span class="text-xs text-gray-700">{{ $moduleLabels[$module] ?? ucfirst($module) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </form>

                            <div>
                                <h4 class="text-sm font-semibold text-gray-800 mb-2">Assigned members</h4>
                                @if($members->isEmpty())
                                    <p class="text-sm text-gray-400 rounded-lg border border-dashed border-gray-200 px-3 py-4">No one is assigned to this position.</p>
                                @else
                                    <div class="space-y-2 max-h-48 overflow-y-auto">
                                        @foreach($members as $member)
                                            <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 px-3 py-2">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    @if($member->photo)
                                                        <img src="{{ storage_url($member->photo) }}"
                                                             alt="{{ name_with_suffix($member->first_name, null, $member->last_name, $member->suffix ?? '') }}"
                                                             class="w-7 h-7 rounded-full object-cover flex-shrink-0"
                                                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                                        <span class="w-7 h-7 rounded-full items-center justify-center text-xs font-semibold text-white flex-shrink-0" style="display:none;background-color:var(--primary-color)">
                                                            {{ strtoupper(substr($member->first_name ?: $member->last_name, 0, 1)) }}
                                                        </span>
                                                    @else
                                                        <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold text-white flex-shrink-0" style="background-color:var(--primary-color)">
                                                            {{ strtoupper(substr($member->first_name ?: $member->last_name, 0, 1)) }}
                                                        </span>
                                                    @endif
                                                    <div class="min-w-0">
                                                        <p class="text-sm text-gray-700 truncate">{{ name_with_suffix($member->first_name, null, $member->last_name, $member->suffix ?? '') }}</p>
                                                        <p class="text-xs text-gray-400 truncate">{{ $member->student_id ?: 'Organization member' }}</p>
                                                    </div>
                                                </div>
                                                <button type="button"
                                                        class="text-xs text-gray-400 hover:text-red-600"
                                                        title="Remove member"
                                                        data-remove-action="{{ route('admin.settings.positions.members.remove', [$position, $member->id]) }}"
                                                         data-member-name="{{ name_with_suffix($member->first_name, null, $member->last_name, $member->suffix ?? '') }}"
                                                        data-position-name="{{ $position->name }}"
                                                        @click="removeAction = $el.dataset.removeAction; removeMemberName = $el.dataset.memberName; removePositionName = $el.dataset.positionName; removeModalOpen = true">
                                                    <i class="ph ph-x"></i>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Remove-position confirmation modal --}}
        <template x-teleport="body">
        <div x-show="removeModalOpen" x-cloak x-transition.opacity
             class="fixed inset-0 z-[9999] flex min-h-screen w-screen items-center justify-center bg-gray-900/50 modal-backdrop px-4 py-6"
             @click.self="removeModalOpen = false">
            <div x-show="removeModalOpen" x-transition
                 class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl"
                 @click.stop>
                <div class="flex items-start justify-between border-b border-gray-100 px-5 py-4">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-red-50 text-red-600">
                            <i class="ph ph-user-minus"></i>
                        </span>
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Remove position access?</h3>
                            <p class="mt-1 text-xs text-gray-500">This action will notify the member.</p>
                        </div>
                    </div>
                    <button type="button" @click="removeModalOpen = false" class="text-gray-400 hover:text-gray-700" aria-label="Close modal">
                        <i class="ph ph-x"></i>
                    </button>
                </div>
                <div class="px-5 py-5">
                    <p class="text-sm leading-6 text-gray-600">
                        Remove <strong class="font-semibold text-gray-900" x-text="removeMemberName"></strong> from
                        <strong class="font-semibold text-gray-900" x-text="removePositionName"></strong>?
                    </p>
                    <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2.5 text-xs leading-5 text-amber-800">
                        Their organization role will be changed back to Member, and they will receive an in-app notification and email.
                    </p>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-4">
                    <button type="button" @click="removeModalOpen = false" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">
                        Cancel
                    </button>
                    <form method="POST" :action="removeAction" data-admin-inplace-form
                          x-data="{ submitting: false }"
                          @submit="submitting = true">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                                :disabled="submitting"
                                :class="submitting ? 'cursor-not-allowed opacity-70' : ''">
                            <i :class="submitting ? 'ph ph-spinner ph-spin mr-1.5' : 'ph ph-user-minus mr-1.5'"></i><span x-text="submitting ? 'Removing…' : 'Remove member'">Remove member</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        </template>

        {{-- Delete-position confirmation modal --}}
        <template x-teleport="body">
        <div x-show="deleteModalOpen" x-cloak x-transition.opacity
             class="fixed inset-0 z-[9999] flex min-h-screen w-screen items-center justify-center bg-gray-900/50 modal-backdrop px-4 py-6"
             @click.self="deleteModalOpen = false">
            <div x-show="deleteModalOpen" x-transition
                 class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl"
                 @click.stop>
                <div class="flex items-start justify-between border-b border-gray-100 px-5 py-4">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-red-50 text-red-600">
                            <i class="ph ph-trash"></i>
                        </span>
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Delete this position?</h3>
                            <p class="mt-1 text-xs text-gray-500">This cannot be undone.</p>
                        </div>
                    </div>
                    <button type="button" @click="deleteModalOpen = false" class="text-gray-400 hover:text-gray-700" aria-label="Close modal">
                        <i class="ph ph-x"></i>
                    </button>
                </div>
                <div class="px-5 py-5">
                    <p class="text-sm leading-6 text-gray-600">
                        Delete <strong class="font-semibold text-gray-900" x-text="deletePositionName"></strong>?
                    </p>
                    <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2.5 text-xs leading-5 text-amber-800">
                        <template x-if="deleteMemberCount > 0">
                            <span><strong x-text="deleteMemberCount"></strong> assigned <span x-text="deleteMemberCount === 1 ? 'member will' : 'members will'"></span> be returned to Member and notified. Custom module access for this position will also be removed.</span>
                        </template>
                        <template x-if="deleteMemberCount === 0">
                            <span>Custom module access for this position will be removed.</span>
                        </template>
                    </p>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-4">
                    <button type="button" @click="deleteModalOpen = false" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">
                        Cancel
                    </button>
                    <form method="POST" :action="deleteAction" data-admin-inplace-form
                          x-data="{ submitting: false }"
                          @submit="submitting = true">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                                :disabled="submitting"
                                :class="submitting ? 'cursor-not-allowed opacity-70' : ''">
                            <i :class="submitting ? 'ph ph-spinner ph-spin mr-1.5' : 'ph ph-trash mr-1.5'"></i><span x-text="submitting ? 'Deleting...' : 'Delete position'">Delete position</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        </template>
    </div>{{-- /positions tab --}}

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- TAB 4 — MANAGE ACCOUNTS (adviser-only)                            --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    @if($isAdviser)
    <div x-show="activeTab==='accounts'" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         class="bg-white rounded-xl border border-gray-200"
         x-data="accountsPanel(@js($accounts), '{{ route('admin.settings.accounts.toggle') }}', '{{ csrf_token() }}')"
         @keydown.escape.window="modalOpen = false">

        <div class="flex items-center justify-between gap-4 px-5 py-3 border-b border-gray-100">
            <div class="flex items-baseline gap-2.5 min-w-0">
                <h2 class="text-base font-semibold text-gray-900 flex-shrink-0">Manage Student Accounts</h2>
                <p class="text-sm text-gray-500 truncate hidden md:block">
                    Officers are listed first (President → 4th Year Rep), then Members. Deactivated accounts lose both Admin and Student access.
                </p>
            </div>
        </div>

        @if($accounts->isEmpty())
            <div class="flex flex-col items-center justify-center text-center py-10 px-6">
                <div class="w-8 h-8 rounded-full bg-gray-50 border border-gray-200 flex items-center justify-center mb-2">
                    <i class="ph ph-user-minus text-gray-300 text-sm"></i>
                </div>
                <p class="text-sm font-medium text-gray-700">No member accounts yet</p>
                <p class="text-sm text-gray-400 mt-0.5">Member accounts of this organization will appear here.</p>
            </div>
        @else
            <div class="px-5 pt-4 pb-2">
                <div class="relative max-w-sm">
                    <i class="ph ph-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400"></i>
                    <input type="search" x-model="query" placeholder="Search name, student ID, or email…"
                           class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-transparent focus:outline-none focus:ring-2"
                           style="--tw-ring-color:var(--primary-color)">
                </div>
            </div>

            <div class="px-5 pb-5 space-y-6">
                {{-- Admins — Officers ordered President → 4th Year Rep --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-xs font-semibold tracking-widest uppercase text-gray-500">Admins — Officers</h3>
                        <span class="text-xs text-gray-400" x-text="filteredAdminAccounts.length + (filteredAdminAccounts.length === 1 ? ' officer' : ' officers')"></span>
                    </div>
                    <div class="border border-gray-200 rounded-xl overflow-hidden divide-y divide-gray-100">
                        <template x-for="account in filteredAdminAccounts" :key="account.id">
                            <div class="flex items-center justify-between gap-4 px-4 py-3 bg-white">
                                <div class="flex items-center gap-3 min-w-0">
                                    <img x-show="account.photo_url" :src="account.photo_url" :alt="account.full_name"
                                         class="w-9 h-9 rounded-full object-cover flex-shrink-0"
                                         x-on:error="$el.style.display='none'">
                                    <span x-show="!account.photo_url" class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-semibold flex-shrink-0"
                                          style="background-color:var(--primary-color)" x-text="(account.full_name || '?').charAt(0).toUpperCase()"></span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate" x-text="account.full_name"></p>
                                        <p class="text-xs text-gray-400 truncate">
                                            <span x-text="account.display_role || account.org_role"></span>
                                            <span x-show="account.student_id" x-text="' · ' + account.student_id"></span>
                                            <span x-show="account.email" x-text="' · ' + account.email"></span>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 flex-shrink-0">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full"
                                          :class="statusOf(account) === 'Active' ? 'bg-green-50 text-green-700 border border-green-200' : (statusOf(account) === 'Pending' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-gray-100 text-gray-500 border border-gray-200')">
                                        <span class="w-1.5 h-1.5 rounded-full"
                                              :class="statusOf(account) === 'Active' ? 'bg-green-500' : (statusOf(account) === 'Pending' ? 'bg-amber-500' : 'bg-gray-400')"></span>
                                        <span x-text="statusOf(account)"></span>
                                    </span>
                                    <template x-if="canToggle(account)">
                                        <button type="button"
                                                @click="openConfirm(account)"
                                                class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors"
                                                :class="statusOf(account) === 'Active'
                                                    ? 'border-red-200 text-red-600 hover:bg-red-50'
                                                    : 'border-gray-200 text-gray-700 hover:bg-gray-50'">
                                            <span x-text="statusOf(account) === 'Active' ? 'Deactivate' : 'Reactivate'"></span>
                                        </button>
                                    </template>
                                    <template x-if="!canToggle(account)">
                                        <span class="text-xs text-gray-400 italic">Pending approval</span>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <p x-show="filteredAdminAccounts.length === 0" class="px-4 py-6 text-center text-sm text-gray-400">No matching officers.</p>
                    </div>
                </div>

                {{-- Students — Members --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-xs font-semibold tracking-widest uppercase text-gray-500">Students — Members</h3>
                        <span class="text-xs text-gray-400" x-text="filteredStudentAccounts.length + (filteredStudentAccounts.length === 1 ? ' member' : ' members')"></span>
                    </div>
                    <div class="border border-gray-200 rounded-xl overflow-hidden divide-y divide-gray-100">
                        <template x-for="account in filteredStudentAccounts" :key="account.id">
                            <div class="flex items-center justify-between gap-4 px-4 py-3 bg-white">
                                <div class="flex items-center gap-3 min-w-0">
                                    <img x-show="account.photo_url" :src="account.photo_url" :alt="account.full_name"
                                         class="w-9 h-9 rounded-full object-cover flex-shrink-0"
                                         x-on:error="$el.style.display='none'">
                                    <span x-show="!account.photo_url" class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-semibold flex-shrink-0"
                                          style="background-color:var(--primary-color)" x-text="(account.full_name || '?').charAt(0).toUpperCase()"></span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate" x-text="account.full_name"></p>
                                        <p class="text-xs text-gray-400 truncate">
                                            <span x-text="account.display_role || account.org_role"></span>
                                            <span x-show="account.student_id" x-text="' · ' + account.student_id"></span>
                                            <span x-show="account.email" x-text="' · ' + account.email"></span>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 flex-shrink-0">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full"
                                          :class="statusOf(account) === 'Active' ? 'bg-green-50 text-green-700 border border-green-200' : (statusOf(account) === 'Pending' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-gray-100 text-gray-500 border border-gray-200')">
                                        <span class="w-1.5 h-1.5 rounded-full"
                                              :class="statusOf(account) === 'Active' ? 'bg-green-500' : (statusOf(account) === 'Pending' ? 'bg-amber-500' : 'bg-gray-400')"></span>
                                        <span x-text="statusOf(account)"></span>
                                    </span>
                                    <template x-if="canToggle(account)">
                                        <button type="button"
                                                @click="openConfirm(account)"
                                                class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors"
                                                :class="statusOf(account) === 'Active'
                                                    ? 'border-red-200 text-red-600 hover:bg-red-50'
                                                    : 'border-gray-200 text-gray-700 hover:bg-gray-50'">
                                            <span x-text="statusOf(account) === 'Active' ? 'Deactivate' : 'Reactivate'"></span>
                                        </button>
                                    </template>
                                    <template x-if="!canToggle(account)">
                                        <span class="text-xs text-gray-400 italic">Pending approval</span>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <p x-show="filteredStudentAccounts.length === 0" class="px-4 py-6 text-center text-sm text-gray-400">No matching members.</p>
                    </div>
                </div>

                <p x-show="filteredAccounts.length === 0" class="text-center text-sm text-gray-400 py-2">No matching accounts.</p>
            </div>
        @endif

        {{-- Confirm status-change modal --}}
        <template x-teleport="body">
        <div x-show="modalOpen" x-cloak x-transition.opacity
             class="fixed inset-0 z-[9999] flex min-h-screen w-screen items-center justify-center bg-gray-900/50 modal-backdrop px-4 py-6"
             @click.self="modalOpen = false">
            <div x-show="modalOpen" x-transition
                 class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl"
                 @click.stop>
                <div class="flex items-start justify-between border-b border-gray-100 px-5 py-4">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full"
                              :class="toDeactivate ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'">
                            <i class="ph" :class="toDeactivate ? 'ph-user-minus' : 'ph-user-plus'"></i>
                        </span>
                        <div>
                            <h3 class="text-base font-semibold text-gray-900" x-text="toDeactivate ? 'Deactivate this account?' : 'Reactivate this account?'"></h3>
                            <p class="mt-1 text-xs text-gray-500">This action will notify the member.</p>
                        </div>
                    </div>
                    <button type="button" @click="modalOpen = false" class="text-gray-400 hover:text-gray-700" aria-label="Close modal">
                        <i class="ph ph-x"></i>
                    </button>
                </div>
                <div class="px-5 py-5">
                    <p class="text-sm leading-6 text-gray-600">
                        <template x-if="actionAccount">
                            <span>Are you sure you want to <strong class="font-semibold text-gray-900" x-text="toDeactivate ? 'deactivate' : 'reactivate'"></strong>
                                <strong class="font-semibold text-gray-900" x-text="actionAccount.full_name"></strong>?</span>
                        </template>
                    </p>
                    <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2.5 text-xs leading-5 text-amber-800"
                       x-text="toDeactivate
                           ? 'They will no longer be able to sign in and will lose access to both the Admin and Student views of this organization. Their role is kept, so reactivating restores the previous access.'
                           : 'Their sign-in access and previous organization access will be restored.'"></p>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-4">
                    <button type="button" @click="modalOpen = false" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">
                        Cancel
                    </button>
                    <button type="button"
                            @click="confirmToggle()"
                            :disabled="submitting"
                            :class="[toDeactivate ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700', submitting ? 'cursor-not-allowed opacity-70' : '']"
                            class="rounded-lg px-4 py-2 text-sm font-semibold text-white hover:opacity-90">
                        <i :class="submitting ? 'ph ph-spinner ph-spin mr-1.5' : (toDeactivate ? 'ph ph-user-minus mr-1.5' : 'ph ph-user-plus mr-1.5')"></i>
                        <span x-text="submitting ? (toDeactivate ? 'Deactivating…' : 'Reactivating…') : (toDeactivate ? 'Deactivate account' : 'Reactivate account')">Deactivate</span>
                    </button>
                </div>
            </div>
        </div>
        </template>
    </div>{{-- /accounts tab --}}
    @endif

</div>{{-- /max-w-7xl (outer settingsPage x-data) --}}

<script>
// ── Outer page controller ─────────────────────────────────────────────────
function settingsPage(initialAdminsBadgeCount, isAdviser) {
    return {
        activeTab: @js(session('settings_tab', request('tab', 'theme'))),
        isAdviser: !!isAdviser,
        successMessage: '',
        errorMessage: '',
        adminsBadgeCount: initialAdminsBadgeCount || 0,
        init() {
            if (!this.isAdviser && this.activeTab === 'accounts') {
                this.activeTab = 'theme';
            }
            window.addEventListener('admins-zero-access-count', e => {
                this.adminsBadgeCount = e.detail.count;
            });
        },
    };
}

// ── Manage Admins panel controller (master-detail) ────────────────────────
// Holds permission state for ALL roles at once, keyed by role name, so switching
// the selected role in the master list never re-fetches or re-inits anything.
function memberPicker(members) {
    return {
        members,
        query: '',
        open: false,
        selectedId: '',
        submitting: false,

        get selectedMember() {
            return this.members.find(member => String(member.id) === String(this.selectedId));
        },

        filteredMembers() {
            const search = this.query.trim().toLowerCase();

            if (!search) return this.members;

            return this.members.filter(member =>
                member.name.toLowerCase().includes(search)
                || String(member.student_id || '').toLowerCase().includes(search)
            );
        },

        choose(member) {
            this.selectedId = member.id;
            this.query = '';
            this.open = false;
        },
    };
}

function adminsPanel(initialPermsByRole, initialRole, updateUrl, csrfToken) {
    return {
        selectedRole: initialRole,
        permsByRole: JSON.parse(JSON.stringify(initialPermsByRole)), // deep clone
        saving: null, // "role:module" key currently being saved

        perm(role, module) {
            return (this.permsByRole[role] && this.permsByRole[role][module])
                || { default: false, granted: false, active: false };
        },

        activeCount(role) {
            const perms = this.permsByRole[role] || {};
            return Object.values(perms).filter(p => p.active).length;
        },

        zeroAccessCount() {
            return Object.keys(this.permsByRole).filter(role => this.activeCount(role) === 0).length;
        },

        notifyZeroAccessCount() {
            window.dispatchEvent(new CustomEvent('admins-zero-access-count', {
                detail: { count: this.zeroAccessCount() },
            }));
        },

        async toggleModule(role, module) {
            const current = this.perm(role, module);
            if (!current || current.default) return; // defaults are non-toggleable
            if (role === 'COMELEC' && module !== 'elections') {
                window.dispatchEvent(new CustomEvent('show-error', { detail: 'COMELEC is elections-only — other modules cannot be granted.' }));
                return;
            }

            const newValue  = !current.active;
            const prevValue = current.active;
            const key       = `${role}:${module}`;

            // Optimistic update
            this.permsByRole[role][module].active  = newValue;
            this.permsByRole[role][module].granted = newValue;
            this.saving = key;

            try {
                const resp = await fetch(updateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept':       'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ role, module, is_granted: newValue }),
                });

                const data = await resp.json();

                if (!resp.ok || !data.success) {
                    this.permsByRole[role][module].active  = prevValue;
                    this.permsByRole[role][module].granted = prevValue;
                    window.dispatchEvent(new CustomEvent('show-error', { detail: data.message || 'Failed to update permission.' }));
                } else {
                    window.dispatchEvent(new CustomEvent('show-success', { detail: data.message || 'Permission updated.' }));
                }
            } catch (err) {
                this.permsByRole[role][module].active  = prevValue;
                this.permsByRole[role][module].granted = prevValue;
                window.dispatchEvent(new CustomEvent('show-error', { detail: 'Network error. Please try again.' }));
            } finally {
                this.saving = null;
                this.notifyZeroAccessCount();
            }
        },
    };
}

// ── Manage Accounts panel controller (adviser-only) ────────────────────────
// Lists the org's member accounts and lets the adviser toggle each one between
// Active and Inactive. Status changes are confirmed via a modal before any
// request is sent to the backend.
function accountsPanel(initialAccounts, toggleUrl, csrfToken) {
    return {
        accounts: initialAccounts,
        query: '',
        modalOpen: false,
        actionAccount: null,
        toDeactivate: true,
        submitting: false,

        get filteredAccounts() {
            const search = this.query.trim().toLowerCase();
            if (!search) return this.accounts;

            return this.accounts.filter(account =>
                (account.full_name || '').toLowerCase().includes(search)
                || (account.email || '').toLowerCase().includes(search)
                || String(account.student_id || '').toLowerCase().includes(search)
                || (account.org_role || '').toLowerCase().includes(search)
                || (account.display_role || '').toLowerCase().includes(search)
            );
        },

        get filteredAdminAccounts() {
            return this.filteredAccounts.filter(a => a.is_admin ?? (String(a.org_role || '').toLowerCase() !== 'member'));
        },

        get filteredStudentAccounts() {
            return this.filteredAccounts.filter(a => !(a.is_admin ?? (String(a.org_role || '').toLowerCase() !== 'member')));
        },

        statusOf(account) {
            if (account.membership_status === 'Pending') return 'Pending';
            return account.is_active ? 'Active' : 'Inactive';
        },

        canToggle(account) {
            return account.membership_status !== 'Pending';
        },

        openConfirm(account) {
            if (!this.canToggle(account)) return;
            this.actionAccount = account;
            this.toDeactivate = this.statusOf(account) === 'Active';
            this.modalOpen = true;
        },

        async confirmToggle() {
            if (!this.actionAccount || this.submitting) return;

            this.submitting = true;
            const account = this.actionAccount;
            const requestedActive = !this.toDeactivate;

            try {
                const resp = await fetch(toggleUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept':       'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ user_id: account.id, is_active: requestedActive }),
                });

                const data = await resp.json();

                if (!resp.ok || !data.success) {
                    window.dispatchEvent(new CustomEvent('show-error', { detail: data.message || 'Failed to update the account status.' }));
                } else {
                    account.is_active = requestedActive;
                    account.membership_status = requestedActive ? 'Active' : 'Inactive';
                    window.dispatchEvent(new CustomEvent('show-success', { detail: data.message || 'Account status updated.' }));
                }
            } catch (err) {
                window.dispatchEvent(new CustomEvent('show-error', { detail: 'Network error. Please try again.' }));
            } finally {
                this.submitting = false;
                this.modalOpen = false;
                this.actionAccount = null;
            }
        },
    };
}
</script>

<script>
// ── settingsManager (theme form) ──────────────────────────────────────────
function settingsManager() {
    return {
        isSubmitting: false, hasChanges: false, successMessage: '', errorMessage: '', validationErrors: {},
        markAsChanged() {
            if (!this.hasChanges) {
                this.hasChanges = true;
                const btn = document.getElementById('settings-save-btn');
                if (btn) { btn.disabled = false; btn.classList.remove('opacity-60','cursor-not-allowed'); btn.classList.add('hover:opacity-90'); }
            }
        },
        async submitForm(event) {
            event.preventDefault();
            if (this.isSubmitting) return;
            this.isSubmitting = true; this.successMessage = ''; this.errorMessage = '';
            const btn = document.getElementById('settings-save-btn');
            const icon = document.getElementById('settings-save-icon');
            const txt  = document.getElementById('settings-save-text');
            if (btn)  { btn.disabled = true; btn.classList.add('opacity-60','cursor-not-allowed'); }
            if (icon) { icon.className = 'ph ph-spinner ph-spin mr-2'; }
            if (txt)  { txt.textContent = 'Saving...'; }
            try {
                const formData = new FormData(event.target);
                const response = await fetch(event.target.action, {
                    method: 'POST', body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                const data = await response.json();
                if (response.ok) {
                    this.successMessage = data.message || 'Theme settings saved successfully!';
                    const pc = data.primary_color || formData.get('primary_color');
                    const cc = data.complementary_color || formData.get('complementary_color');
                    let logoUrl = null;
                    const lf = formData.get('logo');
                    if (lf && lf.size > 0) { logoUrl = URL.createObjectURL(lf); } else { logoUrl = data.logo_url; }
                    if (window.applyThemeUpdate) { window._lastLocalThemeUpdate = Date.now(); window.applyThemeUpdate(pc, cc, logoUrl); }
                    else { if (pc) document.documentElement.style.setProperty('--primary-color', pc); if (cc) document.documentElement.style.setProperty('--complementary-color', cc); }
                    if (window.showThemeUpdateNotification) window.showThemeUpdateNotification();
                    this.hasChanges = false;
                    if (btn) { btn.disabled = true; btn.classList.add('opacity-60','cursor-not-allowed'); btn.classList.remove('hover:opacity-90'); }
                } else {
                    if (response.status === 422 && data.errors) { this.validationErrors = data.errors; this.errorMessage = 'Validation failed: ' + Object.values(data.errors).flat().join(' '); }
                    else { this.errorMessage = data.message || 'Failed to save. Please try again.'; }
                }
            } catch(e) { this.errorMessage = 'An unexpected error occurred. Please try again.'; }
            finally {
                this.isSubmitting = false;
                if (icon) icon.className = 'ph ph-floppy-disk mr-2 transition-colors duration-300';
                if (txt)  txt.textContent = 'Save Changes';
                if (btn)  { if (this.hasChanges) { btn.disabled = false; btn.classList.remove('opacity-60','cursor-not-allowed'); btn.classList.add('hover:opacity-90'); } else { btn.disabled = true; btn.classList.add('opacity-60','cursor-not-allowed'); } }
            }
        },
    };
}

// ── colorPicker ───────────────────────────────────────────────────────────
function colorPicker(initialColor, colorType) {
    return {
        color: initialColor, hexInput: initialColor, error: '', manualMode: false,
        init() {
            this.$watch('color', value => {
                this.hexInput = value; this.error = ''; this.updatePreview();
                if (colorType === 'primary') this.calculateComplementary(value);
            });
            if (colorType === 'primary') {
                window.addEventListener('generate-random-color', e => { this.color = e.detail.primary; });
            } else {
                window.addEventListener('complementary-color-calculated', e => { if (!this.manualMode) { this.color = e.detail.color; this.hexInput = e.detail.color; } });
                window.addEventListener('request-complementary-recalc', () => {
                    const pi = document.querySelector('input[name="primary_color"]');
                    if (pi && pi.value) {
                        const h = pi.value, r=parseInt(h.substr(1,2),16), g=parseInt(h.substr(3,2),16), b=parseInt(h.substr(5,2),16);
                        const c='#'+((255-r).toString(16).padStart(2,'0'))+((255-g).toString(16).padStart(2,'0'))+((255-b).toString(16).padStart(2,'0'));
                        this.color = c.toUpperCase(); this.hexInput = c.toUpperCase();
                    }
                });
            }
        },
        updateFromHex() { if (/^#[0-9A-Fa-f]{6}$/.test(this.hexInput)) { this.color = this.hexInput; this.error = ''; this.updatePreview(); if (colorType==='primary') this.calculateComplementary(this.hexInput); } else { this.error = 'Invalid color. Use #RRGGBB format'; } },
        updatePreview() { const ev = colorType==='primary'?'primary-color-updated':'complementary-color-updated'; window.dispatchEvent(new CustomEvent(ev,{detail:{color:this.color}})); window.dispatchEvent(new Event('settings-changed')); },
        calculateComplementary(h) { const r=parseInt(h.substr(1,2),16),g=parseInt(h.substr(3,2),16),b=parseInt(h.substr(5,2),16); const c='#'+((255-r).toString(16).padStart(2,'0'))+((255-g).toString(16).padStart(2,'0'))+((255-b).toString(16).padStart(2,'0')); window.dispatchEvent(new CustomEvent('complementary-color-calculated',{detail:{color:c.toUpperCase()}})); },
        generateRandomColor() { const c='#'+Math.floor(Math.random()*16777215).toString(16).padStart(6,'0').toUpperCase(); window.dispatchEvent(new CustomEvent('generate-random-color',{detail:{primary:c}})); },
        toggleManualMode() { this.manualMode=!this.manualMode; if (!this.manualMode && colorType==='complementary') window.dispatchEvent(new CustomEvent('request-complementary-recalc')); },
    };
}

// ── logoUploader ──────────────────────────────────────────────────────────
function logoUploader(currentLogoUrl) {
    return {
        logoPreview: currentLogoUrl, fileName: '', error: '',
        handleFileSelect(e) { const f=e.target.files[0]; if (!f) return; if (!this.validateFile(f)) { e.target.value=''; return; } this.fileName=f.name; const r=new FileReader(); r.onload=ev=>{ this.logoPreview=ev.target.result; window.dispatchEvent(new CustomEvent('logo-updated',{detail:{url:ev.target.result}})); window.dispatchEvent(new Event('settings-changed')); }; r.readAsDataURL(f); },
        validateFile(f) { const types=['image/png','image/jpeg','image/jpg','image/svg+xml','image/webp']; if (!types.includes(f.type)) { this.error='Invalid format. Use PNG, JPG, SVG, or WEBP'; return false; } if (f.size>2097152) { this.error='File exceeds 2MB limit'; return false; } this.error=''; return true; },
    };
}

// ── previewRenderer ───────────────────────────────────────────────────────
function previewRenderer(t) {
    return {
        primaryColor: t.primary_color, complementaryColor: t.complementary_color, logoUrl: t.logo_url,
        viewMode: 'calendar', // 'calendar' or 'list' — mirrors the real Events page toggle
        init() {
            window.addEventListener('primary-color-updated',      e => this.primaryColor       = e.detail.color);
            window.addEventListener('complementary-color-updated', e => this.complementaryColor = e.detail.color);
            window.addEventListener('logo-updated',               e => this.logoUrl            = e.detail.url);
        },
    };
}
</script>
<script>
// Role card events are relayed to the outer settingsPage via Alpine's
// x-on:show-success.window and x-on:show-error.window listeners (set on the wrapper div).
// No manual wiring needed here.
</script>
@endsection
