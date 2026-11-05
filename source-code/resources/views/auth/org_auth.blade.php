<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $mode === 'register' ? 'UniOrg - Register' : 'UniOrg - Login' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/LSPU_LOGO.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <style>
        .modal-backdrop {
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
        }
        .ph-spin {
            display: inline-block;
            animation: ph-spin 1s linear infinite;
        }
        @keyframes ph-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @media (max-width: 768px) {
            body {
                align-items: center !important;
                min-height: 100dvh;
                min-height: -webkit-fill-available;
            }
            body > .w-full.max-w-6xl {
                margin-top: auto !important;
                margin-bottom: auto !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen min-h-[100dvh] flex items-center justify-center p-3 sm:p-4 py-6 sm:py-4">
    @include('auth.partials.system-request-skeleton')

    <div class="w-full max-w-6xl my-auto flex flex-col justify-center">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <!-- Mobile blue branding header with smooth image transitions -->
            <div id="mobileBrandHeader" class="md:hidden relative px-4 sm:px-6 pt-10 pb-4 sm:pb-5 rounded-b-3xl shadow-lg overflow-hidden min-h-[175px] sm:min-h-[190px] flex flex-col justify-end items-end">
                <!-- Login Background Layer -->
                <div id="mobileBrandBgLogin" class="mobile-brand-bg absolute inset-0 {{ $mode === 'register' ? 'opacity-0 pointer-events-none scale-105' : 'opacity-100 scale-100' }}" style="background: linear-gradient(135deg, rgba(38, 72, 140, 0.72) 0%, rgba(26, 52, 97, 0.82) 100%), url('{{ asset('images/login.jpeg') }}') 45% center / cover no-repeat;"></div>
                
                <!-- Register Background Layer -->
                <div id="mobileBrandBgRegister" class="mobile-brand-bg absolute inset-0 {{ $mode === 'register' ? 'opacity-100 scale-100' : 'opacity-0 pointer-events-none scale-105' }}" style="background: linear-gradient(135deg, rgba(38, 72, 140, 0.72) 0%, rgba(26, 52, 97, 0.82) 100%), url('{{ asset('images/register.jpeg') }}') center / cover no-repeat;"></div>

                <!-- Subtle Overlay Gradient -->
                <div class="absolute inset-0 bg-gradient-to-t from-black/35 via-black/10 to-transparent pointer-events-none"></div>

                <!-- Branding Content -->
                <div class="relative z-10 w-full flex items-end justify-end gap-3 sm:gap-4 ml-auto">
                    <div class="text-right pb-1 sm:pb-1.5 flex-1 max-w-[220px] sm:max-w-xs">
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight drop-shadow-md leading-none mb-1">
                            UniOrg
                        </h1>
                        <p id="mobileBrandSubtitle" class="text-[11px] sm:text-xs text-blue-100 transition-opacity duration-300 font-medium drop-shadow-sm leading-tight">
                            {{ $mode === 'register' ? 'Student Registration' : 'Laguna State Polytechnic University' }}
                        </p>
                    </div>
                    <div class="flex-shrink-0">
                        <img src="{{ asset('images/LSPU_LOGO.png') }}" alt="LSPU Logo" class="h-24 w-24 sm:h-28 sm:w-28 object-contain drop-shadow-2xl transition-transform duration-300">
                    </div>
                </div>
            </div>

            <div class="auth-wrapper" data-mode="{{ $mode ?? 'login' }}">
                <div class="auth-container" id="authContainer">
                    <!-- Left Panel -->
                    <div class="panel" id="leftPanel">
                        <!-- Login Form (initially visible) -->
                        <div class="panel-content" id="loginFormPanel">
                            <div class="flex items-center justify-center h-full p-6 sm:p-8 md:p-12">
                                <div class="w-full max-w-md">
                                    <div class="mb-6 sm:mb-8">
                                        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1 sm:mb-2">Welcome Back!</h2>
                                        <p class="text-sm sm:text-base text-gray-600">Login to your organization account</p>
                                    </div>

                                    <form method="POST" action="{{ route('org.login.post') }}" id="loginForm">
                                        @csrf
                                        <div class="mb-3 sm:mb-4">
                                            <label for="login_student_id" class="block text-gray-700 text-sm font-semibold mb-2">
                                                <i class="ph ph-identification-card mr-1 text-[#C4973B]"></i> ID Number
                                            </label>
                                            <input type="text" name="student_id" id="login_student_id" required autofocus
                                                placeholder="2025-1020 or 24-1234-5678"
                                                class="w-full px-3 sm:px-4 py-2.5 sm:py-3 text-sm sm:text-base border border-gray-300 rounded-lg focus:outline-none focus:border-[#26488C] focus:ring-2 focus:ring-[rgba(38, 72, 140, 0.2)]">
                                        </div>

                                        <div class="mb-5 sm:mb-6">
                                            <label for="login_password" class="block text-gray-700 text-sm font-semibold mb-2">
                                                <i class="ph ph-lock mr-1 text-[#C4973B]"></i> Password
                                            </label>
                                            <div class="relative">
                                                <input type="password" name="password" id="login_password" required autocomplete="current-password"
                                                    placeholder="Enter your password"
                                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 pr-12 text-sm sm:text-base border border-gray-300 rounded-lg focus:outline-none focus:border-[#26488C] focus:ring-2 focus:ring-[rgba(38, 72, 140, 0.2)]"
                                                    style="-webkit-text-security: disc;">
                                                <button type="button" onclick="togglePassword('login_password', 'loginEyeIcon')" 
                                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-[#26488C] focus:outline-none transition-colors">
                                                    <i id="loginEyeIcon" class="ph ph-eye text-lg"></i>
                                                </button>
                                            </div>
                                            <div class="flex justify-end mt-2">
                                                <a href="#" onclick="openForgotPasswordModal(event)" class="text-sm text-[#26488C] hover:text-[#1a3461] hover:underline font-medium">
                                                    Forgot Password?
                                                </a>
                                            </div>
                                        </div>

                                        @include('auth.partials.turnstile', ['action' => 'login'])

                                        <div class="flex gap-3">
                                            <button type="submit" form="loginForm" class="flex-1 btn-primary text-white font-bold py-2.5 sm:py-3 px-4 text-base sm:text-lg">
                                                <i class="ph ph-sign-in mr-2 text-[#C4973B]"></i> Login
                                            </button>
                                            <button type="button" id="webauthnLoginBtn" class="hidden w-[56px] sm:w-[60px] flex-shrink-0 rounded-lg border-2 border-[#26488C] bg-white text-[#26488C] hover:bg-[#26488C] hover:text-white flex items-center justify-center text-xl sm:text-2xl transition-all shadow-sm group" title="Login with fingerprint (no ID needed)">
                                                <i class="ph ph-fingerprint group-hover:scale-110 transition-transform"></i>
                                            </button>
                                        </div>
                                        <p id="webauthnNotSupported" class="hidden mt-2 text-[11px] text-center text-amber-600"><i class="ph ph-warning-circle mr-1"></i>Fingerprint login not supported on this device</p>
                                    </form>

                                    <div class="mt-5 sm:mt-6 text-center space-y-2">
                                        <p class="text-gray-600 text-xs sm:text-sm">
                                            Student? 
                                            <a href="#" id="switchToRegister" class="text-[#26488C] hover:text-[#1a3461] font-semibold">Register here</a>
                                        </p>
                                        <p class="text-gray-600 text-xs sm:text-sm">
                                            Don't have a system for your Org? 
                                            <a href="{{ route('system-request.combined') }}" id="createPlatformLink" data-desktop-only="true" class="text-[#26488C] hover:text-[#1a3461] font-semibold">Create a Platform or Apply</a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Register Branding (initially hidden) -->
                        <div class="panel-content gradient-bg hidden md:flex flex-col p-12 hide-left" id="registerBrandPanel">
                            <div class="flex justify-center mb-8">
                                <img src="{{ asset('images/LSPU_LOGO.png') }}" alt="LSPU Logo" class="h-48 w-48 object-contain drop-shadow-2xl">
                            </div>
                            
                            <div class="flex-1 flex items-center justify-center">
                                <div class="text-white text-center">
                                    <h2 class="text-5xl font-bold mb-4">UniOrg</h2>
                                    <p class="text-xl mb-2 opacity-90">Join Your Organization</p>
                                    <p class="text-sm mb-12 opacity-75">Laguna State Polytechnic University</p>
                                    
                                    <div class="space-y-4 text-left max-w-sm mx-auto">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-white bg-opacity-20 rounded-full p-2">
                                                <i class="ph ph-check text-lg text-[#C4973B]"></i>
                                            </div>
                                            <span class="text-lg">Easy COR-based Registration</span>
                                        </div>
                                        
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-white bg-opacity-20 rounded-full p-2">
                                                <i class="ph ph-check text-lg text-[#C4973B]"></i>
                                            </div>
                                            <span class="text-lg">Manage Events & Activities</span>
                                        </div>
                                        
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-white bg-opacity-20 rounded-full p-2">
                                                <i class="ph ph-check text-lg text-[#C4973B]"></i>
                                            </div>
                                            <span class="text-lg">Connect with Members</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Panel -->
                    <div class="panel" id="rightPanel">
                        <!-- Login Branding (initially visible) -->
                        <div class="panel-content gradient-bg hidden md:flex flex-col p-12" id="loginBrandPanel">
                            <div class="flex justify-center mb-8">
                                <img src="{{ asset('images/LSPU_LOGO.png') }}" alt="LSPU Logo" class="h-48 w-48 object-contain drop-shadow-2xl">
                            </div>
                            
                            <div class="flex-1 flex items-center justify-center">
                                <div class="text-white text-center">
                                    <h2 class="text-5xl font-bold mb-4">UniOrg</h2>
                                    <p class="text-xl mb-2 opacity-90">Organization Management</p>
                                    <p class="text-sm mb-12 opacity-75">Laguna State Polytechnic University</p>
                                    
                                    <div class="space-y-4 text-left max-w-sm mx-auto">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-white bg-opacity-20 rounded-full p-2">
                                                <i class="ph ph-check text-lg text-[#C4973B]"></i>
                                            </div>
                                            <span class="text-lg">Manage Your Organization</span>
                                        </div>
                                        
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-white bg-opacity-20 rounded-full p-2">
                                                <i class="ph ph-check text-lg text-[#C4973B]"></i>
                                            </div>
                                            <span class="text-lg">Stay Connected with Updates</span>
                                        </div>
                                        
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-white bg-opacity-20 rounded-full p-2">
                                                <i class="ph ph-check text-lg text-[#C4973B]"></i>
                                            </div>
                                            <span class="text-lg">Access Resources Anytime</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Register Form (initially hidden) -->
                        <div class="panel-content hide-right" id="registerFormPanel">
                            <div class="flex items-center justify-center h-full p-6 sm:p-8 md:p-12 overflow-y-auto">
                                <div class="w-full max-w-md">
                                    <div class="mb-4 sm:mb-6">
                                        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1 sm:mb-2">Student Registration</h2>
                                        <p class="text-sm sm:text-base text-gray-600">Register using your COR</p>
                                    </div>

                                    <!-- Step Indicator -->
                                    <div class="mb-5 sm:mb-6">
                                        <div class="flex items-center justify-between mb-2">
                                            <span id="step1Label" class="text-xs sm:text-sm font-semibold text-[#26488C]">
                                                <i class="ph ph-upload mr-1"></i> Upload COR
                                            </span>
                                            <span id="step2Label" class="text-xs sm:text-sm text-gray-500">
                                                <i class="ph ph-user-check mr-1"></i> Complete Info
                                            </span>
                                            <span id="step3Label" class="text-xs sm:text-sm text-gray-500">
                                                <i class="ph ph-envelope-simple mr-1"></i> Verify Email
                                            </span>
                                        </div>
                                        <div class="h-1 bg-gray-200 rounded-full overflow-hidden">
                                            <div id="progressBar" class="h-full bg-[#26488C] transition-all duration-300" style="width: 33%;"></div>
                                        </div>
                                    </div>

                                    <!-- Step 1: Upload COR -->
                                    <div id="step1" class="step-content">
                                        <div id="dropZone" class="border-2 border-dashed border-[#26488C] rounded-lg p-6 sm:p-8 text-center cursor-pointer hover:bg-[rgba(38, 72, 140, 0.05)] transition-colors">
                                            <i class="ph ph-cloud-arrow-up text-4xl sm:text-5xl mb-2 sm:mb-3 text-[#26488C]"></i>
                                            <p class="text-base sm:text-lg font-semibold text-gray-700 mb-1">Drop your COR here</p>
                                            <p class="text-xs sm:text-sm text-gray-500">or click to browse</p>
                                            <p class="text-xs text-gray-400 mt-2">PDF, JPG, PNG (Max 10MB)</p>
                                            <input type="file" id="corFile" accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                                        </div>

                                        <p class="mt-3 flex items-center justify-center gap-1 text-[11px] text-amber-600/90">
                                            <i class="ph ph-warning-circle"></i> OCR can make mistakes. Please double-check the extracted information before submitting.
                                        </p>
                                        
                                        <div id="uploadProgress" class="hidden mt-4 text-center">
                                            <i class="ph ph-spinner ph-spin text-2xl text-[#26488C] mb-2"></i>
                                            <p class="text-sm sm:text-base text-gray-700">Processing your COR...</p>
                                        </div>

                                        <p id="corError" role="alert" class="hidden mt-3 text-center text-sm text-red-600 font-medium"></p>
                                    </div>

                                    <!-- Step 2: Registration Form -->
                                    <form method="POST" action="{{ route('org.register.post') }}" id="registerForm" class="hidden">
                                        @csrf
                                        
                                        <div class="space-y-3">
                                            <!-- Name Fields: First Name | Middle Initial | Last Name -->
                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                                <div>
                                                    <label class="block text-gray-700 text-sm font-semibold mb-1">
                                                        <i class="ph ph-user mr-1 text-[#C4973B]"></i> First Name
                                                    </label>
                                                    <input type="text" name="first_name" id="first_name" required
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#26488C] focus:ring-2 focus:ring-[rgba(38, 72, 140, 0.2)]"
                                                        placeholder="Edit if needed">
                                                </div>
                                                <div>
                                                    <label class="block text-gray-700 text-sm font-semibold mb-1">
                                                        <i class="ph ph-user mr-1 text-[#C4973B]"></i> Middle Initial
                                                    </label>
                                                    <input type="text" name="middle_initial" id="middle_initial" maxlength="5"
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#26488C] focus:ring-2 focus:ring-[rgba(38, 72, 140, 0.2)]"
                                                        placeholder="e.g., R.">
                                                    <input type="hidden" name="middle_name" id="middle_name">
                                                </div>
                                                <div>
                                                    <label class="block text-gray-700 text-sm font-semibold mb-1">
                                                        <i class="ph ph-user mr-1 text-[#C4973B]"></i> Last Name
                                                    </label>
                                                    <input type="text" name="last_name" id="last_name" required
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#26488C] focus:ring-2 focus:ring-[rgba(38, 72, 140, 0.2)]"
                                                        placeholder="Edit if needed">
                                                </div>
                                            </div>
                                            
                                            <!-- Suffix | Student ID | Year Level | Section (Equal 4 columns) -->
                                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                                <div>
                                                    <label class="block text-gray-700 text-sm font-semibold mb-1">
                                                        <i class="ph ph-certificate mr-1 text-[#C4973B]"></i> Suffix
                                                    </label>
                                                    <select name="suffix" id="suffix"
                                                        class="w-full px-2 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#26488C] focus:ring-2 focus:ring-[rgba(38, 72, 140, 0.2)]">
                                                        <option value="">None</option>
                                                        <option value="Jr.">Jr.</option>
                                                        <option value="Sr.">Sr.</option>
                                                        <option value="II">II</option>
                                                        <option value="III">III</option>
                                                        <option value="IV">IV</option>
                                                        <option value="V">V</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-gray-700 text-sm font-semibold mb-1">
                                                        <i class="ph ph-identification-card mr-1 text-[#C4973B]"></i> Student ID
                                                    </label>
                                                    <input type="text" name="student_number" id="student_number" required readonly
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50">
                                                </div>
                                                <div>
                                                    <label class="block text-gray-700 text-sm font-semibold mb-1">
                                                        <i class="ph ph-calendar mr-1 text-[#C4973B]"></i> Year Level
                                                    </label>
                                                    <input type="text" name="year_level" id="year_level" required readonly
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50">
                                                    <input type="hidden" name="school_year" id="school_year" value="">
                                                </div>
                                                <div>
                                                    <label class="block text-gray-700 text-sm font-semibold mb-1">
                                                        <i class="ph ph-users mr-1 text-[#C4973B]"></i> Section
                                                    </label>
                                                    <input type="text" name="section" id="section" required readonly
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50">
                                                </div>
                                            </div>

                                            <!-- Course (Full Width) -->
                                            <div>
                                                <label class="block text-gray-700 text-sm font-semibold mb-1">
                                                    <i class="ph ph-book mr-1 text-[#C4973B]"></i> Course
                                                </label>
                                                <input type="text" name="course" id="course" required readonly
                                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50">
                                            </div>

                                            <!-- Department -->
                                            <div>
                                                <label class="block text-gray-700 text-sm font-semibold mb-1">
                                                    <i class="ph ph-building mr-1 text-[#C4973B]"></i> Department
                                                </label>
                                                <select name="department_id" id="department_id" required
                                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#26488C] focus:ring-2 focus:ring-[rgba(38, 72, 140, 0.2)]">
                                                    <option value="">Select Department</option>
                                                    @foreach($departments as $department)
                                                        <option value="{{ $department->id }}">{{ $department->department_name }} ({{ $department->department_acronym }})</option>
                                                    @endforeach
                                                </select>
                                                <p id="departmentHint" class="hidden mt-1.5 text-xs font-medium text-[#26488C]"></p>
                                            </div>

                                            <!-- Email -->
                                            <div>
                                                <label class="block text-gray-700 text-sm font-semibold mb-1">
                                                    <i class="ph ph-envelope mr-1 text-[#C4973B]"></i> Email Address
                                                </label>
                                                <input type="email" name="email" id="email" required
                                                    placeholder="your.email@example.com"
                                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#26488C] focus:ring-2 focus:ring-[rgba(38, 72, 140, 0.2)]">
                                            </div>

                                            <!-- Password | Confirm Password -->
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-gray-700 text-sm font-semibold mb-1">
                                                        <i class="ph ph-lock mr-1 text-[#C4973B]"></i> Password
                                                    </label>
                                                    <div class="relative">
                                                        <input type="password" name="password" id="password" required minlength="8"
                                                            placeholder="Min 8 characters"
                                                            class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#26488C] focus:ring-2 focus:ring-[rgba(38, 72, 140, 0.2)]"
                                                            style="-webkit-text-security: disc;">
                                                        <button type="button" onclick="togglePassword('password', 'passwordEyeIcon')" 
                                                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-[#26488C] focus:outline-none transition-colors">
                                                            <i id="passwordEyeIcon" class="ph ph-eye"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-gray-700 text-sm font-semibold mb-1">
                                                        <i class="ph ph-lock mr-1 text-[#C4973B]"></i> Confirm
                                                    </label>
                                                    <div class="relative">
                                                        <input type="password" name="password_confirmation" id="password_confirmation" required
                                                            placeholder="Re-enter password"
                                                            class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#26488C] focus:ring-2 focus:ring-[rgba(38, 72, 140, 0.2)]"
                                                            style="-webkit-text-security: disc;">
                                                        <button type="button" onclick="togglePassword('password_confirmation', 'confirmEyeIcon')" 
                                                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-[#26488C] focus:outline-none transition-colors">
                                                            <i id="confirmEyeIcon" class="ph ph-eye"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex space-x-3 mt-4">
                                            <button type="button" id="backBtn" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition text-sm">
                                                <i class="ph ph-arrow-left mr-1 text-[#C4973B]"></i> Back
                                            </button>
                                            <button type="submit" id="submitBtn" class="flex-1 btn-primary text-white font-bold py-2 px-4 rounded-lg text-sm">
                                                <i class="ph ph-user-plus mr-1 text-[#C4973B]"></i> Register
                                            </button>
                                        </div>
                                    </form>

                                    <!-- Step 3: Email Verification (OTP) -->
                                    <div id="otpStep" class="step-content hidden">
                                        <div class="text-center mb-6">
                                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-50 mb-4">
                                                <i class="ph ph-envelope-simple-open text-3xl text-[#26488C]"></i>
                                            </div>
                                            <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-1">Verify Your Email</h3>
                                            <p class="text-sm sm:text-base text-gray-600">
                                                We sent a 6-digit code to
                                                <span id="otpEmailDisplay" class="font-semibold text-[#26488C] break-all"></span>
                                            </p>
                                            <p class="text-xs sm:text-sm text-gray-500 mt-2">
                                                Check your inbox (and spam folder). The code expires in 10 minutes.
                                            </p>
                                        </div>

                                        <div id="otpInputs" class="flex justify-center gap-2 sm:gap-3 mb-4" role="group" aria-label="6-digit verification code">
                                            @for ($i = 1; $i <= 6; $i++)
                                                <input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="1"
                                                    id="otpDigit{{ $i }}" class="otp-digit w-11 h-14 sm:w-14 sm:h-16 text-center text-xl sm:text-2xl font-bold border border-gray-300 rounded-lg bg-white focus:outline-none focus:border-[#26488C] focus:ring-2 focus:ring-[rgba(38, 72, 140, 0.2)] transition-colors"
                                                    aria-label="Digit {{ $i }}">
                                            @endfor
                                        </div>

                                        <p id="otpError" role="alert" class="hidden text-center text-sm text-red-600 mb-4 font-medium"></p>

                                        <button type="button" id="verifyOtpBtn" data-url="{{ route('org.verify-otp') }}" class="w-full btn-primary text-white font-bold py-2.5 sm:py-3 px-4 rounded-lg text-sm sm:text-base">
                                            <i class="ph ph-check-circle mr-2 text-[#C4973B]"></i> Verify Code
                                        </button>

                                        <div class="mt-4 text-center space-y-3">
                                            <p class="text-sm text-gray-600">
                                                Didn't receive the code?
                                                <button type="button" id="resendOtpBtn" data-url="{{ route('org.resend-otp') }}"
                                                    class="text-[#26488C] hover:text-[#1a3461] font-semibold hover:underline focus:outline-none">
                                                    Resend Code
                                                </button>
                                                <span id="resendCountdown" class="text-xs text-gray-400"></span>
                                            </p>
                                            <button type="button" id="otpBackBtn"
                                                class="text-sm text-gray-500 hover:text-[#26488C] font-medium focus:outline-none transition-colors">
                                                <i class="ph ph-arrow-left mr-1"></i> Back to edit your info
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mt-4 text-center">
                                        <p class="text-gray-600 text-sm">
                                            Already have an account? 
                                            <a href="#" id="switchToLogin" class="text-[#26488C] hover:text-[#1a3461] font-semibold">Login here</a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/webauthn.js') }}?v=turnstile1"></script>
    <script src="{{ asset('js/auth.js') }}?v=turnstile1"></script>
    <script>
        // Re-show the student Terms & Conditions modal on every new login.
        try { sessionStorage.removeItem('uniorg_tnc_session_acknowledged'); } catch (e) {}
        // WebAuthn Fingerprint Login - Student mobile only, icon beside Login button
        // v608ef3f3 = passwordless without ID (discoverable) - fixed tolerant lookup
        document.addEventListener('DOMContentLoaded', async function() {
            const btn = document.getElementById('webauthnLoginBtn');
            const hint = document.getElementById('webauthnLoginHint');
            const notSupported = document.getElementById('webauthnNotSupported');
            const loginIdInput = document.getElementById('login_student_id');
            if (!btn || !loginIdInput) return;

            // Only show fingerprint for mobile + platform authenticator available + secure context
            const isSecure = WebAuthn.isSecureContextOk();
            const platformOk = await WebAuthn.isPlatformAvailable().catch(() => false);
            const isMobile = WebAuthn.isMobile();

            if (isMobile && platformOk && isSecure && window.PublicKeyCredential) {
                btn.classList.remove('hidden');
                btn.classList.add('flex');
                if (hint) hint.classList.remove('hidden');
            } else if (isMobile) {
                // On mobile but something missing — show specific reason
                if (notSupported) {
                    if (!isSecure) {
                        notSupported.innerHTML = '<i class="ph ph-warning-circle mr-1"></i>Fingerprint requires HTTPS. Please open via https:// or localhost.';
                    } else if (!platformOk || !window.PublicKeyCredential) {
                        notSupported.innerHTML = '<i class="ph ph-warning-circle mr-1"></i>No fingerprint / Face ID found on this phone. Please set one up: Settings → Security → Biometrics / Face ID, then refresh.';
                    } else {
                        notSupported.innerHTML = '<i class="ph ph-warning-circle mr-1"></i>Fingerprint login not supported on this device/browser.';
                    }
                    notSupported.classList.remove('hidden');
                }
                if (hint) hint.classList.add('hidden');
            }

            btn.addEventListener('click', async function() {
                if (window.UniOrgTurnstile && !window.UniOrgTurnstile.requireToken('loginForm')) return;
                // Temporarily clear required to allow empty ID for passwordless (we validate via JS/B-mode)
                const hadRequired = loginIdInput.hasAttribute('required');
                if (hadRequired) loginIdInput.removeAttribute('required');
                const studentId = loginIdInput.value.trim(); // B-mode: may be empty (bank-like stored ID fallback inside WebAuthn)
                const original = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="ph ph-spinner ph-spin text-xl"></i>';
                try {
                    const data = await WebAuthn.loginWithFingerprint(studentId);
                    showToast(data.message || 'Fingerprint verified!', 'success');
                    setTimeout(() => { window.location.href = data.redirect || '/dashboard'; }, 1200);
                } catch (err) {
                    let msg = err.message || 'Fingerprint login failed.';
                    // Use mapper if available for low-level errors (no biometrics, etc)
                    if (typeof WebAuthn !== 'undefined' && WebAuthn.mapWebAuthnError && (err.name === 'NotAllowedError' || err.name === 'NotSupportedError' || err.name === 'SecurityError' || err.name === 'InvalidStateError' || msg.toLowerCase().includes('no fingerprint') || msg.toLowerCase().includes('not allowed') || msg.toLowerCase().includes('authenticator'))) {
                        try { msg = WebAuthn.mapWebAuthnError(err); } catch(e) {}
                    }
                    const low = msg.toLowerCase();
                    // Specific help for discoverable: old non-resident credential or no resident on this device
                    if (low.includes('no passwordless') || low.includes('no passwordless fingerprint') || low.includes('stored fingerprint not found')) {
                        showToast(msg, 'error');
                    } else if (low.includes('could not identify') || low.includes('old fingerprints') || low.includes('re-register') || low.includes('enable passwordless')) {
                        showToast('Fingerprint found but not passwordless. Please type your ID and tap fingerprint again, OR go to Profile → Change Fingerprint once to enable login without ID.', 'error');
                    } else if (low.includes('no fingerprint') || low.includes('no face') || low.includes('biometric') || low.includes('not set up') || low.includes('no platform authenticator') || low.includes('no sensor') || msg.includes('No fingerprint registered')) {
                        showToast(msg, 'error');
                    } else if (err.name === 'NotAllowedError' || low.includes('cancelled') || low.includes('timed out') || low.includes('timeout')) {
                        showToast('Fingerprint cancelled or timed out. If you have no fingerprint enrolled, please add one in Settings and try again.', 'error');
                    } else {
                        showToast(msg, 'error');
                    }
                    console.warn('WebAuthn login error', err);
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = original;
                    if (hadRequired) loginIdInput.setAttribute('required', 'required');
                }
            });

            // Bank-like: remember ID on password login for next fingerprint tap
            const loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', function() {
                    const idVal = loginIdInput.value.trim();
                    if (idVal) {
                        try { localStorage.setItem('webauthn_last_student_id', idVal); } catch (e) {}
                    }
                });
            }

            // Also support pressing Enter on ID field to trigger fingerprint if password empty?
            // Keep default form submit as password login
        });
    </script>
    <script>
        // Show server errors using elegant toast design
        @if ($errors->any())
            @foreach ($errors->all() as $error)
                showToast('{{ $error }}', 'error');
            @endforeach
        @endif

        @if (session('success'))
            showToast('{{ session('success') }}', 'success');
        @endif

        @if (session('error'))
            showToast('{{ session('error') }}', 'error');
        @endif

        // ============================================================
        // Device Detection - Block "Create a Platform / Apply" on mobile
        // ============================================================
        function isMobileDevice() {
            // 1) Check user agent for common mobile/tablet identifiers
            const ua = navigator.userAgent || navigator.vendor || window.opera || '';
            const mobileUaRegex = /android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini|mobile|tablet|silk|kindle|playbook/i;
            const uaIsMobile = mobileUaRegex.test(ua.toLowerCase());

            // 2) Check for touch capability combined with a small viewport
            const hasTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
            const smallViewport = window.matchMedia('(max-width: 1024px)').matches;

            // Treat as mobile if the user agent says so, OR it's a touch device on a small screen
            return uaIsMobile || (hasTouch && smallViewport);
        }

        function showSystemRequestNavigationSkeleton() {
            const skeleton = document.getElementById('system-request-navigation-skeleton');
            if (!skeleton) return;

            skeleton.classList.add('is-visible');
            skeleton.setAttribute('aria-hidden', 'false');
            document.body.setAttribute('aria-busy', 'true');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const createPlatformLink = document.getElementById('createPlatformLink');
            if (createPlatformLink) {
                createPlatformLink.addEventListener('click', function(e) {
                    if (isMobileDevice()) {
                        e.preventDefault();
                        showDesktopOnlyModal();
                        return;
                    }

                    // Let the skeleton paint first, then navigate immediately to the request page.
                    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
                    e.preventDefault();
                    showSystemRequestNavigationSkeleton();
                    requestAnimationFrame(() => window.location.assign(createPlatformLink.href));
                });
            }
        });

        // Show a clear modal explaining the feature is desktop-only
        function showDesktopOnlyModal() {
            // Avoid creating multiple modals
            if (document.getElementById('desktopOnlyModal')) {
                document.getElementById('desktopOnlyModal').classList.remove('hidden');
                return;
            }

            const modal = document.createElement('div');
            modal.id = 'desktopOnlyModal';
            modal.className = 'fixed inset-0 bg-black/50 z-[110] flex items-center justify-center p-4 modal-backdrop';
            modal.innerHTML = `
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all border border-gray-100">
                    <div class="bg-gradient-to-r from-[#26488C] to-[#1a3461] px-5 sm:px-6 py-4 flex justify-between items-center">
                        <h3 class="text-lg sm:text-xl font-bold text-white">Desktop Required</h3>
                        <button type="button" onclick="closeDesktopOnlyModal()" class="text-white hover:text-gray-200 focus:outline-none transition-colors">
                            <i class="ph ph-x text-xl"></i>
                        </button>
                    </div>
                    <div class="p-5 sm:p-6 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-blue-50 mb-4">
                            <i class="ph ph-desktop text-2xl sm:text-3xl text-[#26488C]"></i>
                        </div>
                        <h4 class="text-base sm:text-lg font-bold text-gray-800 mb-2">This Feature Is Available on Desktop Only</h4>
                        <p class="text-sm text-gray-600 mb-5 leading-relaxed">
                            Creating a platform or applying for an organization requires uploading and reviewing several documents.
                            For the best experience and to avoid errors, please open this page on a computer or laptop using a web browser.
                        </p>
                        <button type="button" onclick="closeDesktopOnlyModal()"
                            class="w-full text-white font-bold py-2.5 sm:py-3 px-4 rounded-lg shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-0.5 text-sm sm:text-base"
                            style="background: linear-gradient(135deg, #26488C 0%, #1a3461 100%);">
                            <i class="ph ph-check mr-2 text-[#C4973B]"></i> Got It
                        </button>
                    </div>
                </div>
            `;

            // Close when clicking the dark backdrop
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeDesktopOnlyModal();
                }
            });

            document.body.appendChild(modal);
        }

        function closeDesktopOnlyModal() {
            const modal = document.getElementById('desktopOnlyModal');
            if (modal) {
                modal.remove();
            }
        }

        const dropZone = document.getElementById('dropZone');
        const corFile = document.getElementById('corFile');
        const uploadProgress = document.getElementById('uploadProgress');
        const step1 = document.getElementById('step1');
        const registerForm = document.getElementById('registerForm');
        const progressBar = document.getElementById('progressBar');
        const step1Label = document.getElementById('step1Label');
        const step2Label = document.getElementById('step2Label');
        const backBtn = document.getElementById('backBtn');

        if (dropZone && corFile) {
            // Click to upload
            dropZone.addEventListener('click', () => corFile.click());
            
            // Drag and drop
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('bg-[rgba(38, 72, 140, 0.05)]');
            });
            
            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('bg-[rgba(38, 72, 140, 0.05)]');
            });
            
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('bg-[rgba(38, 72, 140, 0.05)]');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    handleFileUpload(files[0]);
                }
            });
            
            // File input change
            corFile.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    handleFileUpload(e.target.files[0]);
                }
            });
        }

        // Handle file upload
        function showCorError(message) {
            const el = document.getElementById('corError');
            if (!el) return;
            el.textContent = message;
            el.classList.remove('hidden');
        }

        function clearCorError() {
            const el = document.getElementById('corError');
            if (el) {
                el.textContent = '';
                el.classList.add('hidden');
            }
        }

        // Load an image file (handles EXIF rotation on modern browsers).
        function loadImageFile(file) {
            return new Promise((resolve, reject) => {
                if (window.createImageBitmap) {
                    createImageBitmap(file, { imageOrientation: 'from-image' })
                        .then(resolve)
                        .catch(() => {
                            const img = new Image();
                            const url = URL.createObjectURL(file);
                            img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
                            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Image load failed')); };
                            img.src = url;
                        });
                    return;
                }
                const img = new Image();
                const url = URL.createObjectURL(file);
                img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
                img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Image load failed')); };
                img.src = url;
            });
        }

        // Resize/compress images in the browser before uploading so the server
        // OCR finishes faster. Non-image files pass through unchanged.
        function compressCorImage(file) {
            if (!file.type || !file.type.startsWith('image/')) {
                return Promise.resolve(file);
            }

            const maxDim = 2200;
            const maxBytes = 2 * 1024 * 1024;

            return loadImageFile(file)
                .then((img) => {
                    const scale = Math.min(1, maxDim / Math.max(img.width, img.height));
                    if (scale >= 1 && file.size <= maxBytes) {
                        return file;
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = Math.max(1, Math.round(img.width * scale));
                    canvas.height = Math.max(1, Math.round(img.height * scale));
                    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);

                    return new Promise((resolve) => {
                        const isPng = file.type === 'image/png';
                        const mime = isPng ? 'image/png' : 'image/jpeg';
                        canvas.toBlob((blob) => {
                            if (blob) {
                                resolve(new File([blob], file.name, { type: mime }));
                            } else {
                                resolve(file);
                            }
                        }, mime, isPng ? undefined : 0.82);
                    });
                })
                .catch(() => file);
        }

        async function handleFileUpload(file) {
            clearCorError();
            uploadProgress.classList.remove('hidden');
            dropZone.classList.add('hidden');

            const optimizedFile = await compressCorImage(file);
            
            const formData = new FormData();
            formData.append('cor_file', optimizedFile);
            
            fetch('{{ route("org.upload.cor") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // DEBUG: Log extracted data
                    console.log('=== COR UPLOAD DEBUG ===');
                    console.log('Full Name Extracted:', data.debug?.extracted_full_name || data.data.student_name);
                    console.log('Parsed First:', data.debug?.parsed_first || data.data.first_name);
                    console.log('Parsed Middle:', data.debug?.parsed_middle || data.data.middle_name);
                    console.log('Parsed Last:', data.debug?.parsed_last || data.data.last_name);
                    console.log('Parsed Suffix:', data.debug?.parsed_suffix || data.data.suffix);
                    console.log('========================');
                    
                    // Split full name into first and last name
                    const fullName = data.data.student_name || '';
                    
                    // Fill form with extracted data
                    document.getElementById('first_name').value = data.data.first_name || '';
                    document.getElementById('last_name').value = data.data.last_name || '';
                    
                    // Auto-convert middle name to initial
                    const middleName = data.data.middle_name || '';
                    document.getElementById('middle_name').value = middleName; // Store full middle name
                    
                    if (middleName) {
                        // Convert "Rocamora" to "R."
                        const initial = middleName.charAt(0).toUpperCase() + '.';
                        document.getElementById('middle_initial').value = initial;
                    }
                    
                    // Set suffix if detected
                    const suffix = data.data.suffix || '';
                    console.log('Setting suffix:', suffix);
                    if (suffix) {
                        // Normalize suffix format (e.g., "jr" -> "Jr.", "II" -> "II")
                        let normalizedSuffix = '';
                        const suffixLower = suffix.toLowerCase().replace('.', '');
                        
                        if (suffixLower === 'jr' || suffixLower === 'junior') {
                            normalizedSuffix = 'Jr.';
                        } else if (suffixLower === 'sr' || suffixLower === 'senior') {
                            normalizedSuffix = 'Sr.';
                        } else if (['ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x'].includes(suffixLower)) {
                            normalizedSuffix = suffix.toUpperCase();
                        }
                        
                        console.log('Normalized suffix:', normalizedSuffix);
                        
                        // Set the suffix dropdown value
                        const suffixSelect = document.getElementById('suffix');
                        if (suffixSelect) {
                            // Try to find matching option
                            for (let option of suffixSelect.options) {
                                if (option.value === normalizedSuffix) {
                                    suffixSelect.value = normalizedSuffix;
                                    console.log('Suffix set to:', normalizedSuffix);
                                    break;
                                }
                            }
                        }
                    }
                    
                    document.getElementById('student_number').value = data.data.student_number || '';
                    document.getElementById('course').value = data.data.course || '';
                    document.getElementById('year_level').value = data.data.year_level || '';
                    document.getElementById('section').value = data.data.section || '';
                    document.getElementById('school_year').value = data.data.school_year || '';

                    // Auto-select department based on extracted course
                    autoSelectDepartment(data.data.course || '');
                    
                    // Move to step 2
                    step1.classList.add('hidden');
                    registerForm.classList.remove('hidden');
                    progressBar.style.width = '100%';
                    step1Label.classList.remove('text-[#26488C]', 'font-semibold');
                    step1Label.classList.add('text-gray-500');
                    step2Label.classList.remove('text-gray-500');
                    step2Label.classList.add('text-[#26488C]', 'font-semibold');
                    
                    showToast('COR processed successfully!', 'success');
                } else {
                    showToast(data.message || 'Error processing COR', 'error');
                    showCorError(data.message || 'This file does not appear to be a valid COR. Please upload your Certificate of Registration.');
                    uploadProgress.classList.add('hidden');
                    dropZone.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred while processing your COR. Please try again.', 'error');
                showCorError('An error occurred while processing your COR. Please try again.');
                uploadProgress.classList.add('hidden');
                dropZone.classList.remove('hidden');
            });
        }

        // Course → Department mapping (single source of truth from the server)
        window.courseDepartmentMap = @json($courseDepartmentMap ?? []);

        // Resolve a course string to its department ID, or null when unknown.
        function getDepartmentForCourse(course) {
            if (!course) return null;
            const c = String(course).toLowerCase();
            const map = window.courseDepartmentMap || {};
            for (const deptId of Object.keys(map)) {
                const keywords = map[deptId] || [];
                for (const kw of keywords) {
                    if (c.includes(kw)) {
                        return parseInt(deptId, 10);
                    }
                }
            }
            return null;
        }

        // Auto-select the department based on the extracted course
        function autoSelectDepartment(course) {
            const expectedId = getDepartmentForCourse(course);
            if (expectedId === null) return;
            const select = document.getElementById('department_id');
            if (select) {
                select.value = String(expectedId);
                // Trigger change event in case there are listeners
                select.dispatchEvent(new Event('change'));
            }
        }

        // Live hint below the department select
        function updateDepartmentHint() {
            const hint = document.getElementById('departmentHint');
            const select = document.getElementById('department_id');
            const courseInput = document.getElementById('course');
            if (!hint || !select || !courseInput) return;

            const course = courseInput.value || '';
            const expectedId = getDepartmentForCourse(course);

            if (expectedId === null) {
                hint.classList.add('hidden');
                hint.textContent = '';
                return;
            }

            const option = select.querySelector('option[value="' + expectedId + '"]');
            const expectedName = option ? option.textContent.trim() : '';
            const currentId = select.value;

            hint.classList.remove('text-amber-600', 'text-red-600', 'text-[#26488C]');
            select.classList.remove('border-red-500', 'error');
            select.classList.add('border-gray-300');
            select.style.borderColor = '';

            if (currentId === String(expectedId)) {
                hint.classList.add('hidden');
                hint.textContent = '';
            } else if (currentId !== '') {
                // Mismatch - a real error state, not a soft warning.
                hint.classList.remove('hidden');
                hint.classList.add('text-red-600', 'font-semibold');
                hint.textContent = 'Department does not match this course. It belongs to ' + expectedName + '.';
                select.classList.add('border-red-500', 'error');
                select.style.borderColor = '#ef4444';
            } else {
                hint.classList.remove('hidden');
                hint.classList.add('text-[#26488C]');
                hint.textContent = 'Based on your course (' + course + '), this should be: ' + expectedName;
            }
        }

        const departmentSelect = document.getElementById('department_id');
        if (departmentSelect) {
            departmentSelect.addEventListener('change', updateDepartmentHint);
        }

        // Back button
        if (backBtn) {
            backBtn.addEventListener('click', () => {
                registerForm.classList.add('hidden');
                step1.classList.remove('hidden');
                progressBar.style.width = '50%';
                step2Label.classList.remove('text-[#26488C]', 'font-semibold');
                step2Label.classList.add('text-gray-500');
                step1Label.classList.remove('text-gray-500');
                step1Label.classList.add('text-[#26488C]', 'font-semibold');
                uploadProgress.classList.add('hidden');
                dropZone.classList.remove('hidden');
                clearCorError();
                corFile.value = '';
            });
        }

        // Block registration until the department's organization has a system.
        // Skips when auth.js already flagged a course/department mismatch above.
        window.systemDepartments = @json($systemDepartments ?? []);
        registerForm.addEventListener('submit', function(e) {
            const departmentSelect = document.getElementById('department_id');
            const departmentId = departmentSelect ? departmentSelect.value : '';

            // auth.js already showed the mismatch error - keep that message visible
            if (departmentSelect && departmentSelect.classList.contains('error')) {
                return;
            }

            if (!departmentId || !window.systemDepartments.includes(parseInt(departmentId, 10))) {
                e.preventDefault();
                // Tell auth.js's AJAX submit handler to skip (prevents duplicate toast/loading)
                window.registrationBlocked = true;
                showToast('Your organization does not have a system yet. Please wait until your organization\'s system has been created before registering.', 'error');
            }
        });

        // Auto-format Student ID or Adviser ID
        function formatStudentId(input) {
            let value = input.value.replace(/\D/g, ''); // Remove non-digits
            let formatted = '';
            
            // Determine format based on length
            if (value.length <= 8) {
                // Student ID format: YYYY-XXXX (4-4)
                if (value.length >= 4) {
                    formatted = value.substring(0, 4) + '-' + value.substring(4, 8);
                } else {
                    formatted = value;
                }
            } else {
                // Adviser ID format: 00-0000-0000 (2-4-4)
                formatted = value.substring(0, 2);
                if (value.length > 2) {
                    formatted += '-' + value.substring(2, 6);
                }
                if (value.length > 6) {
                    formatted += '-' + value.substring(6, 10);
                }
            }
            
            input.value = formatted;
        }

        // Apply to login student ID
        const loginStudentId = document.getElementById('login_student_id');
        if (loginStudentId) {
            // Remove maxlength to allow both formats
            loginStudentId.removeAttribute('maxlength');
            
            // Track the last value so we can skip auto-formatting while the user
            // deletes - otherwise the dash would be re-added and impossible to remove.
            let previousValue = loginStudentId.value;
            loginStudentId.addEventListener('input', function() {
                if (this.value.length >= previousValue.length) {
                    formatStudentId(this);
                }
                previousValue = this.value;
                
                // Limit to 12 characters max (10 digits + 2 dashes for adviser format)
                if (this.value.length > 12) {
                    this.value = this.value.substring(0, 12);
                }
            });
        }

        // Toggle password visibility
        function togglePassword(inputId, iconId) {
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

        // --- FORGOT PASSWORD LOGIC ---
        function openForgotPasswordModal(e) {
            e.preventDefault();
            document.getElementById('forgotPasswordModal').classList.remove('hidden');
            backToStep1(); // Reset to step 1
        }

        function closeForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').classList.add('hidden');
        }

        function backToStep1() {
            document.getElementById('fpStep1').classList.remove('hidden');
            document.getElementById('fpStep2').classList.add('hidden');
            document.getElementById('forgotPasswordTitle').textContent = 'Reset Password';
            document.getElementById('fp_code').value = '';
            document.getElementById('fp_new_password').value = '';
            document.getElementById('fp_confirm_password').value = '';
            window.UniOrgTurnstile?.reset('requestResetForm');
        }

        function requestResetCode(e) {
            e.preventDefault();
            if (window.UniOrgTurnstile && !window.UniOrgTurnstile.requireToken('requestResetForm')) return;
            const email = document.getElementById('fp_email').value;
            const btn = document.getElementById('fp_btn_1');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i> Sending...';

            fetch("{{ route('password.email') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    email: email,
                    'cf-turnstile-response': window.UniOrgTurnstile?.token('requestResetForm') || ''
                })
            })
            .then(response => response.json().then(data => ({ status: response.status, body: data })))
            .then(res => {
                btn.disabled = false;
                btn.innerHTML = 'Send Verification Code';
                
                if (res.status === 200) {
                    showToast(res.body.message, 'success');
                    document.getElementById('fp_display_email').textContent = email;
                    document.getElementById('fpStep1').classList.add('hidden');
                    document.getElementById('fpStep2').classList.remove('hidden');
                    document.getElementById('forgotPasswordTitle').textContent = 'Verify & Reset';
                    window.UniOrgTurnstile?.reset('resetPasswordForm');
                } else {
                    const firstError = res.body.errors ? Object.values(res.body.errors)[0]?.[0] : null;
                    showToast(res.body.error || firstError || 'Failed to send code', 'error');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = 'Send Verification Code';
                showToast('A network error occurred', 'error');
            })
            .finally(() => window.UniOrgTurnstile?.reset('requestResetForm'));
        }

        function submitNewPassword(e) {
            e.preventDefault();
            const email = document.getElementById('fp_email').value;
            const code = document.getElementById('fp_code').value;
            const password = document.getElementById('fp_new_password').value;
            const password_confirmation = document.getElementById('fp_confirm_password').value;
            const btn = document.getElementById('fp_btn_2');

            if (password !== password_confirmation) {
                showToast('Passwords do not match!', 'error');
                return;
            }

            if (window.UniOrgTurnstile && !window.UniOrgTurnstile.requireToken('resetPasswordForm')) return;
            
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-spinner ph-spin mr-2"></i> Resetting...';

            fetch("{{ route('password.reset') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ 
                    email: email,
                    code: code,
                    password: password,
                    password_confirmation: password_confirmation,
                    'cf-turnstile-response': window.UniOrgTurnstile?.token('resetPasswordForm') || ''
                })
            })
            .then(response => response.json().then(data => ({ status: response.status, body: data })))
            .then(res => {
                btn.disabled = false;
                btn.innerHTML = 'Reset Password';
                
                if (res.status === 200) {
                    showToast(res.body.message, 'success');
                    closeForgotPasswordModal();
                } else {
                    let errorMessage = res.body.error || 'Failed to reset password';
                    if (res.body.errors) {
                        errorMessage = Object.values(res.body.errors)[0][0]; // get first validation error
                    }
                    showToast(errorMessage, 'error');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = 'Reset Password';
                showToast('A network error occurred', 'error');
            })
            .finally(() => window.UniOrgTurnstile?.reset('resetPasswordForm'));
        }
    </script>

    <!-- Forgot Password Modal HTML -->
    <div id="forgotPasswordModal" class="fixed inset-0 bg-black/50 z-[100] hidden flex items-center justify-center p-3 sm:p-4 modal-backdrop overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md my-auto overflow-hidden transform transition-all border border-gray-100">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-[#26488C] to-[#1a3461] px-4 sm:px-6 py-3 sm:py-4 flex justify-between items-center">
                <h3 class="text-lg sm:text-xl font-bold text-white" id="forgotPasswordTitle">Reset Password</h3>
                <button onclick="closeForgotPasswordModal()" class="text-white hover:text-gray-200 focus:outline-none transition-colors">
                    <i class="ph ph-x text-xl"></i>
                </button>
            </div>
            
            <!-- Step 1: Request Code -->
            <div id="fpStep1" class="p-4 sm:p-6">
                <div class="text-center mb-5 sm:mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-blue-50 mb-3 sm:mb-4">
                        <i class="ph ph-envelope-open text-xl sm:text-2xl text-[#26488C]"></i>
                    </div>
                    <p class="text-gray-600 text-xs sm:text-sm px-2 sm:px-4">Enter your registered email address and we'll send you a 6-digit verification code.</p>
                </div>
                <form id="requestResetForm" onsubmit="requestResetCode(event)">
                    <div class="mb-4 sm:mb-5">
                        <label class="block text-gray-700 text-sm font-semibold mb-2">Email Address</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="ph ph-envelope text-gray-400"></i>
                            </div>
                            <input type="email" id="fp_email" required
                                class="w-full pl-10 pr-4 py-2.5 sm:py-3 text-sm sm:text-base border border-gray-300 rounded-lg focus:outline-none focus:border-[#26488C] focus:ring-2 focus:ring-[rgba(38, 72, 140, 0.2)] transition-colors"
                                placeholder="Enter your registered email">
                        </div>
                    </div>
                    @include('auth.partials.turnstile', ['action' => 'password_email'])
                    <button type="submit" id="fp_btn_1" class="w-full text-white font-bold py-2.5 sm:py-3 px-4 rounded-lg shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-0.5 text-sm sm:text-base" style="background: linear-gradient(135deg, #26488C 0%, #1a3461 100%);">
                        Send Verification Code
                    </button>
                </form>
            </div>

            <!-- Step 2: Verify & Reset -->
            <div id="fpStep2" class="p-4 sm:p-6 hidden">
                <p class="text-gray-600 mb-4 sm:mb-5 text-xs sm:text-sm text-center bg-gray-50 p-2.5 sm:p-3 rounded-lg border border-gray-100">
                    We've sent a 6-digit code to <br><strong id="fp_display_email" class="text-[#26488C] break-all"></strong>
                </p>
                <form id="resetPasswordForm" onsubmit="submitNewPassword(event)">
                    <div class="mb-4 sm:mb-5">
                        <label class="block text-gray-700 text-sm font-semibold mb-2 text-center">Enter 6-Digit Code</label>
                        <input type="text" id="fp_code" required maxlength="6"
                            class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#C4973B] focus:ring-2 focus:ring-[rgba(196, 151, 59, 0.2)] text-center tracking-[0.3em] sm:tracking-[0.5em] font-bold text-xl sm:text-2xl"
                            placeholder="000000" autocomplete="off" inputmode="numeric">
                    </div>
                    <div class="mb-3 sm:mb-4">
                        <label class="block text-gray-700 text-sm font-semibold mb-2">New Password</label>
                        <div class="relative">
                            <input type="password" id="fp_new_password" required minlength="8"
                                class="w-full px-3 sm:px-4 py-2.5 sm:py-3 text-sm sm:text-base border border-gray-300 rounded-lg focus:outline-none focus:border-[#26488C]"
                                placeholder="At least 8 characters">
                        </div>
                    </div>
                    <div class="mb-5 sm:mb-6">
                        <label class="block text-gray-700 text-sm font-semibold mb-2">Confirm New Password</label>
                        <input type="password" id="fp_confirm_password" required minlength="8"
                            class="w-full px-3 sm:px-4 py-2.5 sm:py-3 text-sm sm:text-base border border-gray-300 rounded-lg focus:outline-none focus:border-[#26488C]"
                            placeholder="Re-enter new password">
                    </div>
                    @include('auth.partials.turnstile', ['action' => 'password_reset'])
                    <button type="submit" id="fp_btn_2" class="w-full text-white font-bold py-2.5 sm:py-3 px-4 rounded-lg shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-0.5 text-sm sm:text-base" style="background: linear-gradient(135deg, #26488C 0%, #1a3461 100%);">
                        <i class="ph ph-lock mr-2 text-[#C4973B]"></i> Reset Password
                    </button>
                    <div class="mt-4 sm:mt-5 text-center">
                        <button type="button" onclick="backToStep1()" class="text-xs sm:text-sm text-gray-500 hover:text-[#26488C] transition-colors font-medium">
                            <i class="ph ph-arrow-left mr-1"></i> Use a different email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @if (config('services.turnstile.enabled'))
        <script src="{{ asset('js/turnstile.js') }}?v=1"></script>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=initUniOrgTurnstile&amp;render=explicit" async defer></script>
    @endif
</body>
</html>

