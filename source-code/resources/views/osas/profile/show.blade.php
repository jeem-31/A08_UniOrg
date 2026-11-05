@extends('layouts.osas')

@section('title', 'My Profile')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
    <div class="mb-6 sm:mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold" style="color: #26488C;">My Profile</h1>
            <p class="text-sm sm:text-base text-gray-600 mt-1 sm:mt-2">Manage your personal information and account settings</p>
        </div>
        <button onclick="openEditProfileModal()" class="px-4 sm:px-6 py-2.5 sm:py-3 text-white font-semibold rounded-lg hover:opacity-90 transition text-sm sm:text-base self-start sm:self-auto" style="background-color: #26488C;">
            <i class="ph ph-pencil-simple mr-2" style="color: #C4973B;"></i>Edit Profile
        </button>
    </div>

    @if(session('success'))
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

    <div class="bg-white rounded-lg shadow-lg">
        <div class="h-24 sm:h-32 rounded-t-lg" style="background-color: #26488C;"></div>
        <div class="px-4 sm:px-8 pb-6 sm:pb-8">
            <div class="flex flex-col sm:flex-row sm:items-end -mt-16 sm:-mt-[5.5rem] mb-4 sm:mb-6 mobile-profile-row">
                <div class="relative flex-shrink-0">
                    <div id="avatar-wrapper" onclick="document.getElementById('photo-input').click()" class="cursor-pointer group relative">
                        @if($user->photo)
                            <img id="profile-avatar" src="{{ storage_url($user->photo) }}" alt="{{ $user->full_name }}" class="w-24 h-24 sm:w-32 sm:h-32 rounded-full border-4 border-white shadow-xl object-cover">
                        @else
                            <div id="profile-avatar" class="w-24 h-24 sm:w-32 sm:h-32 rounded-full border-4 border-white shadow-xl flex items-center justify-center" style="background-color: #F9F5FF;">
                                <i class="ph ph-user text-4xl sm:text-5xl" style="color: #26488C;"></i>
                            </div>
                        @endif
                        <div class="absolute inset-0 rounded-full bg-black bg-opacity-0 group-hover:bg-opacity-40 transition-all flex items-center justify-center">
                            <i class="ph ph-camera text-white text-2xl opacity-0 group-hover:opacity-100 transition-opacity"></i>
                        </div>
                    </div>
                    <form id="photo-form" method="POST" action="{{ route('osas.profile.photo.update') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="file" id="photo-input" name="photo" accept="image/*" class="hidden" onchange="onPhotoSelect(event)">
                    </form>
                    <div id="photo-actions" class="hidden absolute left-full ml-3 sm:right-full sm:mr-3 sm:left-auto sm:ml-0 top-1/2 -translate-y-1/2 flex gap-2 bg-white rounded-lg shadow-lg border border-gray-200 px-3 py-2.5 z-10">
                        <button type="button" onclick="savePhoto()" class="px-3 py-1.5 text-xs font-semibold text-white rounded-lg hover:opacity-90 transition whitespace-nowrap" style="background-color: #26488C;">
                            <i class="ph ph-floppy-disk mr-1"></i>Save
                        </button>
                        <button type="button" onclick="cancelPhoto()" class="px-3 py-1.5 text-xs font-semibold text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition whitespace-nowrap">
                            Cancel
                        </button>
                    </div>
                </div>
                <div class="sm:ml-6 mt-3 sm:mt-0 sm:mb-4 min-w-0 overflow-hidden">
                    <h2 id="profile-name" class="text-xl sm:text-2xl md:text-3xl font-bold text-white transition-colors duration-300 truncate">
                        <span class="hidden sm:inline">{{ $user->full_name }}</span>
                        <span class="sm:hidden">{{ $user->first_name }} {{ $user->last_name }}</span>
                    </h2>
                    <p class="text-sm sm:text-base text-gray-600 truncate">
                        @php
                            $currentOrgId = session('current_organization_id');
                            $userPosition = $currentOrgId ? auth()->user()->getPositionInOrganization($currentOrgId) : null;
                            if ($userPosition) {
                                $userPosition = \App\Models\OrganizationUser::formatDisplayLabel($userPosition);
                                $formattedPosition = str_replace('_', ' ', $userPosition);
                                $formattedPosition = ucwords(strtolower($formattedPosition));
                                echo $formattedPosition;
                            } else {
                                echo 'Admin';
                            }
                        @endphp
                    </p>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const nameEl = document.getElementById('profile-name');
                    if (nameEl) {
                        nameEl.style.color = '#ffffff';
                        nameEl.style.textShadow = '0 2px 4px rgba(0,0,0,0.5)';
                    }
                });
            </script>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 lg:gap-8">
                <div class="bg-gray-50 rounded-lg p-4 sm:p-6">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4 flex items-center">
                        <i class="ph ph-user-circle mr-2" style="color: #C4973B;"></i>
                        Personal Information
                    </h3>
                    <div class="space-y-3 sm:space-y-4">
                        <div>
                            <p class="text-xs sm:text-sm text-gray-600 mb-1">Email Address</p>
                            <p class="font-semibold text-gray-900 text-sm sm:text-base break-words">{{ $user->email }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-lg p-4 sm:p-6">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4 flex items-center">
                        <i class="ph ph-gear mr-2" style="color: #C4973B;"></i>
                        Account Settings
                    </h3>
                    <div class="space-y-3 sm:space-y-4">
                        <div>
                            <p class="text-xs sm:text-sm text-gray-600 mb-1">Password</p>
                            <p class="font-semibold text-gray-900 text-sm sm:text-base" id="last-changed-time" data-last-changed="{{ $user->password_changed_at ? $user->password_changed_at->toISOString() : '' }}">Last changed: Never</p>
                        </div>
                        <button onclick="openPasswordModal()"
                            class="w-full px-4 py-2.5 font-semibold rounded-lg transition border text-sm"
                            style="color: #26488C; border-color: #26488C;"
                            onmouseover="this.style.backgroundColor='#F9F5FF'"
                            onmouseout="this.style.backgroundColor='transparent'">
                            Change Password
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Password Change Modal -->
<div id="password-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 modal-backdrop flex items-start sm:items-center justify-center z-50 transition-opacity duration-300 opacity-0 p-3 sm:p-4 overflow-y-auto">
    <div id="password-modal-content" class="bg-white rounded-lg shadow-xl p-5 sm:p-8 max-w-md w-full my-4 sm:my-0 transform transition-all duration-300 scale-95">
        <div class="flex justify-between items-center mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Change Password</h2>
            <button onclick="closePasswordModal()" class="text-gray-400 hover:text-gray-600">
                <i class="ph ph-x text-xl"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('osas.profile.password') }}" id="changePasswordForm">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label for="current_password" class="block text-gray-700 text-sm font-semibold mb-2">Current Password</label>
                <div class="relative">
                    <input type="password" name="current_password" id="current_password" required autocomplete="new-password"
                        class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                        style="-webkit-text-security: disc; -webkit-appearance: none; appearance: none;">
                    <button type="button" onclick="togglePasswordVisibility('current_password', 'currentEyeIcon')"
                        style="color: #26488C;" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover: focus:outline-none transition-colors">
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
                        style="color: #26488C;" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover: focus:outline-none transition-colors">
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
                        style="color: #26488C;" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover: focus:outline-none transition-colors">
                        <i id="confirmEyeIcon" class="ph ph-eye text-lg"></i>
                    </button>
                </div>
            </div>

            <div class="flex gap-4">
                <button type="button" onclick="closePasswordModal()"
                    class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" class="flex-1 px-4 py-3 text-white font-medium rounded-lg hover:opacity-90 transition" style="background-color: #26488C;">
                    Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Profile Modal -->
<div id="edit-profile-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 modal-backdrop flex items-start sm:items-center justify-center z-50 overflow-y-auto transition-opacity duration-300 opacity-0 p-3 sm:p-4">
    <div id="edit-profile-modal-content" class="bg-white rounded-lg shadow-xl p-5 sm:p-8 max-w-4xl w-full my-4 sm:my-8 transform transition-all duration-300 scale-95">
        <div class="flex justify-between items-center mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Edit Profile</h2>
            <button onclick="closeEditProfileModal()" class="text-gray-400 hover:text-gray-600">
                <i class="ph ph-x text-xl"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('osas.profile.update') }}" id="editProfileForm">
            @csrf
            @method('PUT')

            <input type="hidden" name="email" value="{{ $user->email }}">
            <input type="hidden" name="name" value="{{ old('first_name', $user->first_name ?? '') }} {{ old('last_name', $user->last_name ?? '') }}" id="hiddenNameField">

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="ph ph-user-circle mr-2" style="color: #D4AF37;"></i>
                    Personal Information
                </h3>
                <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                        <div>
                            <label for="modal_first_name" class="block text-gray-700 text-sm font-semibold mb-2">First Name</label>
                            <input type="text" name="first_name" id="modal_first_name" value="{{ old('first_name', $user->first_name ?? '') }}" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                        <div>
                            <label for="modal_last_name" class="block text-gray-700 text-sm font-semibold mb-2">Last Name</label>
                            <input type="text" name="last_name" id="modal_last_name" value="{{ old('last_name', $user->last_name ?? '') }}" required
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
                <button type="submit" id="modalSubmitBtn" class="flex-1 px-4 py-3 text-white font-medium rounded-lg hover:opacity-90 transition" style="background-color: #26488C;">
                    <i class="ph ph-floppy-disk mr-2" style="color: #C4973B;"></i>Update Profile
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
        fetch('{{ route("osas.profile.password") }}', {
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
    fetch('{{ route("osas.profile.photo.update") }}', {
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
        wrapper.innerHTML = '<i class="ph ph-user text-4xl sm:text-5xl" style="color: #26488C;"></i>';
        avatar.parentNode.replaceChild(wrapper, avatar);
    }
}
</script>
@endsection
