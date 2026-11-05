@extends(request()->boolean('fragment') ? 'layouts.fragment' : 'layouts.admin')

@section('title', 'My Profile')

@section('content')
@php
    $currentOrgId = session('current_organization_id');
    $profilePosition = $currentOrgId ? auth()->user()->getPositionInOrganization($currentOrgId) : null;
    $profilePosition = $profilePosition ? ucwords(strtolower(str_replace('_', ' ', $profilePosition))) : (auth()->user()->isSuperAdmin() ? 'Super Admin' : 'Admin');
@endphp

<div class="min-h-full bg-slate-50/60 px-4 py-5 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl">
        <div class="mb-5 flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                    <i class="ph ph-shield-check" style="color: var(--primary-color);"></i>
                    Account workspace
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">My Profile</h1>
                <p class="mt-1 text-sm text-slate-500">Manage your identity, access, and account security in one place.</p>
            </div>
            @if(auth()->user()->canSwitchView())
            @php
                $profileIsSwitchingToAdmin = auth()->user()->getActiveView() === 'student';
                $profileSwitchOrgId = session('current_organization_id') ?? auth()->user()->organizations()->first()?->id;
                $profileSwitchHasModules = $profileSwitchOrgId ? app(\App\Services\OrganizationPermissionService::class)->hasAnyModuleAccess(auth()->id(), (int) $profileSwitchOrgId) : true;
            @endphp
            @if($profileIsSwitchingToAdmin && !$profileSwitchHasModules)
                {{-- zero-module admins stay in Student View — switch hidden --}}
            @else
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('view.switch') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-400 hover:bg-slate-50">
                        <i class="ph ph-arrows-left-right mr-2" style="color: var(--primary-color);"></i>{{ auth()->user()->getActiveView() === 'student' ? 'Admin View' : 'Student View' }}
                    </button>
                </form>
            </div>
            @endif
            @endif
        </div>

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
                            <form id="photo-form" method="POST" action="{{ route('admin.profile.photo.update') }}" enctype="multipart/form-data">
                                @csrf
                                <input type="file" id="photo-input" name="photo" accept="image/*" class="hidden" onchange="onPhotoSelect(event)">
                            </form>
                            <div id="photo-actions" class="hidden absolute left-1/2 top-full z-10 mt-2 -translate-x-1/2 flex gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                                <button type="button" onclick="savePhoto()" class="whitespace-nowrap rounded-md px-2.5 py-1.5 text-xs font-semibold text-white" style="background-color: var(--primary-color);"><i class="ph ph-floppy-disk mr-1"></i>Save</button>
                                <button type="button" onclick="cancelPhoto()" class="whitespace-nowrap rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200">Cancel</button>
                            </div>
                            <p class="mt-2 text-xs text-slate-400">Click photo to update</p>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"><i class="ph ph-circle mr-1 text-[8px]"></i>Active account</span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ ucwords(str_replace('_', ' ', $user->role)) }}</span>
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
                                <p class="mt-0.5 text-xs text-slate-500">Details used across your admin account.</p>
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
                        <div class="flex items-center justify-between gap-3 py-3">
                            <span class="text-xs text-slate-500">Access level</span>
                            <span class="text-right text-sm font-semibold text-slate-800">{{ ucwords(str_replace('_', ' ', $user->role)) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3 py-3">
                            <span class="text-xs text-slate-500">Position</span>
                            <span class="text-right text-sm font-semibold text-slate-800">{{ $profilePosition }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3 py-3">
                            <span class="text-xs text-slate-500">Profile photo</span>
                            <span class="text-sm font-semibold {{ $user->photo ? 'text-emerald-700' : 'text-slate-500' }}">{{ $user->photo ? 'Uploaded' : 'Not set' }}</span>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<!-- Password Change Modal -->
<div id="password-modal" class="hidden fixed inset-0 bg-black/50 modal-backdrop flex items-start sm:items-center justify-center z-50 transition-opacity duration-300 opacity-0 p-3 sm:p-4 overflow-y-auto">
    <div id="password-modal-content" class="bg-white rounded-xl shadow-xl p-5 sm:p-8 max-w-md w-full my-4 sm:my-0 transform transition-all duration-300 scale-95">
        <div class="flex justify-between items-center mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Change Password</h2>
            <button onclick="closePasswordModal()" class="text-gray-400 hover:text-gray-600">
                <i class="ph ph-x text-xl"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.profile.password') }}" id="changePasswordForm">
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
<div id="edit-profile-modal" class="hidden fixed inset-0 bg-black/50 modal-backdrop flex items-start sm:items-center justify-center z-50 overflow-y-auto transition-opacity duration-300 opacity-0 p-3 sm:p-4">
    <div id="edit-profile-modal-content" class="bg-white rounded-xl shadow-xl p-5 sm:p-8 max-w-4xl w-full my-4 sm:my-8 transform transition-all duration-300 scale-95">
        <div class="flex justify-between items-center mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Edit Profile</h2>
            <button onclick="closeEditProfileModal()" class="text-gray-400 hover:text-gray-600">
                <i class="ph ph-x text-xl"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.profile.update') }}" id="editProfileForm">
            @csrf
            @method('PUT')

            <input type="hidden" name="email" value="{{ $user->email }}">
            <input type="hidden" name="name" value="{{ title_case_name(trim((string) old('first_name', $user->first_name ?? '') . ' ' . (string) old('last_name', $user->last_name ?? ''))) }}" id="hiddenNameField">

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="ph ph-user-circle mr-2" style="color: #D4AF37;"></i>
                    Personal Information
                </h3>
                <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                        <div>
                            <label for="modal_first_name" class="block text-gray-700 text-sm font-semibold mb-2">First Name</label>
                            <input type="text" name="first_name" id="modal_first_name" value="{{ title_case_name((string) old('first_name', $user->first_name ?? '')) }}" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                        <div>
                            <label for="modal_last_name" class="block text-gray-700 text-sm font-semibold mb-2">Last Name</label>
                            <input type="text" name="last_name" id="modal_last_name" value="{{ title_case_name((string) old('last_name', $user->last_name ?? '')) }}" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                    </div>
                </div>
            </div>

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

<style>
input[type="password"]::-ms-reveal,
input[type="password"]::-ms-clear {
    display: none;
}
input[type="password"]::-webkit-credentials-auto-fill-button,
input[type="password"]::-webkit-strong-password-auto-fill-button {
    display: none;
}
input[type="password"]::-webkit-textfield-decoration-container {
    display: none !important;
}
input[type="password"]::-webkit-security-textfield-decoration {
    display: none !important;
}
input[type="password"]::-moz-reveal {
    display: none;
}
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}
.shake {
    animation: shake 0.4s;
}
@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
@keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}
.toast-slide-in {
    animation: slideInRight 0.3s ease-out forwards;
}
.toast-slide-out {
    animation: slideOutRight 0.3s ease-in forwards;
}
</style>

<script>
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

document.addEventListener('DOMContentLoaded', function() {
    const lastChangedElement = document.getElementById('last-changed-time');
    if (lastChangedElement) {
        const lastChangedDate = lastChangedElement.dataset.lastChanged || null;
        lastChangedElement.textContent = 'Last changed: ' + timeAgo(lastChangedDate);
    }
});

function openPasswordModal() {
    const modal = document.getElementById('password-modal');
    const modalContent = document.getElementById('password-modal-content');
    const form = document.getElementById('changePasswordForm');
    if (form) {
        form.reset();
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

function showPasswordToast(message, type = 'error') {
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
    setTimeout(() => {
        toast.classList.remove('toast-slide-in');
        toast.classList.add('toast-slide-out');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

const changePasswordForm = document.getElementById('changePasswordForm');
if (changePasswordForm) {
    changePasswordForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const currentPasswordInput = document.getElementById('current_password');
        const newPasswordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('password_confirmation');
        [currentPasswordInput, newPasswordInput, confirmPasswordInput].forEach(input => {
            input.classList.remove('border-red-500', 'shake');
        });
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i>Updating...';
        fetch('{{ route("admin.profile.password") }}', {
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
                showPasswordToast(data.message || 'Password changed successfully!', 'success');
                closePasswordModal();
                changePasswordForm.reset();
                const lastChangedElement = document.getElementById('last-changed-time');
                if (lastChangedElement) {
                    lastChangedElement.dataset.lastChanged = new Date().toISOString();
                    lastChangedElement.textContent = 'Last changed: ' + timeAgo(new Date().toISOString());
                }
            }
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        })
        .catch(error => {
            console.error('Error:', error);
            showPasswordToast('An error occurred. Please try again.', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });
}

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

function updateHiddenNameField() {
    const firstName = document.getElementById('modal_first_name').value.trim();
    const lastName = document.getElementById('modal_last_name').value.trim();
    document.getElementById('hiddenNameField').value = firstName + ' ' + lastName;
}

const modalFirstName = document.getElementById('modal_first_name');
const modalLastName = document.getElementById('modal_last_name');
if (modalFirstName && modalLastName) {
    modalFirstName.addEventListener('input', updateHiddenNameField);
    modalLastName.addEventListener('input', updateHiddenNameField);
}

const editProfileForm = document.getElementById('editProfileForm');
if (editProfileForm) {
    editProfileForm.addEventListener('submit', function(e) {
        updateHiddenNameField();
        const submitBtn = document.getElementById('modalSubmitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i>Updating...';
    });
}

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
    fetch('{{ route("admin.profile.photo.update") }}', {
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
</script>
@endsection
