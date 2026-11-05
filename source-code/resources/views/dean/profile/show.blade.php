@extends('layouts.dean')

@section('title', 'My Profile')

@section('content')
@php
    $currentOrgId = session('current_organization_id');
    $profilePosition = $currentOrgId ? auth()->user()->getPositionInOrganization($currentOrgId) : null;
    $profilePosition = $profilePosition
        ? ucwords(strtolower(str_replace('_', ' ', \App\Models\OrganizationUser::formatDisplayLabel($profilePosition))))
        : (auth()->user()->isSuperAdmin() ? 'Dean' : 'Admin');
@endphp

<div class="dean-profile-page min-h-full bg-slate-50/60 px-4 py-5 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl">
        <header class="mb-5 flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                    <i class="ph ph-shield-check" style="color: var(--primary-color);"></i>
                    Account workspace
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">My Profile</h1>
                <p class="mt-1 text-sm text-slate-500">Manage your identity, access, and account security in one place.</p>
            </div>
            <a href="{{ route('dean.dashboard') }}" class="inline-flex items-center self-start rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 sm:self-auto">
                <i class="ph ph-arrow-left mr-2" style="color: var(--primary-color);"></i>Dashboard
            </a>
        </header>

        @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="mb-5 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm" role="status">
            <i class="ph ph-check-circle mt-0.5 text-emerald-600"></i>
            <div class="flex-1 text-emerald-800"><span class="font-semibold">Changes saved.</span> {{ session('success') }}</div>
            <button @click="show = false" class="text-emerald-600 hover:text-emerald-800"><i class="ph ph-x"></i></button>
        </div>
        @endif

        <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1.25fr)_minmax(300px,0.75fr)]">
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="h-2" style="background-color: var(--primary-color);"></div>
                <div class="p-5 sm:p-6">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                        <div class="relative flex-shrink-0 text-center sm:text-left">
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
                            <form id="photo-form" method="POST" action="{{ route('dean.profile.photo.update') }}" enctype="multipart/form-data">
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
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"><i class="ph ph-circle mr-1 text-[8px]"></i>Active account</span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Dean account</span>
                            </div>
                            <h2 id="profile-name" class="mt-3 truncate text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $user->full_name }}</h2>
                            <p class="mt-1 text-sm font-medium text-slate-500">{{ $profilePosition }}</p>
                            <div class="mt-4 flex items-center gap-2 text-sm text-slate-600">
                                <i class="ph ph-envelope w-4 text-center" style="color: var(--primary-color);"></i>
                                <span class="truncate">{{ $user->email }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 border-t border-slate-100 pt-5">
                        <div class="mb-3 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">Personal information</h3>
                                <p class="mt-0.5 text-xs text-slate-500">Details used across your Dean account.</p>
                            </div>
                            <button onclick="openEditProfileModal()" class="text-xs font-semibold transition hover:opacity-75" style="color: var(--primary-color);">Edit details <i class="ph ph-arrow-right ml-1"></i></button>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-lg border border-slate-200 bg-slate-50/70 px-3.5 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Full name</p>
                                <p class="mt-1 truncate text-sm font-semibold text-slate-800">{{ $user->full_name }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50/70 px-3.5 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Email address</p>
                                <p class="mt-1 truncate text-sm font-semibold text-slate-800">{{ $user->email }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="space-y-5">
                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Account security</h3>
                            <p class="mt-0.5 text-xs text-slate-500">Keep your sign-in credentials up to date.</p>
                        </div>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg" style="background-color: rgba(var(--primary-color-rgb, 130, 56, 179), 0.09); color: var(--primary-color);"><i class="ph ph-shield text-sm"></i></span>
                    </div>
                    <div class="p-5">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500"><i class="ph ph-key text-sm"></i></span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-800">Password</p>
                                    <p class="truncate text-xs text-slate-500" id="last-changed-time" data-last-changed="{{ $user->password_changed_at ? $user->password_changed_at->toISOString() : '' }}">Last changed: Never</p>
                                </div>
                            </div>
                            <button onclick="openPasswordModal()" class="flex-shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">Change</button>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h3 class="text-sm font-bold text-slate-900">Access summary</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Your current account context.</p>
                    </div>
                    <div class="divide-y divide-slate-100 px-5">
                        <div class="flex items-center justify-between gap-3 py-3"><span class="text-xs text-slate-500">Access level</span><span class="text-right text-sm font-semibold text-slate-800">Dean</span></div>
                        <div class="flex items-center justify-between gap-3 py-3"><span class="text-xs text-slate-500">Position</span><span class="text-right text-sm font-semibold text-slate-800">{{ $profilePosition }}</span></div>
                        <div class="flex items-center justify-between gap-3 py-3"><span class="text-xs text-slate-500">Profile photo</span><span class="text-sm font-semibold {{ $user->photo ? 'text-emerald-700' : 'text-slate-500' }}">{{ $user->photo ? 'Uploaded' : 'Not set' }}</span></div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<!-- Password Change Modal -->
<div id="password-modal" class="hidden fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 modal-backdrop p-3 opacity-0 transition-opacity duration-300 sm:items-center sm:p-4">
    <div id="password-modal-content" class="my-4 w-full max-w-md scale-95 transform rounded-xl bg-white p-5 shadow-xl transition-all duration-300 sm:my-0 sm:p-8">
        <div class="mb-4 flex items-center justify-between sm:mb-6"><h2 class="text-xl font-bold text-slate-900 sm:text-2xl">Change Password</h2><button onclick="closePasswordModal()" class="text-slate-400 hover:text-slate-600"><i class="ph ph-x text-xl"></i></button></div>
        <form method="POST" action="{{ route('dean.profile.password') }}" id="changePasswordForm">
            @csrf @method('PUT')
            <div class="mb-4"><label for="current_password" class="mb-2 block text-sm font-semibold text-slate-700">Current password</label><div class="relative"><input type="password" name="current_password" id="current_password" required autocomplete="current-password" class="w-full rounded-lg border border-slate-300 px-4 py-3 pr-12 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-purple-500"><button type="button" onclick="togglePasswordVisibility('current_password', 'currentEyeIcon')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" style="color: var(--primary-color);"><i id="currentEyeIcon" class="ph ph-eye text-lg"></i></button></div></div>
            <div class="mb-4"><label for="password" class="mb-2 block text-sm font-semibold text-slate-700">New password</label><div class="relative"><input type="password" name="password" id="password" required autocomplete="new-password" class="w-full rounded-lg border border-slate-300 px-4 py-3 pr-12 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-purple-500"><button type="button" onclick="togglePasswordVisibility('password', 'newEyeIcon')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" style="color: var(--primary-color);"><i id="newEyeIcon" class="ph ph-eye text-lg"></i></button></div></div>
            <div class="mb-6"><label for="password_confirmation" class="mb-2 block text-sm font-semibold text-slate-700">Confirm new password</label><div class="relative"><input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password" class="w-full rounded-lg border border-slate-300 px-4 py-3 pr-12 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-purple-500"><button type="button" onclick="togglePasswordVisibility('password_confirmation', 'confirmEyeIcon')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" style="color: var(--primary-color);"><i id="confirmEyeIcon" class="ph ph-eye text-lg"></i></button></div></div>
            <div class="flex gap-3"><button type="button" onclick="closePasswordModal()" class="flex-1 rounded-lg border border-slate-300 px-4 py-3 font-medium text-slate-700 transition hover:bg-slate-50">Cancel</button><button type="submit" class="flex-1 rounded-lg px-4 py-3 font-medium text-white transition hover:opacity-90" style="background-color: var(--primary-color);">Update password</button></div>
        </form>
    </div>
</div>

<!-- Edit Profile Modal -->
<div id="edit-profile-modal" class="hidden fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 modal-backdrop p-3 opacity-0 transition-opacity duration-300 sm:items-center sm:p-4">
    <div id="edit-profile-modal-content" class="my-4 w-full max-w-2xl scale-95 transform rounded-xl bg-white p-5 shadow-xl transition-all duration-300 sm:my-8 sm:p-8">
        <div class="mb-4 flex items-center justify-between sm:mb-6"><h2 class="text-xl font-bold text-slate-900 sm:text-2xl">Edit Profile</h2><button onclick="closeEditProfileModal()" class="text-slate-400 hover:text-slate-600"><i class="ph ph-x text-xl"></i></button></div>
        <form method="POST" action="{{ route('dean.profile.update') }}" id="editProfileForm">
            @csrf @method('PUT')
            <input type="hidden" name="email" value="{{ $user->email }}">
            <input type="hidden" name="name" value="{{ old('first_name', $user->first_name ?? '') }} {{ old('last_name', $user->last_name ?? '') }}" id="hiddenNameField">
            <div class="mb-6 rounded-lg border border-slate-200 bg-slate-50/70 p-4 sm:p-5"><h3 class="mb-4 flex items-center text-sm font-bold text-slate-900"><i class="ph ph-user-circle mr-2" style="color: var(--complementary-color);"></i>Personal information</h3><div class="grid gap-4 sm:grid-cols-2"><div><label for="modal_first_name" class="mb-2 block text-sm font-semibold text-slate-700">First name</label><input type="text" name="first_name" id="modal_first_name" value="{{ old('first_name', $user->first_name ?? '') }}" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-purple-500"></div><div><label for="modal_last_name" class="mb-2 block text-sm font-semibold text-slate-700">Last name</label><input type="text" name="last_name" id="modal_last_name" value="{{ old('last_name', $user->last_name ?? '') }}" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-purple-500"></div></div></div>
            <div class="flex gap-3"><button type="button" onclick="closeEditProfileModal()" class="flex-1 rounded-lg border border-slate-300 px-4 py-3 font-medium text-slate-700 transition hover:bg-slate-50">Cancel</button><button type="submit" id="modalSubmitBtn" class="flex-1 rounded-lg px-4 py-3 font-medium text-white transition hover:opacity-90" style="background-color: var(--primary-color);"><i class="ph ph-floppy-disk mr-2" style="color: var(--complementary-color);"></i>Update profile</button></div>
        </form>
    </div>
</div>

<style>
    .dean-profile-page { font-family: 'Plus Jakarta Sans', 'Inter', ui-sans-serif, system-ui, sans-serif; }
    .dean-profile-page .rounded-xl { border-radius: 8px; }
    .dean-profile-page input:focus { box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb, 130, 56, 179), .18); }
    .dean-profile-page button, .dean-profile-page a { -webkit-tap-highlight-color: transparent; }
    @keyframes deanProfileToastIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes deanProfileToastOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
    .dean-profile-toast-in { animation: deanProfileToastIn .25s ease-out forwards; }
    .dean-profile-toast-out { animation: deanProfileToastOut .25s ease-in forwards; }
    @keyframes deanProfileShake { 0%,100% { transform: translateX(0); } 25% { transform: translateX(-7px); } 75% { transform: translateX(7px); } }
    .dean-profile-shake { animation: deanProfileShake .35s; }
</style>

<script>
function deanTimeAgo(date) {
    if (!date) return 'Never';
    const seconds = Math.floor((Date.now() - new Date(date).getTime()) / 1000);
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)} minute${Math.floor(seconds / 60) === 1 ? '' : 's'} ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)} hour${Math.floor(seconds / 3600) === 1 ? '' : 's'} ago`;
    if (seconds < 2592000) return `${Math.floor(seconds / 86400)} day${Math.floor(seconds / 86400) === 1 ? '' : 's'} ago`;
    if (seconds < 31536000) return `${Math.floor(seconds / 2592000)} month${Math.floor(seconds / 2592000) === 1 ? '' : 's'} ago`;
    return `${Math.floor(seconds / 31536000)} year${Math.floor(seconds / 31536000) === 1 ? '' : 's'} ago`;
}

document.addEventListener('DOMContentLoaded', function () {
    const lastChanged = document.getElementById('last-changed-time');
    if (lastChanged) lastChanged.textContent = 'Last changed: ' + deanTimeAgo(lastChanged.dataset.lastChanged || null);
});

function deanToggleModal(modalId, contentId, open) {
    const modal = document.getElementById(modalId);
    const content = document.getElementById(contentId);
    if (!modal || !content) return;
    if (open) {
        modal.classList.remove('hidden');
        setTimeout(() => { modal.classList.remove('opacity-0'); modal.classList.add('opacity-100'); content.classList.remove('scale-95'); content.classList.add('scale-100'); }, 10);
    } else {
        modal.classList.remove('opacity-100'); modal.classList.add('opacity-0'); content.classList.remove('scale-100'); content.classList.add('scale-95');
        setTimeout(() => modal.classList.add('hidden'), 250);
    }
}

function openPasswordModal() { deanToggleModal('password-modal', 'password-modal-content', true); }
function closePasswordModal() { deanToggleModal('password-modal', 'password-modal-content', false); }
function openEditProfileModal() { deanToggleModal('edit-profile-modal', 'edit-profile-modal-content', true); }
function closeEditProfileModal() { deanToggleModal('edit-profile-modal', 'edit-profile-modal-content', false); }

function showDeanProfileToast(message, type = 'error') {
    document.querySelectorAll('.dean-profile-toast').forEach(toast => toast.remove());
    const toast = document.createElement('div');
    const success = type === 'success';
    toast.className = 'dean-profile-toast dean-profile-toast-in fixed right-5 top-5 z-[10000] flex max-w-md items-center gap-3 rounded-lg border-l-4 bg-white px-4 py-3 shadow-xl';
    toast.style.borderColor = success ? '#10b981' : '#ef4444';
    toast.innerHTML = `<span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full" style="background:${success ? '#d1fae5' : '#fee2e2'};color:${success ? '#059669' : '#dc2626'}"><i class="ph ${success ? 'ph-check' : 'ph-x'}"></i></span><span class="min-w-0 flex-1"><strong class="block text-sm text-slate-900">${success ? 'Success' : 'Error'}</strong><span class="block text-xs text-slate-600">${message}</span></span><button class="text-slate-400" onclick="this.closest('.dean-profile-toast').remove()"><i class="ph ph-x"></i></button>`;
    document.body.appendChild(toast);
    setTimeout(() => { toast.classList.remove('dean-profile-toast-in'); toast.classList.add('dean-profile-toast-out'); setTimeout(() => toast.remove(), 250); }, 4000);
}

function togglePasswordVisibility(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (!input || !icon) return;
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    icon.classList.toggle('ph-eye', !visible);
    icon.classList.toggle('ph-eye-slash', visible);
}

const deanPasswordForm = document.getElementById('changePasswordForm');
if (deanPasswordForm) deanPasswordForm.addEventListener('submit', function (event) {
    event.preventDefault();
    const submit = this.querySelector('button[type="submit"]');
    const original = submit.innerHTML;
    submit.disabled = true;
    submit.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i>Updating...';
    fetch('{{ route("dean.profile.password") }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }, body: new FormData(this) })
        .then(response => response.json().then(data => ({ ok: response.ok, data })))
        .then(({ ok, data }) => {
            if (!ok || data.errors) {
                const message = data.errors ? Object.values(data.errors)[0][0] : 'Unable to update password.';
                showDeanProfileToast(message);
                if (data.errors?.current_password) document.getElementById('current_password').classList.add('dean-profile-shake');
                return;
            }
            showDeanProfileToast(data.message || 'Password changed successfully!', 'success');
            closePasswordModal();
            deanPasswordForm.reset();
            const lastChanged = document.getElementById('last-changed-time');
            if (lastChanged) lastChanged.textContent = 'Last changed: Just now';
        })
        .catch(() => showDeanProfileToast('An error occurred. Please try again.'))
        .finally(() => { submit.disabled = false; submit.innerHTML = original; });
});

function updateDeanHiddenName() {
    const first = document.getElementById('modal_first_name');
    const last = document.getElementById('modal_last_name');
    const hidden = document.getElementById('hiddenNameField');
    if (first && last && hidden) hidden.value = `${first.value.trim()} ${last.value.trim()}`.trim();
}

['modal_first_name', 'modal_last_name'].forEach(id => document.getElementById(id)?.addEventListener('input', updateDeanHiddenName));
document.getElementById('editProfileForm')?.addEventListener('submit', function () {
    updateDeanHiddenName();
    const submit = document.getElementById('modalSubmitBtn');
    submit.disabled = true;
    submit.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i>Updating...';
});

let deanSelectedPhoto = null;
const deanDefaultPhoto = '{{ $user->photo ? storage_url($user->photo) : "" }}';

function onPhotoSelect(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    deanSelectedPhoto = file;
    const avatar = document.getElementById('profile-avatar');
    const reader = new FileReader();
    reader.onload = function (loadEvent) {
        if (avatar.tagName === 'IMG') avatar.src = loadEvent.target.result;
        else { const image = document.createElement('img'); image.id = 'profile-avatar'; image.src = loadEvent.target.result; image.alt = 'Preview'; image.className = avatar.className; avatar.parentNode.replaceChild(image, avatar); }
    };
    reader.readAsDataURL(file);
    document.getElementById('photo-actions').classList.remove('hidden');
}

function savePhoto() {
    if (!deanSelectedPhoto) return;
    const formData = new FormData(document.getElementById('photo-form'));
    formData.set('photo', deanSelectedPhoto);
    const button = document.querySelector('#photo-actions button:first-child');
    const original = button.innerHTML;
    button.disabled = true; button.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i>Saving...';
    fetch('{{ route("dean.profile.photo.update") }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }, body: formData })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                showDeanProfileToast(data.message || 'Failed to update photo.', 'error');
                return;
            }
            const url = data.photo_url + '?t=' + Date.now();
            const avatar = document.getElementById('profile-avatar');
            if (avatar.tagName === 'IMG') avatar.src = url;
            document.querySelectorAll('.nav-avatar-wrapper').forEach(wrapper => { const image = wrapper.querySelector('img'); if (image) image.src = url; });
            document.getElementById('photo-actions').classList.add('hidden');
            document.getElementById('photo-input').value = '';
            deanSelectedPhoto = null;
            showDeanProfileToast(data.message || 'Profile photo updated!', 'success');
        })
        .catch(() => showDeanProfileToast('Network error. Please try again.'))
        .finally(() => { button.disabled = false; button.innerHTML = original; });
}

function cancelPhoto() {
    deanSelectedPhoto = null;
    document.getElementById('photo-input').value = '';
    document.getElementById('photo-actions').classList.add('hidden');
    const avatar = document.getElementById('profile-avatar');
    if (deanDefaultPhoto) avatar.src = deanDefaultPhoto + '?t=' + Date.now();
    else if (avatar.tagName === 'IMG') { const placeholder = document.createElement('div'); placeholder.id = 'profile-avatar'; placeholder.className = avatar.className + ' flex items-center justify-center'; placeholder.style.backgroundColor = 'rgba(var(--primary-color-rgb, 130, 56, 179), 0.08)'; placeholder.innerHTML = '<i class="ph ph-user text-4xl" style="color: var(--primary-color);"></i>'; avatar.parentNode.replaceChild(placeholder, avatar); }
}

document.addEventListener('keydown', event => { if (event.key === 'Escape') { closePasswordModal(); closeEditProfileModal(); } });
</script>
@endsection
