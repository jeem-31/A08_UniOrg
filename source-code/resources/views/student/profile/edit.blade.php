@extends('layouts.app')

@section('title', 'Edit Profile')

@section('content')
<div class="student-neu-page max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-10">
    <div class="mb-6 sm:mb-8">
        <a href="{{ route('profile.show') }}" class="inline-flex items-center font-medium transition text-sm sm:text-base" 
           style="color: var(--primary-color);" 
           onmouseover="this.style.color='rgba(var(--primary-color-rgb, 130, 56, 179), 0.8)'"
           onmouseout="this.style.color='var(--primary-color)'">
            <i class="ph ph-arrow-left mr-2" style="color: #D4AF37;"></i>Back to Profile
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="h-20 sm:h-24" style="background: linear-gradient(135deg, var(--primary-color) 0%, rgba(var(--primary-color-rgb, 130, 56, 179), 0.8) 100%);"></div>
        <div class="px-4 sm:px-8 pb-6 sm:pb-8">
            <div class="flex flex-col sm:flex-row sm:items-center -mt-10 sm:-mt-12 mb-6 sm:mb-8">
                <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full border-4 border-white shadow-xl flex items-center justify-center flex-shrink-0" style="background-color: #F9F5FF;">
                    <i class="ph ph-user text-3xl sm:text-4xl" style="color: var(--primary-color);"></i>
                </div>
                <div class="sm:ml-6 mt-3 sm:mt-0">
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight" style="color: #D4AF37;">Edit Profile</h1>
                    <p class="text-sm sm:text-base text-gray-600 mt-1">Update your personal information</p>
                </div>
            </div>

            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')

                <!-- Hidden email field to satisfy backend validation (not editable by user) -->
                <input type="hidden" name="email" value="{{ $user->email }}">
                <!-- Hidden name field to satisfy backend validation (combined from first and last name) -->
                <input type="hidden" name="name" value="{{ title_case_name(trim((string) old('first_name', $user->first_name ?? '') . ' ' . (string) old('last_name', $user->last_name ?? ''))) }}" id="hiddenNameField">

                
                <div class="mb-6 sm:mb-8">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4 flex items-center">
                        <i class="ph ph-user-circle mr-2" style="color: #D4AF37;"></i>
                        Personal Information
                    </h3>
                    <div class="bg-gray-50 rounded-lg p-4 sm:p-6 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6">
                            <div>
                                <label for="first_name" class="block text-gray-700 text-sm font-semibold mb-2">First Name</label>
                                <input type="text" name="first_name" id="first_name" value="{{ title_case_name((string) old('first_name', $user->first_name ?? '')) }}" required
                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm sm:text-base">
                            </div>

                            <div>
                                <label for="last_name" class="block text-gray-700 text-sm font-semibold mb-2">Last Name</label>
                                <input type="text" name="last_name" id="last_name" value="{{ title_case_name((string) old('last_name', $user->last_name ?? '')) }}" required
                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm sm:text-base">
                            </div>

                            <div>
                                <label for="student_id" class="block text-gray-700 text-sm font-semibold mb-2">Student ID</label>
                                <input type="text" name="student_id" id="student_id" value="{{ old('student_id', $user->student_id) }}" required
                                    placeholder="0000-0000"
                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm sm:text-base">
                            </div>
                        </div>
                    </div>
                </div>

                @if($user->isStudent())
                <div class="mb-6 sm:mb-8">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4 flex items-center">
                        <i class="ph ph-graduation-cap mr-2" style="color: #D4AF37;"></i>
                        Academic Information
                    </h3>
                    <div class="bg-gray-50 rounded-lg p-4 sm:p-6 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6">
                            <div>
                                <label for="course" class="block text-gray-700 text-sm font-semibold mb-2">Course</label>
                                <select name="course" id="course" required
                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm sm:text-base">
                                    <option value="">Select Course</option>
                                    <option value="BSIT" {{ old('course', $user->course) == 'BSIT' ? 'selected' : '' }}>BSIT</option>
                                    <option value="BSCS" {{ old('course', $user->course) == 'BSCS' ? 'selected' : '' }}>BSCS</option>
                                </select>
                            </div>

                            <div>
                                <label for="year_level" class="block text-gray-700 text-sm font-semibold mb-2">Year Level</label>
                                <select name="year_level" id="year_level" required
                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm sm:text-base">
                                    <option value="">Select Year</option>
                                    <option value="1st Year" {{ old('year_level', $user->year_level) == '1st Year' ? 'selected' : '' }}>1st Year</option>
                                    <option value="2nd Year" {{ old('year_level', $user->year_level) == '2nd Year' ? 'selected' : '' }}>2nd Year</option>
                                    <option value="3rd Year" {{ old('year_level', $user->year_level) == '3rd Year' ? 'selected' : '' }}>3rd Year</option>
                                    <option value="4th Year" {{ old('year_level', $user->year_level) == '4th Year' ? 'selected' : '' }}>4th Year</option>
                                </select>
                            </div>

                            <div>
                                <label for="section" class="block text-gray-700 text-sm font-semibold mb-2">Section</label>
                                <select name="section" id="section" required
                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm sm:text-base">
                                    <option value="">Select</option>
                                    <option value="A" {{ old('section', $user->section) == 'A' ? 'selected' : '' }}>A</option>
                                    <option value="B" {{ old('section', $user->section) == 'B' ? 'selected' : '' }}>B</option>
                                    <option value="C" class="bsit-only" {{ old('section', $user->section) == 'C' ? 'selected' : '' }}>C</option>
                                    <option value="D" class="bsit-only" {{ old('section', $user->section) == 'D' ? 'selected' : '' }}>D</option>
                                    <option value="E" class="bsit-only" {{ old('section', $user->section) == 'E' ? 'selected' : '' }}>E</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                    <a href="{{ route('profile.show') }}" class="profile-cancel flex-1 text-center px-4 sm:px-6 py-2.5 sm:py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition text-sm sm:text-base">
                        Cancel
                    </a>
                    <button type="submit" id="submitBtn" class="flex-1 px-4 sm:px-6 py-2.5 sm:py-3 text-white font-medium rounded-lg hover:opacity-90 transition text-sm sm:text-base" style="background-color: var(--primary-color);">
                        <i class="ph ph-floppy-disk mr-2" style="color: #D4AF37;"></i>Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<style>
    .student-neu-page a.profile-cancel {
        border: 0 !important;
        background: var(--neu-bg) !important;
        color: var(--neu-muted) !important;
        box-shadow: 4px 4px 8px var(--neu-dark-shadow), -4px -4px 8px var(--neu-light-shadow) !important;
        transition: box-shadow .22s ease, color .22s ease;
    }
    .student-neu-page a.profile-cancel:hover {
        box-shadow: inset 3px 3px 6px var(--neu-dark-shadow), inset -3px -3px 6px var(--neu-light-shadow) !important;
        color: var(--neu-text) !important;
    }
    .student-neu-page .bg-gray-50.rounded-lg { box-shadow: inset 4px 4px 8px rgba(163, 177, 198, .4), inset -4px -4px 8px rgba(255, 255, 255, .85) !important; }
</style>

<script>
// Toast notification function with consistent design (matching announcement page)
function showToast(message, type = 'error') {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.toast-notification');
    existingToasts.forEach(toast => toast.remove());
    
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    
    // Colors based on type
    const borderColor = type === 'error' ? 'border-red-500' : 'border-green-500';
    const bgColor = type === 'error' ? 'bg-red-100' : 'bg-green-100';
    const iconColor = type === 'error' ? 'text-red-600' : 'text-green-600';
    const icon = type === 'error' ? 'exclamation-circle' : 'check-circle';
    const title = type === 'error' ? 'Error!' : 'Success!';
    
    toast.style.cssText = `
        position: fixed;
        top: 24px;
        right: 24px;
        z-index: 9999;
        max-width: 28rem;
        display: flex;
        align-items: center;
        background-color: white;
        border-left-width: 4px;
        border-radius: 0.5rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        padding: 1rem 1.5rem;
        animation: slideInRight 0.3s ease-out;
    `;
    
    toast.classList.add(borderColor);
    
    toast.innerHTML = `
        <div class="flex-shrink-0">
            <div class="h-10 w-10 rounded-full ${bgColor} flex items-center justify-center">
                <i class="ph fa-${icon} text-xl ${iconColor}"></i>
            </div>
        </div>
        <div class="flex-1 ml-3">
            <p class="text-sm font-semibold text-gray-900">${title}</p>
            <p class="text-sm text-gray-600">${message}</p>
        </div>
        <button onclick="this.parentElement.remove()" class="flex-shrink-0 ml-3 text-gray-400 hover:text-gray-600 transition">
            <i class="ph ph-x text-lg"></i>
        </button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto dismiss after 4 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease-out';
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 4000);
}

document.addEventListener('DOMContentLoaded', function() {
    // Show server-side errors as toast notifications
    @if ($errors->any())
        @foreach ($errors->all() as $error)
            showToast('{{ $error }}', 'error');
        @endforeach
    @endif
    
    @if (session('success'))
        showToast('{{ session('success') }}', 'success');
    @endif
    
    @if (session('info'))
        showToast('{{ session('info') }}', 'error');
    @endif
    
    // Basic form validation
    const firstNameInput = document.getElementById('first_name');
    const lastNameInput = document.getElementById('last_name');
    const studentIdInput = document.getElementById('student_id');
    const hiddenNameField = document.getElementById('hiddenNameField');
    const submitBtn = document.getElementById('submitBtn');
    
    // Function to update hidden name field
    function updateHiddenNameField() {
        const firstName = firstNameInput.value.trim();
        const lastName = lastNameInput.value.trim();
        hiddenNameField.value = firstName + ' ' + lastName;
    }
    
    // Update hidden name field when first or last name changes
    firstNameInput.addEventListener('input', updateHiddenNameField);
    lastNameInput.addEventListener('input', updateHiddenNameField);
    
    // Store original values to detect changes
    const originalValues = {
        firstName: firstNameInput.value.trim(),
        lastName: lastNameInput.value.trim(),
        studentId: studentIdInput.value.trim(),
        course: document.getElementById('course') ? document.getElementById('course').value : '',
        yearLevel: document.getElementById('year_level') ? document.getElementById('year_level').value : '',
        section: document.getElementById('section') ? document.getElementById('section').value : ''
    };

    // Student ID format validation
    if (studentIdInput) {
        studentIdInput.addEventListener('input', function() {
            const value = this.value;
            // Auto-format to 0000-0000
            if (value.length === 4 && !value.includes('-')) {
                this.value = value + '-';
            } else if (value.length > 9) {
                this.value = value.substring(0, 9);
            }
            
            // Add visual feedback for format
            if (value.match(/^\d{4}-\d{4}$/)) {
                this.classList.remove('border-red-500');
                this.classList.add('border-green-500');
            } else if (value.length > 0) {
                this.classList.remove('border-green-500');
                this.classList.add('border-red-500');
            }
        });
    }

    // Prevent form submission if validation fails
    const editForm = document.querySelector('form');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Always prevent default first
            
            // Update hidden name field FIRST before any validation
            updateHiddenNameField();
            
            const firstName = firstNameInput.value.trim();
            const lastName = lastNameInput.value.trim();
            const studentId = studentIdInput.value.trim();
            const course = document.getElementById('course') ? document.getElementById('course').value : '';
            const yearLevel = document.getElementById('year_level') ? document.getElementById('year_level').value : '';
            const section = document.getElementById('section') ? document.getElementById('section').value : '';
            
            let hasError = false;
            
            // Check if fields are empty
            if (!firstName) {
                firstNameInput.classList.add('border-red-500');
                firstNameInput.focus();
                showToast('Please enter your first name.', 'error');
                hasError = true;
                return;
            }
            
            if (!lastName) {
                lastNameInput.classList.add('border-red-500');
                lastNameInput.focus();
                showToast('Please enter your last name.', 'error');
                hasError = true;
                return;
            }
            
            if (!studentId) {
                studentIdInput.classList.add('border-red-500');
                studentIdInput.focus();
                showToast('Please enter your student ID.', 'error');
                hasError = true;
                return;
            } else if (!studentId.match(/^\d{4}-\d{4}$/)) {
                studentIdInput.classList.add('border-red-500');
                studentIdInput.focus();
                showToast('Student ID must be in format 0000-0000.', 'error');
                hasError = true;
                return;
            }
            
            // Check if academic fields are empty (for students)
            @if($user->isStudent())
            if (!course) {
                document.getElementById('course').classList.add('border-red-500');
                document.getElementById('course').focus();
                showToast('Please select your course.', 'error');
                hasError = true;
                return;
            }
            
            if (!yearLevel) {
                document.getElementById('year_level').classList.add('border-red-500');
                document.getElementById('year_level').focus();
                showToast('Please select your year level.', 'error');
                hasError = true;
                return;
            }
            
            if (!section) {
                document.getElementById('section').classList.add('border-red-500');
                document.getElementById('section').focus();
                showToast('Please select your section.', 'error');
                hasError = true;
                return;
            }
            @endif
            
            // Check if any changes were made
            const hasChanges = 
                firstName !== originalValues.firstName ||
                lastName !== originalValues.lastName ||
                studentId !== originalValues.studentId ||
                course !== originalValues.course ||
                yearLevel !== originalValues.yearLevel ||
                section !== originalValues.section;
            
            if (!hasChanges) {
                showToast('No changes detected. Please modify at least one field.', 'error');
                return;
            }
            
            // If validation passes, submit the form
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i>Updating...';
            
            // Debug: Log form data before submission
            const formData = new FormData(editForm);
            console.log('=== FORM SUBMISSION DEBUG ===');
            console.log('Original values:', originalValues);
            console.log('Current values:', {
                firstName: firstNameInput.value.trim(),
                lastName: lastNameInput.value.trim(),
                studentId: studentIdInput.value.trim(),
                course: document.getElementById('course') ? document.getElementById('course').value : '',
                yearLevel: document.getElementById('year_level') ? document.getElementById('year_level').value : '',
                section: document.getElementById('section') ? document.getElementById('section').value : ''
            });
            console.log('Has changes:', hasChanges);
            console.log('Submitting form data:');
            for (let [key, value] of formData.entries()) {
                console.log(`${key}: "${value}"`);
            }
            console.log('Form action:', editForm.action);
            console.log('Form method:', editForm.method);
            console.log('=== END DEBUG ===');
            
            // Actually submit the form
            editForm.submit();
        });
    }

    // Remove error border on input
    [firstNameInput, lastNameInput, studentIdInput].forEach(input => {
        if (input) {
            input.addEventListener('input', function() {
                this.classList.remove('border-red-500');
            });
        }
    });

    // Dynamic section filtering based on course
    const courseSelect = document.getElementById('course');
    const sectionSelect = document.getElementById('section');
    
    if (courseSelect && sectionSelect) {
        function updateSectionOptions() {
            const selectedCourse = courseSelect.value;
            const bsitOnlyOptions = sectionSelect.querySelectorAll('.bsit-only');
            
            if (selectedCourse === 'BSCS') {
                // Hide C, D, E for BSCS
                bsitOnlyOptions.forEach(option => {
                    option.style.display = 'none';
                    option.disabled = true;
                });
                
                // Reset section if it was C, D, or E
                if (['C', 'D', 'E'].includes(sectionSelect.value)) {
                    sectionSelect.value = '';
                }
            } else {
                // Show all options for BSIT
                bsitOnlyOptions.forEach(option => {
                    option.style.display = 'block';
                    option.disabled = false;
                });
            }
        }
        
        courseSelect.addEventListener('change', updateSectionOptions);
        
        // Run on page load in case there's an old value
        updateSectionOptions();
    }
});

// Add CSS animations
var style = document.createElement('style');
style.textContent = `
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
`;
document.head.appendChild(style);
</script>
@endsection
