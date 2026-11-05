<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>System Request - UniOrg</title>
    <link rel="icon" type="image/png" href="{{ asset('images/LSPU_LOGO.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <!-- PDF.js for PDF preview -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        if (typeof pdfjsLib !== 'undefined') {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }
    </script>
    <style>
        /* Removed fade-in animations for body and cards */
        
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

        /* Toast Notification Styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .toast {
            min-width: 300px;
            max-width: 400px;
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: start;
            gap: 12px;
            animation: slideInRight 0.3s ease-out;
        }
        
        .toast.hiding {
            animation: slideOutRight 0.3s ease-out;
        }
        
        .toast-error {
            background: #fee;
            border-left: 4px solid #dc2626;
            color: #991b1b;
        }
        
        .toast-success {
            background: #efe;
            border-left: 4px solid #16a34a;
            color: #166534;
        }
        
        .toast-warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }
        
        .toast-icon {
            flex-shrink: 0;
            font-size: 20px;
        }
        
        .toast-content {
            flex: 1;
        }
        
        .toast-title {
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .toast-message {
            font-size: 14px;
            line-height: 1.4;
        }
        
        .toast-close {
            flex-shrink: 0;
            cursor: pointer;
            opacity: 0.6;
            transition: opacity 0.2s;
        }
        
        .toast-close:hover {
            opacity: 1;
        }
        
        /* Field validation states */
        .field-error {
            border-color: #dc2626 !important;
            background-color: #fef2f2 !important;
        }
        
        .field-success {
            border-color: #16a34a !important;
            background-color: #f0fdf4 !important;
        }
        
        .field-error:focus {
            border-color: #dc2626 !important;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1) !important;
        }
        
        .field-success:focus {
            border-color: #16a34a !important;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1) !important;
        }

        /* Sliding container animation */
        .slide-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            transition: transform 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: relative;
        }

        .slide-container.slide-right {
            transform: translateX(50%);
        }

        .slide-container.slide-left {
            transform: translateX(-50%);
        }

        /* Form panels that slide in */
        .form-panel {
            position: absolute;
            width: 50%;
            height: 100%;
            opacity: 0;
            transition: opacity 0.8s ease-in-out;
            pointer-events: none;
        }

        .form-panel.show {
            opacity: 1;
            pointer-events: auto;
        }

        .form-panel-left {
            left: 0;
            top: 0;
        }

        .form-panel-right {
            right: 0;
            top: 0;
        }

        /* Wrapper for the sliding effect */
        .animation-wrapper {
            position: relative;
            overflow: hidden;
            min-height: 600px;
            width: 100%;
        }

        /* Ensure cards are visible initially */
        .slide-container {
            transform: translateX(0);
        }

        /* Hover effect with delay to prevent glitch */
        #applyButton:hover {
            opacity: 0.9;
        }
        
        /* Disable hover temporarily after restore */
        .split-hover-disabled {
            pointer-events: none !important;
        }
        
        .split-hover-disabled:hover {
            opacity: 0 !important;
        }

        /* Fixed button position for all steps */
        .app-step-content {
            display: flex;
            flex-direction: column;
            min-height: 500px;
        }
        
        .app-step-content > div:last-child {
            margin-top: auto !important;
        }

        .ph-spin {
            display: inline-block;
            animation: ph-spin 1s linear infinite;
        }
        @keyframes ph-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Floating ID lookup progress card (Matching Officer IDs / Member ID Lookup) */
        #lookup-progress-card {
            width: 340px;
            max-width: calc(100vw - 32px);
            animation: slideInRight 0.3s ease-out;
        }
        #lookup-progress-card.hiding {
            animation: slideOutRight 0.3s ease-out;
        }
        #lookup-progress-fill {
            transition: width 0.25s ease-out;
        }
        #lookup-progress-card.is-loading #lookup-progress-fill {
            background-image: linear-gradient(
                45deg,
                rgba(255, 255, 255, 0.35) 25%,
                transparent 25%,
                transparent 50%,
                rgba(255, 255, 255, 0.35) 50%,
                rgba(255, 255, 255, 0.35) 75%,
                transparent 75%
            );
            background-size: 16px 16px;
            animation: lookup-stripes 0.8s linear infinite;
        }
        @keyframes lookup-stripes {
            from { background-position: 0 0; }
            to { background-position: 16px 0; }
        }
        /* Same loading shimmer for the List-of-Members lookup bar */
        [data-idlookup-bar].is-loading {
            background-image: linear-gradient(
                45deg,
                rgba(255, 255, 255, 0.35) 25%,
                transparent 25%,
                transparent 50%,
                rgba(255, 255, 255, 0.35) 50%,
                rgba(255, 255, 255, 0.35) 75%,
                transparent 75%
            );
            background-size: 16px 16px;
            animation: lookup-stripes 0.8s linear infinite;
        }
        #lookup-progress-card.is-loading #lookup-progress-dot {
            animation: lookup-pulse 1.2s ease-in-out infinite;
        }
        @keyframes lookup-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.45; transform: scale(0.85); }
        }
    </style>
</head>
<body class="bg-[#F8F9FA] min-h-screen">
    @include('auth.partials.system-request-skeleton', ['visible' => true])
    <script>
        // Keep the destination layout covered until the page has been parsed and initialized.
        document.addEventListener('DOMContentLoaded', function() {
            requestAnimationFrame(function() {
                const skeleton = document.getElementById('system-request-navigation-skeleton');
                if (!skeleton) return;

                skeleton.classList.remove('is-visible');
                skeleton.setAttribute('aria-hidden', 'true');
                document.body.removeAttribute('aria-busy');
            });
        }, { once: true });
    </script>

    <!-- Toast Container (from auth.js) -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 flex flex-col gap-2"></div>

    <!-- Floating ID lookup progress card -->
    <div id="lookup-progress-card" class="hidden fixed top-20 right-4 z-50 bg-white rounded-lg shadow-xl border border-gray-200 px-4 py-3" role="status" aria-live="polite">
        <div class="flex items-center gap-2">
            <span id="lookup-progress-dot" class="w-3 h-3 rounded-full bg-amber-400 flex-shrink-0"></span>
            <span id="lookup-progress-title" class="font-bold text-gray-900 text-sm flex-1">Matching Officer IDs</span>
            <button type="button" onclick="hideLookupProgress()" class="text-gray-400 hover:text-gray-600 text-sm leading-none px-1" aria-label="Dismiss">&times;</button>
        </div>
        <div class="flex items-center gap-2 mt-2">
            <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                <div id="lookup-progress-fill" class="h-full bg-[#C4973B] rounded-full" style="width: 0%;"></div>
            </div>
            <span id="lookup-progress-pct" class="text-xs font-bold text-gray-700 w-10 text-right tabular-nums">0%</span>
        </div>
        <p id="lookup-progress-line1" class="text-xs text-gray-600 mt-2 truncate">Preparing…</p>
        <p id="lookup-progress-line2" class="text-xs text-gray-500">0s elapsed</p>
    </div>
    
    <!-- Back to Login - Upper Right Corner -->
    <div class="fixed top-4 left-4 z-50">
        <a href="{{ route('org.login') }}" class="inline-flex items-center text-[#26488C] hover:text-[#1a3461] font-semibold bg-white px-4 py-2 rounded-lg shadow-md hover:shadow-lg transition-all">
            <i class="ph ph-arrow-left mr-2"></i> Back to Login
        </a>
    </div>
    
    <div class="min-h-screen flex items-center justify-center p-3 sm:p-4 pb-16">
    <div class="w-full max-w-6xl relative">
        <!-- Choose Type View -->
        <div id="chooseView">
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                <div class="animation-wrapper">
                <!-- Sliding Cards Container -->
                <div class="slide-container" id="slideContainer">
                        <!-- Request Card -->
                        <div class="bg-white overflow-hidden hover:shadow-xl transition-all duration-300 flex flex-col" id="requestCard">
                            <div class="bg-gradient-to-br from-[#26488C] to-[#1a3461] p-4 sm:p-6 text-white">
                                <div class="flex items-center justify-center mb-3 sm:mb-4">
                                    <div class="bg-white rounded-full p-3 sm:p-4">
                                        <i class="ph ph-power text-[#26488C] text-3xl sm:text-4xl"></i>
                                    </div>
                                </div>
                                <h2 class="text-xl sm:text-2xl font-bold text-center mb-1 sm:mb-2">Create a Platform</h2>
                                <p class="text-center text-gray-200 text-xs sm:text-sm">For approved organizations</p>
                            </div>
                            
                            <div class="p-5 sm:p-6 md:p-8 flex-1 flex flex-col">
                                <div class="mb-5 sm:mb-6">
                                    <h3 class="font-bold text-base sm:text-lg text-gray-800 mb-2 sm:mb-3">This is for you if:</h3>
                                    <ul class="space-y-2 text-sm sm:text-base text-gray-600">
                                        <li class="flex items-start">
                                            <i class="ph ph-check-circle text-green-500 mr-2 mt-1 flex-shrink-0"></i>
                                            <span>Your organization is already approved/accredited</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="ph ph-check-circle text-green-500 mr-2 mt-1 flex-shrink-0"></i>
                                            <span>You are the organization president</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="ph ph-check-circle text-green-500 mr-2 mt-1 flex-shrink-0"></i>
                                            <span>You want to activate the management platform</span>
                                        </li>
                                    </ul>
                                </div>

                                <div class="mb-6 flex-1">
                                    <h3 class="font-bold text-lg text-gray-800 mb-3">What you'll need:</h3>
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <ul class="space-y-2 text-gray-700">
                                            <li class="flex items-start">
                                                <i class="ph ph-building text-blue-500 mr-2 mt-1"></i>
                                                <span>Organization name, type, and acronym</span>
                                            </li>
                                            <li class="flex items-start">
                                                <i class="ph ph-user text-blue-500 mr-2 mt-1"></i>
                                                <span>President information (must match records)</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <button id="requestButton" onclick="selectRequest()" class="block w-full bg-gradient-to-r from-[#26488C] to-[#1a3461] text-white font-bold py-4 px-6 rounded-lg hover:opacity-90 transition text-center mt-auto">
                                    <!-- Default State -->
                                    <span id="reqBtnDefault">
                                        <i class="ph ph-arrow-right mr-2"></i> Continue to Create
                                    </span>
                                    <!-- Back State -->
                                    <span id="reqBtnBack" class="hidden">
                                        <i class="ph ph-arrow-left mr-2"></i> Back to Main Menu
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- Apply Card -->
                        <div class="bg-white overflow-hidden hover:shadow-xl transition-all duration-300 flex flex-col" id="applyCard">
                            <div class="bg-gradient-to-br from-[#C4973B] to-[#a67d2e] p-4 sm:p-6 text-white">
                                <div class="flex items-center justify-center mb-3 sm:mb-4">
                                    <div class="bg-white rounded-full p-3 sm:p-4">
                                        <i class="ph ph-plus-circle text-[#C4973B] text-3xl sm:text-4xl"></i>
                                    </div>
                                </div>
                                <h2 class="text-xl sm:text-2xl font-bold text-center mb-1 sm:mb-2">Apply Organization</h2>
                                <p class="text-center text-gray-100 text-xs sm:text-sm">For new organizations</p>
                            </div>
                            
                            <div class="p-5 sm:p-6 md:p-8 flex-1 flex flex-col">
                                <div class="mb-5 sm:mb-6">
                                    <h3 class="font-bold text-base sm:text-lg text-gray-800 mb-2 sm:mb-3">This is for you if:</h3>
                                    <ul class="space-y-2 text-sm sm:text-base text-gray-600">
                                        <li class="flex items-start">
                                            <i class="ph ph-check-circle text-green-500 mr-2 mt-1 flex-shrink-0"></i>
                                            <span>You're creating a brand new organization</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="ph ph-check-circle text-green-500 mr-2 mt-1 flex-shrink-0"></i>
                                            <span>You need to submit complete documentation</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="ph ph-check-circle text-green-500 mr-2 mt-1 flex-shrink-0"></i>
                                            <span>You want to get your organization accredited</span>
                                        </li>
                                    </ul>
                                </div>

                                <div class="mb-5 sm:mb-6 flex-1">
                                    <h3 class="font-bold text-base sm:text-lg text-gray-800 mb-2 sm:mb-3">Required Documents:</h3>
                                    <div class="bg-gray-50 rounded-lg p-3 sm:p-4 space-y-2 text-xs sm:text-sm text-gray-600">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2">
                                            <div class="flex items-start">
                                                <i class="ph ph-file-text text-[#C4973B] mr-2 mt-1 flex-shrink-0"></i>
                                                <span>Organization Adviser Commitment</span>
                                            </div>
                                            <div class="flex items-start">
                                                <i class="ph ph-file-text text-[#C4973B] mr-2 mt-1 flex-shrink-0"></i>
                                                <span>Application letter for recognition/renewal</span>
                                            </div>
                                            <div class="flex items-start">
                                                <i class="ph ph-file-text text-[#C4973B] mr-2 mt-1 flex-shrink-0"></i>
                                                <span>List of Members</span>
                                            </div>
                                            <div class="flex items-start">
                                                <i class="ph ph-file-text text-[#C4973B] mr-2 mt-1 flex-shrink-0"></i>
                                                <span>Certification of Officers</span>
                                            </div>
                                            <div class="flex items-start">
                                                <i class="ph ph-file-text text-[#C4973B] mr-2 mt-1 flex-shrink-0"></i>
                                                <span>Plan of Activities</span>
                                            </div>
                                            <div class="flex items-start">
                                                <i class="ph ph-file-text text-[#C4973B] mr-2 mt-1 flex-shrink-0"></i>
                                                <span>Constitution and By-laws</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button id="applyButton" onclick="selectApply()" class="block w-full bg-gradient-to-r from-[#C4973B] to-[#a67d2e] text-white font-bold py-4 px-6 rounded-lg hover:opacity-90 transition">
                                    <span id="applyBtnDefault">
                                        <i class="ph ph-arrow-right mr-2"></i> Continue with Application
                                    </span>
                                    <span id="applyBtnBack" class="hidden">
                                        <i class="ph ph-arrow-left mr-2"></i> Back to Main Menu
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Request Form Panel (slides in from left) -->
                    <div class="form-panel form-panel-left" id="requestFormPanel">
                        <div class="bg-white h-full overflow-y-auto shadow-xl">
                            <div class="p-8">
                                <div class="mb-6">
                                    <h2 class="text-3xl font-bold text-gray-900 mb-2">Create a Platform</h2>
                                    <p class="text-gray-600">Verify your organization and create the platform</p>
                                </div>

                                <form id="requestForm" enctype="multipart/form-data" autocomplete="off">
                                    @csrf
                                    <input type="hidden" name="request_type" value="activate">

                                    <!-- Organization Info -->
                                    <div class="mb-6">
                                        <h3 class="text-lg font-bold text-gray-800 mb-3">Organization Information</h3>
                                        <p class="text-sm text-gray-600 mb-3">Enter your organization details exactly as registered</p>
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-gray-700 text-sm font-semibold mb-1">Department *</label>
                                                <select id="activate_department" required autocomplete="off"
                                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#26488C]"
                                                    onchange="loadActivateOrganizations(this.value)">
                                                    <option value="">Select Department</option>
                                                    @foreach($departments as $dept)
                                                        <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-gray-700 text-sm font-semibold mb-1">Organization Name *</label>
                                                <select name="organization_name" id="activate_org_name" required disabled autocomplete="off"
                                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#26488C] bg-gray-50"
                                                    onchange="fillOrganizationDetails(this.value)">
                                                    <option value="">Select Department First</option>
                                                </select>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-gray-700 text-sm font-semibold mb-1">Organization Acronym *</label>
                                                    <input type="text" name="organization_acronym" id="activate_org_acronym" required readonly autocomplete="off"
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed">
                                                </div>
                                                <div>
                                                    <label class="block text-gray-700 text-sm font-semibold mb-1">Organization Type *</label>
                                                    <input type="text" name="organization_type" id="activate_org_type" required readonly autocomplete="off"
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Activator Info -->
                                    <div class="mb-6">
                                        <h3 class="text-lg font-bold text-gray-800 mb-3">Activator Information</h3>
                                        <p class="text-sm text-gray-600 mb-3">Only the Organization President or Adviser can activate the system</p>
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-gray-700 text-sm font-semibold mb-1">Student/Employee ID *</label>
                                                <input type="text" name="activator_student_id" id="activator_student_id" required autocomplete="off"
                                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#26488C]"
                                                    placeholder="2021-0001 or 00-0000-0000"
                                                    pattern="^(\d{4}-\d{4}|\d{2}-\d{4}-\d{4})$"
                                                    title="Student 0000-0000 (e.g. 2021-0001) or Adviser 00-0000-0000 (e.g. 00-1002-0000)"
                                                    oninput="formatActivatorID(this); autoFetchActivatorData(this.value, false);"
                                                    onblur="autoFetchActivatorData(this.value, true)"
                                                    maxlength="12">
                                                <p class="text-xs text-gray-500 mt-1">Enter Student 0000-0000 or Adviser 00-0000-0000 to auto-fill — 00-... auto detected as Adviser</p>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-gray-700 text-sm font-semibold mb-1">First Name *</label>
                                                    <input type="text" name="activator_first_name" id="activator_first_name" required readonly autocomplete="off"
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed">
                                                </div>
                                                <div>
                                                    <label class="block text-gray-700 text-sm font-semibold mb-1">Last Name *</label>
                                                    <input type="text" name="activator_last_name" id="activator_last_name" required readonly autocomplete="off"
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed">
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-gray-700 text-sm font-semibold mb-1">Middle Initial</label>
                                                    <input type="text" name="activator_middle_name" id="activator_middle_name" maxlength="1" pattern="[A-Za-z]" readonly autocomplete="off"
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed uppercase" placeholder="M.I.">
                                                </div>
                                                <div>
                                                    <label class="block text-gray-700 text-sm font-semibold mb-1">Email *</label>
                                                    <input type="email" name="activator_email" id="activator_email" required readonly autocomplete="off"
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed">
                                                </div>
                                            </div>
                                            <div id="activator_department_container">
                                                <label class="block text-gray-700 text-sm font-semibold mb-1">Department *</label>
                                                <select name="activator_department" id="activator_department" required
                                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#26488C]"
                                                    onchange="updateCourseOptions('activator_course', this.value)">
                                                    <option value="">Select Department</option>
                                                    @foreach($departments as $dept)
                                                        @if($dept->id != 11)
                                                            <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-gray-700 text-sm font-semibold mb-1">Course *</label>
                                                <select name="activator_course" id="activator_course" required
                                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#26488C]">
                                                    <option value="">Select Department First</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex justify-end mt-6">
                                        <button type="submit" id="reqSubmitBtn" class="px-4 py-2 text-sm bg-[#26488C] text-white rounded-lg hover:opacity-90">
                                            <i class="ph ph-check mr-1"></i> Create a Platform
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Apply Form Panel (slides in from right) -->
                    <div class="form-panel form-panel-right" id="applyFormPanel">
                        <div class="bg-white h-full overflow-y-auto shadow-xl">
                            <div class="p-8">
                                <div class="mb-6">
                                    <h2 class="text-3xl font-bold text-gray-900 mb-2">Apply Organization</h2>
                                    <p class="text-gray-600">Complete the application in 5 steps</p>
                                </div>

                                <!-- Progress Steps -->
                                <div class="mb-8">
                                    <div class="flex items-center justify-between max-w-3xl mx-auto">
                                        <div class="flex items-center flex-1">
                                            <div id="app-step1-circle" class="w-8 h-8 rounded-full bg-[#C4973B] text-white flex items-center justify-center font-bold text-xs">1</div>
                                            <div class="flex-1 h-1 bg-gray-300 mx-1" id="app-progress1"></div>
                                        </div>
                                        <div class="flex items-center flex-1">
                                            <div id="app-step2-circle" class="w-8 h-8 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center font-bold text-xs">2</div>
                                            <div class="flex-1 h-1 bg-gray-300 mx-1" id="app-progress2"></div>
                                        </div>
                                        <div class="flex items-center flex-1">
                                            <div id="app-step3-circle" class="w-8 h-8 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center font-bold text-xs">2.1</div>
                                            <div class="flex-1 h-1 bg-gray-300 mx-1" id="app-progress3"></div>
                                        </div>
                                        <div class="flex items-center flex-1">
                                            <div id="app-step4-circle" class="w-8 h-8 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center font-bold text-xs">2.2</div>
                                            <div class="flex-1 h-1 bg-gray-300 mx-1" id="app-progress4"></div>
                                        </div>
                                        <div class="flex items-center flex-1">
                                            <div id="app-step5-circle" class="w-8 h-8 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center font-bold text-xs">2.3</div>
                                            <div class="flex-1 h-1 bg-gray-300 mx-1" id="app-progress5"></div>
                                        </div>
                                        <div id="app-step6-circle" class="w-8 h-8 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center font-bold text-xs">3</div>
                                    </div>
                                    <div class="grid grid-cols-6 items-start max-w-3xl mx-auto mt-2 text-xs relative" style="padding-bottom: 16px;">
                                        <span class="text-[#C4973B] font-semibold text-left translate-x-2">Info</span>
                                        <div class="relative col-start-2 col-span-3">
                                            <!-- Bracket using border (pointing down) -->
                                            <div style="position: absolute; top: 0; left: 15%; right: 15%; height: 10px; border-left: 2px solid #9CA3AF; border-bottom: 2px solid #9CA3AF; border-right: 2px solid #9CA3AF;"></div>
                                            <span class="text-gray-500" style="display: block; margin-top: 14px; text-align: center;">Document Requirements</span>
                                        </div>
                                        <span class="text-gray-500 col-start-5 text-center translate-x-4">Dean</span>
                                        <span class="text-gray-500 col-start-6 text-center translate-x-4">Officers</span>
                                    </div>
                                </div>

                                <form id="applyForm" enctype="multipart/form-data" novalidate>
                                    @csrf
                                    <input type="hidden" name="request_type" value="apply">
                                    <input type="hidden" name="application_type" id="applicationType" value="apply">
                                    <input type="hidden" name="current_step_app" id="currentStepApp" value="1">

                                    <!-- STEP 1 -->
                                    <div id="app-step1" class="app-step-content">
                                        <!-- Organization Info (Apply) -->
                                        <div id="applyOrgInfo" class="mb-4">
                                            <h3 class="text-lg font-bold text-gray-800 mb-3">Organization Information</h3>
                                            <div class="space-y-3">
                                                <div>
                                                    <label class="block text-gray-700 text-sm font-semibold mb-1">Organization Name *</label>
                                                    <input type="text" name="organization_name" id="org_name_apply" required
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#C4973B]">
                                                </div>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-gray-700 text-sm font-semibold mb-1">Organization Acronym *</label>
                                                        <input type="text" name="organization_acronym" id="org_acronym_apply" required
                                                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#C4973B]"
                                                            placeholder="e.g., BSITS">
                                                    </div>
                                                    <div>
                                                        <label class="block text-gray-700 text-sm font-semibold mb-1">Organization Type *</label>
                                                        <select name="organization_type" id="org_type_apply" required
                                                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#C4973B]">
                                                            <option value="">Select type</option>
                                                            <option value="Mother Org">Mother Org</option>
                                                            <option value="Sub Org">Sub Org</option>
                                                            <option value="Non-Academic">Non-Academic</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-gray-700 text-sm font-semibold mb-1">Department *</label>
                                                    <select name="department_id" id="department_apply" required
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#C4973B]">
                                                        <option value="">Select department</option>
                                                        @foreach($departments ?? [] as $department)
                                                            <option value="{{ $department->id }}">{{ $department->department_name }} ({{ $department->department_acronym }})</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Requester Info (Apply) -->
                                        <div id="applyRequesterInfo" class="mb-4">
                                            <h3 class="text-lg font-bold text-gray-800 mb-3">Requester Information</h3>
                                            <div class="space-y-3">
                                                <div>
                                                    <label class="block text-gray-700 text-sm font-semibold mb-1">Student / Employee ID *</label>
                                                    <input type="text" name="requester_student_id" id="req_student_id_apply"
                                                        placeholder="Type your ID — position is detected automatically"
                                                        maxlength="12"
                                                        pattern="^(\d{4}-\d{4}|\d{2}-\d{4}-\d{4})$"
                                                        title="President 0000-0000 (8 digits) / Adviser 00-0000-0000 (10 digits)"
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#C4973B]"
                                                        oninput="formatRequesterID(this)"
                                                        onblur="fetchRequesterData(this.value)">
                                                    <p id="req_id_hint" class="text-[10px] text-gray-500 mt-1">Type your ID — 0000-0000 (e.g. 2021-0001) is President, 00-0000-0000 (e.g. 00-1002-0000) is Adviser. Enter ID to auto-fill name &amp; email.</p>
                                                </div>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-gray-700 text-sm font-semibold mb-1">First Name *</label>
                                                        <input type="text" name="requester_first_name" id="req_first_name_apply" required
                                                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#C4973B]">
                                                    </div>
                                                    <div>
                                                        <label class="block text-gray-700 text-sm font-semibold mb-1">Last Name *</label>
                                                        <input type="text" name="requester_last_name" id="req_last_name_apply" required
                                                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#C4973B]">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-gray-700 text-sm font-semibold mb-1">Email *</label>
                                                    <input type="email" name="requester_email" id="req_email_apply" required
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-[#C4973B]">
                                                </div>
                                                <div>
                                                    <label class="block text-gray-700 text-sm font-semibold mb-1">Position</label>
                                                    <div id="req_position_apply" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-700 font-semibold" aria-live="polite">Type your ID above — position is detected automatically</div>
                                                    <p class="text-[10px] text-gray-500 mt-1">0000-0000 → Organization President · 00-0000-0000 → Organization Adviser</p>
                                                </div>
                                            </div>
                                        </div>

                                        <button type="button" onclick="nextAppStep(2)" class="px-4 py-2 text-sm bg-[#C4973B] text-white rounded-lg hover:opacity-90 ml-auto mt-6">
                                            Next <i class="ph ph-arrow-right ml-1"></i>
                                        </button>
                                    </div>

                                    <!-- STEP 2 (Documents Part 1) -->
                                    <div id="app-step2" class="app-step-content hidden">
                                        <div>
                                            <h3 class="text-lg font-bold text-gray-800 mb-2">Document Requirements (Part 1 of 2)</h3>
                                            <p class="text-sm text-gray-600 mb-3">Upload the first set of required documents</p>
                                            <div id="docs-step2-container" class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-96 overflow-y-auto">
                                                <!-- Documents will be generated dynamically -->
                                            </div>
                                        </div>

                                        <div class="flex justify-between mt-6">
                                            <button type="button" onclick="prevAppStep(1)" class="px-4 py-2 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                                <i class="ph ph-arrow-left mr-1"></i> Previous
                                            </button>
                                            <button type="button" onclick="nextAppStep(3)" class="px-4 py-2 text-sm bg-[#C4973B] text-white rounded-lg hover:opacity-90">
                                                Next <i class="ph ph-arrow-right ml-1"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- STEP 3 (Documents Part 2) -->
                                    <div id="app-step3" class="app-step-content hidden">
                                        <div>
                                            <h3 class="text-lg font-bold text-gray-800 mb-2">Document Requirements (Part 2 of 2)</h3>
                                            <p class="text-sm text-gray-600 mb-3">Upload the second set of required documents</p>
                                            <div id="docs-step3-container" class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-96 overflow-y-auto">
                                                <!-- Documents will be generated dynamically -->
                                            </div>
                                        </div>

                                        <div class="flex justify-between mt-6">
                                            <button type="button" onclick="prevAppStep(2)" class="px-4 py-2 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                                <i class="ph ph-arrow-left mr-1"></i> Previous
                                            </button>
                                            <button type="button" onclick="nextAppStep(4)" class="px-4 py-2 text-sm bg-[#C4973B] text-white rounded-lg hover:opacity-90">
                                                Next <i class="ph ph-arrow-right ml-1"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- STEP 4 (List of Members - Multiple Upload) -->
                                    <div id="app-step4" class="app-step-content hidden">
                                        <!-- Sub Org: Mother Org already has members (no upload needed) -->
                                        <div id="motherOrgExistsNotice" class="hidden">
                                            <div class="flex flex-col items-center justify-center py-10 text-center">
                                                <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mb-4">
                                                    <i class="ph ph-check-circle text-green-500 text-3xl"></i>
                                                </div>
                                                <h3 class="text-lg font-bold text-gray-800 mb-2">No Upload Needed!</h3>
                                                <p class="text-sm text-gray-500 max-w-xs">Your Mother Organization has already submitted the List of Members for this department. You can proceed to the next step.</p>
                                            </div>
                                        </div>

                                        <!-- Regular upload (Mother Org or no Mother Org found) -->
                                        <div id="memberUploadSection">
                                            <h3 class="text-lg font-bold text-gray-800 mb-2">Upload List of Members</h3>
                                            <p class="text-sm text-gray-600 mb-3">Upload multiple files for list of members (unlimited)</p>
                                            
                                            <div class="border-2 border-dashed border-[#C4973B] rounded-lg p-6">
                                                <div class="text-center mb-4">
                                                    <i class="ph ph-users text-4xl text-[#C4973B] mb-2"></i>
                                                    <p class="text-sm font-semibold text-gray-700">Click or drag files to upload</p>
                                                    <p class="text-xs text-gray-500">PDF, JPG, PNG - Multiple files allowed</p>
                                                </div>
                                                
                                                <input type="file" id="listOfMembersInput" name="list_of_members[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="hidden" required>
                                                <button type="button" onclick="document.getElementById('listOfMembersInput').click()" class="w-full px-4 py-2 bg-[#C4973B] text-white rounded-lg hover:opacity-90">
                                                    <i class="ph ph-plus mr-2"></i> Add Files
                                                </button>
                                                
                                                <div id="memberFilesPreview" class="mt-4 space-y-2">
                                                    <!-- File previews will appear here -->
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Additional Documents (Other Files) -->
                                        <div class="mt-6 pt-6 border-t border-gray-200">
                                            <h3 class="text-lg font-bold text-gray-800 mb-2">Additional Documents <span class="text-sm font-normal text-gray-500">(Optional)</span></h3>
                                            <p class="text-sm text-gray-600 mb-3">Upload any other supporting files (max 20)</p>
                                            <div class="border border-dashed border-[#C4973B] rounded-lg p-4 bg-gray-50/50">
                                                <div class="flex items-center justify-between mb-3">
                                                    <p class="text-xs text-gray-500">PDF, JPG, PNG — 10MB each</p>
                                                    <button type="button" id="otherFilesAddBtn" onclick="document.getElementById('otherFilesInput').click()" class="px-3 py-1.5 bg-white border border-gray-200 text-xs font-bold uppercase tracking-wide rounded-md hover:border-gray-300"><i class="ph ph-plus mr-1"></i> Add Files</button>
                                                </div>
                                                <input type="file" id="otherFilesInput" name="other_files[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                                                <div id="otherFilesPreview" class="space-y-2"></div>
                                            </div>
                                        </div>

                                        <div class="flex justify-between mt-6">
                                            <button type="button" onclick="prevAppStep(3)" class="px-4 py-2 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                                <i class="ph ph-arrow-left mr-1"></i> Previous
                                            </button>
                                            <button type="button" id="appStep4NextBtn" onclick="nextAppStep(5)" class="px-4 py-2 text-sm bg-[#C4973B] text-white rounded-lg hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed">
                                                Next <i class="ph ph-arrow-right ml-1"></i>
                                            </button>
                                        </div>
                                    </div>


                                    <!-- STEP 5 (Dean — OSAS-assigned, autofilled, read-only) -->
                                    <div id="app-step5" class="app-step-content hidden">
                                        <div>
                                            <h3 class="text-lg font-bold text-gray-800 mb-2">College Dean</h3>
                                            <p class="text-sm text-gray-600 mb-4">The Dean is set by OSAS for each college and filled in automatically below. These details are read-only — no action needed.</p>

                                            <div class="border border-green-200 rounded-xl p-5 bg-green-50 space-y-4">
                                                <p id="apply_dean_status" class="text-xs font-semibold text-green-800"><i class="ph ph-check-circle mr-1"></i> Auto-assigned by OSAS</p>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-700 mb-1">ID Number</label>
                                                        <input type="text" id="apply_dean_id_number" disabled
                                                            class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg bg-gray-100 text-gray-600"
                                                            placeholder="—">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Email</label>
                                                        <input type="email" id="apply_dean_email" disabled
                                                            class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg bg-gray-100 text-gray-600"
                                                            placeholder="—">
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-700 mb-1">First Name</label>
                                                        <input type="text" id="apply_dean_first_name" disabled
                                                            class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Middle Initial</label>
                                                        <input type="text" id="apply_dean_middle_initial" disabled
                                                            class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg bg-gray-100 text-gray-600 uppercase" placeholder="M.I.">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Last Name</label>
                                                        <input type="text" id="apply_dean_last_name" disabled
                                                            class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                                                    </div>
                                                </div>

                                                <div>
                                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Academic/Professional Suffix</label>
                                                    <input type="text" id="apply_dean_suffix" disabled
                                                        class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg bg-gray-100 text-gray-600"
                                                        placeholder="e.g. PhD, EdD, MBA, LPT">
                                                </div>
                                                <p id="apply_dean_hint" class="text-[10px] text-gray-500">The assigned Dean for your selected college appears here automatically. If none is assigned yet, you will be asked to contact OSAS upon submission.</p>
                                            </div>
                                        </div>

                                        <div class="flex justify-between mt-6">
                                            <button type="button" onclick="prevAppStep(4)" class="px-4 py-2 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                                <i class="ph ph-arrow-left mr-1"></i> Previous
                                            </button>
                                            <button type="button" onclick="nextAppStep(6)" class="px-4 py-2 text-sm bg-[#C4973B] text-white rounded-lg hover:opacity-90">
                                                Next <i class="ph ph-arrow-right ml-1"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- STEP 6 (Officers) -->
                                    <div id="app-step6" class="app-step-content hidden">
                                        <div>
                                            <h3 class="text-lg font-bold text-gray-800 mb-2">Assign Officers</h3>
                                            <p class="text-sm text-gray-600 mb-3">Provide information for all officer positions</p>
                                            
                                            <div id="appOfficersContainer" class="space-y-4 max-h-96 overflow-y-auto">
                                                <!-- Officers will be generated here -->
                                            </div>
                                        </div>

                                        <div class="flex justify-between mt-6">
                                            <button type="button" onclick="prevAppStep(5)" class="px-4 py-2 text-sm border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                                <i class="ph ph-arrow-left mr-1"></i> Previous
                                            </button>
                                            <button type="submit" id="appSubmitBtn" class="px-4 py-2 text-sm bg-[#C4973B] text-white rounded-lg hover:opacity-90">
                                                <i class="ph ph-paper-plane mr-1"></i> Submit
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Emitted from the same exact role-slot registry used by the parser.
        const officerPositions = @json(\App\Services\CertificationOfOfficersRoleSlotRegistry::browserPositions());

        // "Other Positions" — dynamically captured from the Certification PDF or
        // added manually. Always rendered after the default positions so they
        // stay clearly separated from them.
        const customOfficerSlots = []; // {key, label, source: 'auto' | 'manual'}

        // Course Mapping (Full Names)
        const courseMapping = {
            "1": [ // COE
                "Bachelor of Science in Civil Engineering",
                "Bachelor of Science in Computer Engineering",
                "Bachelor of Science in Electrical Engineering",
                "Bachelor of Science in Electronics Engineering",
                "Bachelor of Science in Mechanical Engineering"
            ],
            "2": [ // CIHTM
                "Bachelor of Science in Hospitality Management",
                "Bachelor of Science in Tourism Management"
            ],
            "3": [ // CONAH
                "Bachelor of Science in Nursing"
            ],
            "4": [ // CBAA
                "Bachelor of Public Administration",
                "Bachelor of Science in Accountancy",
                "Bachelor of Science in Business Administration Major in Financial Management",
                "Bachelor of Science in Business Administration Major in Marketing Management",
                "Bachelor of Science in Entrepreneurship",
                "Bachelor of Science in Office Administration"
            ],
            "5": [ // CIT
                "Bachelor of Industrial Technology Major in Architectural Drafting Technology",
                "Bachelor of Industrial Technology Major in Automotive Technology",
                "Bachelor of Industrial Technology Major in Culinary Technology",
                "Bachelor of Industrial Technology Major in Electrical Technology",
                "Bachelor of Industrial Technology Major in Electronics Technology",
                "Bachelor of Industrial Technology Major in Heating, Ventilation, Air-Conditioning and Refrigeration",
                "Bachelor of Science in Industrial Technology Major in Architectural Drafting",
                "Bachelor of Science in Industrial Technology Major in Automotive Technology",
                "Bachelor of Science in Industrial Technology Major in Electrical Technology",
                "Bachelor of Science in Industrial Technology Major in Electronics Technology",
                "Bachelor of Science in Industrial Technology Major in Food & Beverage Preparation Service Mgt.",
                "Bachelor of Science in Industrial Technology Major in Heating, Ventilation, Air-Conditioning and Refrigeration"
            ],
            "6": [ // CTE
                "Bachelor of Elementary Education",
                "Bachelor of Physical Education",
                "Bachelor of Secondary Education Major in English",
                "Bachelor of Secondary Education Major in Filipino",
                "Bachelor of Secondary Education Major in Mathematics",
                "Bachelor of Secondary Education Major in Science",
                "Bachelor of Secondary Education Major in Social Studies",
                "Bachelor of Technical-Vocational Teacher Education Major in Computer Programming",
                "Bachelor of Technical-Vocational Teacher Education Major in Food and Service Management",
                "Bachelor of Technology and Livelihood Education Major in Home Economics",
                "Bachelor of Technology and Livelihood Education Major in Industrial Arts"
            ],
            "7": [ // CCS
                "Bachelor of Science in Computer Science",
                "Bachelor of Science in Information Technology"
            ],
            "8": [ // CAS
                "Bachelor of Arts in Broadcasting",
                "Bachelor of Science in Biology",
                "Bachelor of Science in Biology Major in Animal Biology",
                "Bachelor of Science in Biology Major in Microbiology",
                "Bachelor of Science in Chemistry",
                "Bachelor of Science in Mathematics",
                "Bachelor of Science in Psychology"
            ],
            "9": [ // CCJE
                "Bachelor of Science in Criminology"
            ],
            "11": [ // Non-Academic
                "Bachelor of Science in Civil Engineering",
                "Bachelor of Science in Computer Engineering",
                "Bachelor of Science in Electrical Engineering",
                "Bachelor of Science in Electronics Engineering",
                "Bachelor of Science in Mechanical Engineering",
                "Bachelor of Science in Hospitality Management",
                "Bachelor of Science in Tourism Management",
                "Bachelor of Science in Nursing",
                "Bachelor of Public Administration",
                "Bachelor of Science in Accountancy",
                "Bachelor of Science in Business Administration Major in Financial Management",
                "Bachelor of Science in Business Administration Major in Marketing Management",
                "Bachelor of Science in Entrepreneurship",
                "Bachelor of Science in Office Administration",
                "Bachelor of Industrial Technology Major in Architectural Drafting Technology",
                "Bachelor of Industrial Technology Major in Automotive Technology",
                "Bachelor of Industrial Technology Major in Culinary Technology",
                "Bachelor of Industrial Technology Major in Electrical Technology",
                "Bachelor of Industrial Technology Major in Electronics Technology",
                "Bachelor of Industrial Technology Major in Heating, Ventilation, Air-Conditioning and Refrigeration",
                "Bachelor of Science in Industrial Technology Major in Architectural Drafting",
                "Bachelor of Science in Industrial Technology Major in Automotive Technology",
                "Bachelor of Science in Industrial Technology Major in Electrical Technology",
                "Bachelor of Science in Industrial Technology Major in Electronics Technology",
                "Bachelor of Science in Industrial Technology Major in Food & Beverage Preparation Service Mgt.",
                "Bachelor of Science in Industrial Technology Major in Heating, Ventilation, Air-Conditioning and Refrigeration",
                "Bachelor of Elementary Education",
                "Bachelor of Physical Education",
                "Bachelor of Secondary Education Major in English",
                "Bachelor of Secondary Education Major in Filipino",
                "Bachelor of Secondary Education Major in Mathematics",
                "Bachelor of Secondary Education Major in Science",
                "Bachelor of Secondary Education Major in Social Studies",
                "Bachelor of Technical-Vocational Teacher Education Major in Computer Programming",
                "Bachelor of Technical-Vocational Teacher Education Major in Food and Service Management",
                "Bachelor of Technology and Livelihood Education Major in Home Economics",
                "Bachelor of Technology and Livelihood Education Major in Industrial Arts",
                "Bachelor of Science in Computer Science",
                "Bachelor of Science in Information Technology",
                "Bachelor of Arts in Broadcasting",
                "Bachelor of Science in Biology",
                "Bachelor of Science in Biology Major in Animal Biology",
                "Bachelor of Science in Biology Major in Microbiology",
                "Bachelor of Science in Chemistry",
                "Bachelor of Science in Mathematics",
                "Bachelor of Science in Psychology",
                "Bachelor of Science in Criminology"
            ]
        };

        function updateCourseOptions(selectId, departmentId) {
            const select = document.getElementById(selectId);
            if (!select) return;
            
            const currentValue = select.dataset.pendingValue || select.value;
            select.innerHTML = '<option value="">Select Course</option>';
            
            const courses = courseMapping[departmentId] || [];
            courses.forEach(course => {
                const option = document.createElement('option');
                option.value = course;
                option.textContent = course;
                select.appendChild(option);
            });
            
            if (currentValue) {
                select.value = currentValue;
                delete select.dataset.pendingValue;
            }
        }

        // Certification of Officers PDF auto-fill belongs only to the currently selected file.
        const officersPrefillState = {
            generation: 0,
            abortController: null,
            status: 'manual',
            data: null,
            warnings: [],
            file: null,
            manualValues: {},
        };
        const certifierPrefillFields = ['first_name', 'middle_name', 'last_name', 'suffix'];
        const officerPrefillFields = [...certifierPrefillFields, 'year_level', 'section'];

        function isCertificationPdf(file) {
            return Boolean(file) && (file.type === 'application/pdf' || /\.pdf$/i.test(file.name || ''));
        }

        function currentOfficerSlots() {
            return typeof officerPositions === 'undefined' ? [] : officerPositions;
        }

        function isDefaultPositionKey(key) {
            return currentOfficerSlots().some(position => position.key === key);
        }

        function isCustomPositionKey(key) {
            return String(key).startsWith('custom_');
        }

        function customPrefillIds(includeIdentityFields) {
            const fields = includeIdentityFields
                ? ['student_id', 'email', 'department_id', 'course', ...officerPrefillFields]
                : [...officerPrefillFields];
            return customOfficerSlots.flatMap(position =>
                fields.map(field => `officers_${position.key}_${field}`)
            );
        }

        function prefillDestinationIds() {
            const deanIds = certifierPrefillFields.map(field => `dean_${field}`);
            const officerIds = currentOfficerSlots().flatMap(position => {
                const fields = position.is_adviser ? certifierPrefillFields : officerPrefillFields;
                return fields.map(field => `officers_${position.key}_${field}`);
            });
            return [...deanIds, ...officerIds, ...customPrefillIds(false)];
        }

        function prefillTrackedIds() {
            const deanIds = ['id_number', 'email', ...certifierPrefillFields].map(field => `dean_${field}`);
            const officerIds = currentOfficerSlots().flatMap(position => {
                const identityFields = position.is_adviser
                    ? ['id_number', 'email', ...certifierPrefillFields]
                    : ['student_id', 'email', 'department_id', 'course', ...officerPrefillFields];
                return identityFields.map(field => `officers_${position.key}_${field}`);
            });
            return [...deanIds, ...officerIds, ...customPrefillIds(true)];
        }

        function bindPrefillDestinationTracking() {
            prefillTrackedIds().forEach(id => {
                const input = document.getElementById(id);
                if (!input || input.dataset.autoFillDirtyBound === 'true') return;
                const markDirty = () => {
                    if (input.dataset.autoFillWriting !== 'true') {
                        input.dataset.autoFillDirty = 'true';
                        officersPrefillState.manualValues[id] = input.value;
                    }
                };
                input.addEventListener('input', markDirty);
                input.addEventListener('change', markDirty);
                input.dataset.autoFillDirtyBound = 'true';
            });
        }

        function rememberManualPrefillValues() {
            prefillTrackedIds().forEach(id => {
                const input = document.getElementById(id);
                if (input?.dataset.autoFillDirty === 'true') {
                    officersPrefillState.manualValues[id] = input.value;
                }
            });
        }

        function assignManualPrefillValue(input, value) {
            if (!input) return;
            input.value = value;
            input.dataset.autoFillDirty = 'true';
            delete input.dataset.autoFillToken;
            delete input.dataset.autoFillValue;
            if (input.id) officersPrefillState.manualValues[input.id] = value;
        }

        function hydrateManualPrefillValues() {
            Object.entries(officersPrefillState.manualValues).forEach(([id, value]) => {
                const input = document.getElementById(id);
                if (!input) return;
                input.value = id.endsWith('_year_level') ? formatYearLevel(value) : value;
                input.dataset.autoFillDirty = 'true';
            });
        }

        function clearPriorAutomaticValues() {
            prefillDestinationIds().forEach(id => {
                const input = document.getElementById(id);
                if (!input) return;
                const wasManuallyChanged = input.dataset.autoFillDirty === 'true';
                const wasAutoFilled = Boolean(input.dataset.autoFillToken);
                // Only clear values that were placed by auto-fill, never values
                // the user typed before dirty tracking kicked in.
                if (wasAutoFilled && !wasManuallyChanged) {
                    input.value = '';
                }
                delete input.dataset.autoFillToken;
                delete input.dataset.autoFillValue;
                if (wasManuallyChanged) {
                    input.dataset.autoFillDirty = 'true';
                } else if (wasAutoFilled) {
                    delete input.dataset.autoFillDirty;
                }
            });
        }

        function assignAutomaticValue(input, value, requestToken) {
            if (!input || value === undefined || value === null || value === '') return;
            const isAutoOwned = Boolean(input.dataset.autoFillToken)
                && input.dataset.autoFillDirty !== 'true';
            if (input.dataset.autoFillDirty === 'true' || (input.value !== '' && !isAutoOwned)) return;

            input.dataset.autoFillWriting = 'true';
            input.value = value;
            input.dataset.autoFillToken = String(requestToken);
            input.dataset.autoFillValue = String(value);
            input.dataset.autoFillDirty = 'false';
            delete input.dataset.autoFillWriting;
        }

        // Normalize a raw year-level value ("3") or an already-formatted one
        // ("3rd", "3rd Year") into the ordinal label shown in the field
        // ("3rd Year"). Non-numeric values pass through untouched.
        function formatYearLevel(raw) {
            if (raw === undefined || raw === null || raw === '') return raw;
            const text = String(raw).trim();
            let digits = null;
            if (/^\d+$/.test(text)) {
                digits = parseInt(text, 10);
            } else {
                const alreadyFormatted = text.match(/^(\d+)(st|nd|rd|th)\b/i);
                if (alreadyFormatted) digits = parseInt(alreadyFormatted[1], 10);
            }
            if (digits === null || digits < 1) return raw;
            const last = digits % 10;
            const lastTwo = digits % 100;
            let suffix = 'th';
            if (lastTwo < 11 || lastTwo > 13) {
                if (last === 1) suffix = 'st';
                else if (last === 2) suffix = 'nd';
                else if (last === 3) suffix = 'rd';
            }
            return `${digits}${suffix} Year`;
        }

        function applyPrefillFields(namePrefix, positionKey, data, fields, requestToken) {
            fields.forEach(field => {
                const id = positionKey ? `${namePrefix}_${positionKey}_${field}` : `${namePrefix}_${field}`;
                const value = field === 'year_level' ? formatYearLevel(data?.[field]) : data?.[field];
                assignAutomaticValue(document.getElementById(id), value, requestToken);
            });
        }

        function applyDeanPrefill() {
            if (!officersPrefillState.data?.dean) return;
            applyPrefillFields('dean', '', officersPrefillState.data.dean, certifierPrefillFields, officersPrefillState.generation);
        }

        function applyOfficerPrefill(namePrefix, positionKey) {
            const isCustom = isCustomPositionKey(positionKey);
            const data = isCustom
                ? officersPrefillState.data?.officers?.[positionKey]
                : positionKey === 'senior_adviser' || positionKey === 'junior_adviser'
                    ? officersPrefillState.data?.[positionKey]
                    : officersPrefillState.data?.officers?.[positionKey];
            if (!data) return;
            const fields = isCustom
                ? officerPrefillFields
                : positionKey === 'senior_adviser' || positionKey === 'junior_adviser'
                    ? certifierPrefillFields
                    : officerPrefillFields;
            applyPrefillFields(namePrefix, positionKey, data, fields, officersPrefillState.generation);
        }

        function applyVisibleOfficersPrefill() {
            currentOfficerSlots().forEach(position => applyOfficerPrefill('officers', position.key));
            if (currentAppStep() !== 6) return;
            Object.entries(officersPrefillState.data?.officers || {}).forEach(([key, officer]) => {
                if (isDefaultPositionKey(key)) return;
                const label = officer?.position_name || humanizePositionKey(key);
                if (ensureCustomOfficerCard('officers', key, label)) {
                    bindPrefillDestinationTracking();
                }
                applyOfficerPrefill('officers', key);
            });
            // Ensure every visible card (default and custom) reflects the
            // department chosen on Step 1. For orgs without a global department
            // (Non-Academic), prefer the college detected from the PDF.
            const preferredDeptId = !(document.getElementById('department_apply')?.value ?? '')
                ? departmentIdForCollege(officersPrefillState.data?.college)
                : null;
            [...currentOfficerSlots(), ...customOfficerSlots].forEach(slot => {
                autoLinkOfficerDepartment('officers', slot.key, preferredDeptId);
            });
            // Course options load during department linking above — match the
            // prefilled courses only now that options exist (covers customs too).
            Object.entries(officersPrefillState.data?.officers || {}).forEach(([key, officer]) => {
                if (officer?.course) matchAndSetCourse(`officers_${key}_course`, officer.course);
            });
            // Officer names are settled here (step-6 entry and late prefill
            // arrival) — kick off the List of Members ID lookup when possible.
            maybeLookupOfficerIds();
        }

        function currentAppStep() {
            return Number(document.getElementById('currentStepApp')?.value || 0);
        }

        function applyVisiblePrefill() {
            bindPrefillDestinationTracking();
            const step = currentAppStep();
            // Dean fields are static. Apply them regardless of step so they are
            // always filled as soon as data arrives (or when navigating back/forward).
            applyDeanPrefill();
            if (step === 6) applyVisibleOfficersPrefill();
        }

        function showPrefillOutcome(result) {
            if (typeof showToast !== 'function') return;
            // Binary outcome on officers count only: even one detected
            // officer is a success; zero officers is a failure.
            const officersFound = !!(result.data?.officers && Object.keys(result.data.officers).length > 0);
            const sourceLabel = result.data_source === 'ai' ? 'via AI'
                : result.data_source === 'merged' ? 'via AI + Document Format'
                : '';
            if (officersFound) {
                showToast(
                    'Certification of Officers auto-filled successfully.' + (sourceLabel ? ' (extracted ' + sourceLabel + ')' : ''),
                    'success', 'Autofill Successful'
                );
            } else {
                showToast(result.warnings?.[0] || 'Certification details need manual entry.', 'warning', 'Autofill Failed');
            }
        }

        // Resolves the human-readable position label for the extraction debug
        // panel: custom slots use their captured position_name, default slots
        // use the canonical browser label, and anything else falls back to a
        // humanized form of the slot key.
        function positionDisplayLabel(key, officer) {
            if (officer && typeof officer === 'object' && officer.position_name) {
                return String(officer.position_name).trim();
            }
            if (typeof officerPositions !== 'undefined') {
                const match = officerPositions.find(position => position.key === key);
                if (match && match.label) return match.label;
            }
            return humanizePositionKey(key);
        }

        function updateOfficersDebugUI() { /* debugger removed — user feedback via toast only */ }

        async function requestOfficersPrefill(file, generation) {
            const controller = officersPrefillState.abortController;
            const formData = new FormData();
            formData.append('certification_of_officers', file);
            formData.append('request_token', String(generation));
            formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            const lookupRun = (typeof showLookupProgress === 'function')
                ? showLookupProgress('Matching Officer IDs', 'Reading ' + (file?.name || 'certification file') + '…')
                : 0;

            try {
                const response = await fetch('/apply-org/prefill-from-officers', {
                    method: 'POST', body: formData, signal: controller.signal,
                });
                const result = await response.json();
                const isCurrent = officersPrefillState.generation === generation
                    && officersPrefillState.file === file
                    && String(result.request_token) === String(generation);
                if (!isCurrent) return;

                officersPrefillState.status = result.success ? (result.outcome || 'completed') : 'manual_required';
                officersPrefillState.data = result.success ? (result.data || null) : null;
                officersPrefillState.warnings = result.warnings || (result.message ? [result.message] : []);
                applyVisiblePrefill();
                if (typeof completeLookupProgress === 'function' && officersPrefillState.status !== 'manual_required') {
                    completeLookupProgress(lookupRun, 'Officer IDs matched — review and edit below.');
                } else if (typeof failLookupProgress === 'function') {
                    failLookupProgress(lookupRun, 'Matching Officer IDs', officersPrefillState.warnings?.[0] || 'Certification details need manual entry.', 'Please enter IDs manually.');
                }
                showPrefillOutcome({
                    outcome: officersPrefillState.status,
                    warnings: officersPrefillState.warnings,
                    data: officersPrefillState.data,
                    data_source: result.data_source || null,
                });

            } catch (error) {
                if (error.name === 'AbortError' || officersPrefillState.generation !== generation) return;
                officersPrefillState.status = 'manual_required';
                officersPrefillState.data = null;
                officersPrefillState.warnings = ['Certification details could not be read. Please complete them manually.'];
                if (typeof failLookupProgress === 'function') failLookupProgress(lookupRun, 'Member ID Lookup', 'Lookup failed', 'Please enter IDs manually.');
                showPrefillOutcome({ outcome: officersPrefillState.status, warnings: officersPrefillState.warnings });
            }
        }

        function transitionOfficersPrefill(file) {
            if (file && file !== officersPrefillState.file) {
                officersPrefillState.manualValues = {};
                prefillTrackedIds().forEach(id => {
                    const input = document.getElementById(id);
                    if (input) delete input.dataset.autoFillDirty;
                });
            } else {
                rememberManualPrefillValues();
            }
            officersPrefillState.generation += 1;
            officersPrefillState.abortController?.abort();
            clearPriorAutomaticValues();
            officersPrefillState.abortController = null;
            officersPrefillState.status = 'manual';
            officersPrefillState.data = null;
            officersPrefillState.warnings = [];
            officersPrefillState.file = file || null;

            if (!isCertificationPdf(file)) return;
            officersPrefillState.status = 'loading';
            officersPrefillState.abortController = new AbortController();
            void requestOfficersPrefill(file, officersPrefillState.generation);
        }

        function triggerOfficersPrefill() {
            const file = document.getElementById('file3')?.files?.[0] || null;
            if (file !== officersPrefillState.file) transitionOfficersPrefill(file);
            return Promise.resolve();
        }

        function attachOfficersPrefillListener(fileInput = document.getElementById('file3')) {
            if (!fileInput || fileInput.dataset.officersPrefillBound === 'true') return;
            fileInput.addEventListener('change', event => transitionOfficersPrefill(event.target.files?.[0] || null));
            fileInput.dataset.officersPrefillBound = 'true';
        }

        bindPrefillDestinationTracking();
        attachOfficersPrefillListener();

        // Officer ID lookup from List of Members (Step 6): matches officer
        // names against member rows and fills student IDs. Optional, editable,
        // nonblocking — manual values are never overwritten.
        const officerIdLookupState = {
            status: 'idle',
            cacheKey: null,
            abortController: null,
            results: {},
        };
        const OFFICER_ID_LOOKUP_TOKEN = 'member-lookup';

        function officerLookupSlots() {
            const slots = currentOfficerSlots()
                .filter(position => !position.is_adviser)
                .map(position => position.key);
            customOfficerSlots.forEach(slot => {
                if (!slots.includes(slot.key)) slots.push(slot.key);
            });
            return slots;
        }

        function officerLookupSlotLabel(slot) {
            const defaults = typeof officerPositions !== 'undefined' ? officerPositions : [];
            const customs = typeof customOfficerSlots !== 'undefined' ? customOfficerSlots : [];
            const found = defaults.find(position => position.key === slot)
                || customs.find(position => position.key === slot);
            if (found && found.label) return found.label;
            return typeof humanizePositionKey === 'function' ? humanizePositionKey(slot) : String(slot);
        }

        function readOfficerNameFromCard(slot) {
            const get = field => document.getElementById(`officers_${slot}_${field}`)?.value?.trim() || '';
            const first = get('first_name');
            const last = get('last_name');
            if (!first && !last) return null;
            return { first_name: first, middle_name: get('middle_name'), last_name: last, suffix: get('suffix') };
        }

        function shortHash(text) {
            let hash = 5381;
            for (let i = 0; i < text.length; i++) hash = ((hash << 5) + hash + text.charCodeAt(i)) >>> 0;
            return hash.toString(16);
        }

        function officerLookupCacheKey(officers) {
            const fileSig = (typeof memberFiles === 'undefined' || !Array.isArray(memberFiles)) ? '' : memberFiles
                .map(entry => `${entry.file.name}|${entry.file.size}|${entry.file.lastModified}`).join(';');
            return shortHash(`${fileSig}::${JSON.stringify(officers)}`);
        }

        function clearOfficerIdLookupWidgets(slot) {
            document.querySelectorAll(`[data-idlookup-slot="${slot}"]`).forEach(el => el.remove());
        }

        function renderOfficerIdBadge(slot, memberName, studentId, rawLine) {
            const input = document.getElementById(`officers_${slot}_student_id`);
            if (!input || !input.parentElement || !memberName || !studentId) return;
            const badge = document.createElement('div');
            badge.className = 'text-[11px] text-green-700 mt-0.5';
            badge.dataset.idlookupSlot = slot;
            if (rawLine) badge.title = `List of Members row: ${rawLine}`;
            badge.textContent = `✓ From List of Members: ${memberName} (${studentId})`;
            input.parentElement.appendChild(badge);
        }

        function renderOfficerIdPicker(slot, candidates) {
            const input = document.getElementById(`officers_${slot}_student_id`);
            if (!input || !input.parentElement || !Array.isArray(candidates) || candidates.length === 0) return;
            const wrap = document.createElement('div');
            wrap.className = 'mt-1';
            wrap.dataset.idlookupSlot = slot;
            const label = document.createElement('label');
            label.className = 'block text-[11px] font-semibold text-amber-700 mb-0.5';
            label.textContent = `${candidates.length} possible matches in List of Members — piliin ang tama:`;
            const select = document.createElement('select');
            select.className = 'w-full px-2 py-1 text-xs border border-amber-400 rounded focus:outline-none focus:border-amber-600 bg-amber-50';
            select.dataset.idlookupSlot = slot;
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Select matching ID…';
            select.appendChild(placeholder);
            candidates.forEach(candidate => {
                if (!candidate?.student_id) return;
                const option = document.createElement('option');
                option.value = candidate.student_id;
                option.textContent = `${candidate.member_name || candidate.student_id} — ${candidate.student_id}`;
                option.dataset.rawLine = candidate.raw_line || '';
                option.dataset.memberName = candidate.member_name || '';
                select.appendChild(option);
            });
            select.addEventListener('change', () => {
                const picked = select.selectedOptions[0];
                if (!picked || !picked.value) return;
                assignManualPrefillValue(input, picked.value);
                input.classList.remove('field-error');
                clearOfficerIdLookupWidgets(slot);
                renderOfficerIdBadge(slot, picked.dataset.memberName, picked.value, picked.dataset.rawLine);
                autoFetchStudent('officers', slot, picked.value, true);
            });
            wrap.appendChild(label);
            wrap.appendChild(select);
            input.parentElement.appendChild(wrap);
        }

        function applyOfficerIdLookupResults(results, announce = true, rowsRead = 0) {
            if (!results || typeof results !== 'object') return;
            let filled = 0;
            let pickers = 0;
            Object.entries(results).forEach(([slot, result]) => {
                if (!result || typeof result !== 'object') return;
                clearOfficerIdLookupWidgets(slot);
                const input = document.getElementById(`officers_${slot}_student_id`);
                if (!input) return;
                if (result.match?.student_id) {
                    const hadManualValue = input.dataset.autoFillDirty === 'true'
                        || (input.value !== '' && !input.dataset.autoFillToken);
                    assignAutomaticValue(input, result.match.student_id, OFFICER_ID_LOOKUP_TOKEN);
                    if (input.value === result.match.student_id) {
                        renderOfficerIdBadge(slot, result.match.member_name, result.match.student_id, result.match.raw_line);
                        if (!hadManualValue) {
                            filled++;
                            autoFetchStudent('officers', slot, result.match.student_id, true);
                        }
                    }
                } else if (Array.isArray(result.candidates) && result.candidates.length > 0) {
                    renderOfficerIdPicker(slot, result.candidates);
                    pickers++;
                }
            });
            if (!announce || typeof showToast !== 'function') return;
            if (filled > 0 || pickers > 0) {
                const parts = [];
                if (filled > 0) parts.push(`${filled} ID${filled > 1 ? 's' : ''} auto-matched`);
                if (pickers > 0) parts.push(`${pickers} need${pickers > 1 ? '' : 's'} your pick`);
                showToast(`Matched from List of Members: ${parts.join(', ')}.`, 'success', 'Member ID Lookup');
                // Name the slots left with neither match nor candidates so
                // they get manual IDs instead of being silently skipped.
                const silent = Object.entries(results)
                    .filter(([, result]) => result && typeof result === 'object'
                        && !result.match?.student_id
                        && !(Array.isArray(result.candidates) && result.candidates.length > 0))
                    .map(([slot]) => officerLookupSlotLabel(slot));
                if (silent.length > 0) {
                    showToast(`Still need manual IDs: ${silent.join(', ')}. Enter them manually.`, 'info', 'Manual IDs Needed');
                }
            } else if (rowsRead === 0) {
                showToast('No member rows found — hindi mabasa ang format ng file. Please check the file or enter IDs manually.', 'warning', 'No Rows Found');
            } else {
                showToast(`No matching IDs found in List of Members (${rowsRead} rows read). Check name spelling or enter IDs manually.`, 'info', 'No Matches');
            }
        }

        const OFFICER_ID_LOOKUP_PAGE_SIZE = 25;

        function officerLookupElapsed(startedAt) {
            return Math.max(0, Math.round((Date.now() - startedAt) / 1000)) + 's';
        }

        function renderOfficerIdLookupProgress(opts) {
            opts = opts || {};
            const tones = { amber: '#C4973B', blue: '#3b82f6', green: '#22c55e', red: '#ef4444' };
            const color = tones[opts.tone] || tones.amber;
            let card = document.querySelector('[data-idlookup-status]');
            if (!card) {
                card = document.createElement('div');
                card.setAttribute('data-idlookup-status', 'true');
                card.className = 'fixed bottom-4 right-4 z-50 w-80 max-w-[calc(100vw-2rem)] rounded-lg border border-gray-200 bg-white shadow-xl';
                card.innerHTML = ''
                    + '<div class="flex items-center gap-2 px-3 pt-2.5">'
                    + '<span data-idlookup-dot style="width:10px;height:10px;border-radius:9999px;display:inline-block;" class="animate-pulse"></span>'
                    + '<p data-idlookup-title class="text-xs font-bold text-gray-800">Matching Officer IDs</p>'
                    + '<button type="button" data-idlookup-close class="ml-auto text-gray-400 hover:text-gray-600 text-base leading-none px-1" title="Dismiss">×</button>'
                    + '</div>'
                    + '<div class="px-3 pt-2 flex items-center gap-2"><div style="height:8px;border-radius:9999px;background:#e5e7eb;overflow:hidden;" class="flex-1">'
                    + '<div data-idlookup-bar style="height:100%;border-radius:9999px;width:0%;transition:width .3s;"></div>'
                    + '</div><span data-idlookup-pct class="text-[11px] font-bold text-gray-700 tabular-nums" style="min-width:34px;text-align:right;">0%</span></div>'
                    + '<p data-idlookup-detail class="px-3 pt-1.5 text-[11px] text-gray-600 truncate"></p>'
                    + '<p data-idlookup-stats class="px-3 pb-2.5 text-[11px] text-gray-500"></p>';
                card.querySelector('[data-idlookup-close]').addEventListener('click', () => card.remove());
                document.body.appendChild(card);
            }
            const dot = card.querySelector('[data-idlookup-dot]');
            const bar = card.querySelector('[data-idlookup-bar]');
            if (dot) {
                dot.style.background = color;
                dot.classList.toggle('animate-pulse', opts.tone !== 'green');
            }
            if (bar) {
                // backgroundColor (not shorthand background) so the striped
                // shimmer background-image from .is-loading still shows.
                bar.style.backgroundColor = color;
                const pctLabel = card.querySelector('[data-idlookup-pct]');
                const setIdlookupPct = (p) => {
                    const clamped = Math.max(0, Math.min(100, Math.floor(p)));
                    bar.style.width = clamped + '%';
                    if (pctLabel) pctLabel.textContent = clamped + '%';
                };
                if (opts.percent === null || opts.percent === undefined) {
                    // Indeterminate (total pages still unknown): never jump to
                    // full — crawl gradually from the last % toward 99% until
                    // a chunk with a real total arrives.
                    let sim = parseFloat(card.dataset.simPct || '');
                    if (!isFinite(sim)) sim = parseFloat(bar.style.width) || 0;
                    sim = Math.min(99, sim + Math.max(0.8, (99 - sim) * 0.04));
                    card.dataset.simPct = String(sim);
                    bar.classList.remove('animate-pulse');
                    bar.classList.add('is-loading');
                    setIdlookupPct(sim);
                } else {
                    delete card.dataset.simPct;
                    bar.classList.remove('animate-pulse', 'is-loading');
                    setIdlookupPct(opts.percent);
                }
            }
            const title = card.querySelector('[data-idlookup-title]');
            if (title && opts.title !== undefined) title.textContent = opts.title;
            const detail = card.querySelector('[data-idlookup-detail]');
            if (detail && opts.detail !== undefined) {
                detail.textContent = opts.detail;
                detail.title = opts.detail;
            }
            const stats = card.querySelector('[data-idlookup-stats]');
            if (stats && opts.stats !== undefined) stats.textContent = opts.stats;
        }

        function clearOfficerIdLookupStatus() {
            document.querySelectorAll('[data-idlookup-status]').forEach(el => el.remove());
        }

        async function runMotherOrgLookup(officers, key, departmentId, controller, csrf, startedAt, rowsCounter) {
            renderOfficerIdLookupProgress({ title: 'Matching Officer IDs', detail: 'Reading Mother Org list…', stats: '', percent: null, tone: 'amber' });
            let meta = null;
            try {
                const metaResponse = await fetch(`/apply-org/mother-org-member-files/${encodeURIComponent(departmentId)}`, {
                    headers: { 'Accept': 'application/json' }, signal: controller.signal,
                });
                meta = await metaResponse.json();
            } catch (error) {
                if (error.name === 'AbortError') return null;
                throw error;
            }
            const files = meta && Array.isArray(meta.files) ? meta.files : [];
            if (!meta || !meta.has_mother_org || files.length === 0) return { empty: true };
            let partial = false;
            let failReason = '';
            for (let i = 0; i < files.length; i++) {
                if (controller.signal.aborted) return null;
                const f = files[i];
                let pageFrom = 1;
                let knownTotal = 0;
                for (;;) {
                    if (controller.signal.aborted) return null;
                    renderOfficerIdLookupProgress({
                        title: 'Matching Officer IDs',
                        detail: `Reading Mother Org list (${i + 1}/${files.length}): ${f.name || 'file'}`,
                        stats: knownTotal > 0
                            ? `Pages ${pageFrom} of ${knownTotal} · ${officerLookupElapsed(startedAt)} elapsed · ${rowsCounter.count} rows found`
                            : `Pages ${pageFrom}… · ${officerLookupElapsed(startedAt)} elapsed`,
                        percent: knownTotal > 0 ? ((pageFrom - 1) / knownTotal) * 100 : null,
                        tone: 'amber',
                    });
                    const formData = new FormData();
                    formData.append('department_id', String(departmentId));
                    formData.append('file_index', String(f.index));
                    formData.append('page_from', String(pageFrom));
                    formData.append('page_size', String(OFFICER_ID_LOOKUP_PAGE_SIZE));
                    formData.append('officers', JSON.stringify(officers));
                    formData.append('request_key', key);
                    formData.append('_token', csrf);
                    let chunk = null;
                    try {
                        const response = await fetch('/apply-org/match-mother-org-ids', {
                            method: 'POST', body: formData, signal: controller.signal,
                        });
                        chunk = await response.json();
                    } catch (error) {
                        if (error.name === 'AbortError') return null;
                        throw error;
                    }
                    if (!chunk || !chunk.success) {
                        partial = true;
                        if (!failReason && chunk && chunk.message) failReason = String(chunk.message);
                        break;
                    }
                    knownTotal = Number(chunk.pages_total) || knownTotal;
                    rowsCounter.count = Number(chunk.rows_total) || rowsCounter.count;
                    console.info('[Member ID Lookup] Mother Org chunk parsed:', { file: f.name, pages_done: chunk.pages_done, pages_total: chunk.pages_total, rows_total: chunk.rows_total });
                    if (chunk.complete) break;
                    pageFrom = (Number(chunk.pages_done) || pageFrom) + 1;
                    if (knownTotal > 0 && pageFrom > knownTotal) break;
                }
            }
            if (controller.signal.aborted) return null;
            renderOfficerIdLookupProgress({ title: 'Matching Officer IDs', detail: 'Matching officer IDs…', stats: `${officerLookupElapsed(startedAt)} elapsed`, percent: 100, tone: 'blue' });
            const finishData = new FormData();
            finishData.append('department_id', String(departmentId));
            finishData.append('officers', JSON.stringify(officers));
            finishData.append('request_key', key);
            finishData.append('finish', '1');
            finishData.append('_token', csrf);
            let fin = null;
            try {
                const finishResponse = await fetch('/apply-org/match-mother-org-ids', {
                    method: 'POST', body: finishData, signal: controller.signal,
                });
                fin = await finishResponse.json();
            } catch (error) {
                if (error.name === 'AbortError') return null;
                throw error;
            }
            if (!fin || !fin.success || !fin.results) return { empty: true, partial, failReason: (fin && fin.message) || failReason };
            return { results: fin.results, rowsRead: Number(fin.rows_received) || 0, partial, failReason };
        }

        async function maybeLookupOfficerIds() {
            if (currentAppStep() !== 6) return;
            // typeof guards: memberFiles/memberSource are declared later in
            // this view, and the prefill test harnesses execute this block
            // without them.
            const useMotherOrg = typeof memberSource !== 'undefined' && !!memberSource
                && memberSource.mode === 'mother_org' && !!memberSource.departmentId;
            const hasUploads = typeof memberFiles !== 'undefined' && Array.isArray(memberFiles) && memberFiles.length > 0;
            if (!useMotherOrg && !hasUploads) return;
            const officers = {};
            officerLookupSlots().forEach(slot => {
                const name = readOfficerNameFromCard(slot);
                if (name) officers[slot] = name;
            });
            if (Object.keys(officers).length === 0) return;
            let key = officerLookupCacheKey(officers);
            if (useMotherOrg) key += `::mother-${memberSource.departmentId}`;
            if (officerIdLookupState.cacheKey === key) {
                applyOfficerIdLookupResults(officerIdLookupState.results, false);
                return;
            }
            // Names or files changed — cancel any stale in-flight run.
            officerIdLookupState.abortController?.abort();
            const controller = new AbortController();
            officerIdLookupState.abortController = controller;
            officerIdLookupState.status = 'loading';
            officerIdLookupState.cacheKey = key;
            officerIdLookupState.results = {};

            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const files = hasUploads ? memberFiles.slice() : [];
            const allRows = [];
            let filesPartial = 0;
            const lookupStartedAt = Date.now();
            const rowsCounter = { count: 0 };
            let failReason = '';

            const lookupProgress = (title, detail, stats, percent) => renderOfficerIdLookupProgress({
                title, detail, stats, percent, tone: 'amber',
            });

            try {
                let matchResult = null;
                let partialMessage = '';
                if (useMotherOrg) {
                    const mother = await runMotherOrgLookup(officers, key, memberSource.departmentId, controller, csrf, lookupStartedAt, rowsCounter);
                    if (mother === null) return;
                    if (!mother.empty && mother.results) {
                        matchResult = { success: true, results: mother.results, rows_received: mother.rowsRead || 0 };
                        if (mother.partial) partialMessage = 'Mother Org list could not be fully read. Unmatched IDs may need manual entry.';
                    } else if (!mother.empty) {
                        failReason = mother.failReason || failReason;
                    }
                } else {
                    for (let i = 0; i < files.length; i++) {
                        if (controller.signal.aborted) return;
                        const entry = files[i];
                        let pageFrom = 1;
                        let knownTotal = 0;
                        let fileOk = true;
                        for (;;) {
                            if (controller.signal.aborted) return;
                            lookupProgress(
                                'Matching Officer IDs',
                                `Reading file ${i + 1}/${files.length}: ${entry.file.name}`,
                                knownTotal > 0
                                    ? `Pages ${pageFrom} of ${knownTotal} · ${officerLookupElapsed(lookupStartedAt)} elapsed · ${allRows.length} rows found`
                                    : `Pages ${pageFrom}… · ${officerLookupElapsed(lookupStartedAt)} elapsed`,
                                knownTotal > 0 ? ((pageFrom - 1) / knownTotal) * 100 : null
                            );
                            const formData = new FormData();
                            formData.append('member_file', entry.file, entry.file.name);
                            formData.append('page_from', String(pageFrom));
                            formData.append('page_size', String(OFFICER_ID_LOOKUP_PAGE_SIZE));
                            formData.append('request_key', `${key}-f${i}`);
                            formData.append('_token', csrf);
                            let result = null;
                            try {
                                const response = await fetch('/apply-org/parse-member-rows', {
                                    method: 'POST', body: formData, signal: controller.signal,
                                });
                                result = await response.json();
                            } catch (error) {
                                if (error.name === 'AbortError') return;
                                throw error;
                            }
                            if (!result || !result.success || !Array.isArray(result.rows)) {
                                fileOk = false;
                                if (!failReason && result && result.message) failReason = String(result.message);
                                break;
                            }
                            knownTotal = Number(result.pages_total) || knownTotal;
                            allRows.push(...result.rows);
                            console.info('[Member ID Lookup] Parsed chunk:', { file: entry.file.name, pages_done: result.pages_done, pages_total: result.pages_total, rows_in_chunk: result.rows.length, rows_total: allRows.length });
                            if (result.complete) break;
                            pageFrom = (Number(result.pages_done) || pageFrom) + 1;
                            if (knownTotal > 0 && pageFrom > knownTotal) break;
                        }
                        if (!fileOk) filesPartial++;
                    }
                    if (controller.signal.aborted) return;
                    if (officerIdLookupState.abortController !== controller) return;
                    if (officerIdLookupState.cacheKey !== key) return;

                    renderOfficerIdLookupProgress({ title: 'Matching Officer IDs', detail: 'Matching officer IDs…', stats: `${officerLookupElapsed(lookupStartedAt)} elapsed · ${allRows.length} rows read`, percent: 100, tone: 'blue' });
                    const matchData = new FormData();
                    matchData.append('officers', JSON.stringify(officers));
                    matchData.append('rows', JSON.stringify(allRows));
                    matchData.append('request_key', key);
                    matchData.append('_token', csrf);
                    try {
                        const matchResponse = await fetch('/apply-org/match-officer-ids', {
                            method: 'POST', body: matchData, signal: controller.signal,
                        });
                        matchResult = await matchResponse.json();
                    } catch (error) {
                        if (error.name === 'AbortError') return;
                        throw error;
                    }
                    if (!matchResult || !matchResult.success) {
                        if (!failReason && matchResult && matchResult.message) failReason = String(matchResult.message);
                    }
                    if (filesPartial > 0) partialMessage = `${filesPartial} member file(s) could not be fully read. Unmatched IDs may need manual entry.`;
                }
                if (controller.signal.aborted) return;
                if (officerIdLookupState.abortController !== controller) return;
                if (officerIdLookupState.cacheKey !== key) return;
                officerIdLookupState.status = 'done';
                clearOfficerIdLookupStatus();
                if (matchResult && matchResult.success && matchResult.results) {
                    officerIdLookupState.results = matchResult.results;
                    applyOfficerIdLookupResults(matchResult.results, true, Number(matchResult.rows_received) || 0);
                    if (partialMessage !== '' && typeof showToast === 'function') {
                        showToast(partialMessage, 'warning', 'Partial Read');
                    }
                } else {
                    officerIdLookupState.results = {};
                    officerIdLookupState.cacheKey = null;
                    renderOfficerIdLookupProgress({ title: 'Member ID Lookup', detail: 'Lookup failed', stats: failReason || 'Please enter IDs manually.', percent: 100, tone: 'red' });
                    if (typeof showToast === 'function') {
                        showToast(failReason || 'Member ID lookup failed. Please enter IDs manually.', 'warning', 'Lookup Failed');
                    }
                }
            } catch (error) {
                if (error.name === 'AbortError') return;
                if (officerIdLookupState.abortController !== controller) return;
                officerIdLookupState.status = 'idle';
                officerIdLookupState.cacheKey = null;
                renderOfficerIdLookupProgress({ title: 'Member ID Lookup', detail: 'Lookup failed', stats: 'Please try again or enter IDs manually.', percent: 100, tone: 'red' });
                if (typeof showToast === 'function') {
                    showToast('Member ID lookup failed. Please try again or enter IDs manually.', 'warning', 'Lookup Failed');
                }
            }
        }

        // Apply Form File Uploads (Step 2) with Preview
        for (let i = 1; i <= 6; i++) {
            const fileInput = document.getElementById(`file${i}`);
            const previewContainer = document.getElementById(`preview${i}`);
            const previewImg = document.getElementById(`preview${i}-img`);
            
            if (fileInput) {
                fileInput.addEventListener('change', async (e) => {
                    if (e.target.files.length > 0) {
                        const file = e.target.files[0];
                        const fileType = file.type;

                        // Show preview container
                        previewContainer.classList.remove('hidden');

                        if (fileType.startsWith('image/')) {
                            // For image files, show actual image
                            const reader = new FileReader();
                            reader.onload = (event) => {
                                previewImg.src = event.target.result;
                            };
                            reader.readAsDataURL(file);
                        } else if (fileType === 'application/pdf') {
                            // For PDF files, render first page
                            const reader = new FileReader();
                            reader.onload = async (event) => {
                                try {
                                    const typedArray = new Uint8Array(event.target.result);
                                    const pdf = await pdfjsLib.getDocument(typedArray).promise;
                                    const page = await pdf.getPage(1);
                                    
                                    // Calculate scale to fit width
                                    const viewport = page.getViewport({scale: 1});
                                    const scale = 300 / viewport.width; // 300px width
                                    const scaledViewport = page.getViewport({scale: scale});
                                    
                                    // Create canvas
                                    const canvas = document.createElement('canvas');
                                    const context = canvas.getContext('2d');
                                    canvas.height = scaledViewport.height;
                                    canvas.width = scaledViewport.width;
                                    
                                    // Render PDF page to canvas
                                    await page.render({
                                        canvasContext: context,
                                        viewport: scaledViewport
                                    }).promise;
                                    
                                    // Set canvas as image source
                                    previewImg.src = canvas.toDataURL();
                                } catch (error) {
                                    console.error('PDF rendering error:', error);
                                    // Fallback: Show PDF icon
                                    previewImg.src = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMDAiIGhlaWdodD0iMTUwIiB2aWV3Qm94PSIwIDAgMjAwIDE1MCI+PHJlY3Qgd2lkdGg9IjIwMCIgaGVpZ2h0PSIxNTAiIGZpbGw9IiNmM2Y0ZjYiLz48dGV4dCB4PSIxMDAiIHk9IjYwIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMjQiIGZpbGw9IiNlZjQ0NDQiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtd2VpZ2h0PSJib2xkIj5QREYgRmlsZTwvdGV4dD48dGV4dCB4PSIxMDAiIHk9IjkwIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTIiIGZpbGw9IiM2YjcyODAiIHRleHQtYW5jaG9yPSJtaWRkbGUiPkNsaWNrIHRvIHZpZXc8L3RleHQ+PC9zdmc+';
                                }
                            };
                            reader.readAsArrayBuffer(file);
                        }
                    }
                });
            }
        }

        // Apply Requester Information helper
        function applyRequesterInfoToOfficer(namePrefix, positionKey, isAdviser) {
            const firstName = document.getElementById('req_first_name_apply')?.value || '';
            const lastName = document.getElementById('req_last_name_apply')?.value || '';
            const email = document.getElementById('req_email_apply')?.value || '';
            
            if (!firstName && !lastName && !email) {
                if (typeof showToast === 'function') {
                    showToast('Please fill in Requester Information in Step 1 first!', 'warning', 'No Data');
                } else {
                    alert('Please fill in Requester Information in Step 1 first!');
                }
                return;
            }
            
            // Requester-driven values are manual intent, regardless of the destination role.
            // Use the shared generated IDs so later PDF applies and step revisits cannot replace them.
            assignManualPrefillValue(
                document.getElementById(`${namePrefix}_${positionKey}_first_name`),
                firstName
            );
            assignManualPrefillValue(
                document.getElementById(`${namePrefix}_${positionKey}_last_name`),
                lastName
            );
            assignManualPrefillValue(
                document.getElementById(`${namePrefix}_${positionKey}_email`),
                email
            );
            
            if (typeof showToast === 'function') {
                showToast('Requester Information applied successfully!', 'success', 'Applied');
            }
        }

        // Generate officer forms
        function generateOfficerForms(containerId, namePrefix) {
            const container = document.getElementById(containerId);
            container.innerHTML = '';

            const reqFirstName = document.getElementById('req_first_name_apply')?.value || '';
            const reqLastName = document.getElementById('req_last_name_apply')?.value || '';
            const reqEmail = document.getElementById('req_email_apply')?.value || '';
            const reqPosition = (typeof detectRequesterPosition === 'function') ? detectRequesterPosition() : '';

            let officerCounter = 0;
            officerPositions.forEach(position => {
                officerCounter++;
                const positionKey = position.key;
                const positionLabel = position.label;
                const isAdviser = position.is_adviser;

                    const officerDiv = document.createElement('div');
                    officerDiv.className = 'border border-gray-300 rounded-lg p-3 bg-gray-50';
                    
                    if (isAdviser) {
                        let uniqueIdNumber = '';
                        let uniqueEmail = '';
                        let firstName = '';
                        let lastName = '';

                        const isThisRequesterAdviser = (positionKey === 'senior_adviser' || positionKey === 'junior_adviser') && reqPosition === 'Organization Adviser' ||
                                                       (positionKey === 'senior_adviser' && reqPosition === 'Organization Senior Adviser') ||
                                                       (positionKey === 'junior_adviser' && reqPosition === 'Organization Junior Adviser');

                        if (isThisRequesterAdviser) {
                            if (reqFirstName) firstName = reqFirstName;
                            if (reqLastName) lastName = reqLastName;
                            if (reqEmail) uniqueEmail = reqEmail;
                        }

                        // Adviser fields: ID Number with student_id-based autofill (email updatable) - STRICT 00-0000-0000
                        officerDiv.innerHTML = `
                            <h4 class="font-bold text-sm text-gray-800 mb-2">${positionLabel}</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-semibold mb-1">ID Number *</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][id_number]" 
                                        id="${namePrefix}_${positionKey}_id_number" required
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C]"
                                        placeholder="00-0000-0000"
                                        maxlength="12"
                                        pattern="^\\d{2}-\\d{4}-\\d{4}$"
                                        title="Dean/Adviser ID must be 00-0000-0000"
                                        value="${uniqueIdNumber}"
                                        oninput="formatAdviserID(this); autoFetchAdviser('${namePrefix}','${positionKey}', this.value, false)"
                                        onblur="autoFetchAdviser('${namePrefix}','${positionKey}', this.value, true)">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Email *</label>
                                    <input type="email" name="${namePrefix}[${positionKey}][email]" required
                                        id="${namePrefix}_${positionKey}_email"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C]"
                                        value="${uniqueEmail}"
                                        placeholder="email@lspu.edu.ph">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">First Name *</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][first_name]" required
                                        id="${namePrefix}_${positionKey}_first_name"
                                        value="${firstName}"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C]">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Middle Initial</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][middle_initial]"
                                        id="${namePrefix}_${positionKey}_middle_name" maxlength="1" pattern="[A-Za-z]"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C] uppercase" placeholder="M.I.">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Last Name *</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][last_name]" required
                                        id="${namePrefix}_${positionKey}_last_name"
                                        value="${lastName}"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C]">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Suffix</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][suffix]"
                                        id="${namePrefix}_${positionKey}_suffix"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C]"
                                        placeholder="Jr., Sr., III">
                                </div>
                            </div>
                            <input type="hidden" name="${namePrefix}[${positionKey}][position]" value="${positionKey}">
                        `;
                    } else if (positionKey === 'comelec') {
                        // COMELEC: ID Number with autofill (email updatable) - STRICT 00-0000-0000 - debounced 2s
                        officerDiv.innerHTML = `
                            <h4 class="font-bold text-sm text-gray-800 mb-2">${positionLabel}</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-semibold mb-1">ID Number *</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][id_number]" required
                                        id="${namePrefix}_${positionKey}_id_number"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C]"
                                        placeholder="00-0000-0000"
                                        maxlength="12"
                                        pattern="^\\d{2}-\\d{4}-\\d{4}$"
                                        title="Adviser ID must be 00-0000-0000"
                                        oninput="formatAdviserID(this); autoFetchAdviser('${namePrefix}','${positionKey}', this.value, false)"
                                        onblur="autoFetchAdviser('${namePrefix}','${positionKey}', this.value, true)">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Email *</label>
                                    <input type="email" name="${namePrefix}[${positionKey}][email]" required
                                        id="${namePrefix}_${positionKey}_email"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C]"
                                        placeholder="email@lspu.edu.ph">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">First Name *</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][first_name]" required
                                        id="${namePrefix}_${positionKey}_first_name"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C]">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Middle Initial</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][middle_initial]"
                                        id="${namePrefix}_${positionKey}_middle_name" maxlength="1" pattern="[A-Za-z]"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C] uppercase" placeholder="M.I.">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Last Name *</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][last_name]" required
                                        id="${namePrefix}_${positionKey}_last_name"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C]">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Suffix</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][suffix]"
                                        id="${namePrefix}_${positionKey}_suffix"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C]"
                                        placeholder="Jr., Sr., III">
                                </div>
                            </div>
                            <input type="hidden" name="${namePrefix}[${positionKey}][position]" value="${positionKey}">
                        `;
                    } else {
                        let uniqueStudentId = '';
                        let uniqueEmail = '';
                        let firstName = '';
                        let lastName = '';

                        const isThisRequesterPresident = (positionKey === 'president' && reqPosition === 'Organization President');

                        if (isThisRequesterPresident) {
                            if (reqFirstName) firstName = reqFirstName;
                            if (reqLastName) lastName = reqLastName;
                            if (reqEmail) uniqueEmail = reqEmail;
                        }

                        // Student fields: Student ID with auto-fill — STRICT 0000-0000
                        officerDiv.innerHTML = `
                            <h4 class="font-bold text-sm text-gray-800 mb-2">${positionLabel}</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div class="col-span-2">
                                    <label class="block text-xs font-semibold mb-1">Student ID *</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][student_id]" required
                                        id="${namePrefix}_${positionKey}_student_id"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C]"
                                        placeholder="2021-0001"
                                        maxlength="9"
                                        pattern="^\\d{4}-\\d{4}$"
                                        title="Student ID must be 0000-0000"
                                        value="${uniqueStudentId}"
                                        oninput="formatStudentID(this, '${namePrefix}', '${positionKey}')"
                                        onblur="autoFetchStudent('${namePrefix}', '${positionKey}', this.value, true)">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">First Name *</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][first_name]" required
                                        id="${namePrefix}_${positionKey}_first_name"
                                        value="${firstName}"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C]">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Middle Initial</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][middle_initial]"
                                        id="${namePrefix}_${positionKey}_middle_name" maxlength="1" pattern="[A-Za-z]"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C] uppercase" placeholder="M.I.">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Last Name *</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][last_name]" required
                                        id="${namePrefix}_${positionKey}_last_name"
                                        value="${lastName}"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C]">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Email *</label>
                                    <input type="email" name="${namePrefix}[${positionKey}][email]" required
                                        id="${namePrefix}_${positionKey}_email"
                                        value="${uniqueEmail}"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C]">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Department *</label>
                                    <select name="${namePrefix}[${positionKey}][department_id]" required
                                        id="${namePrefix}_${positionKey}_department_id"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C]"
                                        onchange="updateCourseOptions('${namePrefix}_${positionKey}_course', this.value)">
                                        <option value="">Select Department</option>
                                        @foreach($departments ?? [] as $department)
                                            <option value="{{ $department->id }}">{{ $department->department_name }} ({{ $department->department_acronym }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Course *</label>
                                    <select name="${namePrefix}[${positionKey}][course]" required
                                        id="${namePrefix}_${positionKey}_course"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C]">
                                        <option value="">Select Course</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Year Level *</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][year_level]" required
                                        id="${namePrefix}_${positionKey}_year_level"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C]">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Section *</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][section]" required
                                        id="${namePrefix}_${positionKey}_section"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C]">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Suffix</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][suffix]"
                                        id="${namePrefix}_${positionKey}_suffix"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#26488C]">
                                </div>
                            </div>
                            <input type="hidden" name="${namePrefix}[${positionKey}][position]" value="${positionKey}">
                        `;
                    }
                    container.appendChild(officerDiv);

                    autoLinkOfficerDepartment(namePrefix, positionKey);
            });

            renderCustomOfficerSection(namePrefix);
        }

        // Maps the college name detected from the Certification of Officers PDF
        // ("College of ...") to a department option id by normalized text match.
        // Link and lock the Department dropdown of an officer card:
        // Academic orgs reuse the global department; Non-Academic orgs use the
        // college detected from the Certification of Officers PDF when it
        // matches an option, and otherwise stay blank for manual selection.
        function autoLinkOfficerDepartment(namePrefix, positionKey, preferredDeptId = null) {
            const orgType = document.getElementById('org_type_apply')?.value ?? '';
            const globalDeptId = document.getElementById('department_apply')?.value ?? '';

            const officerDeptSelect = document.getElementById(`${namePrefix}_${positionKey}_department_id`);
            if (!officerDeptSelect) return;

            if (orgType === 'Non-Academic') {
                // No global department exists for Non-Academic orgs. Select the
                // college detected from the PDF when it matches an option;
                // otherwise leave the select blank for manual selection.
                // Re-selection is safe here (no hidden input is involved).
                const linkedByCollege = officerDeptSelect.dataset.deptLinked === 'true';
                const matchedOption = preferredDeptId && Array.from(officerDeptSelect.options).some(option =>
                    String(option.value ?? option) === String(preferredDeptId));
                if (matchedOption) {
                    officerDeptSelect.value = String(preferredDeptId);
                    officerDeptSelect.dataset.deptLinked = 'true';

                    // Load course options automatically
                    updateCourseOptions(`${namePrefix}_${positionKey}_course`, officerDeptSelect.value);
                } else if (linkedByCollege) {
                    // A college-linked card no longer matches the current PDF:
                    // clear it for manual selection.
                    officerDeptSelect.value = '';
                    delete officerDeptSelect.dataset.deptLinked;
                    const courseSelect = document.getElementById(`${namePrefix}_${positionKey}_course`);
                    if (courseSelect) courseSelect.value = '';
                }
            } else if (globalDeptId) {
                // Idempotency guard: never re-link (or duplicate the hidden
                // submit input) for a card that was already linked.
                if (officerDeptSelect.dataset.deptLinked === 'true') return;

                // Default to the global department and lock it
                officerDeptSelect.value = globalDeptId;
                officerDeptSelect.disabled = true;
                officerDeptSelect.classList.add('bg-gray-100', 'cursor-not-allowed');
                officerDeptSelect.dataset.deptLinked = 'true';
                
                // Create a hidden input to make sure it gets submitted even when disabled
                const hiddenDeptInput = document.createElement('input');
                hiddenDeptInput.type = 'hidden';
                hiddenDeptInput.name = `${namePrefix}[${positionKey}][department_id]`;
                hiddenDeptInput.value = globalDeptId;
                officerDeptSelect.parentNode.appendChild(hiddenDeptInput);
                
                // Load course options automatically
                updateCourseOptions(`${namePrefix}_${positionKey}_course`, globalDeptId);
            }
        }

        // Match a prefilled course name against the loaded course options
        // (case/format-insensitive). No match leaves the select blank for
        // manual selection — never guesses.
        function matchAndSetCourse(selectId, courseName) {
            const select = document.getElementById(selectId);
            if (!select || !courseName) return;
            const norm = s => String(s || '').toLowerCase().replace(/[^a-z0-9]/g, '');
            const target = norm(courseName);
            if (!target) return;

            let matchedVal = null;
            for (const opt of select.options) {
                if (!opt.value) continue;
                const optNorm = norm(opt.value);
                if (optNorm === target || optNorm.includes(target) || target.includes(optNorm)) {
                    matchedVal = opt.value;
                    break;
                }
            }
            if (matchedVal) {
                select.value = matchedVal;
            }
        }

        // Map a college name (from the Certification of Officers PDF) to the
        // matching department option; null when nothing matches. Non-Academic
        // orgs have no global department, so this is the only way to autofill.
        function departmentIdForCollege(collegeName) {
            if (!collegeName) return null;
            const normalize = value => String(value ?? '')
                .replace(/\([^)]*\)/g, ' ')
                .toLowerCase()
                .replace(/[^a-z0-9\s]/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
            const target = normalize(collegeName);
            if (!target) return null;
            const select = document.getElementById('officers_president_department_id')
                || document.getElementById('department_apply');
            if (!select || !select.options) return null;
            for (const option of select.options) {
                const text = normalize(option.textContent ?? String(option));
                if (text && (text.includes(target) || target.includes(text))) {
                    return String(option.value ?? option);
                }
            }
            return null;
        }

        // "Other Position" cards — appended at the end of the officers container,
        // visually separated from the default positions.
        function humanizePositionKey(key) {
            const cleaned = String(key).replace(/^custom_\d+$/, '').split('_')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ').trim();
            return cleaned || 'Other Position';
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        let customIdSeq = 0;
        function refreshCustomIdSeq() {
            customOfficerSlots.forEach(slot => {
                const m = String(slot.key).match(/^custom_(\d+)$/);
                if (m) customIdSeq = Math.max(customIdSeq, parseInt(m[1], 10));
            });
        }
        function nextCustomSlotKey() {
            refreshCustomIdSeq();
            customIdSeq += 1;
            return `custom_${customIdSeq}`;
        }

        function addManualCustomPosition(namePrefix) {
            ensureCustomOfficerCard(namePrefix, nextCustomSlotKey(), '');
        }

        function appendCustomAddButton(container) {
            if (document.getElementById('customAddPositionBtn')) return;
            const addBtn = document.createElement('div');
            addBtn.id = 'customAddPositionBtn';
            addBtn.className = 'border-t-2 border-dashed border-amber-300 pt-3';
            addBtn.innerHTML = `<button type="button" class="w-full px-3 py-2 text-xs font-semibold border-2 border-dashed border-amber-400 text-amber-700 rounded-lg hover:bg-amber-50">
                <i class="fas fa-plus mr-1"></i> Add Other Position
            </button>`;
            addBtn.querySelector('button').addEventListener('click', () => addManualCustomPosition('officers'));
            container.appendChild(addBtn);
        }

        function buildCustomOfficerCard(namePrefix, positionKey, label) {
            const officerDiv = document.createElement('div');
            officerDiv.className = 'border-2 border-dashed border-amber-400 rounded-lg p-3 bg-amber-50';
            officerDiv.innerHTML = `
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex-1 mr-2">
                                    <label class="block text-xs font-semibold mb-1">Position Name *</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][position_name]" required
                                        id="${namePrefix}_${positionKey}_position_name"
                                        value="${escapeHtml(label)}"
                                        placeholder="e.g. Business Manager 2"
                                        oninput="const p=document.getElementById('${namePrefix}_${positionKey}_position'); if(p) p.value=this.value;"
                                        class="w-full px-2 py-1 text-xs border border-amber-300 rounded focus:outline-none focus:border-amber-500">
                                </div>
                                <button type="button" onclick="removeCustomOfficerCard('${positionKey}')" class="ml-2 mt-5 text-red-500 hover:text-red-700 p-1" title="Remove"><i class="ph ph-x text-sm"></i></button>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div class="col-span-2">
                                    <label class="block text-xs font-semibold mb-1">Student ID *</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][student_id]" required
                                        id="${namePrefix}_${positionKey}_student_id"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-amber-600"
                                        placeholder="2021-0001"
                                        maxlength="9"
                                        pattern="^\\d{4}-\\d{4}$"
                                        title="Student ID must be 0000-0000"
                                        oninput="formatStudentID(this, '${namePrefix}', '${positionKey}')">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">First Name *</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][first_name]" required
                                        id="${namePrefix}_${positionKey}_first_name"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-amber-600">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Last Name *</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][last_name]" required
                                        id="${namePrefix}_${positionKey}_last_name"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-amber-600">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Middle Name</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][middle_name]"
                                        id="${namePrefix}_${positionKey}_middle_name"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-amber-600">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Email *</label>
                                    <input type="email" name="${namePrefix}[${positionKey}][email]" required
                                        id="${namePrefix}_${positionKey}_email"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-amber-600">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Department *</label>
                                    <select name="${namePrefix}[${positionKey}][department_id]" required
                                        id="${namePrefix}_${positionKey}_department_id"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-amber-600"
                                        onchange="updateCourseOptions('${namePrefix}_${positionKey}_course', this.value)">
                                        <option value="">Select Department</option>
                                        @foreach($departments ?? [] as $department)
                                            <option value="{{ $department->id }}">{{ $department->department_name }} ({{ $department->department_acronym }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Course *</label>
                                    <select name="${namePrefix}[${positionKey}][course]" required
                                        id="${namePrefix}_${positionKey}_course"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-amber-600">
                                        <option value="">Select Course</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Year Level *</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][year_level]" required
                                        id="${namePrefix}_${positionKey}_year_level"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-amber-600">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Section *</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][section]" required
                                        id="${namePrefix}_${positionKey}_section"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-amber-600">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Suffix</label>
                                    <input type="text" name="${namePrefix}[${positionKey}][suffix]"
                                        id="${namePrefix}_${positionKey}_suffix"
                                        class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-amber-600">
                                </div>
                            </div>
                            <input type="hidden" name="${namePrefix}[${positionKey}][position]" id="${namePrefix}_${positionKey}_position" value="${escapeHtml(label)}">
                        `;
            autoLinkOfficerDepartment(namePrefix, positionKey);
            return officerDiv;
        }

        function removeCustomOfficerCard(positionKey) {
            const input = document.getElementById(`officers_${positionKey}_position_name`);
            const card = input ? input.closest('.border-dashed') : null;
            if (card) card.remove();
            customOfficerSlots = customOfficerSlots.filter(s => s.key !== positionKey);
            // Rebuild divider/button
            document.getElementById('customAddPositionBtn')?.remove();
            const container = document.getElementById('appOfficersContainer');
            if (container) {
                const existingDivider = document.getElementById('customOfficersDivider');
                if (customOfficerSlots.length === 0 && existingDivider) existingDivider.remove();
                if (container && typeof appendCustomAddButton === 'function') appendCustomAddButton(container);
            }
        }

        // Renders the "Other Positions" section (divider, cards, add button)
        // at the end of the container during form generation.
        function renderCustomOfficerSection(namePrefix) {
            const container = document.getElementById('appOfficersContainer');
            if (!container) return;
            document.getElementById('customOfficersDivider')?.remove();
            const cardsCount = customOfficerSlots.length;
            if (cardsCount > 0) {
                const divider = document.createElement('div');
                divider.id = 'customOfficersDivider';
                divider.className = 'border-t-2 border-dashed border-amber-400 pt-3';
                divider.innerHTML = `<h4 class="text-sm font-bold text-amber-700 uppercase tracking-wide"><i class="fas fa-plus-circle mr-1"></i>Other Positions</h4>
                    <p class="text-xs text-gray-500 mt-0.5">Positions not part of the default list are listed below.</p>`;
                container.appendChild(divider);
            }
            customOfficerSlots.forEach(slot => {
                const existing = document.getElementById(`officers_${slot.key}_student_id`);
                if (!existing) container.appendChild(buildCustomOfficerCard(namePrefix, slot.key, slot.label));
            });
            appendCustomAddButton(container);
        }

        function ensureCustomOfficerCard(namePrefix, positionKey, label) {
            if (customOfficerSlots.some(slot => slot.key === positionKey)) return false;
            // Keep custom_1 key, allow rename via position_name input; delete -> next add is custom_2 via persistent seq (no reuse)
            // Preserve verbatim label (e.g. Assistant Secretary stays Assistant Secretary); no Custom_1 fallback — display uses Other Position when missing
            customOfficerSlots.push({ key: positionKey, label: label || humanizePositionKey(positionKey), source: 'auto' });
            const _m = String(positionKey).match(/^custom_(\d+)$/);
            if (_m) customIdSeq = Math.max(customIdSeq, parseInt(_m[1], 10));
            const container = document.getElementById('appOfficersContainer');
            if (!container) return true;

            let divider = document.getElementById('customOfficersDivider');
            if (!divider) {
                divider = document.createElement('div');
                divider.id = 'customOfficersDivider';
                divider.className = 'border-t-2 border-dashed border-amber-400 pt-3';
                divider.innerHTML = `<h4 class="text-sm font-bold text-amber-700 uppercase tracking-wide"><i class="fas fa-plus-circle mr-1"></i>Other Positions</h4>
                    <p class="text-xs text-gray-500 mt-0.5">Positions not part of the default list are listed below.</p>`;
                const existingButton = document.getElementById('customAddPositionBtn');
                if (existingButton) {
                    container.insertBefore(divider, existingButton);
                } else {
                    container.appendChild(divider);
                }
            }
            const buttonEl = document.getElementById('customAddPositionBtn');
            const card = buildCustomOfficerCard(namePrefix, positionKey, label || humanizePositionKey(positionKey));
            if (buttonEl) {
                container.insertBefore(card, buttonEl);
            } else {
                container.appendChild(card);
            }
            if (!buttonEl) appendCustomAddButton(container);

            return true;
        }

        // Debounce store for student_id autofill - 2s on input, instant on blur, updates all fields
        const studentDebounceTimers = {};

        function debounceAutoFetch(key, fn, delay = 2000) {
            if (studentDebounceTimers[key]) clearTimeout(studentDebounceTimers[key]);
            studentDebounceTimers[key] = setTimeout(fn, delay);
        }

        // Auto-fetch student data - STRICT: Student only 8 digits (0000-0000) - debounced 2s on input, instant on blur, updates all fields
        function autoFetchStudent(namePrefix, positionKey, value, immediate = false) {
            const key = `${namePrefix}_${positionKey}_student`;
            const doFetch = () => {
                const digitsOnly = value.replace(/\D/g, '');
                console.log('Auto-fetch triggered:', { value, digitsOnly, length: digitsOnly.length, immediate });
                if (digitsOnly.length === 10) {
                    if (typeof showToast === 'function') showToast('Student ID must be 0000-0000 (8 digits). Dean/Adviser uses 00-0000-0000.', 'error', 'Invalid Student ID');
                    const el = document.getElementById(`${namePrefix}_${positionKey}_student_id`);
                    if (el) el.classList.add('field-error');
                    return;
                }
                if (digitsOnly.length === 8) {
                    console.log('Fetching user data for:', value);
                    const el = document.getElementById(`${namePrefix}_${positionKey}_student_id`);
                    if (el) el.classList.remove('field-error');
                    fetchStudentData(namePrefix, positionKey, value);
                }
            };
            if (immediate) {
                if (studentDebounceTimers[key]) clearTimeout(studentDebounceTimers[key]);
                doFetch();
            } else {
                debounceAutoFetch(key, doFetch, 2000);
            }
        }

        // Format Adviser/Dean ID as 00-0000-0000 - STRICT 10 digits only (2-4-4), digits.slice 0,10 -> 2-4-4, maxlength 12
        function formatAdviserID(input) {
            const raw = input.value.replace(/\D/g, '');
            if (raw.length === 8) {
                if (typeof showToast === 'function') showToast('Adviser/Dean ID must be 00-0000-0000 (10 digits). Student 0000-0000 not allowed here.', 'error', 'Invalid Adviser ID');
                input.classList.add('field-error');
            }
            let value = raw.substring(0, 10);
            let formatted = '';
            if (value.length > 0) {
                formatted = value.substring(0, 2);
                if (value.length > 2) formatted += '-' + value.substring(2, 6);
                if (value.length > 6) formatted += '-' + value.substring(6, 10);
            }
            input.value = formatted;
            if (value.length === 10) input.classList.remove('field-error');
            else if (value.length > 0 && value.length < 10 && raw.length !== 8) input.classList.remove('field-error');
            // Strict autofill only on 10 digits is handled by autoFetchAdviser/autoFetchDean (shows error on 8)
        }

        // Format Student ID as YYYY-XXXX - STRICT 8 digits (0000-0000), digits.slice 0,8 -> 4-4, maxlength 9
        function formatStudentID(input, namePrefix, positionKey) {
            const raw = input.value.replace(/\D/g, '');
            if (raw.length === 10 || raw.length > 8) {
                if (typeof showToast === 'function') showToast('Student ID must be 0000-0000 (8 digits). Dean uses 00-0000-0000.', 'error', 'Invalid Student ID');
                input.classList.add('field-error');
            }
            let value = raw.substring(0, 8);
            let formatted = '';
            if (value.length > 0) {
                formatted = value.substring(0, 4);
                if (value.length > 4) formatted += '-' + value.substring(4, 8);
            }
            input.value = formatted;
            if (raw.length === 10 || raw.length > 8) return;
            if (value.length === 8) {
                input.classList.remove('field-error');
                if (namePrefix && positionKey) autoFetchStudent(namePrefix, positionKey, formatted, false);
            } else if (value.length > 0 && value.length < 8) {
                input.classList.remove('field-error');
            }
        }

        // Fetch student data by Student ID
        async function fetchStudentData(namePrefix, positionKey, studentId) {
            if (!studentId) {
                return;
            }

            const lookupRun = (typeof showLookupProgress === 'function')
                ? showLookupProgress('Matching Officer IDs', 'Matching ID ' + studentId + '…')
                : 0;
            console.log('=== FETCH STUDENT DATA ===');
            console.log('namePrefix:', namePrefix);
            console.log('positionKey:', positionKey);
            console.log('studentId:', studentId);
            console.log('Fetching from API:', `/api/student/${studentId}`);

            try {
                const response = await fetch(`/api/student/${encodeURIComponent(studentId)}`, {
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                });

                console.log('API Response status:', response.status);
                const data = await response.json();
                console.log('API Response data:', data);

                if (data.success && data.student) {
                    console.log('✅ Student found! Filling fields...');
                    
                    // Check if fields exist - handle middle_initial from users.middle_initial (M.I.)
                    const fieldIds = [
                        'first_name', 'last_name', 'middle_name', 'middle_initial', 'email', 
                        'course', 'year_level', 'section', 'suffix'
                    ];
                    
                    fieldIds.forEach(fieldName => {
                        // For officers, the input id is still middle_name but name is middle_initial - handle both
                        const fieldId = `${namePrefix}_${positionKey}_${fieldName}`;
                        const element = document.getElementById(fieldId) || (fieldName==='middle_initial' ? document.getElementById(`${namePrefix}_${positionKey}_middle_name`) : null);
                        if (!element) {
                            if (fieldName==='middle_initial' || fieldName==='middle_name') {
                                // Try alternative id for middle
                                const altId = `${namePrefix}_${positionKey}_middle_name`;
                                const altEl = document.getElementById(altId);
                                if (altEl && fieldName==='middle_initial' && !document.getElementById(fieldId)) {
                                    // Will handle via middle_name case
                                }
                            }
                            if (!element && fieldName==='middle_initial') return;
                        }
                        console.log(`Field ${fieldId}:`, element ? 'EXISTS' : '❌ NOT FOUND');
                        
                        if (element) {
                            let value = data.student[fieldName] || '';
                            // If filling middle_name field but API has middle_initial, use it (first letter)
                            if (fieldName==='middle_name' && !value && data.student.middle_initial) value = data.student.middle_initial;
                            if (fieldName==='middle_initial' && !value && data.student.middle_name) value = data.student.middle_name.trim().charAt(0).toUpperCase();
                            if (fieldName==='middle_initial' || fieldName==='middle_name') {
                                // For M.I. field, always use single letter from middle_initial or middle_name
                                if (!value && data.student.middle_initial) value = data.student.middle_initial;
                                else if (!value && data.student.middle_name) value = data.student.middle_name.trim().charAt(0).toUpperCase();
                                if (fieldName==='middle_initial' || element.id.includes('middle_name')) {
                                    value = value.toString().trim().charAt(0).toUpperCase();
                                    if (value) element.value = value;
                                    else element.value = '';
                                    console.log(`  → Set M.I. to: "${value}"`);
                                    return;
                                }
                            }
                            const isNameField = ['first_name', 'last_name', 'middle_name', 'middle_initial'].includes(fieldName);
                            const isPdfAutoFilled = Boolean(element.dataset.autoFillToken) && element.value !== '';

                            // DB record wins for names when it carries a value; the PDF
                            // value stays only as fallback when the DB value is empty.
                            if (isNameField && isPdfAutoFilled && String(value || '').trim() === '') {
                                console.log(`  → Kept PDF auto-filled ${fieldName} (DB empty): "${element.value}"`);
                                return;
                            }

                            if (fieldName === 'course') {
                                // If it's a select, we might need to wait for options or handle pending value
                                if (element.tagName === 'SELECT') {
                                    if (element.options.length > 1) {
                                        element.value = value;
                                    } else {
                                        element.dataset.pendingValue = value;
                                    }
                                } else {
                                    element.value = value;
                                }
                            } else {
                                element.value = value;
                            }
                            console.log(`  → Set value to: "${value}"`);
                        }
                    });
                    
                    console.log('✅ All fields filled successfully!');
                    if (typeof completeLookupProgress === 'function') completeLookupProgress(lookupRun, 'ID ' + studentId + ' matched — fields auto-filled.');
                } else {
                    console.log('❌ Student not found, leaving existing fields intact');
                    if (typeof failLookupProgress === 'function') failLookupProgress(lookupRun, 'Matching Officer IDs', 'No record for ID ' + studentId, 'Please enter details manually.');
                }
            } catch (error) {
                console.error('❌ Error fetching student data:', error);
                if (typeof failLookupProgress === 'function') failLookupProgress(lookupRun, 'Member ID Lookup', 'Lookup failed', 'Please enter IDs manually.');
            }
        }

        // --- Student_id-based autofill for Dean (STRICT: 10 digits 00-0000-0000 only) ---
        function autoFetchDean(value, immediate=false){
            const key = 'dean_id_number';
            const doFetch = () => {
                const digits = (value||'').replace(/\D/g,'');
                if(digits.length===8){
                    if(typeof showToast==='function') showToast('Dean/Adviser ID must be 00-0000-0000 (10 digits). Student 0000-0000 not allowed here.', 'error', 'Invalid Dean ID');
                    const el=document.getElementById('dean_id_number'); if(el) el.classList.add('field-error');
                    return;
                }
                if(digits.length===10){
                    const el=document.getElementById('dean_id_number'); if(el) el.classList.remove('field-error');
                    fetchDeanData(value);
                }
            };
            if(immediate) {
                if(studentDebounceTimers[key]) clearTimeout(studentDebounceTimers[key]);
                doFetch();
            } else {
                debounceAutoFetch(key, doFetch, 2000);
            }
        }
        async function fetchDeanData(studentId){
            if(!studentId) return;
            const clean = studentId.trim();
            const digitsCheck = clean.replace(/\D/g,'');
            if(digitsCheck.length !== 10){
                if(digitsCheck.length===8 && typeof showToast==='function') showToast('Dean ID must be 00-0000-0000 (10 digits). Student 0000-0000 not allowed here.', 'error', 'Invalid Dean ID');
                else if(digitsCheck.length>0 && typeof showToast==='function') showToast('Dean ID must be 00-0000-0000 (10 digits).', 'error', 'Invalid Dean ID');
                const el=document.getElementById('dean_id_number'); if(el && digitsCheck.length===8) el.classList.add('field-error');
                return;
            }
            console.log('Fetching Dean via /api/student:', clean);
            const deanLookupRun = (typeof showLookupProgress === 'function')
                ? showLookupProgress('Matching Officer IDs', 'Matching Dean ID ' + clean + '…')
                : 0;
            try{
                const res = await fetch(`/api/student/${encodeURIComponent(clean)}`, { method:'GET', headers:{'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', 'Accept':'application/json'}});
                const data = await res.json();
                if(data.success && data.student){
                    const s = data.student;
                    const mi = s.middle_initial || (s.middle_name ? s.middle_name.trim().charAt(0).toUpperCase() : '');
                    const mapped = { first_name:'dean_first_name', middle_initial:'dean_middle_name', last_name:'dean_last_name', suffix:'dean_suffix', email:'dean_email' };
                    const srcData = { ...s, middle_initial: mi };
                    Object.entries(mapped).forEach(([src, id])=>{
                        const el=document.getElementById(id);
                        if(el && srcData[src]!==undefined && srcData[src]!==null){
                            const isName = ['first_name','last_name','middle_initial'].includes(src);
                            const isPdfFilled = Boolean(el.dataset.autoFillToken) && el.value!=='';
                            if(isName && isPdfFilled) return;
                            let val = srcData[src] || '';
                            if(src==='middle_initial') val = val.toUpperCase().substring(0,1);
                            el.value = val;
                        }
                    });
                    if(window.showToast) window.showToast('Dean info auto-filled from ID. Email remains editable (will update on submit).','success','Dean Found');
                    if (typeof completeLookupProgress === 'function') completeLookupProgress(deanLookupRun, 'Dean ID ' + clean + ' matched — fields auto-filled.');
                } else {
                    console.log('Dean not found for', clean);
                    if (typeof failLookupProgress === 'function') failLookupProgress(deanLookupRun, 'Matching Officer IDs', 'No record for Dean ID ' + clean, 'Please enter details manually.');
                }
            }catch(e){ console.error('Dean fetch error', e); if (typeof failLookupProgress === 'function') failLookupProgress(deanLookupRun, 'Member ID Lookup', 'Lookup failed', 'Please enter IDs manually.'); }
        }
        function formatDeanID(input){
            formatAdviserID(input);
            autoFetchDean(input.value);
        }

        // Position is derived from the requester ID format — never picked manually.
        // 8 digits (0000-0000) => Organization President, 10 digits (00-0000-0000) => Organization Adviser.
        function detectRequesterPosition(){
            const raw = (document.getElementById('req_student_id_apply')?.value || '').replace(/\D/g,'');
            if(raw.length === 8) return 'Organization President';
            if(raw.length === 10) return 'Organization Adviser';
            return '';
        }
        function renderRequesterPosition(){
            const pos = detectRequesterPosition();
            const badge = document.getElementById('req_position_apply');
            if(badge){
                badge.textContent = pos || 'Type your ID above — position is detected automatically';
                badge.classList.toggle('text-gray-400', !pos);
            }
            return pos;
        }

        // Sync Requester ID placeholder/pattern/title/maxlength (load-time init only).
        // Always permissive: the mask follows digit count while typing, and the
        // dual pattern + backend derivation enforce the final format.
        function syncRequesterPattern(){
            const inp = document.getElementById('req_student_id_apply');
            const hint = document.getElementById('req_id_hint');
            if(!inp) return;
            inp.placeholder = 'Type your ID — position is detected automatically';
            inp.pattern = '^(\\d{4}-\\d{4}|\\d{2}-\\d{4}-\\d{4})$';
            inp.title = 'President 0000-0000 (8 digits) / Adviser 00-0000-0000 (10 digits)';
            inp.maxLength = 12;
            if(hint) hint.textContent = 'Type your ID — 0000-0000 (e.g. 2021-0001) is President, 00-0000-0000 (e.g. 00-1002-0000) is Adviser. Enter ID to auto-fill name & email.';
            // Reformat restored value to the digit-count rule and clear stale error states
            inp.classList.remove('field-error');
            formatRequesterID(inp);
        }
        // Init sync on load (covers back-navigation and autofill restore)
        document.addEventListener('DOMContentLoaded', function(){ try{ syncRequesterPattern(); }catch(e){} });

        // --- Requester autofill: mask follows digit count so typing never locks.
        // Never shrink maxLength while typing — the browser would swallow the
        // keystroke that crosses formats (8 -> 10 digits). Final format is
        // enforced by the pattern attribute + backend derivation.
        function formatRequesterID(input){
            if(!input) return;
            renderRequesterPosition();
            const raw = input.value.replace(/\D/g,'');
            input.maxLength = 12;
            input.pattern = '^(\\d{4}-\\d{4}|\\d{2}-\\d{4}-\\d{4})$';
            input.classList.remove('field-error');

            let fmt = '';
            if (raw.length > 8) {
                // Adviser track: 00-0000-0000 (10 digits, 2-4-4)
                let v = raw.substring(0, 10);
                if (v.length <= 2) fmt = v;
                else if (v.length <= 6) fmt = v.substring(0, 2) + '-' + v.substring(2);
                else fmt = v.substring(0, 2) + '-' + v.substring(2, 6) + '-' + v.substring(6);
                input.value = fmt;
                if (v.length === 10) fetchRequesterData(fmt);
            } else {
                // President track: 0000-0000 (8 digits, 4-4)
                let v = raw.substring(0, 8);
                if (v.length <= 4) fmt = v;
                else fmt = v.substring(0, 4) + '-' + v.substring(4);
                input.value = fmt;
                if (v.length === 8) fetchRequesterData(fmt);
            }
        }
        async function fetchRequesterData(studentId){
            if(!studentId) return;
            const clean=studentId.trim();
            const digits = clean.replace(/\D/g,'');
            const posVal = detectRequesterPosition();
            // Strict length gate per position
            if(posVal === 'Organization Adviser' && digits.length !== 10){
                if(digits.length===8 && typeof showToast==='function') showToast('Adviser ID must be 00-0000-0000 (10 digits).', 'error', 'Invalid Adviser ID');
                return;
            }
            if(posVal === 'Organization President' && digits.length !== 8){
                if(digits.length===10 && typeof showToast==='function') showToast('President ID must be 0000-0000 (8 digits).', 'error', 'Invalid President ID');
                return;
            }
            if(digits.length !== 8 && digits.length !== 10){
                return;
            }
            console.log('Fetching Requester via /api/student:', clean, 'pos:', posVal);
            try{
                const res = await fetch(`/api/student/${encodeURIComponent(clean)}`, { method:'GET', headers:{'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', 'Accept':'application/json'}});
                const data = await res.json();
                if(data.success && data.student){
                    const s=data.student;
                    const fn=document.getElementById('req_first_name_apply');
                    const ln=document.getElementById('req_last_name_apply');
                    const em=document.getElementById('req_email_apply');
                    const pos=document.getElementById('req_position_apply');
                    if(fn && s.first_name) fn.value=s.first_name;
                    if(ln && s.last_name) ln.value=s.last_name;
                    if(em && s.email) em.value=s.email;
                    renderRequesterPosition();
                    if(window.showToast) window.showToast('Requester info auto-filled. Email editable — will update via ID.','success','Found');
                }
            }catch(e){ console.error('Requester fetch error',e); }
        }

        // --- Adviser/officer id_number autofill STRICT 10 digits (00-0000-0000) - debounced 2s on input, instant on blur ---
        function autoFetchAdviser(namePrefix, positionKey, value, immediate=false){
            const key = `${namePrefix}_${positionKey}_adviser`;
            const doFetch = () => {
                const digits=(value||'').replace(/\D/g,'');
                if(digits.length===8){
                    if(typeof showToast==='function') showToast('Adviser/Dean ID must be 00-0000-0000 (10 digits). Student uses 0000-0000.', 'error', 'Invalid Adviser ID');
                    const el=document.getElementById(`${namePrefix}_${positionKey}_id_number`);
                    if(el) el.classList.add('field-error');
                    return;
                }
                if(digits.length===10){
                    const el=document.getElementById(`${namePrefix}_${positionKey}_id_number`);
                    if(el) el.classList.remove('field-error');
                    fetchStudentData(namePrefix, positionKey, value);
                }
            };
            if(immediate) {
                if(studentDebounceTimers[key]) clearTimeout(studentDebounceTimers[key]);
                doFetch();
            } else {
                debounceAutoFetch(key, doFetch, 2000);
            }
        }

        // Format Activator ID STRICT Dean/Adviser 00-0000-0000 (10 digits, 2-4-4) - maxlength 12
        // Activator ID dual: 0000-0000 (Student/President) or 00-0000-0000 (Adviser) — auto President/Adviser via length
        function formatActivatorID(input) {
            const raw = input.value.replace(/\D/g,'');
            const isAdviser = raw.length > 8;
            if(isAdviser){
                // 00-0000-0000 (10 digits, 2-4-4) — Adviser
                let value = raw.substring(0, 10);
                let formatted = '';
                if (value.length <= 2) formatted = value;
                else if (value.length <= 6) formatted = value.substring(0, 2) + '-' + value.substring(2, 6);
                else formatted = value.substring(0, 2) + '-' + value.substring(2, 6) + '-' + value.substring(6, 10);
                input.value = formatted;
                input.maxLength = 12;
                input.pattern = '^\\d{2}-\\d{4}-\\d{4}$';
                input.title = 'Adviser ID must be 00-0000-0000 (10 digits)';
                input.classList.remove('field-error');
                if(value.length===10) input.classList.remove('field-error');
            } else {
                // 0000-0000 (8 digits, 4-4) — Student/President
                let value = raw.substring(0, 8);
                let formatted = '';
                if (value.length <= 4) formatted = value;
                else formatted = value.substring(0, 4) + '-' + value.substring(4, 8);
                input.value = formatted;
                input.maxLength = 9;
                // Keep dual pattern while typing so 8 stays valid; will narrow to 9 vs 12 via maxLength
                // Use dual pattern to allow both during input, but enforce exact on submit
                input.pattern = '^(\\d{4}-\\d{4}|\\d{2}-\\d{4}-\\d{4})$';
                input.title = 'Student 0000-0000 or Adviser 00-0000-0000';
                input.classList.remove('field-error');
            }
            // Normalize pattern to dual for HTML5 validation (allows both), length-specific check handled via maxLength + backend
            input.pattern = '^(\\d{4}-\\d{4}|\\d{2}-\\d{4}-\\d{4})$';
            input.title = 'Student 0000-0000 (e.g. 2021-0001) or Adviser 00-0000-0000 (e.g. 00-1002-0000)';
        }

        // Toast Notification Function
        function showToast(message, type = 'error', title = null) {
            const container = document.getElementById('toast-container');
            if (!container) return;
            
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            const icons = {
                error: 'ph-warning-circle',
                success: 'ph-check-circle',
                warning: 'ph-warning'
            };
            
            const titles = {
                error: title || 'Error',
                success: title || 'Success',
                warning: title || 'Warning'
            };
            
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="ph ${icons[type]}"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title">${titles[type]}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <div class="toast-close" onclick="this.parentElement.remove()">
                    <i class="ph ph-x"></i>
                </div>
            `;
            
            container.appendChild(toast);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.classList.add('hiding');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }
        
        // --- Floating ID lookup progress card (gradual bar + % on the right + loading animation) ---
        const lookupProgress = { runId: 0, tick: null, clock: null, pct: 0, startedAt: 0, hideTimer: null };

        function setLookupBar(pct) {
            const fill = document.getElementById('lookup-progress-fill');
            const label = document.getElementById('lookup-progress-pct');
            const clamped = Math.max(0, Math.min(100, Math.floor(pct)));
            if (fill) fill.style.width = clamped + '%';
            if (label) label.textContent = clamped + '%';
            lookupProgress.pct = clamped;
        }

        function showLookupProgress(title, detail) {
            const card = document.getElementById('lookup-progress-card');
            if (!card) return 0;
            const runId = ++lookupProgress.runId;
            if (lookupProgress.hideTimer) { clearTimeout(lookupProgress.hideTimer); lookupProgress.hideTimer = null; }
            if (lookupProgress.tick) clearInterval(lookupProgress.tick);
            if (lookupProgress.clock) clearInterval(lookupProgress.clock);
            card.classList.remove('hidden', 'hiding', 'is-failed');
            card.classList.add('is-loading');
            document.getElementById('lookup-progress-title').textContent = title || 'Matching Officer IDs';
            document.getElementById('lookup-progress-dot').className = 'w-3 h-3 rounded-full bg-amber-400 flex-shrink-0';
            document.getElementById('lookup-progress-fill').className = 'h-full bg-[#C4973B] rounded-full';
            document.getElementById('lookup-progress-line1').textContent = detail || 'Working…';
            lookupProgress.startedAt = Date.now();
            document.getElementById('lookup-progress-line2').textContent = '0s elapsed';
            setLookupBar(0);
            // Ease toward 90% while the request is pending — never jump straight to full.
            lookupProgress.tick = setInterval(() => {
                if (runId !== lookupProgress.runId) { clearInterval(lookupProgress.tick); return; }
                const next = lookupProgress.pct + Math.max(0.6, (90 - lookupProgress.pct) * 0.055);
                setLookupBar(Math.min(90, next));
            }, 200);
            lookupProgress.clock = setInterval(() => {
                if (runId !== lookupProgress.runId) { clearInterval(lookupProgress.clock); return; }
                const secs = Math.floor((Date.now() - lookupProgress.startedAt) / 1000);
                const line2 = document.getElementById('lookup-progress-line2');
                if (line2 && document.getElementById('lookup-progress-card').classList.contains('is-loading')) {
                    line2.textContent = secs + 's elapsed';
                }
            }, 500);
            return runId;
        }

        function completeLookupProgress(runId, doneText) {
            if (runId !== lookupProgress.runId) return;
            const card = document.getElementById('lookup-progress-card');
            if (!card) return;
            if (lookupProgress.tick) clearInterval(lookupProgress.tick);
            if (lookupProgress.clock) clearInterval(lookupProgress.clock);
            card.classList.remove('is-loading');
            document.getElementById('lookup-progress-dot').className = 'w-3 h-3 rounded-full bg-green-500 flex-shrink-0';
            if (doneText) document.getElementById('lookup-progress-line1').textContent = doneText;
            setLookupBar(100);
            lookupProgress.hideTimer = setTimeout(() => hideLookupProgress(), 1200);
        }

        function failLookupProgress(runId, title, line1, line2) {
            if (runId !== lookupProgress.runId) return;
            const card = document.getElementById('lookup-progress-card');
            if (!card) return;
            if (lookupProgress.tick) clearInterval(lookupProgress.tick);
            if (lookupProgress.clock) clearInterval(lookupProgress.clock);
            card.classList.remove('is-loading');
            document.getElementById('lookup-progress-title').textContent = title || 'Member ID Lookup';
            document.getElementById('lookup-progress-dot').className = 'w-3 h-3 rounded-full bg-red-500 flex-shrink-0';
            document.getElementById('lookup-progress-fill').className = 'h-full bg-red-500 rounded-full';
            document.getElementById('lookup-progress-line1').textContent = line1 || 'Lookup failed';
            document.getElementById('lookup-progress-line2').textContent = line2 || 'Please enter IDs manually.';
            setLookupBar(100);
        }

        function hideLookupProgress() {
            const card = document.getElementById('lookup-progress-card');
            if (!card || card.classList.contains('hidden')) return;
            lookupProgress.runId++;
            if (lookupProgress.tick) clearInterval(lookupProgress.tick);
            if (lookupProgress.clock) clearInterval(lookupProgress.clock);
            if (lookupProgress.hideTimer) { clearTimeout(lookupProgress.hideTimer); lookupProgress.hideTimer = null; }
            card.classList.add('hiding');
            setTimeout(() => card.classList.add('hidden'), 280);
            setTimeout(() => card.classList.remove('hiding'), 320);
        }
        
        // Duplicate formatActivatorID removed - strict 2-4-4 enforced above

        // Load organizations based on selected department (for Create a Platform form)
        async function loadActivateOrganizations(departmentId) {
            const orgSelect = document.getElementById('activate_org_name');
            const acronymInput = document.getElementById('activate_org_acronym');
            const typeInput = document.getElementById('activate_org_type');
            
            console.log('Loading organizations for department:', departmentId);
            
            // Reset fields
            orgSelect.innerHTML = '<option value="">Loading...</option>';
            orgSelect.disabled = true;
            acronymInput.value = '';
            typeInput.value = '';
            window.selectedOrganizationId = null;
            
            if (!departmentId) {
                orgSelect.innerHTML = '<option value="">Select Department First</option>';
                return;
            }
            
            try {
                const response = await fetch(`/api/organizations-by-department/${departmentId}`, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                });
                
                const data = await response.json();
                console.log('Organizations loaded:', data);
                
                if (data.success && data.organizations.length > 0) {
                    orgSelect.innerHTML = '<option value="">Select Organization</option>';
                    data.organizations.forEach(org => {
                        const option = document.createElement('option');
                        option.value = org.name;
                        option.setAttribute('data-acronym', org.acronym);
                        option.setAttribute('data-type', org.type);
                        option.setAttribute('data-org-id', org.id);
                        option.textContent = org.name;
                        orgSelect.appendChild(option);
                    });
                    orgSelect.disabled = false;
                    
                    // Restore previously selected organization if it exists
                    if (window.selectedOrganizationName) {
                        orgSelect.value = window.selectedOrganizationName;
                        acronymInput.value = window.selectedOrganizationAcronym || '';
                        typeInput.value = window.selectedOrganizationType || '';
                        console.log('Restored organization selection:', window.selectedOrganizationName);
                    }
                    
                    console.log('Organizations dropdown populated');
                } else {
                    orgSelect.innerHTML = '<option value="">No approved organizations found</option>';
                    console.log('No organizations found');
                }
            } catch (error) {
                console.error('Error loading organizations:', error);
                orgSelect.innerHTML = '<option value="">Error loading organizations</option>';
            }
        }
        
        // Fill organization details when organization is selected
        function fillOrganizationDetails(orgName) {
            const orgSelect = document.getElementById('activate_org_name');
            const selectedOption = orgSelect.options[orgSelect.selectedIndex];
            const acronymInput = document.getElementById('activate_org_acronym');
            const typeInput = document.getElementById('activate_org_type');
            
            if (selectedOption && selectedOption.value && selectedOption.value !== '') {
                acronymInput.value = selectedOption.getAttribute('data-acronym') || '';
                typeInput.value = selectedOption.getAttribute('data-type') || '';
                
                // Store organization ID and name for filtering activator
                window.selectedOrganizationId = selectedOption.getAttribute('data-org-id');
                window.selectedOrganizationName = orgName;
                window.selectedOrganizationAcronym = acronymInput.value;
                window.selectedOrganizationType = typeInput.value;
                
                console.log('Organization selected:', {
                    name: orgName,
                    acronym: acronymInput.value,
                    type: typeInput.value,
                    id: window.selectedOrganizationId
                });
            } else {
                acronymInput.value = '';
                typeInput.value = '';
                window.selectedOrganizationId = null;
                window.selectedOrganizationName = null;
                window.selectedOrganizationAcronym = null;
                window.selectedOrganizationType = null;
                
                console.log('Organization cleared');
            }
        }
        
        // Auto-fetch activator data dual: 0000-0000 (Student) or 00-0000-0000 (Adviser) — auto President/Adviser
        async function autoFetchActivatorData(value) {
            const digitsOnly = value.replace(/\D/g, '');
            const el=document.getElementById('activator_student_id');
            if (digitsOnly.length === 8 || digitsOnly.length === 10) {
                if(el) el.classList.remove('field-error');
                await fetchActivatorData(value);
            } else {
                if(el && digitsOnly.length>0) el.classList.remove('field-error');
                document.getElementById('activator_first_name').value = '';
                document.getElementById('activator_last_name').value = '';
                document.getElementById('activator_middle_name').value = '';
                document.getElementById('activator_email').value = '';
            }
        }
        
        // Fetch activator data filtered by department/organization
        async function fetchActivatorData(userId) {
            if (!userId) return;
            
            const departmentId = document.getElementById('activate_department').value;
            const organizationId = window.selectedOrganizationId;
            const idInput = document.getElementById('activator_student_id');
            
            console.log('Fetching activator data:', {
                userId: userId,
                departmentId: departmentId,
                organizationId: organizationId
            });
            
            // Remove previous error state
            idInput.classList.remove('field-error');
            
            if (!departmentId) {
                showToast('Please select a department first', 'warning', 'Department Required');
                idInput.classList.add('field-error');
                return;
            }
            
            if (!organizationId) {
                showToast('Please select an organization first', 'warning', 'Organization Required');
                idInput.classList.add('field-error');
                return;
            }
            
            try {
                const response = await fetch(`/api/user-by-id/${encodeURIComponent(userId)}?department_id=${departmentId}&organization_id=${organizationId}`, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                });
                
                const data = await response.json();
                console.log('API Response:', data);
                
                if (data.success && data.user) {
                    // Fill in the activator fields - middle_initial from users.middle_initial (M.I.)
                    const miAct = data.user.middle_initial || (data.user.middle_name ? data.user.middle_name.trim().charAt(0).toUpperCase() : '');
                    document.getElementById('activator_first_name').value = data.user.first_name || '';
                    document.getElementById('activator_last_name').value = data.user.last_name || '';
                    document.getElementById('activator_middle_name').value = miAct;
                    document.getElementById('activator_email').value = data.user.email || '';
                    
                    // Auto-fill Activator Department and populate Course options
                    if (data.user.department_id) {
                        const actDeptSelect = document.getElementById('activator_department');
                        if (actDeptSelect) {
                            actDeptSelect.value = data.user.department_id;
                            updateCourseOptions('activator_course', data.user.department_id);
                        }
                    }
                    
                    // Remove error state (keep normal appearance)
                    idInput.classList.remove('field-error');
                    
                    showToast('User information loaded successfully', 'success', 'User Found');
                } else {
                    // Show specific error message
                    const errorMessages = {
                        'User not found': 'No user found with this ID number',
                        'User does not belong to the selected department': 'This user is not registered in the selected department',
                        'User is not a President or Adviser in this organization': 'This user is not a President or Adviser in the selected organization. Only Presidents and Advisers can activate the system.'
                    };
                    
                    const errorMsg = errorMessages[data.message] || data.message || 'User not found or not authorized';
                    
                    // Add error state (red)
                    idInput.classList.add('field-error');
                    
                    showToast(errorMsg, 'error', 'Validation Failed');
                    
                    // Clear fields
                    document.getElementById('activator_first_name').value = '';
                    document.getElementById('activator_last_name').value = '';
                    document.getElementById('activator_middle_name').value = '';
                    document.getElementById('activator_email').value = '';
                }
            } catch (error) {
                console.error('Error fetching activator data:', error);
                
                // Add error state (red)
                idInput.classList.add('field-error');
                
                showToast('Unable to connect to server. Please try again.', 'error', 'Connection Error');
            }
        }

        // Auto-fetch president data STRICT 10 digits only (00-0000-0000) - deprecated, use Activator
        function autoFetchPresidentData(value) {
            const digitsOnly = value.replace(/\D/g, '');
            console.log('President auto-fetch triggered (strict 10):', { value, digitsOnly, length: digitsOnly.length });
            if (digitsOnly.length === 8) {
                if(typeof showToast==='function') showToast('Activator ID must be 00-0000-0000 (10 digits).', 'error', 'Invalid Activator ID');
                return;
            }
            if (digitsOnly.length === 10) {
                console.log('Fetching activator data for:', value);
                fetchPresidentData(value);
            }
        }

        // Fetch president data by Student ID
        async function fetchPresidentData(studentId) {
            if (!studentId) {
                return;
            }

            console.log('=== FETCH PRESIDENT DATA ===');
            console.log('studentId:', studentId);
            console.log('Fetching from API:', `/api/student/${studentId}`);

            try {
                const response = await fetch(`/api/student/${encodeURIComponent(studentId)}`, {
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                });

                console.log('API Response status:', response.status);
                const data = await response.json();
                console.log('API Response data:', data);

                if (data.success && data.student) {
                    console.log('✅ President found! Filling fields...');
                    
                    // Fill in the activator fields
                    document.getElementById('activator_first_name').value = data.student.first_name || '';
                    document.getElementById('activator_last_name').value = data.student.last_name || '';
                    document.getElementById('activator_middle_name').value = data.student.middle_name || '';
                    document.getElementById('activator_email').value = data.student.email || '';
                    
                    const courseSelect = document.getElementById('activator_course');
                    if (data.student.course) {
                        if (courseSelect.options.length > 1) {
                            courseSelect.value = data.student.course;
                        } else {
                            courseSelect.dataset.pendingValue = data.student.course;
                        }
                    }
                    
                    console.log('✅ Activator fields filled successfully!');
                } else {
                    console.log('❌ Activator not found, clearing fields');
                    // Clear fields if student not found
                    document.getElementById('activator_first_name').value = '';
                    document.getElementById('activator_last_name').value = '';
                    document.getElementById('activator_middle_name').value = '';
                    document.getElementById('activator_email').value = '';
                }
            } catch (error) {
                console.error('❌ Error fetching president data:', error);
            }
        }

        // Original nextAppStep was moved and merged below

            // Generate document upload fields for the application
        function generateDocumentUploads(stepNum, containerId) {
            const container = document.getElementById(containerId);
            
            // Define documents for Apply — single Application letter for recognition/renewal (merged)
            const applyDocuments = [
                { name: 'adviser_commitment', label: 'Organization Adviser Commitment', id: 1 },
                { name: 'application_form', label: 'Application letter for recognition/renewal', id: 2 },
                { name: 'certification_of_officers', label: 'Certification of Officers', id: 3 },
                { name: 'plan_of_activities', label: 'Plan of Activities', id: 4 },
                { name: 'constitution_bylaws', label: 'Constitution and By-laws', id: 5 },
                { name: 'certification_dean', label: 'Certification from Dean', id: 6, optional: true },
                { name: 'financial_report', label: 'Financial Report', id: 7, optional: true }
            ];
            
            const allDocuments = applyDocuments;
            
            // Split documents based on step — 4 + 3 (total 7, after merge)
            let documents = [];
            if (stepNum === 2) {
                // First 4 documents
                documents = allDocuments.slice(0, 4);
            } else if (stepNum === 3) {
                // Next 3 documents
                documents = allDocuments.slice(4, 7);
            }
            
            // Preserve already-selected files when the user goes Back and Next again
            if (container.querySelector('input[type="file"]')) {
                return;
            }

            // Clear existing content
            container.innerHTML = '';

            documents.forEach(doc => {
                const isOptional = !!doc.optional;
                const required = isOptional ? '' : '*';
                const requiredAttr = isOptional ? '' : 'required';
                
                const docDiv = document.createElement('div');
                docDiv.innerHTML = `
                    <label class="block text-sm font-semibold mb-1.5 min-h-[2.5rem] leading-5">${doc.label} ${required}</label>
                    <div class="relative">
                        <input type="file" name="${doc.name}" id="file${doc.id}" ${requiredAttr} accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                        <div onclick="document.getElementById('file${doc.id}').click()" class="border-2 border-dashed border-[#C4973B] rounded-lg p-3 cursor-pointer hover:bg-gray-50 transition">
                            <div class="flex items-center mb-2">
                                <i class="ph ph-cloud-arrow-up text-xl text-[#C4973B] mr-2"></i>
                                <div class="flex-1">
                                    <p class="text-xs font-semibold text-gray-700">Click to upload</p>
                                    <p class="text-xs text-gray-500">PDF, JPG, PNG</p>
                                </div>
                            </div>
                            <div id="preview${doc.id}" class="hidden mt-2">
                                <img id="preview${doc.id}-img" class="w-full h-32 object-cover rounded border border-gray-300">
                            </div>
                        </div>

                    </div>
                `;
                container.appendChild(docDiv);
            });
            
            // Re-attach file preview handlers
            documents.forEach(doc => {
                attachFilePreview(doc.id);
            });
        }
        
        // Attach file preview handler
        function attachFilePreview(fileId) {
            const fileInput = document.getElementById(`file${fileId}`);
            const previewContainer = document.getElementById(`preview${fileId}`);
            const previewImg = document.getElementById(`preview${fileId}-img`);
            
            if (fileInput) {
                if (fileId === 3) attachOfficersPrefillListener(fileInput);
                fileInput.addEventListener('change', async (e) => {
                    if (e.target.files.length > 0) {
                        const file = e.target.files[0];
                        const fileType = file.type;
                        
                        previewContainer.classList.remove('hidden');
                        
                        if (fileType.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = (event) => {
                                previewImg.src = event.target.result;
                            };
                            reader.readAsDataURL(file);
                        } else if (fileType === 'application/pdf') {
                            try {
                                const typedArray = new Uint8Array(await file.arrayBuffer());
                                const pdf = await pdfjsLib.getDocument(typedArray).promise;
                                const page = await pdf.getPage(1);
                                
                                const viewport = page.getViewport({scale: 1});
                                const scale = 300 / viewport.width;
                                const scaledViewport = page.getViewport({scale: scale});
                                
                                const canvas = document.createElement('canvas');
                                const context = canvas.getContext('2d');
                                canvas.height = scaledViewport.height;
                                canvas.width = scaledViewport.width;
                                
                                await page.render({
                                    canvasContext: context,
                                    viewport: scaledViewport
                                }).promise;
                                
                                previewImg.src = canvas.toDataURL();
                            } catch (error) {
                                console.error('PDF rendering error:', error);
                                previewImg.src = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMDAiIGhlaWdodD0iMTUwIiB2aWV3Qm94PSIwIDAgMjAwIDE1MCI+PHJlY3Qgd2lkdGg9IjIwMCIgaGVpZ2h0PSIxNTAiIGZpbGw9IiNmM2Y0ZjYiLz48dGV4dCB4PSIxMDAiIHk9IjYwIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMjQiIGZpbGw9IiNlZjQ0NDQiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtd2VpZ2h0PSJib2xkIj5QREYgRmlsZTwvdGV4dD48dGV4dCB4PSIxMDAiIHk9IjkwIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTIiIGZpbGw9IiM2YjcyODAiIHRleHQtYW5jaG9yPSJtaWRkbGUiPkNsaWNrIHRvIHZpZXc8L3RleHQ+PC9zdmc+';
                            }
                        }
                    }
                });
            }
        }

        // Multiple file upload for List of Members (Step 4) - id-based to avoid duplicate-name removal
        let memberFiles = []; // array of {id, file}
        // Member source for the Step 6 officer ID lookup: uploaded files, or
        // the Mother Org roster when a Sub Org skips the upload.
        let memberSource = { mode: 'upload', departmentId: null };
        
        document.getElementById('listOfMembersInput').addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            files.forEach(file => {
                const fileId = `member-file-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
                memberFiles.push({id: fileId, file: file});
                addMemberFilePreview(file, fileId);
            });
            e.target.value = ''; // Reset input to allow same file again
            // Member files changed — drop the cached ID lookup so Step 6 re-runs it.
            if (typeof officerIdLookupState !== 'undefined') {
                officerIdLookupState.cacheKey = null;
                officerIdLookupState.results = {};
            }
            // clear validation error if now has files
            if (memberFiles.length > 0) {
                const dashedBox = document.querySelector('#memberUploadSection .border-dashed');
                if (dashedBox) dashedBox.classList.remove('border-red-500', 'bg-red-50');
            }
        });
        
        function addMemberFilePreview(file, fileId) {
            const previewContainer = document.getElementById('memberFilesPreview');
            // allow call with pre-generated id, fallback for legacy calls
            if (!fileId) fileId = `member-file-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
            
            const fileDiv = document.createElement('div');
            fileDiv.className = 'flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200';
            fileDiv.id = fileId;
            
            fileDiv.innerHTML = `
                <div class="flex items-center flex-1">
                    <i class="ph ph-file-${file.type === 'application/pdf' ? 'pdf' : 'image'} text-2xl text-[#C4973B] mr-3"></i>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-700">${file.name}</p>
                        <p class="text-xs text-gray-500">${(file.size / 1024).toFixed(2)} KB</p>
                    </div>
                </div>
                <button type="button" onclick="removeMemberFile('${fileId}')" class="text-red-500 hover:text-red-700">
                    <i class="ph ph-x"></i>
                </button>
            `;
            
            previewContainer.appendChild(fileDiv);
        }
        
        function removeMemberFile(fileId) {
            // Remove from array by unique id (fixes duplicate-name bug)
            memberFiles = memberFiles.filter(entry => entry.id !== fileId);
            // Remove from DOM
            document.getElementById(fileId)?.remove();
            // Member files changed — drop the cached ID lookup so Step 6 re-runs it.
            if (typeof officerIdLookupState !== 'undefined') {
                officerIdLookupState.cacheKey = null;
                officerIdLookupState.results = {};
            }
        }

        // Additional Documents (Other Files) - optional multi-file like members (id-based)
        let otherFiles = []; // array of {id, file}
        document.getElementById('otherFilesInput')?.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            files.forEach(file => {
                const fileId = `other-file-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
                otherFiles.push({id: fileId, file: file});
                addOtherFilePreview(file, fileId);
            });
            e.target.value = '';
        });
        function addOtherFilePreview(file, fileId) {
            const previewContainer = document.getElementById('otherFilesPreview');
            if (!previewContainer) return;
            if (!fileId) fileId = `other-file-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
            const fileDiv = document.createElement('div');
            fileDiv.className = 'flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200';
            fileDiv.id = fileId;
            fileDiv.innerHTML = `
                <div class="flex items-center flex-1">
                    <i class="ph ph-file-${file.type === 'application/pdf' ? 'pdf' : 'image'} text-2xl text-[#C4973B] mr-3"></i>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-700">${file.name}</p>
                        <p class="text-xs text-gray-500">${(file.size / 1024).toFixed(2)} KB</p>
                    </div>
                </div>
                <button type="button" onclick="removeOtherFile('${fileId}')" class="text-red-500 hover:text-red-700">
                    <i class="ph ph-x"></i>
                </button>
            `;
            previewContainer.appendChild(fileDiv);
        }
        function removeOtherFile(fileId) {
            otherFiles = otherFiles.filter(entry => entry.id !== fileId);
            document.getElementById(fileId)?.remove();
        }

        async function nextAppStep(step, force = false) {
            const currentStep = parseInt(document.getElementById('currentStepApp').value);
            const currentStepEl = document.getElementById(`app-step${currentStep}`);
            
            // Hard block: Sub Org without Mother Org cannot proceed from Step 1
            if (!force && currentStep === 1 && step === 2) {
                const orgTypeCheck = document.getElementById('org_type_apply')?.value;
                const deptIdCheck = document.getElementById('department_apply')?.value;
                if (orgTypeCheck === 'Sub Org' && deptIdCheck && deptIdCheck !== '11') {
                    try {
                        const res = await fetch(`/apply-org/check-mother-org/${deptIdCheck}`);
                        const data = await res.json();
                        if (!data.has_mother_org) {
                            const deptEl = document.getElementById('department_apply');
                            if (deptEl) {
                                deptEl.classList.add('border-red-500', 'bg-red-50');
                                deptEl.addEventListener('change', function handler(){ deptEl.classList.remove('border-red-500','bg-red-50'); deptEl.removeEventListener('change', handler); }, {once:true});
                            }
                            if (typeof showToast === 'function') showToast('Cannot create Sub Org without an approved Mother Org in this department. Please create Mother Org first.', 'error', 'Blocked');
                            else alert('Cannot create Sub Org without an approved Mother Org in this department.');
                            return;
                        }
                    } catch (e) {
                        console.error('Mother Org check failed', e);
                        // On check failure, allow to proceed (backend will also validate)
                    }
                }
            }
            
            if (!force) {
                // Basic validation for required fields
                let isValid = true;
                let customStep4Error = false;
                let requiredInputs = currentStepEl.querySelectorAll('input[required], select[required]');

                // Step 4: List of Members uses hidden input that is cleared after push to memberFiles array
                // So generic `input.value` check always fails. Handle via memberFiles.length instead.
                if (currentStep === 4) {
                    const motherNotice = document.getElementById('motherOrgExistsNotice');
                    const isMotherSkipped = motherNotice && !motherNotice.classList.contains('hidden');
                    const orgTypeStep4 = document.getElementById('org_type_apply')?.value;
                    const deptValStep4 = document.getElementById('department_apply')?.value;
                    const isNonAcademicStep4 = orgTypeStep4 === 'Non-Academic' || deptValStep4 === '11';
                    // Exclude hidden multi-file inputs managed via JS arrays
                    requiredInputs = Array.from(requiredInputs).filter(el => el.id !== 'listOfMembersInput' && el.id !== 'otherFilesInput');
                    const dashedBox = document.querySelector('#memberUploadSection .border-dashed');
                    if (!isMotherSkipped && !isNonAcademicStep4 && memberFiles.length === 0) {
                        customStep4Error = true;
                        isValid = false;
                        if (dashedBox) {
                            dashedBox.classList.add('border-red-500', 'bg-red-50');
                            dashedBox.classList.remove('border-[#C4973B]');
                        }
                    } else {
                        if (dashedBox) {
                            dashedBox.classList.remove('border-red-500', 'bg-red-50');
                            dashedBox.classList.add('border-[#C4973B]');
                        }
                    }
                } else {
                    requiredInputs = Array.from(requiredInputs);
                }
                
                requiredInputs.forEach(input => {
                    if (!input.value) {
                        isValid = false;
                        input.classList.remove('border-gray-300', 'focus:border-[#C4973B]');
                        input.classList.add('border-red-500', 'focus:border-red-500', 'bg-red-50');
                    } else {
                        input.classList.add('border-gray-300', 'focus:border-[#C4973B]');
                        input.classList.remove('border-red-500', 'focus:border-red-500', 'bg-red-50');
                    }
                });
                
                // Add input listeners to clear errors
                requiredInputs.forEach(input => {
                    input.addEventListener('input', function() {
                        if (this.value) {
                            this.classList.add('border-gray-300', 'focus:border-[#C4973B]');
                            this.classList.remove('border-red-500', 'focus:border-red-500', 'bg-red-50');
                        }
                    }, { once: true });
                });
                
                if (!isValid) {
                    if (typeof showToast === 'function') {
                        if (customStep4Error) {
                            showToast('Please upload at least one List of Members file before proceeding.', 'error');
                        } else {
                            showToast('Please fill all required fields before proceeding.', 'error');
                        }
                    }
                    return;
                }
            }

            const nextStepEl = document.getElementById(`app-step${step}`);
            currentStepEl.classList.add('hidden');
            nextStepEl.classList.remove('hidden');
            document.getElementById('currentStepApp').value = step;
            updateAppProgress(step);

            // Generate documents for step 2 (first 4 docs)
            if (step === 2) {
                generateDocumentUploads(2, 'docs-step2-container');
            }
            
            // Generate documents for step 3 (next 4 docs)
            if (step === 3) {
                generateDocumentUploads(3, 'docs-step3-container');
                // Trigger prefill extraction from Certification of Officers PDF
                triggerOfficersPrefill();
            }

            // Generate officers for step 6, preserving manual values before the dynamic form is rebuilt.
            if (step === 6) {
                rememberManualPrefillValues();
                generateOfficerForms('appOfficersContainer', 'officers');
                hydrateManualPrefillValues();
                bindPrefillDestinationTracking();
                applyVisibleOfficersPrefill();
            }

            // Step 5 only hydrates compatible, current-file Dean values.
            if (step === 5) {
                bindPrefillDestinationTracking();
                applyDeanPrefill();
                fetchApplyAssignedDean();
            }

            // Step 4: Check if Sub Org and Mother Org already submitted members
            if (step === 4) {
                const orgType = document.getElementById('org_type_apply').value;
                const departmentId = document.getElementById('department_apply').value;

                const motherOrgNotice = document.getElementById('motherOrgExistsNotice');
                const memberUploadSection = document.getElementById('memberUploadSection');
                const listInput = document.getElementById('listOfMembersInput');
                const step4NextBtn = document.getElementById('appStep4NextBtn');

                if (orgType === 'Sub Org' && departmentId) {
                    // Disable Next while checking to avoid race with validation
                    if (step4NextBtn) { step4NextBtn.disabled = true; step4NextBtn.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i> Checking...'; }
                    fetch(`/apply-org/check-mother-org/${departmentId}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data.has_mother_org) {
                                // Hide upload, show friendly notice
                                motherOrgNotice.classList.remove('hidden');
                                memberUploadSection.classList.add('hidden');
                                // Clear any file selection since it's not needed
                                listInput.value = '';
                                listInput.removeAttribute('required');
                                listInput.removeAttribute('name');
                                // Clear JS array and preview since upload not needed
                                memberFiles = [];
                                const preview = document.getElementById('memberFilesPreview');
                                if (preview) preview.innerHTML = '';
                                const dashedBox = document.querySelector('#memberUploadSection .border-dashed');
                                if (dashedBox) { dashedBox.classList.remove('border-red-500','bg-red-50'); dashedBox.classList.add('border-[#C4973B]'); }
                                // Officer ID lookup will use the Mother Org roster instead of uploads.
                                memberSource = { mode: 'mother_org', departmentId: departmentId };
                                if (typeof officerIdLookupState !== 'undefined') {
                                    officerIdLookupState.cacheKey = null;
                                    officerIdLookupState.results = {};
                                }
                            } else {
                                motherOrgNotice.classList.add('hidden');
                                memberUploadSection.classList.remove('hidden');
                                listInput.setAttribute('required', '');
                                listInput.setAttribute('name', 'list_of_members[]');
                                memberSource = { mode: 'upload', departmentId: null };
                            }
                        })
                        .catch(() => {
                            // On error, show upload normally
                            motherOrgNotice.classList.add('hidden');
                            memberUploadSection.classList.remove('hidden');
                            memberSource = { mode: 'upload', departmentId: null };
                        })
                        .finally(() => {
                            if (step4NextBtn) { step4NextBtn.disabled = false; step4NextBtn.innerHTML = 'Next <i class="ph ph-arrow-right ml-1"></i>'; }
                        });
                } else {
                    // Not a Sub Org, always show upload
                    motherOrgNotice.classList.add('hidden');
                    memberUploadSection.classList.remove('hidden');
                    listInput.setAttribute('required', '');
                    listInput.setAttribute('name', 'list_of_members[]');
                    memberSource = { mode: 'upload', departmentId: null };
                    if (step4NextBtn) { step4NextBtn.disabled = false; step4NextBtn.innerHTML = 'Next <i class="ph ph-arrow-right ml-1"></i>'; }
                }
            }
        }


        function prevAppStep(step) {
            const currentStep = parseInt(document.getElementById('currentStepApp').value);
            const currentStepEl = document.getElementById(`app-step${currentStep}`);
            const prevStepEl = document.getElementById(`app-step${step}`);

            currentStepEl.classList.add('hidden');
            prevStepEl.classList.remove('hidden');
            document.getElementById('currentStepApp').value = step;
            updateAppProgress(step);
        }

        function updateAppProgress(step) {
            const step1 = document.getElementById('app-step1-circle');
            const step2 = document.getElementById('app-step2-circle');
            const step3 = document.getElementById('app-step3-circle');
            const step4 = document.getElementById('app-step4-circle');
            const step5 = document.getElementById('app-step5-circle');
            const step6 = document.getElementById('app-step6-circle');
            const progress1 = document.getElementById('app-progress1');
            const progress2 = document.getElementById('app-progress2');
            const progress3 = document.getElementById('app-progress3');
            const progress4 = document.getElementById('app-progress4');
            const progress5 = document.getElementById('app-progress5');

            // Step 2
            if (step >= 2) {
                step2.classList.remove('bg-gray-300', 'text-gray-600');
                step2.classList.add('bg-[#C4973B]', 'text-white');
                progress1.classList.remove('bg-gray-300');
                progress1.classList.add('bg-[#C4973B]');
            } else {
                step2.classList.add('bg-gray-300', 'text-gray-600');
                step2.classList.remove('bg-[#C4973B]', 'text-white');
                progress1.classList.add('bg-gray-300');
                progress1.classList.remove('bg-[#C4973B]');
            }

            // Step 3
            if (step >= 3) {
                step3.classList.remove('bg-gray-300', 'text-gray-600');
                step3.classList.add('bg-[#C4973B]', 'text-white');
                progress2.classList.remove('bg-gray-300');
                progress2.classList.add('bg-[#C4973B]');
            } else {
                step3.classList.add('bg-gray-300', 'text-gray-600');
                step3.classList.remove('bg-[#C4973B]', 'text-white');
                progress2.classList.add('bg-gray-300');
                progress2.classList.remove('bg-[#C4973B]');
            }

            // Step 4
            if (step >= 4) {
                step4.classList.remove('bg-gray-300', 'text-gray-600');
                step4.classList.add('bg-[#C4973B]', 'text-white');
                progress3.classList.remove('bg-gray-300');
                progress3.classList.add('bg-[#C4973B]');
            } else {
                step4.classList.add('bg-gray-300', 'text-gray-600');
                step4.classList.remove('bg-[#C4973B]', 'text-white');
                progress3.classList.add('bg-gray-300');
                progress3.classList.remove('bg-[#C4973B]');
            }

            // Step 5 (Dean)
            if (step >= 5) {
                step5.classList.remove('bg-gray-300', 'text-gray-600');
                step5.classList.add('bg-[#C4973B]', 'text-white');
                progress4.classList.remove('bg-gray-300');
                progress4.classList.add('bg-[#C4973B]');
            } else {
                step5.classList.add('bg-gray-300', 'text-gray-600');
                step5.classList.remove('bg-[#C4973B]', 'text-white');
                progress4.classList.add('bg-gray-300');
                progress4.classList.remove('bg-[#C4973B]');
            }

            // Step 6 (Officers)
            if (step >= 6) {
                step6.classList.remove('bg-gray-300', 'text-gray-600');
                step6.classList.add('bg-[#C4973B]', 'text-white');
                progress5.classList.remove('bg-gray-300');
                progress5.classList.add('bg-[#C4973B]');
            } else {
                step6.classList.add('bg-gray-300', 'text-gray-600');
                step6.classList.remove('bg-[#C4973B]', 'text-white');
                progress5.classList.add('bg-gray-300');
                progress5.classList.remove('bg-[#C4973B]');
            }
        }

        // Form Submissions
        document.getElementById('requestForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('reqSubmitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i> Submitting...';

            const formData = new FormData(e.target);
            try {
                const response = await fetch('{{ route("system-request.submit") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();
                if (data.success) {
                    showModal('success', 'System Activation Initiated!', data.message, () => {
                        if (typeof clearSavedData === 'function') clearSavedData();
                        window.location.href = '{{ route("org.login") }}';
                    });
                } else {
                    showModal('error', 'Error Occurred', data.message || 'An error occurred while processing your request.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="ph ph-check mr-1"></i> Create a Platform';
                }
            } catch (error) {
                console.error('Submission Error:', error);
                showModal('error', 'Error Occurred', 'An unexpected error occurred. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="ph ph-check mr-1"></i> Create a Platform';
            }
        });

        document.getElementById('applyForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            console.log('applyForm submit fired', { memberFilesCount: memberFiles.length, otherFilesCount: otherFiles.length });
            const submitBtn = document.getElementById('appSubmitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i> Submitting...';

            // --- INSTANT REQUIRED FIELDS CHECK (before async R2) ---
            // Show error immediately without waiting for network/R2, per user request
            let instantValid = true;
            let instantMsg = '';
            // Check all required inputs in the form (excluding hidden list_of_members handled via array)
            const allRequired = document.querySelectorAll('#applyForm input[required], #applyForm select[required]');
            allRequired.forEach(input => {
                if (input.id === 'listOfMembersInput' || input.id === 'otherFilesInput') return;
                const isFile = input.type === 'file';
                const hasValue = isFile ? (input.files && input.files.length > 0) : !!input.value.trim();
                if (!hasValue) {
                    instantValid = false;
                    input.classList.add('border-red-500', 'bg-red-50');
                    // Also highlight dashed box for document file inputs
                    const dashed = input.closest('.border-dashed');
                    if (dashed) dashed.classList.add('border-red-500');
                } else {
                    input.classList.remove('border-red-500', 'bg-red-50');
                }
            });
            // List of Members check via memberFiles array (handled like Next button) - Non-Academic is optional
            const motherNotice = document.getElementById('motherOrgExistsNotice');
            const isMotherSkipped = motherNotice && !motherNotice.classList.contains('hidden');
            if (!isMotherSkipped && memberFiles.length === 0) {
                const orgType = document.getElementById('org_type_apply')?.value;
                if (orgType === 'Mother Org') {
                    instantValid = false;
                    instantMsg = 'Please upload at least one List of Members file.';
                    const dashedBox = document.querySelector('#memberUploadSection .border-dashed');
                    if (dashedBox) dashedBox.classList.add('border-red-500', 'bg-red-50');
                }
            }
            if (!instantValid) {
                if (typeof showToast === 'function') showToast(instantMsg || 'Please fill all required fields before submitting. Check highlighted fields.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="ph ph-paper-plane mr-1"></i> Submit';
                return;
            }

            // --- CLIENT-SIDE DUPLICATE CHECK ---
            const studentIds = [];
            const adviserIds = [];
            const emails = [];
            
            let duplicateFound = false;
            let duplicateMsg = '';
            
            // 1. Collect and check Requester Email
            const reqEmail = document.getElementById('req_email_apply')?.value?.trim()?.toLowerCase();
            if (reqEmail) {
                emails.push({ val: reqEmail, source: 'Requester Email' });
            }
            
            // Dean is OSAS-assigned and autofilled server-side — no Dean fields in the form.
            // 3. Collect and check Officers
            const officerDivs = document.querySelectorAll('#appOfficersContainer > div');
            officerDivs.forEach(div => {
                const positionHeader = div.querySelector('h4')?.textContent || 'Officer';
                
                // Student ID (if present)
                const studentIdInput = div.querySelector('input[name*="[student_id]"]');
                if (studentIdInput && studentIdInput.value.trim()) {
                    const val = studentIdInput.value.trim();
                    studentIds.push({ val: val, source: `${positionHeader} Student ID` });
                }
                
                // Adviser ID Number (if present)
                const idNumberInput = div.querySelector('input[name*="[id_number]"]');
                if (idNumberInput && idNumberInput.value.trim()) {
                    const val = idNumberInput.value.trim();
                    adviserIds.push({ val: val, source: `${positionHeader} ID Number` });
                }
                
                // Email
                const emailInput = div.querySelector('input[name*="[email]"]');
                if (emailInput && emailInput.value.trim()) {
                    const val = emailInput.value.trim().toLowerCase();
                    const reqPosition = (typeof detectRequesterPosition === 'function') ? detectRequesterPosition() : '';
                    
                    // Bypass duplicate check for matching requester officer slot
                    const isRequesterPresidentSlot = (positionHeader.toLowerCase().includes('president') && !positionHeader.toLowerCase().includes('vice') && reqPosition === 'Organization President');
                    const isRequesterAdviserSlot = ((positionHeader.toLowerCase().includes('senior adviser') || positionHeader.toLowerCase().includes('junior adviser') || positionHeader.toLowerCase().includes('adviser')) && reqPosition === 'Organization Adviser');
                    const isLegacySeniorSlot = (positionHeader.toLowerCase().includes('senior adviser') && reqPosition === 'Organization Senior Adviser');
                    const isLegacyJuniorSlot = (positionHeader.toLowerCase().includes('junior adviser') && reqPosition === 'Organization Junior Adviser');
                    
                    if (val === reqEmail && (isRequesterPresidentSlot || isRequesterAdviserSlot || isLegacySeniorSlot || isLegacyJuniorSlot)) {
                        console.log(`Bypassing duplicate email check for matching requester officer slot: ${positionHeader}`);
                    } else {
                        emails.push({ val: val, source: `${positionHeader} Email` });
                    }
                }
            });
            
            // Helper to check duplicates in an array
            function findDuplicate(arr) {
                const seen = {};
                for (let i = 0; i < arr.length; i++) {
                    const item = arr[i];
                    if (seen[item.val]) {
                        return { item1: seen[item.val], item2: item };
                    }
                    seen[item.val] = item;
                }
                return null;
            }
            
            const dupStudent = findDuplicate(studentIds);
            if (dupStudent) {
                duplicateFound = true;
                duplicateMsg = `Duplicate Student ID detected: "${dupStudent.item1.val}" is entered for both ${dupStudent.item1.source} and ${dupStudent.item2.source}.`;
            }
            
            if (!duplicateFound) {
                const dupAdviser = findDuplicate(adviserIds);
                if (dupAdviser) {
                    duplicateFound = true;
                    duplicateMsg = `Duplicate ID Number detected: "${dupAdviser.item1.val}" is entered for both ${dupAdviser.item1.source} and ${dupAdviser.item2.source}.`;
                }
            }
            
            // Email duplicate is intentionally not blocked when student_id is the dedupe key — email is mutable via student_id.
            // Different student_ids sharing same email will be caught server-side as student_id-aware check; same student_id email change is allowed.
            
            if (duplicateFound) {
                if (typeof showToast === 'function') showToast(duplicateMsg, 'error', 'Duplicate Entry Detected');
                else alert(duplicateMsg);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="ph ph-paper-plane mr-1"></i> Submit';
                return;
            }

            const formData = new FormData(e.target);
            // Avoid ghost empty file part from hidden inputs (like renew does)
            formData.delete('list_of_members[]');
            formData.delete('other_files[]');
            // Re-append real files from JS arrays (id-based storage)
            memberFiles.forEach((entry) => {
                const file = entry && entry.file ? entry.file : entry;
                if (file) formData.append(`list_of_members[]`, file);
            });
            otherFiles.forEach((entry) => {
                const file = entry && entry.file ? entry.file : entry;
                if (file) formData.append(`other_files[]`, file);
            });
            console.log('formData prepared', { listCount: memberFiles.length, otherCount: otherFiles.length });
            
            try {
                const response = await fetch('{{ route("system-request.submit") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                });
                
                const responseText = await response.text();
                let data;
                
                try {
                    // Bulletproof Regex to find and extract only the JSON object, ignoring any warnings or trailing noise
                    const jsonMatch = responseText.match(/\{[\s\S]*\}/);
                    if (jsonMatch) {
                        data = JSON.parse(jsonMatch[0]);
                    } else {
                        throw new SyntaxError("No JSON found");
                    }
                } catch (parseError) {
                    console.error('Non-JSON Response:', responseText);
                    let cleanWarning = responseText.replace(/<[^>]*>/g, ' ').trim().substring(0, 800);
                    if (typeof showToast === 'function') showToast('Server warning: ' + cleanWarning, 'error', 'Server Warning');
                    else showModal('error', 'Server Warning/Notice Detected', 'The server processed the request but returned a PHP warning:\n\n' + cleanWarning);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="ph ph-paper-plane mr-1"></i> Submit';
                    return;
                }

                if (data.success) {
                    showModal('success', 'Successfully Submitted!', 'Your application has been successfully submitted. Please wait for OSAS approval through email. You will be notified once reviewed.', () => {
                        if (typeof clearSavedData === 'function') clearSavedData();
                        window.location.href = '{{ route("org.login") }}';
                    });
                } else {
                    // Handle validation errors — show via toast (existing style) + highlight fields
                    let errorMsg = data.message || 'An error occurred while submitting your application.';
                    if (data.errors) {
                        const allMsgs = Object.entries(data.errors).map(([k, v]) => `${k}: ${Array.isArray(v) ? v[0] : v}`).join('\n');
                        errorMsg = allMsgs || errorMsg;
                        // Highlight fields with errors and toast each
                        Object.entries(data.errors).forEach(([key, msgs]) => {
                            const msg = Array.isArray(msgs) ? msgs[0] : msgs;
                            if (typeof showToast === 'function') showToast(msg, 'error', key);
                            // Try to find input by name containing the key
                            const selector = `[name="${key}"], [name="${key.replace('.', '[')}"]`;
                            document.querySelectorAll(selector).forEach(el => {
                                el.classList.add('border-red-500', 'bg-red-50');
                                el.addEventListener('input', function handler() {
                                    el.classList.remove('border-red-500', 'bg-red-50');
                                    el.removeEventListener('input', handler);
                                });
                            });
                            // Fallback: try last part after dot
                            const lastPart = key.split('.').pop();
                            if (lastPart) {
                                document.querySelectorAll(`[name*="${lastPart}"]`).forEach(el => {
                                    if (!el.classList.contains('border-red-500')) {
                                        el.classList.add('border-red-500');
                                        el.addEventListener('input', () => el.classList.remove('border-red-500'), { once: true });
                                    }
                                });
                            }
                        });
                    }
                    if (typeof showToast === 'function' && !data.errors) showToast(errorMsg, 'error', 'Submission Failed');
                    else if (!data.errors) showModal('error', 'Submission Failed', errorMsg);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="ph ph-paper-plane mr-1"></i> Submit';
                }
            } catch (error) {
                console.error('Submission Error:', error);
                const is419 = error.message && error.message.includes('419');
                const msg = is419 ? 'Session expired, please refresh the page and try again.' : (error.message || 'An unexpected error occurred. Please try again.');
                if (typeof showToast === 'function') showToast(msg, 'error', is419 ? 'Session Expired' : 'Error Occurred');
                else showModal('error', 'Error Occurred', 'An unexpected error occurred: ' + error.message + '\n\nStack: ' + error.stack);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="ph ph-paper-plane mr-1"></i> Submit';
            }
        });

        // Split Button Handlers
        let hideTimeout;
        
        function showSplitButton(button) {
            // Clear any pending hide timeout
            if (hideTimeout) {
                clearTimeout(hideTimeout);
            }
            
            const defaultBtn = button.querySelector('.split-button-default');
            const splitBtn = button.querySelector('.split-button-split');
            defaultBtn.style.opacity = '0';
            splitBtn.style.opacity = '1';
        }

        function hideSplitButton(button) {
            // Add 1 second delay before hiding
            hideTimeout = setTimeout(() => {
                const defaultBtn = button.querySelector('.split-button-default');
                const splitBtn = button.querySelector('.split-button-split');
                defaultBtn.style.opacity = '1';
                splitBtn.style.opacity = '0';
            }, 1000);
        }

        // Select Request - Card slides RIGHT, Form appears on LEFT
        function selectRequest() {
            console.log('Create a Platform selected');
            
            const slideContainer = document.getElementById('slideContainer');
            const requestFormPanel = document.getElementById('requestFormPanel');
            const reqButton = document.getElementById('requestButton');
            const reqBtnDefault = document.getElementById('reqBtnDefault');
            const reqBtnBack = document.getElementById('reqBtnBack');
            
            // Transform button to "Back to Main Menu"
            reqBtnDefault.classList.add('hidden');
            reqBtnBack.classList.remove('hidden');
            reqButton.onclick = backToChoose;
            
            // Slide the cards to the right
            slideContainer.classList.add('slide-right');
            
            // Show the form panel on the left after a brief delay
            setTimeout(() => {
                requestFormPanel.classList.add('show');
            }, 400);
        }

        // Select Apply (New Organization) - Card slides LEFT, Form appears on RIGHT
        function selectApply(event) {
            if (event) event.stopPropagation();
            console.log('Apply selected');
            
            const slideContainer = document.getElementById('slideContainer');
            const applyFormPanel = document.getElementById('applyFormPanel');
            const btnDefault = document.getElementById('applyBtnDefault');
            const btnBack = document.getElementById('applyBtnBack');
            // Set application type to 'apply'
            document.getElementById('applicationType').value = 'apply';
            
            // Update form title
            const formTitle = applyFormPanel.querySelector('h2');
            formTitle.textContent = 'Apply Organization';
            
            // Show Apply fields
            document.getElementById('applyOrgInfo').classList.remove('hidden');
            document.getElementById('applyRequesterInfo').classList.remove('hidden');
            
            // Transform button to "Back to Main Menu"
            btnDefault.classList.add('hidden');
            btnBack.classList.remove('hidden');
            
            // Make button clickable to go back
            document.getElementById('applyButton').onclick = backToChoose;
            
            // Slide the cards to the left
            slideContainer.classList.add('slide-left');
            
            // Show the form panel on the right after a brief delay
            setTimeout(() => {
                applyFormPanel.classList.add('show');
            }, 400);
        }

        // Back to Choose
        function backToChoose(e) {
            if (e) e.preventDefault();
            console.log('Back to choose');
            
            const slideContainer = document.getElementById('slideContainer');
            const requestFormPanel = document.getElementById('requestFormPanel');
            const applyFormPanel = document.getElementById('applyFormPanel');
            
            // Restore Request button
            const reqButton = document.getElementById('requestButton');
            const reqBtnDefault = document.getElementById('reqBtnDefault');
            const reqBtnBack = document.getElementById('reqBtnBack');
            if (reqBtnDefault && reqBtnBack) {
                reqBtnDefault.classList.remove('hidden');
                reqBtnBack.classList.add('hidden');
                reqButton.onclick = selectRequest;
            }
            
            // Restore Apply button
            const btnDefault = document.getElementById('applyBtnDefault');
            const btnBack = document.getElementById('applyBtnBack');
            btnDefault.classList.remove('hidden');
            btnBack.classList.add('hidden');
            document.getElementById('applyButton').onclick = selectApply;
            
            // Disable hover temporarily to prevent glitch
            // Re-enable hover after 1 second
            setTimeout(() => {
                }, 1000);
            
            // Hide form panels
            requestFormPanel.classList.remove('show');
            applyFormPanel.classList.remove('show');
            
            // Reset Create a Platform form
            restoreFormData();
            
            // Reset slide position after forms fade out
            setTimeout(() => {
                slideContainer.classList.remove('slide-right', 'slide-left');
                
                // Reset Apply form to step 1
                document.getElementById('currentStepApp').value = 1;
                document.getElementById('app-step1').classList.remove('hidden');
                document.getElementById('app-step2').classList.add('hidden');
                document.getElementById('app-step3').classList.add('hidden');
                document.getElementById('app-step4').classList.add('hidden');
                document.getElementById('app-step5').classList.add('hidden');
                updateAppProgress(1);
                
                // Clear member files and other files (id-based arrays)
                memberFiles = [];
                otherFiles = [];
                document.getElementById('memberFilesPreview').innerHTML = '';
                const otherPreview = document.getElementById('otherFilesPreview');
                if (otherPreview) otherPreview.innerHTML = '';
                
                // Clear localStorage
                localStorage.removeItem('uniorg_system_request_data');
            }, 400);
        }

        // --- AUTO-SAVE FUNCTIONALITY (DISABLED AS REQUESTED) ---
        
        function saveFormData() {
            // Disabled
        }

        function restoreFormData() {
            // Reset all form fields
            document.getElementById('activate_department').value = '';
            document.getElementById('activate_org_name').value = '';
            document.getElementById('activate_org_name').disabled = true;
            document.getElementById('activate_org_acronym').value = '';
            document.getElementById('activate_org_type').value = '';
            document.getElementById('activator_student_id').value = '';
            document.getElementById('activator_first_name').value = '';
            document.getElementById('activator_last_name').value = '';
            document.getElementById('activator_middle_name').value = '';
            document.getElementById('activator_email').value = '';
            
            // Reset organization select options
            document.getElementById('activate_org_name').innerHTML = '<option value="">Select Department First</option>';
            
            // Clear any error styling
            document.getElementById('activator_student_id').classList.remove('field-error');
            
            // Reset global variable
            window.selectedOrganizationId = null;
            
            console.log('✅ Create a Platform form reset');
        }

        // Attach auto-save listeners
        document.addEventListener('DOMContentLoaded', () => {
            // Reset Create a Platform form on page load
            restoreFormData();
            
            restoreFormData();
            
            const inputs = document.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('input', saveFormData);
                input.addEventListener('change', saveFormData);
            });

            // Disable department field dynamically if Non-Academic type is selected
            const orgTypeApply = document.getElementById('org_type_apply');
            const departmentApply = document.getElementById('department_apply');

            // OSAS-assigned Dean display (Apply Step 5): read-only autofill.
            // Display-only — submit never trusts these fields and always
            // resolves the Dean server-side from the department.
            function applyAssignedDeanDeptId() {
                const orgType = document.getElementById('org_type_apply')?.value || '';
                if (orgType === 'Non-Academic') return '11';
                return document.getElementById('department_apply')?.value || '';
            }
            function setApplyDeanFields(data) {
                const map = {
                    apply_dean_id_number: data?.id_number || '',
                    apply_dean_email: data?.email || '',
                    apply_dean_first_name: data?.first_name || '',
                    apply_dean_middle_initial: data?.middle_initial || '',
                    apply_dean_last_name: data?.last_name || '',
                    apply_dean_suffix: data?.suffix || '',
                };
                Object.entries(map).forEach(([id, val]) => {
                    const el = document.getElementById(id);
                    if (el) el.value = val || '';
                });
            }
            function fetchApplyAssignedDean() {
                const deptId = applyAssignedDeanDeptId();
                const statusEl = document.getElementById('apply_dean_status');
                const hintEl = document.getElementById('apply_dean_hint');
                if (!deptId) {
                    setApplyDeanFields(null);
                    if (statusEl) statusEl.innerHTML = '<i class="ph ph-info mr-1"></i> Select your college/department first';
                    return;
                }
                if (statusEl) statusEl.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i> Loading assigned Dean...';
                fetch(`/apply-org/assigned-dean/${deptId}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data && data.has_dean) {
                            setApplyDeanFields(data);
                            if (statusEl) statusEl.innerHTML = '<i class="ph ph-check-circle mr-1"></i> Auto-assigned by OSAS';
                            if (hintEl) hintEl.textContent = 'The assigned Dean for your selected college appears above automatically. These details are read-only.';
                        } else {
                            setApplyDeanFields(null);
                            if (statusEl) statusEl.innerHTML = '<i class="ph ph-warning mr-1"></i> No Dean assigned for this college yet';
                            if (hintEl) hintEl.textContent = 'Please contact OSAS to set the Dean for this college before submitting.';
                        }
                    })
                    .catch(() => {
                        setApplyDeanFields(null);
                        if (statusEl) statusEl.innerHTML = '<i class="ph ph-warning mr-1"></i> Could not load the assigned Dean — it will still be attached automatically upon submission.';
                    });
            }
            window.fetchApplyAssignedDean = fetchApplyAssignedDean;
            if (orgTypeApply) orgTypeApply.addEventListener('change', fetchApplyAssignedDean);
            if (departmentApply) departmentApply.addEventListener('change', fetchApplyAssignedDean);
            
            // Store all original department options to dynamically filter them
            const originalDeptOptions = departmentApply ? Array.from(departmentApply.options) : [];

            function handleOrgTypeChange() {
                if (orgTypeApply && departmentApply) {
                    const selectedType = orgTypeApply.value;
                    const currentValue = departmentApply.value;
                    
                    // Reconstruct department options list based on chosen organization type
                    departmentApply.innerHTML = '';
                    originalDeptOptions.forEach(opt => {
                        // If type is Mother Org or Sub Org, don't show the Non-Academic department (ID 11)
                        if ((selectedType === 'Mother Org' || selectedType === 'Sub Org') && opt.value === '11') {
                            return;
                        }
                        departmentApply.appendChild(opt.cloneNode(true));
                    });

                    if (selectedType === 'Non-Academic') {
                        departmentApply.value = '';
                        departmentApply.disabled = true;
                        departmentApply.required = false;
                        departmentApply.classList.add('bg-gray-100', 'cursor-not-allowed');
                        departmentApply.classList.remove('bg-white');
                    } else {
                        departmentApply.disabled = false;
                        departmentApply.required = true;
                        departmentApply.classList.remove('bg-gray-100', 'cursor-not-allowed');
                        departmentApply.classList.add('bg-white');
                        
                        // Restore previous selection if it is still a valid option
                        if (currentValue && currentValue !== '11') {
                            departmentApply.value = currentValue;
                        } else {
                            departmentApply.value = '';
                        }
                    }
                }
            }

            if (orgTypeApply) {
                orgTypeApply.addEventListener('change', handleOrgTypeChange);
                // Trigger once initially after restoring form data
                handleOrgTypeChange();
            }
        });

        // Clear storage on successful submit
        function clearSavedData() {
            localStorage.removeItem('uniorg_system_request_data');
        }

        // Setup Organization auto-fill for Activate System
        const approvedOrganizations = {!! json_encode($organizations ?? []) !!};
        const activateDeptSelect = document.getElementById('activate_department');
        const activateOrgNameSelect = document.getElementById('activate_org_name');
        const activateOrgAcronymInput = document.getElementById('activate_org_acronym');
        const activateOrgTypeSelect = document.getElementById('activate_org_type');

        if (activateDeptSelect && activateOrgNameSelect) {
            activateDeptSelect.addEventListener('change', function() {
                const deptId = this.value;
                activateOrgNameSelect.innerHTML = '<option value="">Select Organization</option>';
                
                if (deptId) {
                    // Filter approved organizations by selected department
                    let filteredOrgs = [];
                    if (deptId === '11') {
                        filteredOrgs = approvedOrganizations.filter(org => org.type === 'Non-Academic' || org.department_id == '11');
                    } else {
                        filteredOrgs = approvedOrganizations.filter(org => org.department_id == deptId && org.type !== 'Non-Academic');
                    }

                    if (filteredOrgs.length > 0) {
                        filteredOrgs.forEach(org => {
                            const option = document.createElement('option');
                            option.value = org.name;
                            option.textContent = org.name;
                            option.dataset.acronym = org.org_acronym || '';
                            option.dataset.type = org.type || '';
                            activateOrgNameSelect.appendChild(option);
                        });
                        activateOrgNameSelect.disabled = false;
                        activateOrgNameSelect.classList.remove('bg-gray-50');
                    } else {
                        activateOrgNameSelect.innerHTML = '<option value="">No Approved Organizations Found</option>';
                        activateOrgNameSelect.disabled = true;
                        activateOrgNameSelect.classList.add('bg-gray-50');
                    }
                } else {
                    activateOrgNameSelect.innerHTML = '<option value="">Select Department First</option>';
                    activateOrgNameSelect.disabled = true;
                    activateOrgNameSelect.classList.add('bg-gray-50');
                }
                
                // Trigger change to reset acronym and type
                activateOrgNameSelect.dispatchEvent(new Event('change'));
            });

            activateOrgNameSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption && selectedOption.value) {
                    activateOrgAcronymInput.value = selectedOption.dataset.acronym || '';
                    activateOrgTypeSelect.value = selectedOption.dataset.type || '';
                } else {
                    activateOrgAcronymInput.value = '';
                    activateOrgTypeSelect.value = '';
                }
            });
        }
    </script>
    <script src="{{ asset('js/auth.js') }}"></script>

    <!-- Success/Error Modal -->
    <div id="notificationModal" class="fixed inset-0 bg-black/50 modal-backdrop hidden items-center justify-center z-50" style="display: none; -webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px);">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 transform transition-all">
            <div id="modalHeader" class="px-6 py-4 border-b border-gray-200 flex items-center">
                <div id="modalIcon" class="w-12 h-12 rounded-full flex items-center justify-center mr-4">
                    <i id="modalIconElement" class="text-2xl"></i>
                </div>
                <h3 id="modalTitle" class="text-xl font-bold"></h3>
            </div>
            <div class="px-6 py-6">
                <p id="modalMessage" class="text-gray-700 leading-relaxed"></p>
            </div>
            <div class="px-6 py-4 bg-gray-50 rounded-b-xl flex justify-end">
                <button id="modalCloseBtn" class="px-6 py-2 rounded-lg font-semibold transition-colors">
                    OK
                </button>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function showModal(type, title, message, onClose = null) {
            const modal = document.getElementById('notificationModal');
            const modalIcon = document.getElementById('modalIcon');
            const modalIconElement = document.getElementById('modalIconElement');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const modalCloseBtn = document.getElementById('modalCloseBtn');

            // Set content
            modalTitle.textContent = title;
            modalMessage.textContent = message;

            // Set styling based on type
            if (type === 'success') {
                modalIcon.className = 'w-12 h-12 rounded-full flex items-center justify-center mr-4 bg-green-100';
                modalIconElement.className = 'ph ph-check-circle text-2xl text-green-600';
                modalTitle.className = 'text-xl font-bold text-green-800';
                modalCloseBtn.className = 'px-6 py-2 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition-colors';
            } else if (type === 'error') {
                modalIcon.className = 'w-12 h-12 rounded-full flex items-center justify-center mr-4 bg-red-100';
                modalIconElement.className = 'ph ph-warning-circle text-2xl text-red-600';
                modalTitle.className = 'text-xl font-bold text-red-800';
                modalCloseBtn.className = 'px-6 py-2 bg-red-600 text-white rounded-lg font-semibold hover:bg-red-700 transition-colors';
            } else if (type === 'warning') {
                modalIcon.className = 'w-12 h-12 rounded-full flex items-center justify-center mr-4 bg-yellow-100';
                modalIconElement.className = 'ph ph-warning text-2xl text-yellow-600';
                modalTitle.className = 'text-xl font-bold text-yellow-800';
                modalCloseBtn.className = 'px-6 py-2 bg-yellow-600 text-white rounded-lg font-semibold hover:bg-yellow-700 transition-colors';
            }

            // Show modal
            modal.style.display = 'flex';
            modal.classList.remove('hidden');

            // Handle close
            const closeHandler = () => {
                modal.style.display = 'none';
                modal.classList.add('hidden');
                if (onClose) onClose();
            };

            modalCloseBtn.onclick = closeHandler;
            modal.onclick = (e) => {
                if (e.target === modal) closeHandler();
            };
        }
    </script>
</body>
</html>



