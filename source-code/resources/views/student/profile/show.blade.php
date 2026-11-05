@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
<style>
    .profile-page {
        color: #111827;
    }

    .profile-surface {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 1rem;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
    }

    .profile-cover {
        min-height: 116px;
        background: linear-gradient(135deg, var(--primary-color) 0%, rgba(var(--primary-color-rgb), 0.82) 100%);
        border-radius: 1rem 1rem 0 0;
        position: relative;
        overflow: hidden;
    }

    .profile-cover::after {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 85% 20%, rgba(255,255,255,.22), transparent 28%), linear-gradient(115deg, transparent 55%, rgba(255,255,255,.08) 55%);
        pointer-events: none;
    }

    .profile-identity {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 1.5rem;
        align-items: end;
    }

    .profile-info-card {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: .875rem;
        transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
    }

    .profile-info-card:hover {
        transform: translateY(-2px);
        border-color: rgba(var(--primary-color-rgb), .35);
        box-shadow: 0 8px 18px rgba(15, 23, 42, .06);
    }

    .profile-status {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .35rem .65rem;
        border-radius: 999px;
        background: #ecfdf5;
        color: #047857;
        font-size: .75rem;
        font-weight: 700;
    }

    @media (max-width: 639px) {
        .profile-identity { grid-template-columns: 1fr; gap: .75rem; }
    }
</style>

<div class="student-profile-page student-neu-page min-h-screen">
    <div class="mx-auto max-w-7xl px-3 sm:px-6 lg:px-8">
        <header class="profile-hero">
            <div class="profile-hero__copy">
                <p class="profile-eyebrow"><i class="ph ph-student mr-1.5" style="color: var(--primary-color);"></i>Student workspace</p>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-gray-900">My Profile</h1>
                <p>Manage your identity, academic details, and account security.</p>
            </div>
            <div class="profile-hero__actions hidden sm:flex">
                <button onclick="openEditProfileModal()" class="profile-edit-btn">
                    <span class="profile-edit-btn__icon"><i class="ph ph-pencil-simple" aria-hidden="true"></i></span>
                    <span>Edit Profile</span>
                </button>
            </div>
        </header>

        <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1.25fr)_minmax(300px,0.75fr)]">
            <section class="profile-card overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" style="--profile-index: 0;">
                <div class="p-5 sm:p-6">
                    <div class="flex items-center gap-4 sm:gap-5">
                        <div class="relative flex-shrink-0">
                            <div id="avatar-wrapper" onclick="document.getElementById('photo-input').click()" class="group relative mx-auto w-fit cursor-pointer">
                                @if($user->photo)
                                    <img id="profile-avatar" src="{{ storage_url($user->photo) }}" alt="{{ $user->full_name }}" class="h-28 w-28 rounded-xl border-4 border-white object-cover shadow-md sm:h-32 sm:w-32">
                                @else
                                    <div id="profile-avatar" class="flex h-28 w-28 items-center justify-center rounded-xl border-4 border-white shadow-md sm:h-32 sm:w-32" style="background-color: rgba(var(--primary-color-rgb, 130, 56, 179), 0.08);">
                                        <i class="ph ph-user text-4xl" style="color: var(--primary-color);"></i>
                                    </div>
                                @endif
                                <div class="absolute inset-0 flex items-center justify-center rounded-xl bg-slate-900/0 transition group-hover:bg-slate-900/45">
                                    <span class="rounded-md bg-white/90 px-2 py-1 text-xs font-semibold text-slate-700 opacity-0 shadow transition group-hover:opacity-100"><i class="ph ph-camera mr-1"></i>Change</span>
                                </div>
                            </div>
                            <form id="photo-form" method="POST" action="{{ route('profile.photo.update') }}" enctype="multipart/form-data">
                                @csrf
                                <input type="file" id="photo-input" name="photo" accept="image/*" class="hidden" onchange="onPhotoSelect(event)">
                            </form>
                            <div id="photo-actions" class="hidden absolute left-1/2 top-full z-10 mt-2 flex -translate-x-1/2 gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                                <button type="button" onclick="savePhoto()" class="whitespace-nowrap rounded-md px-2.5 py-1.5 text-xs font-semibold text-white" style="background-color: var(--primary-color);"><i class="ph ph-floppy-disk mr-1"></i>Save</button>
                                <button type="button" onclick="cancelPhoto()" class="whitespace-nowrap rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200">Cancel</button>
                            </div>
                            <p class="mt-2 text-xs text-slate-400">Click photo to update</p>
                        </div>

                        <div class="min-w-0 flex-1">
                            <h2 id="profile-name" class="profile-fit-text text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $user->full_name }}</h2>
                            @php($courseNames = ['BSIT' => 'Bachelor of Science in Information Technology', 'BSCS' => 'Bachelor of Science in Computer Science'])
                            <p id="profile-course" class="profile-fit-text mt-1 text-sm font-medium text-slate-500">{{ ($user->course ? ($courseNames[$user->course] ?? $user->course) : null) ?: 'Student account' }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"><i class="ph ph-circle mr-1 text-[8px]"></i>Active student</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 border-t border-slate-100 pt-5">
                        <section class="profile-subcard rounded-xl border border-slate-200 bg-white p-4">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div><h3 class="text-sm font-bold text-slate-900">Personal & academic information</h3><p class="mt-0.5 text-xs text-slate-500">Details used across your student account.</p></div>
                                <button onclick="openEditProfileModal()" class="flex-shrink-0 text-xs font-semibold transition hover:opacity-75" style="color: var(--primary-color);">Edit details <i class="ph ph-arrow-right ml-1"></i></button>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="grid content-start gap-3">
                                    <div class="profile-detail rounded-lg border border-slate-200 bg-slate-50/70 px-3.5 py-3"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Email address</p><p class="mt-1 truncate text-sm font-semibold text-slate-800">{{ $user->email }}</p></div>
                                    @if($user->isStudent() || $user->isEffectivelyStudent())
                                    <div class="profile-detail rounded-lg border border-slate-200 bg-slate-50/70 px-3.5 py-3"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Course</p><p class="mt-1 text-sm font-semibold text-slate-800">{{ $user->course ? ($courseNames[$user->course] ?? $user->course) : 'Not set' }}</p></div>
                                    @endif
                                </div>
                                @if($user->isStudent() || $user->isEffectivelyStudent())
                                <div class="grid content-start gap-3">
                                    <div class="profile-detail rounded-lg border border-slate-200 bg-slate-50/70 px-3.5 py-3"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Student ID</p><p class="mt-1 truncate text-sm font-semibold text-slate-800">{{ $user->student_id ?: 'Not set' }}</p></div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="profile-detail rounded-lg border border-slate-200 bg-slate-50/70 px-3.5 py-3"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Year level</p><p class="mt-1 truncate text-sm font-semibold text-slate-800">{{ $user->year_level ?: 'Not set' }}</p></div>
                                        <div class="profile-detail rounded-lg border border-slate-200 bg-slate-50/70 px-3.5 py-3"><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Section</p><p class="mt-1 truncate text-sm font-semibold text-slate-800">{{ $user->section ?: 'Not set' }}</p></div>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </section>
                    </div>
                </div>
            </section>

            <div class="space-y-5">
                <section class="profile-card rounded-xl border border-slate-200 bg-white shadow-sm" style="--profile-index: 1;">
                    <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4"><div><h3 class="text-sm font-bold text-slate-900">Account security</h3><p class="mt-0.5 text-xs text-slate-500">Keep your sign-in credentials up to date.</p></div><span class="flex h-8 w-8 items-center justify-center rounded-lg" style="background-color: rgba(var(--primary-color-rgb, 130, 56, 179), 0.09); color: var(--primary-color);"><i class="ph ph-shield text-sm"></i></span></div>
                    <div class="p-5"><div class="flex items-center justify-between gap-3"><div class="flex min-w-0 items-center gap-3"><span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500"><i class="ph ph-key text-sm"></i></span><div class="min-w-0"><p class="text-sm font-semibold text-slate-800">Password</p><p class="truncate text-xs text-slate-500" id="last-changed-time" data-last-changed="{{ $user->password_changed_at ? $user->password_changed_at->toISOString() : '' }}">Last changed: Never</p></div></div><button onclick="openPasswordModal()" class="profile-outline-btn flex-shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">Change</button></div></div>
                </section>

                {{-- Fingerprint Login - Student mobile only, 1 per account (Change only, no Add) --}}
                @if($user->role === 'student')
                <section class="profile-card rounded-xl border border-slate-200 bg-white shadow-sm" style="--profile-index: 1.5;" id="webauthn-card">
                    <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2"><i class="ph ph-fingerprint text-base" style="color: var(--primary-color);"></i> Fingerprint Login</h3>
                            <p class="mt-0.5 text-xs text-slate-500">Use your phone's fingerprint / Face ID to login faster. <span class="hidden sm:inline">1 device only — Change to switch phone.</span></p>
                        </div>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg" style="background-color: rgba(var(--primary-color-rgb, 130, 56, 179), 0.09); color: var(--primary-color);"><i class="ph ph-hand text-sm"></i></span>
                    </div>
                    <div class="p-5 space-y-3">
                        <div id="webauthn-status" class="flex items-center gap-2 text-xs">
                            <i class="ph ph-spinner ph-spin text-slate-400"></i><span class="text-slate-500">Checking fingerprint support...</span>
                        </div>
                        {{-- Kept hidden for JS compatibility; never shown (no yellow banners) --}}
                        <div id="webauthn-not-supported" class="hidden"></div>
                        <div id="webauthn-enabled" class="hidden space-y-3">
                            <div class="profile-detail flex items-center gap-3 px-3.5 py-3">
                                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl" style="background-color: rgba(var(--primary-color-rgb, 130, 56, 179), 0.10); color: var(--primary-color);"><i class="ph ph-fingerprint text-lg"></i></span>
                                <div class="min-w-0 flex-1">
                                    <p class="flex items-center gap-2 text-xs font-bold text-slate-800">Fingerprint active <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold" style="background-color: rgba(16, 185, 129, 0.12); color: #047857;"><span class="inline-block h-1.5 w-1.5 rounded-full" style="background-color: #10b981;"></span>On</span></p>
                                    <p class="mt-0.5 truncate text-[11px] text-slate-500" id="webauthn-enabled-detail">Registered</p>
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-500 leading-relaxed"><i class="ph ph-info mr-1"></i>Only <strong>1 fingerprint per account</strong>. To use a new phone, remove then register again.</p>
                            <div class="flex gap-2">
                                <div class="group/webauthn relative flex-1" id="webauthn-change-wrap">
                                    <button id="webauthn-change-btn-main" type="button" onclick="handleBlockedChangeClick(event)" aria-disabled="false" class="profile-outline-btn w-full rounded-lg px-3 py-2 text-xs font-semibold"><i class="ph ph-arrows-clockwise mr-1"></i>Change Fingerprint</button>
                                    <div id="webauthn-change-tip" class="webauthn-tip pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 hidden w-60 -translate-x-1/2 rounded-xl p-3 text-center shadow-xl group-hover/webauthn:block" style="background: var(--neu-bg); box-shadow: 6px 6px 12px var(--neu-dark-shadow), -6px -6px 12px var(--neu-light-shadow); border: 1px solid rgba(163,177,198,.4);">
                                        <p class="flex items-center justify-center gap-1.5 text-xs font-bold text-slate-800"><i class="ph ph-device-mobile" style="color: var(--primary-color);"></i>Mobile only</p>
                                        <p class="mt-1 text-[11px] leading-relaxed text-slate-500">Open this page on your phone to Change. You can still Remove here.</p>
                                    </div>
                                </div>
                                <button onclick="openWebAuthnRemoveModal()" class="flex-1 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100"><i class="ph ph-trash mr-1"></i>Remove</button>
                            </div>
                            <p class="text-[11px] text-slate-400 text-center">Removing requires your password.</p>
                        </div>
                        <div id="webauthn-disabled" class="hidden space-y-3">
                            <div class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-2.5 flex items-center gap-2">
                                <i class="ph ph-fingerprint text-slate-400 text-base"></i>
                                <span class="text-xs text-slate-600">No fingerprint registered yet.</span>
                            </div>
                            <div class="group/webauthn relative" id="webauthn-enable-wrap">
                                <button id="webauthn-enable-btn" onclick="handleBlockedEnableClick(event)" class="w-full rounded-lg px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:opacity-90 transition flex items-center justify-center gap-2" style="background-color: var(--primary-color);">
                                    <i class="ph ph-fingerprint"></i> Enable Fingerprint Login
                                </button>
                                <div id="webauthn-enable-tip" class="webauthn-tip pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 hidden w-60 -translate-x-1/2 rounded-xl p-3 text-center shadow-xl group-hover/webauthn:block" style="background: var(--neu-bg); box-shadow: 6px 6px 12px var(--neu-dark-shadow), -6px -6px 12px var(--neu-light-shadow); border: 1px solid rgba(163,177,198,.4);">
                                    <p class="flex items-center justify-center gap-1.5 text-xs font-bold text-slate-800"><i class="ph ph-device-mobile" style="color: var(--primary-color);"></i>Mobile only</p>
                                    <p class="mt-1 text-[11px] leading-relaxed text-slate-500">Please open this page on your phone (Chrome/Safari) to enable fingerprint login.</p>
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-500 text-center leading-relaxed">You'll be asked to verify with your phone's fingerprint / Face ID. Only 1 per account — changing will replace the old one.</p>
                        </div>
                    </div>
                </section>
                @endif

            </div>
        </div>
    </div>
</div>
</div>

@if(false)
<div class="profile-page max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8">
    <div class="mb-6 sm:mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">Student workspace</p>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-black">My Profile</h1>
            <p class="text-sm sm:text-base text-gray-600 mt-1">Keep your identity and academic details up to date.</p>
        </div>
        <button onclick="openEditProfileModal()" class="px-4 sm:px-5 py-2.5 text-white font-semibold rounded-lg hover:opacity-90 transition text-sm self-start sm:self-auto shadow-sm" style="background-color: var(--primary-color);">
            <i class="ph ph-pencil-simple mr-2" style="color: var(--complementary-color);"></i>Edit Profile
        </button>
    </div>

    @if(session('success'))
        <!-- Success Toast Notification -->
        <div x-data="{ show: true }" 
             x-show="show" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform translate-x-8"
             x-transition:enter-end="opacity-100 transform translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform translate-x-0"
             x-transition:leave-end="opacity-0 transform translate-x-8"
             x-init="setTimeout(() => show = false, 4000)" 
             class="fixed top-6 right-6 z-50 bg-white border-l-4 border-green-500 rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md" 
             style="display: none;">
            <div class="flex-shrink-0">
                <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                    <i class="ph ph-check-circle text-xl text-green-600"></i>
                </div>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-900">Success!</p>
                <p class="text-sm text-gray-600">{{ session('success') }}</p>
            </div>
            <button @click="show = false" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition">
                <i class="ph ph-x text-lg"></i>
            </button>
        </div>
    @endif

    <div class="profile-surface overflow-hidden">
        <div class="profile-cover"></div>
        <div class="px-4 sm:px-8 pb-6 sm:pb-8">
            <div class="profile-identity -mt-16 sm:-mt-[5.5rem] mb-6 mobile-profile-row">
                <div class="relative flex-shrink-0">
                    <div id="avatar-wrapper" onclick="document.getElementById('photo-input').click()" class="cursor-pointer group relative">
                        @if($user->photo)
                            <img id="profile-avatar" src="{{ storage_url($user->photo) }}" alt="{{ $user->full_name }}" class="w-24 h-24 sm:w-32 sm:h-32 rounded-full border-4 border-white shadow-xl object-cover">
                        @else
                            <div id="profile-avatar" class="w-24 h-24 sm:w-32 sm:h-32 rounded-full border-4 border-white shadow-xl flex items-center justify-center" style="background-color: #F9F5FF;">
                                <i class="ph ph-user text-4xl sm:text-5xl" style="color: var(--primary-color);"></i>
                            </div>
                        @endif
                        <div class="absolute inset-0 rounded-full bg-black bg-opacity-0 group-hover:bg-opacity-40 transition-all flex items-center justify-center">
                            <i class="ph ph-camera text-white text-2xl opacity-0 group-hover:opacity-100 transition-opacity"></i>
                        </div>
                    </div>
                    <form id="photo-form" method="POST" action="{{ route('profile.photo.update') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="file" id="photo-input" name="photo" accept="image/*" class="hidden" onchange="onPhotoSelect(event)">
                    </form>
                    <div id="photo-actions" class="hidden absolute left-full ml-3 sm:right-full sm:mr-3 sm:left-auto sm:ml-0 top-1/2 -translate-y-1/2 flex gap-2 bg-white rounded-lg shadow-lg border border-gray-200 px-3 py-2.5 z-10">
                        <button type="button" onclick="savePhoto()" class="px-3 py-1.5 text-xs font-semibold text-white rounded-lg hover:opacity-90 transition whitespace-nowrap" style="background-color: var(--primary-color);">
                            <i class="ph ph-floppy-disk mr-1"></i>Save
                        </button>
                        <button type="button" onclick="cancelPhoto()" class="px-3 py-1.5 text-xs font-semibold text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition whitespace-nowrap">
                            Cancel
                        </button>
                    </div>
                </div>
                <div class="sm:ml-6 mt-3 sm:mt-0 sm:mb-4 min-w-0 overflow-hidden">
                    <h2 id="profile-name" class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-700 transition-colors duration-300 truncate">
                        <span class="hidden sm:inline">{{ $user->full_name }}</span>
                        <span class="sm:hidden">{{ $user->first_name }} {{ $user->last_name }}</span>
                    </h2>
                    <div class="flex flex-wrap items-center gap-2 mt-1">
                        <p class="text-sm sm:text-base text-gray-600 truncate">{{ $user->student_id }}</p>
                        <span class="profile-status"><i class="ph ph-circle text-[8px]"></i>Active student</span>
                    </div>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    function updateProfileTextColor() {
                        const root = document.documentElement;
                        const primaryColor = getComputedStyle(root).getPropertyValue('--primary-color').trim();
                        
                        if (!primaryColor) return;
                        
                        let r, g, b;
                        
                        // Parse hex or rgb
                        if (primaryColor.startsWith('#')) {
                            const hex = primaryColor.replace('#', '');
                            r = parseInt(hex.length === 3 ? hex.charAt(0)+hex.charAt(0) : hex.substring(0, 2), 16);
                            g = parseInt(hex.length === 3 ? hex.charAt(1)+hex.charAt(1) : hex.substring(2, 4), 16);
                            b = parseInt(hex.length === 3 ? hex.charAt(2)+hex.charAt(2) : hex.substring(4, 6), 16);
                        } else if (primaryColor.startsWith('rgb')) {
                            const match = primaryColor.match(/\d+/g);
                            if (match && match.length >= 3) {
                                r = parseInt(match[0]);
                                g = parseInt(match[1]);
                                b = parseInt(match[2]);
                            }
                        }
                        
                        if (r !== undefined && g !== undefined && b !== undefined) {
                            // Calculate perceived luminance
                            const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                            
                            const nameEl = document.getElementById('profile-name');
                            
                            // If dark background (luminance < 0.5), use white text with shadow for readability
                            if (luminance < 0.5) {
                                nameEl.style.color = '#ffffff';
                                nameEl.style.textShadow = '0 2px 4px rgba(0,0,0,0.5)';
                            } else {
                                // If light background, use dark text
                                nameEl.style.color = '#1f2937'; // gray-800
                                nameEl.style.textShadow = 'none';
                            }
                        }
                    }
                    
                    // Run initially
                    updateProfileTextColor();
                    
                    // Optional: Re-run if theme color is updated dynamically via websockets
                    if (window.applyThemeUpdate) {
                        const originalApplyThemeUpdate = window.applyThemeUpdate;
                        window.applyThemeUpdate = function(primaryColor, complementaryColor, logoUrl) {
                            originalApplyThemeUpdate(primaryColor, complementaryColor, logoUrl);
                            setTimeout(updateProfileTextColor, 50);
                        };
                    }
                    
                    // Fallback observer for style changes on html element
                    const observer = new MutationObserver((mutations) => {
                        mutations.forEach((mutation) => {
                            if (mutation.attributeName === 'style') {
                                updateProfileTextColor();
                            }
                        });
                    });
                    observer.observe(document.documentElement, { attributes: true });
                });
            </script>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 lg:gap-8">
                <div class="profile-info-card p-4 sm:p-5">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4 flex items-center">
                        <i class="ph ph-user-circle mr-2" style="color: var(--complementary-color);"></i>
                        Personal Information
                    </h3>
                    <div class="space-y-3 sm:space-y-4">
                        <div>
                            <p class="text-xs sm:text-sm text-gray-600 mb-1">Email Address</p>
                            <p class="font-semibold text-gray-900 text-sm sm:text-base break-words">{{ $user->email }}</p>
                        </div>
                    </div>
                </div>

                @if($user->isStudent())
                <div class="profile-info-card p-4 sm:p-5">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4 flex items-center">
                        <i class="ph ph-graduation-cap mr-2" style="color: var(--complementary-color);"></i>
                        Academic Information
                    </h3>
                    <div class="space-y-3 sm:space-y-4">
                        @if($user->course)
                        <div>
                            <p class="text-xs sm:text-sm text-gray-600 mb-1">Course</p>
                            <p class="font-semibold text-gray-900 text-sm sm:text-base">{{ $user->course }}</p>
                        </div>
                        @endif
                        @if($user->year_level)
                        <div>
                            <p class="text-xs sm:text-sm text-gray-600 mb-1">Year Level</p>
                            <p class="font-semibold text-gray-900 text-sm sm:text-base">{{ $user->year_level }}</p>
                        </div>
                        @endif
                        @if($user->section)
                        <div>
                            <p class="text-xs sm:text-sm text-gray-600 mb-1">Section</p>
                            <p class="font-semibold text-gray-900 text-sm sm:text-base">{{ $user->section }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>

            <div class="mt-6 sm:mt-8 pt-6 sm:pt-8 border-t border-gray-200">
                <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4 flex items-center">
                    <i class="ph ph-gear mr-2" style="color: var(--complementary-color);"></i>
                    Account Settings
                </h3>
                <div class="profile-info-card flex flex-col sm:flex-row sm:items-center sm:justify-between p-3 sm:p-4 gap-3">
                    <div>
                        <p class="font-semibold text-gray-900 text-sm sm:text-base">Password</p>
                        <p class="text-xs sm:text-sm text-gray-600" id="last-changed-time" data-last-changed="{{ $user->password_changed_at ? $user->password_changed_at->toISOString() : '' }}">Last changed: Never</p>
                    </div>
                    <button onclick="openPasswordModal()" 
                        class="px-4 py-2 font-medium rounded-lg transition border text-sm sm:text-base self-start sm:self-auto" 
                        style="color: var(--primary-color); border-color: var(--primary-color);"
                        onmouseover="this.style.backgroundColor='#F9F5FF'"
                        onmouseout="this.style.backgroundColor='transparent'">
                        Change Password
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@endif

<!-- Password Change Modal -->
<div id="password-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-start sm:items-center justify-center z-50 transition-opacity duration-300 opacity-0 p-3 sm:p-4 overflow-y-auto">
    <div id="password-modal-content" class="bg-white rounded-xl shadow-xl p-5 sm:p-8 max-w-md w-full my-4 sm:my-0 transform transition-all duration-300 scale-95">
        <div class="flex justify-between items-center mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Change Password</h2>
            <button onclick="closePasswordModal()" class="text-gray-400 hover:text-gray-600">
                <i class="ph ph-x text-xl"></i>
            </button>
        </div>
        
        <form method="POST" action="{{ route('profile.password') }}" id="changePasswordForm">
            @csrf
            @method('PUT')
            
            <div class="mb-4">
                <label for="current_password" class="block text-gray-700 text-sm font-semibold mb-2">Current Password</label>
                <div class="relative">
                    <input type="password" name="current_password" id="current_password" required autocomplete="new-password"
                        class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                        style="-webkit-text-security: disc; -webkit-appearance: none; appearance: none;">
                    <button type="button" onclick="togglePasswordVisibility('current_password', 'currentEyeIcon')" 
                        style="color: var(--primary-color);" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover: focus:outline-none transition-colors">
                        <i id="currentEyeIcon" class="ph ph-eye text-lg"></i>
                    </button>
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">New Password</label>
                <div class="relative">
                    <input type="password" name="password" id="password" required autocomplete="new-password"
                        class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                        style="-webkit-text-security: disc; -webkit-appearance: none; appearance: none;">
                    <button type="button" onclick="togglePasswordVisibility('password', 'newEyeIcon')" 
                        style="color: var(--primary-color);" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover: focus:outline-none transition-colors">
                        <i id="newEyeIcon" class="ph ph-eye text-lg"></i>
                    </button>
                </div>
            </div>

            <div class="mb-6">
                <label for="password_confirmation" class="block text-gray-700 text-sm font-semibold mb-2">Confirm New Password</label>
                <div class="relative">
                    <input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password"
                        class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                        style="-webkit-text-security: disc; -webkit-appearance: none; appearance: none;">
                    <button type="button" onclick="togglePasswordVisibility('password_confirmation', 'confirmEyeIcon')" 
                        style="color: var(--primary-color);" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover: focus:outline-none transition-colors">
                        <i id="confirmEyeIcon" class="ph ph-eye text-lg"></i>
                    </button>
                </div>
            </div>

            <div class="flex gap-4">
                <button type="button" onclick="closePasswordModal()"
                    class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" class="flex-1 px-4 py-3 text-white font-medium rounded-lg hover:opacity-90 transition" style="background-color: var(--primary-color);">
                    Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Profile Modal -->
<div id="edit-profile-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-start sm:items-center justify-center z-50 overflow-y-auto transition-opacity duration-300 opacity-0 p-3 sm:p-4">
    <div id="edit-profile-modal-content" class="bg-white rounded-xl shadow-xl p-5 sm:p-8 max-w-4xl w-full my-4 sm:my-8 transform transition-all duration-300 scale-95">
        <div class="flex justify-between items-center mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Edit Profile</h2>
            <button onclick="closeEditProfileModal()" class="text-gray-400 hover:text-gray-600">
                <i class="ph ph-x text-xl"></i>
            </button>
        </div>
        
        <form method="POST" action="{{ route('profile.update') }}" id="editProfileForm">
            @csrf
            @method('PUT')

            <!-- Hidden email field to satisfy backend validation (not editable by user) -->
            <input type="hidden" name="email" value="{{ $user->email }}">
            <!-- Hidden name field to satisfy backend validation (combined from first and last name) -->
            <input type="hidden" name="name" value="{{ title_case_name(trim(trim((string) old('first_name', $user->first_name ?? '')) . ' ' . trim((string) old('middle_name', $user->middle_name ?? '')) . ' ' . trim((string) old('last_name', $user->last_name ?? '')) . ' ' . trim((string) old('suffix', $user->suffix ?? '')))) }}" id="hiddenNameField">

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="ph ph-user-circle mr-2" style="color: #D4AF37;"></i>
                    Personal Information
                </h3>
                <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6">
                        <div>
                            <label for="modal_first_name" class="block text-gray-700 text-sm font-semibold mb-2">First Name</label>
                            <input type="text" name="first_name" id="modal_first_name" value="{{ title_case_name((string) old('first_name', $user->first_name ?? '')) }}" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>

                        <div>
                            <label for="modal_middle_name" class="block text-gray-700 text-sm font-semibold mb-2">Middle Name <span class="font-normal text-gray-400">(optional)</span></label>
                            <input type="text" name="middle_name" id="modal_middle_name" value="{{ title_case_name((string) old('middle_name', $user->middle_name ?? '')) }}"
                                placeholder="Middle name"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>

                        <div>
                            <label for="modal_last_name" class="block text-gray-700 text-sm font-semibold mb-2">Last Name</label>
                            <input type="text" name="last_name" id="modal_last_name" value="{{ title_case_name((string) old('last_name', $user->last_name ?? '')) }}" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>

                        <div class="sm:col-span-2">
                            <label for="modal_student_id" class="block text-gray-700 text-sm font-semibold mb-2">Student ID</label>
                            <input type="text" name="student_id" id="modal_student_id" value="{{ old('student_id', $user->student_id) }}" required
                                placeholder="0000-0000" inputmode="numeric"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>

                        <div>
                            <label for="modal_suffix" class="block text-gray-700 text-sm font-semibold mb-2">Suffix <span class="font-normal text-gray-400">(optional)</span></label>
                            <input type="text" name="suffix" id="modal_suffix" value="{{ old('suffix', $user->suffix ?? '') }}"
                                placeholder="Jr., Sr., II, III…" maxlength="10"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                    </div>
                </div>
            </div>

            @if($user->isStudent())
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="ph ph-graduation-cap mr-2" style="color: #D4AF37;"></i>
                    Academic Information
                    <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500"><i class="ph ph-lock mr-1"></i>Read-only</span>
                </h3>
                <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <p class="block text-gray-700 text-sm font-semibold mb-2">Course</p>
                            <div class="field-static w-full px-4 py-3 text-sm font-semibold text-gray-800">{{ $user->course ? ($courseNames[$user->course] ?? $user->course) : 'Not set' }}</div>
                        </div>

                        <div>
                            <p class="block text-gray-700 text-sm font-semibold mb-2">Year Level</p>
                            <div class="field-static w-full px-4 py-3 text-sm font-semibold text-gray-800">{{ $user->year_level ?: 'Not set' }}</div>
                        </div>

                        <div>
                            <p class="block text-gray-700 text-sm font-semibold mb-2">Section</p>
                            <div class="field-static w-full px-4 py-3 text-sm font-semibold text-gray-800">{{ $user->section ?: 'Not set' }}</div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <div class="flex gap-4">
                <button type="button" onclick="closeEditProfileModal()"
                    class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" id="modalSubmitBtn" class="flex-1 px-4 py-3 text-white font-medium rounded-lg hover:opacity-90 transition" style="background-color: var(--primary-color);">
                    <i class="ph ph-floppy-disk mr-2" style="color: var(--complementary-color);"></i>Update Profile
                </button>
            </div>
        </form>
    </div>
</div>

<!-- WebAuthn: Remove Fingerprint Modal -->
<div id="webauthn-remove-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 transition-opacity duration-300 opacity-0 p-3 sm:p-4 overflow-y-auto">
    <div id="webauthn-remove-content" class="bg-white rounded-xl shadow-xl p-5 sm:p-6 max-w-md w-full my-auto transform transition-all duration-300 scale-95">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2"><i class="ph ph-warning-circle text-red-500"></i> Remove Fingerprint?</h2>
            <button onclick="closeWebAuthnRemoveModal()" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x text-xl"></i></button>
        </div>
        <p class="text-sm text-gray-600 mb-4 leading-relaxed">This will remove fingerprint login from this account. You can register again on any device afterwards. Enter your password to confirm.</p>
        <div class="mb-3">
            <label class="block text-sm font-semibold text-gray-700 mb-1">Current Password</label>
            <input type="password" id="webauthn-remove-password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Enter password" autocomplete="current-password">
        </div>
        <p id="webauthn-remove-error" class="hidden mb-3 text-sm text-red-600 font-medium"></p>
        <div class="flex gap-3">
            <button onclick="closeWebAuthnRemoveModal()" class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50">Cancel</button>
            <button id="webauthn-remove-btn" onclick="handleWebAuthnRemove()" class="flex-1 px-4 py-3 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700">Remove</button>
        </div>
    </div>
</div>

<!-- WebAuthn: Change Fingerprint Modal -->
<div id="webauthn-change-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 transition-opacity duration-300 opacity-0 p-3 sm:p-4 overflow-y-auto">
    <div id="webauthn-change-content" class="bg-white rounded-xl shadow-xl p-5 sm:p-6 max-w-md w-full my-auto transform transition-all duration-300 scale-95">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2"><i class="ph ph-arrows-clockwise" style="color: var(--primary-color);"></i> Change Fingerprint</h2>
            <button onclick="closeWebAuthnChangeModal()" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x text-xl"></i></button>
        </div>
        <p class="text-sm text-gray-600 mb-4 leading-relaxed">This will replace your current fingerprint with a new one from this device. Enter your password first, then verify with your new fingerprint.</p>
        <div class="mb-3">
            <label class="block text-sm font-semibold text-gray-700 mb-1">Current Password</label>
            <input type="password" id="webauthn-change-password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="Enter password" autocomplete="current-password">
        </div>
        <p id="webauthn-change-error" class="hidden mb-3 text-sm text-red-600 font-medium"></p>
        <div class="flex gap-3">
            <button onclick="closeWebAuthnChangeModal()" class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50">Cancel</button>
            <button id="webauthn-change-btn" onclick="handleWebAuthnChange()" class="flex-1 px-4 py-3 text-white font-bold rounded-lg hover:opacity-90" style="background-color: var(--primary-color);"><i class="ph ph-fingerprint mr-1"></i> Continue</button>
        </div>
    </div>
</div>

<!-- WebAuthn: Mobile-only info (desktop blocked Change/Enable tap) -->
<div id="webauthn-mobile-only-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 transition-opacity duration-300 opacity-0 p-3 sm:p-4 overflow-y-auto">
    <div id="webauthn-mobile-only-content" class="bg-white rounded-xl shadow-xl p-5 sm:p-6 max-w-sm w-full my-auto transform transition-all duration-300 scale-95 text-center">
        <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl" style="background-color: rgba(var(--primary-color-rgb, 130, 56, 179), 0.10); color: var(--primary-color);"><i class="ph ph-device-mobile text-xl"></i></span>
        <h2 class="mt-3 text-lg font-bold text-gray-900">Mobile only</h2>
        <p class="mt-1.5 text-sm text-gray-600 leading-relaxed">Fingerprint Change/Enable works on your phone only. Please open this page on your phone (Chrome/Safari) to continue. You can still Remove here on desktop.</p>
        <button onclick="closeWebAuthnMobileOnlyModal()" class="mt-4 w-full px-4 py-3 text-white font-bold rounded-lg hover:opacity-90" style="background-color: var(--primary-color);">Got it</button>
    </div>
</div>

<style>
/* Hide browser password reveal controls */
input[type="password"]::-ms-reveal,
input[type="password"]::-ms-clear {
    display: none;
}

input[type="password"]::-webkit-credentials-auto-fill-button,
input[type="password"]::-webkit-strong-password-auto-fill-button {
    display: none;
}

/* Hide password reveal for all browsers */
input[type="password"]::-webkit-textfield-decoration-container {
    display: none !important;
}

input[type="password"]::-webkit-security-textfield-decoration {
    display: none !important;
}

/* Additional hiding for Firefox */
input[type="password"]::-moz-reveal {
    display: none;
}

/* Shake animation for error fields */
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}

.shake {
    animation: shake 0.4s;
}

/* Toast notification styles */
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

.toast-slide-in {
    animation: slideInRight 0.3s ease-out forwards;
}

.toast-slide-out {
    animation: slideOutRight 0.3s ease-in forwards;
}

@media (max-width: 639px) {
    .mobile-profile-row {
        flex-direction: row !important;
        align-items: flex-start !important;
        margin-top: -1.5rem !important;
    }
    .mobile-profile-row .min-w-0 {
        margin-left: 0.75rem !important;
        margin-top: 0 !important;
        overflow: hidden !important;
    }
    .mobile-profile-row .min-w-0 #profile-name {
        font-size: 1.125rem !important;
    }
    .mobile-profile-row .min-w-0 p {
        font-size: 0.75rem !important;
    }
}
/* Neumorphism */
.student-profile-page {
    --neu-bg: #E9EDF3;
    --neu-text: #2E3A4C;
    --neu-muted: #5A6B80;
    --neu-faint: #7C8A9E;
    --neu-divider: rgba(163, 177, 198, .45);
    --neu-dark-shadow: rgba(163, 177, 198, .6);
    --neu-light-shadow: rgba(255, 255, 255, .85);
    min-height: calc(100vh + 4rem) !important;
    margin-top: -2rem;
    margin-bottom: -2rem;
    background: var(--neu-bg);
    color: var(--neu-text);
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", sans-serif;
    -webkit-font-smoothing: antialiased;
}

html:has(.student-profile-page),
body:has(.student-profile-page),
main:has(.student-profile-page) {
    background: var(--neu-bg) !important;
}

.student-profile-page > .mx-auto { padding-top: 3.25rem !important; padding-bottom: 4rem !important; }

.profile-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    padding: 0 0 clamp(1.25rem, 2.25vw, 1.75rem);
}

.profile-hero__copy { max-width: 44rem; }
.profile-eyebrow {
    margin: 0 0 .45rem;
    color: var(--primary-color, #7c3aed);
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .08em;
    line-height: 1.25;
    text-transform: uppercase;
}

.profile-hero h1 { margin: 0; color: var(--neu-text); letter-spacing: -.025em; line-height: 1.25; }
.profile-hero__copy > p:last-child {
    max-width: 38rem;
    margin: .8rem 0 0;
    color: var(--neu-muted);
    font-size: clamp(.95rem, 2vw, 1.05rem);
    line-height: 1.55;
}

.profile-edit-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .55rem;
    min-height: 2.9rem;
    padding: .42rem 1rem .42rem .5rem;
    border: 0;
    border-radius: 999px;
    background: var(--primary-color, #0071e3);
    box-shadow: 5px 5px 12px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -5px -5px 12px rgba(255, 255, 255, .85);
    color: #fff;
    font-size: .9rem;
    font-weight: 600;
    white-space: nowrap;
    transition: transform .28s cubic-bezier(.2, .8, .2, 1), box-shadow .28s cubic-bezier(.2, .8, .2, 1), filter .28s ease;
}
.profile-edit-btn__icon {
    display: inline-flex;
    width: 2rem;
    height: 2rem;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(255, 255, 255, .18);
    color: #fff;
    font-size: 1rem;
}
.profile-edit-btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.04);
    box-shadow: 7px 7px 16px rgba(var(--primary-color-rgb, 124, 58, 237), .4), -7px -7px 16px rgba(255, 255, 255, .9);
}

.profile-card {
    border: 0 !important;
    border-radius: 1.5rem !important;
    background: var(--neu-bg) !important;
    box-shadow: 10px 10px 20px var(--neu-dark-shadow), -10px -10px 20px var(--neu-light-shadow) !important;
    animation: profile-card-in .6s both cubic-bezier(.2, .8, .2, 1);
    animation-delay: calc(var(--profile-index, 0) * 70ms);
}
.profile-card h3 { color: var(--neu-text) !important; letter-spacing: -.01em !important; }
.profile-card > div > h3, .profile-card .p-5 h3, .profile-card .px-5 h3 { color: var(--neu-text) !important; }

.profile-subcard {
    border: 0 !important;
    border-radius: 1.1rem !important;
    background: var(--neu-bg) !important;
    box-shadow: 6px 6px 12px var(--neu-dark-shadow), -6px -6px 12px var(--neu-light-shadow) !important;
    transition: transform .3s cubic-bezier(.2, .8, .2, 1), box-shadow .3s cubic-bezier(.2, .8, .2, 1);
}
.profile-subcard:hover {
    transform: translateY(-3px);
    box-shadow: 8px 8px 16px var(--neu-dark-shadow), -8px -8px 16px var(--neu-light-shadow), 0 0 0 1px rgba(var(--primary-color-rgb, 124, 58, 237), .25) !important;
}

.profile-detail {
    border: 0 !important;
    border-radius: .9rem !important;
    background: var(--neu-bg) !important;
    box-shadow: inset 3px 3px 7px rgba(163, 177, 198, .45), inset -3px -3px 7px rgba(255, 255, 255, .85) !important;
}
.profile-detail > p:first-child { color: var(--neu-muted) !important; letter-spacing: .05em; }
.profile-detail > p:last-child { color: var(--neu-text) !important; }

.profile-outline-btn {
    border: 0 !important;
    border-radius: .9rem !important;
    background: var(--neu-bg) !important;
    color: var(--neu-text) !important;
    box-shadow: 4px 4px 8px var(--neu-dark-shadow), -4px -4px 8px var(--neu-light-shadow) !important;
    transition: transform .25s cubic-bezier(.2, .8, .2, 1), box-shadow .25s ease, background-color .25s ease !important;
}
.profile-outline-btn:hover {
    transform: translateY(-2px);
    box-shadow: inset 3px 3px 6px var(--neu-dark-shadow), inset -3px -3px 6px var(--neu-light-shadow) !important;
}

/* Fingerprint blocked-state tooltip: hover-only (tap uses modal) */
@media (hover: none) {
    .webauthn-tip { display: none !important; }
}
#webauthn-mobile-only-modal {
    background: rgba(17, 17, 19, .46) !important;
    -webkit-backdrop-filter: blur(4px);
    backdrop-filter: blur(4px);
}
#webauthn-mobile-only-content {
    border: 0 !important;
    border-radius: 1.5rem !important;
    background: #ffffff !important;
    box-shadow: 0 24px 60px rgba(17, 17, 19, .28) !important;
}

#password-modal, #edit-profile-modal {
    background: rgba(17, 17, 19, .46) !important;
    -webkit-backdrop-filter: blur(4px);
    backdrop-filter: blur(4px);
}
#password-modal-content, #edit-profile-modal-content {
    border: 0 !important;
    border-radius: 1.5rem !important;
    background: #ffffff !important;
    box-shadow: 0 24px 60px rgba(17, 17, 19, .28) !important;
}
#password-modal h2, #edit-profile-modal h2, #edit-profile-modal h3 {
    color: var(--neu-text) !important;
    letter-spacing: -.03em !important;
}
#password-modal input, #edit-profile-modal input, #edit-profile-modal select, #edit-profile-modal .field-static {
    border: 0 !important;
    border-radius: .8rem !important;
    background: var(--neu-bg) !important;
    color: var(--neu-text) !important;
    box-shadow: inset 3px 3px 6px rgba(163, 177, 198, .45), inset -3px -3px 6px rgba(255, 255, 255, .85) !important;
}
#password-modal input:focus, #edit-profile-modal input:focus, #edit-profile-modal select:focus {
    outline: 3px solid rgba(var(--primary-color-rgb, 124, 58, 237), .30);
    outline-offset: 2px;
}
#password-modal button[style*="background-color"], #edit-profile-modal button[style*="background-color"] {
    border-radius: .9rem !important;
    box-shadow: 4px 4px 10px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -4px -4px 10px rgba(255, 255, 255, .85) !important;
    transition: transform .28s cubic-bezier(.2, .8, .2, 1), box-shadow .28s cubic-bezier(.2, .8, .2, 1), filter .28s ease;
}
#password-modal button[style*="background-color"]:hover, #edit-profile-modal button[style*="background-color"]:hover {
    transform: translateY(-2px);
    filter: brightness(1.04);
}
#password-modal button.border, #edit-profile-modal button.border {
    border: 0 !important;
    border-radius: .9rem !important;
    background: var(--neu-bg) !important;
    color: var(--neu-muted) !important;
    box-shadow: 4px 4px 8px var(--neu-dark-shadow), -4px -4px 8px var(--neu-light-shadow) !important;
}
#password-modal button.border:hover, #edit-profile-modal button.border:hover { box-shadow: inset 3px 3px 6px var(--neu-dark-shadow), inset -3px -3px 6px var(--neu-light-shadow) !important; }
#edit-profile-modal .bg-gray-50 { border: 0 !important; border-radius: 1rem !important; background: var(--neu-bg) !important; box-shadow: inset 4px 4px 8px rgba(163, 177, 198, .4), inset -4px -4px 8px rgba(255, 255, 255, .85) !important; }
#edit-profile-modal .bg-gray-50 h3 { color: var(--neu-text) !important; }

.profile-edit-btn:focus-visible, .profile-outline-btn:focus-visible {
    outline: 3px solid rgba(var(--primary-color-rgb, 124, 58, 237), .35);
    outline-offset: 3px;
}

@keyframes profile-card-in { from { opacity: 0; transform: translateY(14px) scale(.99); } to { opacity: 1; transform: translateY(0) scale(1); } }

@media (max-width: 640px) {
    .student-profile-page { min-height: calc(100vh + 2rem) !important; margin-top: -1rem; margin-bottom: -1rem; }
    .student-profile-page > .mx-auto { padding-top: 2rem !important; padding-bottom: 3rem !important; }
    .profile-hero { align-items: flex-start; flex-direction: column; gap: 1.25rem; padding-bottom: 1.25rem; }
    .profile-hero__actions, .profile-edit-btn { width: 100%; }
    .profile-edit-btn { justify-content: flex-start; }
    .profile-card { border-radius: 1.25rem !important; }
    #password-modal-content, #edit-profile-modal-content { border-radius: 1.25rem !important; }
}

@media (prefers-reduced-motion: reduce) {
    .profile-card, .profile-subcard { animation: none !important; }
    .profile-subcard, .profile-edit-btn, .profile-outline-btn { transition-duration: .01ms !important; }
}
</style>

<script>
// Function to calculate time ago
function timeAgo(date) {
    if (!date) return 'Never';
    
    const now = new Date();
    const pastDate = new Date(date);
    const diffInSeconds = Math.floor((now - pastDate) / 1000);
    
    if (diffInSeconds < 60) {
        return 'Just now';
    } else if (diffInSeconds < 3600) {
        const minutes = Math.floor(diffInSeconds / 60);
        return minutes === 1 ? '1 minute ago' : `${minutes} minutes ago`;
    } else if (diffInSeconds < 86400) {
        const hours = Math.floor(diffInSeconds / 3600);
        return hours === 1 ? '1 hour ago' : `${hours} hours ago`;
    } else if (diffInSeconds < 2592000) {
        const days = Math.floor(diffInSeconds / 86400);
        return days === 1 ? '1 day ago' : `${days} days ago`;
    } else if (diffInSeconds < 31536000) {
        const months = Math.floor(diffInSeconds / 2592000);
        return months === 1 ? '1 month ago' : `${months} months ago`;
    } else {
        const years = Math.floor(diffInSeconds / 31536000);
        return years === 1 ? '1 year ago' : `${years} years ago`;
    }
}

// Update last changed time on page load
document.addEventListener('DOMContentLoaded', function() {
    const lastChangedElement = document.getElementById('last-changed-time');
    if (lastChangedElement) {
        // You can pass the actual date from backend or use a placeholder
        // For now, we'll check if there's a data attribute or use Never
        const lastChangedDate = lastChangedElement.dataset.lastChanged || null;
        lastChangedElement.textContent = 'Last changed: ' + timeAgo(lastChangedDate);
    }
});

// Open Password Modal with animation
function openPasswordModal() {
    const modal = document.getElementById('password-modal');
    const modalContent = document.getElementById('password-modal-content');
    
    // Reset form and clear any errors
    const form = document.getElementById('changePasswordForm');
    if (form) {
        form.reset();
        // Remove any error classes
        const inputs = form.querySelectorAll('input');
        inputs.forEach(input => {
            input.classList.remove('border-red-500', 'shake');
        });
    }
    
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modal.classList.add('opacity-100');
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');
    }, 10);
}

// Close Password Modal with animation
function closePasswordModal() {
    const modal = document.getElementById('password-modal');
    const modalContent = document.getElementById('password-modal-content');
    
    modal.classList.remove('opacity-100');
    modal.classList.add('opacity-0');
    modalContent.classList.remove('scale-100');
    modalContent.classList.add('scale-95');
    
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

// Show toast notification
function showPasswordToast(message, type = 'error') {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.password-toast');
    existingToasts.forEach(toast => toast.remove());
    
    const toast = document.createElement('div');
    toast.className = 'password-toast fixed top-6 right-6 z-[10000] bg-white border-l-4 rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md toast-slide-in';
    toast.style.borderColor = type === 'error' ? '#ef4444' : '#10b981';
    
    const iconBg = type === 'error' ? '#fee2e2' : '#d1fae5';
    const iconColor = type === 'error' ? '#dc2626' : '#059669';
    const icon = type === 'error' ? 'ph-x-circle' : 'ph-check-circle';
    
    toast.innerHTML = `
        <div class="flex-shrink-0">
            <div class="h-10 w-10 rounded-full flex items-center justify-center" style="background-color: ${iconBg};">
                <i class="ph ${icon} text-xl" style="color: ${iconColor};"></i>
            </div>
        </div>
        <div class="flex-1">
            <p class="text-sm font-semibold text-gray-900">${type === 'error' ? 'Error!' : 'Success!'}</p>
            <p class="text-sm text-gray-600">${message}</p>
        </div>
        <button onclick="this.parentElement.remove()" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition">
            <i class="ph ph-x text-lg"></i>
        </button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        toast.classList.remove('toast-slide-in');
        toast.classList.add('toast-slide-out');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// Handle password change form submission
var changePasswordForm = document.getElementById('changePasswordForm');
if (changePasswordForm) {
    changePasswordForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const currentPasswordInput = document.getElementById('current_password');
        const newPasswordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('password_confirmation');
        
        // Remove previous error states
        [currentPasswordInput, newPasswordInput, confirmPasswordInput].forEach(input => {
            input.classList.remove('border-red-500', 'shake');
        });
        
        const formData = new FormData(this);
        
        // Disable submit button
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i>Updating...';
        
        fetch('{{ route("profile.password") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.errors) {
                // Handle validation errors
                if (data.errors.current_password) {
                    currentPasswordInput.classList.add('border-red-500', 'shake');
                    showPasswordToast(data.errors.current_password[0], 'error');
                    setTimeout(() => currentPasswordInput.classList.remove('shake'), 400);
                } else if (data.errors.password) {
                    newPasswordInput.classList.add('border-red-500', 'shake');
                    showPasswordToast(data.errors.password[0], 'error');
                    setTimeout(() => newPasswordInput.classList.remove('shake'), 400);
                } else if (data.errors.password_confirmation) {
                    confirmPasswordInput.classList.add('border-red-500', 'shake');
                    showPasswordToast(data.errors.password_confirmation[0], 'error');
                    setTimeout(() => confirmPasswordInput.classList.remove('shake'), 400);
                }
            } else if (data.message || data.success) {
                // Success
                showPasswordToast(data.message || 'Password changed successfully!', 'success');
                closePasswordModal();
                changePasswordForm.reset();
                
                // Update last changed time
                const lastChangedElement = document.getElementById('last-changed-time');
                if (lastChangedElement) {
                    lastChangedElement.dataset.lastChanged = new Date().toISOString();
                    lastChangedElement.textContent = 'Last changed: ' + timeAgo(new Date().toISOString());
                }
            }
            
            // Re-enable submit button
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        })
        .catch(error => {
            console.error('Error:', error);
            showPasswordToast('An error occurred. Please try again.', 'error');
            
            // Re-enable submit button
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });
}

// Open Edit Profile Modal with animation
function openEditProfileModal() {
    const modal = document.getElementById('edit-profile-modal');
    const modalContent = document.getElementById('edit-profile-modal-content');
    
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modal.classList.add('opacity-100');
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');
    }, 10);
}

// Close Edit Profile Modal with animation
function closeEditProfileModal() {
    const modal = document.getElementById('edit-profile-modal');
    const modalContent = document.getElementById('edit-profile-modal-content');
    
    modal.classList.remove('opacity-100');
    modal.classList.add('opacity-0');
    modalContent.classList.remove('scale-100');
    modalContent.classList.add('scale-95');
    
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

// Toggle password visibility
function togglePasswordVisibility(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    
    if (input.type === 'password') {
        input.type = 'text';
        input.style.webkitTextSecurity = 'none';
        icon.classList.remove('ph-eye');
        icon.classList.add('ph-eye-slash');
    } else {
        input.type = 'password';
        input.style.webkitTextSecurity = 'disc';
        icon.classList.remove('ph-eye-slash');
        icon.classList.add('ph-eye');
    }
}

// Format Student ID - only numbers with dash
function formatStudentId(input) {
    let value = input.value.replace(/\D/g, ''); // Remove non-digits
    
    if (value.length > 4) {
        value = value.substring(0, 4) + '-' + value.substring(4, 8);
    }
    
    input.value = value;
}

// Apply to modal student ID
var modalStudentId = document.getElementById('modal_student_id');
if (modalStudentId) {
    modalStudentId.addEventListener('input', function() {
        formatStudentId(this);
    });
    modalStudentId.setAttribute('maxlength', '9'); // 0000-0000 = 9 chars
}

// Update hidden name field
function updateHiddenNameField() {
    const firstName = (document.getElementById('modal_first_name') || {}).value || '';
    const middleName = (document.getElementById('modal_middle_name') || {}).value || '';
    const lastName = (document.getElementById('modal_last_name') || {}).value || '';
    const suffix = (document.getElementById('modal_suffix') || {}).value || '';
    document.getElementById('hiddenNameField').value = [firstName.trim(), middleName.trim(), lastName.trim(), suffix.trim()].filter(Boolean).join(' ');
}

// Update hidden name field when name parts change
['modal_first_name', 'modal_middle_name', 'modal_last_name', 'modal_suffix'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('input', updateHiddenNameField);
});

// Dynamic section filtering based on course
var modalCourseSelect = document.getElementById('modal_course');
var modalSectionSelect = document.getElementById('modal_section');

if (modalCourseSelect && modalSectionSelect) {
    function updateSectionOptions() {
        const selectedCourse = modalCourseSelect.value;
        const bsitOnlyOptions = modalSectionSelect.querySelectorAll('.bsit-only');
        
        if (selectedCourse === 'BSCS') {
            // Hide C, D, E for BSCS
            bsitOnlyOptions.forEach(option => {
                option.style.display = 'none';
                option.disabled = true;
            });
            
            // Reset section if it was C, D, or E
            if (['C', 'D', 'E'].includes(modalSectionSelect.value)) {
                modalSectionSelect.value = '';
            }
        } else {
            // Show all options for BSIT
            bsitOnlyOptions.forEach(option => {
                option.style.display = 'block';
                option.disabled = false;
            });
        }
    }
    
    modalCourseSelect.addEventListener('change', updateSectionOptions);
    updateSectionOptions();
}

// Form submission with validation
var editProfileForm = document.getElementById('editProfileForm');
if (editProfileForm) {
    editProfileForm.addEventListener('submit', function(e) {
        // Update hidden name field before submission
        updateHiddenNameField();
        
        const submitBtn = document.getElementById('modalSubmitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i>Updating...';
    });
}

// Photo upload handling
var selectedPhotoFile = null;
var defaultPhotoUrl = '{{ $user->photo ? storage_url($user->photo) : "" }}';

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

    document.getElementById('photo-actions').classList.remove('hidden');
}

function savePhoto() {
    if (!selectedPhotoFile) return;

    const form = document.getElementById('photo-form');
    const formData = new FormData(form);
    formData.set('photo', selectedPhotoFile);

    const saveBtn = document.querySelector('#photo-actions button:first-child');
    const originalText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i>Saving...';

    fetch('{{ route("profile.photo.update") }}', {
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
            document.getElementById('photo-actions').classList.add('hidden');
            selectedPhotoFile = null;
            document.getElementById('photo-input').value = '';
            const avatar = document.getElementById('profile-avatar');
            if (avatar.tagName === 'IMG') {
                avatar.src = data.photo_url + '?t=' + Date.now();
            }
            // Update nav profile images
            const navWrappers = document.querySelectorAll('.nav-avatar-wrapper');
            navWrappers.forEach(wrapper => {
                const isMobile = !!wrapper.closest('.p-4');
                const size = isMobile ? 'h-12 w-12' : 'h-8 w-8';
                const extra = isMobile ? 'flex-shrink-0' : 'mr-2';
                const newUrl = data.photo_url + '?t=' + Date.now();
                wrapper.innerHTML = `<img src="${newUrl}" alt="" class="${size} rounded-full nav-profile-img object-cover ${extra}">`;
            });
            showPasswordToast(data.message || 'Profile photo updated!', 'success');
        } else {
            showPasswordToast(data.message || 'Failed to update photo.', 'error');
        }
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
    })
    .catch(() => {
        showPasswordToast('An error occurred. Please try again.', 'error');
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
    });
}

function cancelPhoto() {
    selectedPhotoFile = null;
    document.getElementById('photo-input').value = '';
    document.getElementById('photo-actions').classList.add('hidden');

    const avatar = document.getElementById('profile-avatar');
    if (defaultPhotoUrl) {
        avatar.src = defaultPhotoUrl + '?t=' + Date.now();
    } else {
        const wrapper = document.createElement('div');
        wrapper.id = 'profile-avatar';
        wrapper.className = 'w-24 h-24 sm:w-32 sm:h-32 rounded-full border-4 border-white shadow-xl flex items-center justify-center';
        wrapper.style.backgroundColor = '#F9F5FF';
        wrapper.innerHTML = '<i class="ph ph-user text-4xl sm:text-5xl" style="color: var(--primary-color);"></i>';
        avatar.parentNode.replaceChild(wrapper, avatar);
    }
}

// Auto-fit profile name & course so they always fit beside the avatar
function fitProfileText() {
    const elements = document.querySelectorAll('.profile-fit-text');
    if (!elements.length) return;

    elements.forEach((el) => {
        el.style.fontSize = '';
        el.style.whiteSpace = '';

        const parent = el.parentElement;
        if (!parent) return;
        const available = parent.clientWidth;
        if (!available) return;

        el.style.whiteSpace = 'nowrap';
        let size = parseFloat(window.getComputedStyle(el).fontSize);
        const minSize = 12;

        el.style.fontSize = size + 'px';
        while (el.scrollWidth > available && size > minSize) {
            size -= 0.5;
            el.style.fontSize = size + 'px';
        }

        // Never overflow: fall back to wrapping if even the minimum is too wide
        if (el.scrollWidth > available) {
            el.style.whiteSpace = 'normal';
            el.style.fontSize = '';
        }
    });
}

fitProfileText();
window.addEventListener('resize', fitProfileText);
if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(fitProfileText);
}
setTimeout(fitProfileText, 300);

// ===== WebAuthn Fingerprint Logic (Portfolio) =====
async function ensureWebAuthnLoaded() {
    if (typeof WebAuthn !== 'undefined' && WebAuthn.isPlatformAvailable) return true;
    // Try to load script dynamically (covers fragment navigation before layout script injects)
    return new Promise((resolve) => {
        if (document.querySelector('script[src*="webauthn.js"]') && typeof WebAuthn !== 'undefined') { resolve(true); return; }
        const s = document.createElement('script');
        s.src = "{{ asset('js/webauthn.js') }}";
        s.onload = () => resolve(typeof WebAuthn !== 'undefined');
        s.onerror = () => resolve(false);
        document.head.appendChild(s);
        // fallback timeout
        setTimeout(() => resolve(typeof WebAuthn !== 'undefined'), 2000);
    });
}

function isMobileDeviceFallback() {
    try {
        if (typeof WebAuthn !== 'undefined' && WebAuthn.isMobile) return WebAuthn.isMobile();
    } catch(e) {}
    const ua = navigator.userAgent || '';
    const mobileUA = /android|iphone|ipad|ipod|mobile/i.test(ua);
    const hasTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
    const smallViewport = window.matchMedia('(max-width: 1024px)').matches;
    return mobileUA || (hasTouch && smallViewport);
}

// Desktop-block flags (buttons look enabled but are intercepted when true)
let webauthnChangeBlocked = false;
let webauthnEnableBlocked = false;

function setWebAuthnTipArmed(tipId, armed) {
    const tip = document.getElementById(tipId);
    if (!tip) return;
    // Inline style beats the group-hover rule: hide tip when not blocked.
    tip.style.display = armed ? '' : 'none';
}

function setChangeBlocked(blocked) {
    webauthnChangeBlocked = blocked;
    const btn = document.getElementById('webauthn-change-btn-main');
    if (btn) {
        btn.setAttribute('aria-disabled', blocked ? 'true' : 'false');
        btn.title = blocked ? 'Available on mobile only' : '';
    }
    setWebAuthnTipArmed('webauthn-change-tip', blocked);
}

function setEnableBlocked(blocked) {
    webauthnEnableBlocked = blocked;
    const btn = document.getElementById('webauthn-enable-btn');
    if (btn) {
        btn.setAttribute('aria-disabled', blocked ? 'true' : 'false');
        btn.title = blocked ? 'Available on mobile only' : '';
    }
    setWebAuthnTipArmed('webauthn-enable-tip', blocked);
}

function isTouchNoHover() {
    try {
        return window.matchMedia('(hover: none)').matches || (navigator.maxTouchPoints > 0 && window.matchMedia('(max-width: 1024px)').matches);
    } catch(e) { return false; }
}

function openWebAuthnMobileOnlyModal() {
    const m = document.getElementById('webauthn-mobile-only-modal');
    if (!m) return;
    m.classList.remove('hidden');
    setTimeout(() => {
        m.classList.remove('opacity-0');
        const c = document.getElementById('webauthn-mobile-only-content');
        if (c) { c.classList.remove('scale-95'); c.classList.add('scale-100'); }
    }, 10);
}
function closeWebAuthnMobileOnlyModal() {
    const m = document.getElementById('webauthn-mobile-only-modal');
    if (!m) return;
    m.classList.add('opacity-0');
    const c = document.getElementById('webauthn-mobile-only-content');
    if (c) { c.classList.remove('scale-100'); c.classList.add('scale-95'); }
    setTimeout(() => m.classList.add('hidden'), 300);
}

// Click guards: look enabled, but desktop click is intercepted (hover shows tip, tap opens modal)
function handleBlockedChangeClick(e) {
    if (webauthnChangeBlocked) {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        if (isTouchNoHover()) openWebAuthnMobileOnlyModal();
        return false;
    }
    openWebAuthnChangeModal();
}
function handleBlockedEnableClick(e) {
    if (webauthnEnableBlocked) {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        if (isTouchNoHover()) openWebAuthnMobileOnlyModal();
        return false;
    }
    handleWebAuthnEnable();
}

async function refreshWebAuthnStatus() {
    const statusEl = document.getElementById('webauthn-status');
    const notSupported = document.getElementById('webauthn-not-supported');
    const enabled = document.getElementById('webauthn-enabled');
    const disabled = document.getElementById('webauthn-disabled');
    if (!statusEl || !enabled || !disabled) return;

    await ensureWebAuthnLoaded();

    // Hide all first (yellow banner stays hidden always)
    enabled.classList.add('hidden');
    disabled.classList.add('hidden');
    if (notSupported) notSupported.classList.add('hidden');
    setChangeBlocked(false);
    setEnableBlocked(false);

    // Check secure context + platform availability + MOBILE ONLY
    const isSecure = location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
    const isMobile = isMobileDeviceFallback();
    let platformOk = false;
    try {
        if (window.PublicKeyCredential && PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable) {
            platformOk = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
        } else {
            platformOk = !!window.PublicKeyCredential;
        }
    } catch(e) { platformOk = false; }

    // Fetch actual status from server regardless (to show enabled state)
    let hasCredential = false;
    let createdAt = null;
    try {
        const res = await fetch('/webauthn/status', { headers: { 'Accept': 'application/json' } });
        const data = await res.json().catch(() => ({}));
        if (data.success) {
            hasCredential = !!data.has_credential;
            if (data.credential) createdAt = data.credential.created_at;
        }
    } catch(e) {}

    statusEl.classList.add('hidden');

    if (hasCredential) {
        enabled.classList.remove('hidden');
        const detail = document.getElementById('webauthn-enabled-detail');
        if (detail && createdAt) {
            const d = new Date(createdAt);
            detail.textContent = 'Registered on ' + d.toLocaleDateString() + ' • ' + d.toLocaleTimeString();
        }
        // Desktop: Change looks enabled but is intercepted (hover tip / tap modal). Remove stays usable.
        setChangeBlocked(!isMobile);
        return;
    }

    // No credential yet — enforce mobile-only without any yellow banner
    if (!isMobile) {
        disabled.classList.remove('hidden');
        setEnableBlocked(true);
        return;
    }

    if (!platformOk || !isSecure || !window.PublicKeyCredential) {
        disabled.classList.remove('hidden');
        setEnableBlocked(true);
        return;
    }

    disabled.classList.remove('hidden');
    setEnableBlocked(false);
}

async function handleWebAuthnEnable() {
    const btn = document.getElementById('webauthn-enable-btn');
    const original = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i> Waiting for fingerprint...'; }
    try {
        const isMobile = isMobileDeviceFallback();
        if (!isMobile) {
            throw new Error('Fingerprint registration is available on mobile phones only. Please open this page on your phone.');
        }
        const loaded = await ensureWebAuthnLoaded();
        if (!loaded || typeof WebAuthn === 'undefined') throw new Error('Fingerprint helper failed to load. Please refresh and try again.');
        const data = await WebAuthn.registerFingerprint();
        showPasswordToast(data.message || 'Fingerprint enabled!', 'success');
        await refreshWebAuthnStatus();
    } catch (err) {
        const msg = err.message || 'Failed to enable fingerprint.';
        const low = msg.toLowerCase();
        if (low.includes('no fingerprint') || low.includes('no face') || low.includes('face id') || low.includes('biometric') || low.includes('not set up') || low.includes('not configured') || low.includes('no platform authenticator') || low.includes('no sensor')) {
            showPasswordToast(msg, 'error');
        } else if (err.name === 'NotAllowedError' || low.includes('cancelled') || low.includes('timed out') || low.includes('timeout')) {
            // If message already mentions no fingerprint, it was handled above
            if (low.includes('no fingerprint') || low.includes('biometric')) {
                showPasswordToast(msg, 'error');
            } else {
                showPasswordToast('Fingerprint cancelled or timed out. If you have no fingerprint enrolled, please add one in Settings and try again.', 'error');
            }
        } else if (msg.includes('already registered')) {
            showPasswordToast(msg, 'error');
            await refreshWebAuthnStatus();
        } else {
            showPasswordToast(msg, 'error');
        }
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = original; }
    }
}

function openWebAuthnRemoveModal() {
    document.getElementById('webauthn-remove-modal').classList.remove('hidden');
    setTimeout(() => {
        const m = document.getElementById('webauthn-remove-modal');
        m.classList.remove('opacity-0');
        m.querySelector('#webauthn-remove-content').classList.remove('scale-95');
        m.querySelector('#webauthn-remove-content').classList.add('scale-100');
    }, 10);
}
function closeWebAuthnRemoveModal() {
    const m = document.getElementById('webauthn-remove-modal');
    const c = document.getElementById('webauthn-remove-content');
    m.classList.add('opacity-0');
    c.classList.remove('scale-100');
    c.classList.add('scale-95');
    setTimeout(() => m.classList.add('hidden'), 300);
    document.getElementById('webauthn-remove-password').value = '';
    document.getElementById('webauthn-remove-error').classList.add('hidden');
}
function openWebAuthnChangeModal() {
    document.getElementById('webauthn-change-modal').classList.remove('hidden');
    setTimeout(() => {
        const m = document.getElementById('webauthn-change-modal');
        m.classList.remove('opacity-0');
        m.querySelector('#webauthn-change-content').classList.remove('scale-95');
        m.querySelector('#webauthn-change-content').classList.add('scale-100');
    }, 10);
}
function closeWebAuthnChangeModal() {
    const m = document.getElementById('webauthn-change-modal');
    const c = document.getElementById('webauthn-change-content');
    m.classList.add('opacity-0');
    c.classList.remove('scale-100');
    c.classList.add('scale-95');
    setTimeout(() => m.classList.add('hidden'), 300);
    document.getElementById('webauthn-change-password').value = '';
    document.getElementById('webauthn-change-error').classList.add('hidden');
}

async function handleWebAuthnRemove() {
    const pw = document.getElementById('webauthn-remove-password').value;
    const errEl = document.getElementById('webauthn-remove-error');
    const btn = document.getElementById('webauthn-remove-btn');
    if (!pw) { errEl.textContent = 'Please enter your password.'; errEl.classList.remove('hidden'); return; }
    const orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i> Removing...';
    errEl.classList.add('hidden');
    try {
        const res = await fetch('/webauthn/credential', {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ password: pw })
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Failed to remove.');
        showPasswordToast(data.message, 'success');
        closeWebAuthnRemoveModal();
        await refreshWebAuthnStatus();
    } catch (err) {
        errEl.textContent = err.message || 'Failed to remove.';
        errEl.classList.remove('hidden');
    } finally {
        btn.disabled = false; btn.innerHTML = orig;
    }
}

async function handleWebAuthnChange() {
    const pw = document.getElementById('webauthn-change-password').value;
    const errEl = document.getElementById('webauthn-change-error');
    const btn = document.getElementById('webauthn-change-btn');
    if (!pw) { errEl.textContent = 'Please enter your password.'; errEl.classList.remove('hidden'); return; }
    const orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i> Verifying...';
    errEl.classList.add('hidden');
    try {
        // First remove old credential with password
        const delRes = await fetch('/webauthn/credential', {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ password: pw })
        });
        const delData = await delRes.json();
        if (!delData.success) throw new Error(delData.message || 'Password incorrect.');
        closeWebAuthnChangeModal();
        showPasswordToast('Old fingerprint removed. Now verify new fingerprint...', 'success');
        await new Promise(r => setTimeout(r, 800));
        // Then register new one
        await handleWebAuthnEnable();
    } catch (err) {
        errEl.textContent = err.message || 'Failed.';
        errEl.classList.remove('hidden');
    } finally {
        btn.disabled = false; btn.innerHTML = orig;
    }
}

// Run immediately and on both navigation events (full load + SPA fragment)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', refreshWebAuthnStatus);
} else {
    refreshWebAuthnStatus();
}
document.addEventListener('student:navigation-ready', refreshWebAuthnStatus);
window.addEventListener('pageshow', refreshWebAuthnStatus);
// Also trigger after a short delay to cover late-injected fragment scripts
setTimeout(refreshWebAuthnStatus, 500);
</script>
@endsection
