<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Request Type - UniOrg</title>
    <link rel="icon" type="image/png" href="{{ asset('images/LSPU_LOGO.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <style>
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        body {
            animation: fadeInUp 0.6s ease-out;
        }
        
        .card-animate {
            opacity: 0;
            animation: fadeInUp 0.8s ease-out forwards;
        }
        
        .card-animate:nth-child(1) {
            animation-delay: 0.2s;
        }
        
        .card-animate:nth-child(2) {
            animation-delay: 0.4s;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen py-6 sm:py-8 px-3 sm:px-4">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8 sm:mb-12">
            <div class="flex items-center justify-center mb-4 sm:mb-6">
                <img src="{{ asset('images/LSPU_LOGO.png') }}" alt="LSPU Logo" class="h-16 w-16 sm:h-20 sm:w-20 md:h-24 md:w-24">
            </div>
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-[#26488C] mb-2 sm:mb-3 px-2">Organization System Request</h1>
            <p class="text-sm sm:text-base md:text-lg text-gray-600 px-2">Choose the type of request that applies to your organization</p>
        </div>

        <!-- Request Type Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6 md:gap-8 mb-6 sm:mb-8">
            <!-- Request a System (Existing Org) -->
            <div class="card-animate bg-white rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2 flex flex-col">
                <div class="bg-gradient-to-br from-[#26488C] to-[#1a3461] p-4 sm:p-6 text-white">
                    <div class="flex items-center justify-center mb-3 sm:mb-4">
                        <div class="bg-white rounded-full p-3 sm:p-4">
                            <i class="ph ph-arrows-clockwise text-[#26488C] text-3xl sm:text-4xl"></i>
                        </div>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-bold text-center mb-1 sm:mb-2">Request a System</h2>
                    <p class="text-center text-gray-200 text-xs sm:text-sm">For existing organizations</p>
                </div>
                
                <div class="p-5 sm:p-6 md:p-8 flex-1 flex flex-col">
                    <div class="mb-5 sm:mb-6">
                        <h3 class="font-bold text-base sm:text-lg text-gray-800 mb-2 sm:mb-3">This is for you if:</h3>
                        <ul class="space-y-2 text-sm sm:text-base text-gray-600">
                            <li class="flex items-start">
                                <i class="ph ph-check-circle text-green-500 mr-2 mt-1 flex-shrink-0"></i>
                                <span>Your organization is already accredited</span>
                            </li>
                            <li class="flex items-start">
                                <i class="ph ph-check-circle text-green-500 mr-2 mt-1 flex-shrink-0"></i>
                                <span>You need a management system for your existing org</span>
                            </li>
                            <li class="flex items-start">
                                <i class="ph ph-check-circle text-green-500 mr-2 mt-1 flex-shrink-0"></i>
                                <span>You're renewing your organization's system access</span>
                            </li>
                        </ul>
                    </div>

                    <div class="mb-5 sm:mb-6 flex-1">
                        <h3 class="font-bold text-base sm:text-lg text-gray-800 mb-2 sm:mb-3">Required Document:</h3>
                        <div class="bg-gray-50 rounded-lg p-3 sm:p-4">
                            <div class="flex items-center text-sm sm:text-base text-gray-700">
                                <i class="ph ph-file-pdf text-red-500 mr-2 sm:mr-3 text-lg sm:text-xl"></i>
                                <span class="font-medium">Accreditation Form</span>
                            </div>
                        </div>
                    </div>

                    <a href="{{ route('system-request.form', ['type' => 'request']) }}" 
                       class="block w-full bg-gradient-to-r from-[#26488C] to-[#1a3461] text-white font-bold py-3 sm:py-4 px-4 sm:px-6 rounded-lg hover:opacity-90 transition text-center mt-auto text-sm sm:text-base">
                        <i class="ph ph-arrow-right mr-2"></i> Continue with Request
                    </a>
                </div>
            </div>

            <!-- Apply an Organization (New Org) -->
            <div class="card-animate bg-white rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2 flex flex-col">
                <div class="bg-gradient-to-br from-[#C4973B] to-[#a67d2e] p-4 sm:p-6 text-white">
                    <div class="flex items-center justify-center mb-3 sm:mb-4">
                        <div class="bg-white rounded-full p-3 sm:p-4">
                            <i class="ph ph-plus-circle text-[#C4973B] text-3xl sm:text-4xl"></i>
                        </div>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-bold text-center mb-1 sm:mb-2">Apply an Organization</h2>
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
                                <span>You need to assign officer positions</span>
                            </li>
                        </ul>
                    </div>

                    <div class="mb-5 sm:mb-6 flex-1">
                        <h3 class="font-bold text-base sm:text-lg text-gray-800 mb-2 sm:mb-3">Required Documents:</h3>
                        <div class="bg-gray-50 rounded-lg p-3 sm:p-4 space-y-2 text-xs sm:text-sm text-gray-600">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2">
                                <div class="flex items-start">
                                    <i class="ph ph-file-text text-blue-500 mr-2 mt-1 flex-shrink-0"></i>
                                    <span>Letter of Application</span>
                                </div>
                                <div class="flex items-start">
                                    <i class="ph ph-file-text text-blue-500 mr-2 mt-1 flex-shrink-0"></i>
                                    <span>Constitution & By-laws</span>
                                </div>
                                <div class="flex items-start">
                                    <i class="ph ph-file-text text-blue-500 mr-2 mt-1 flex-shrink-0"></i>
                                    <span>Renewal Form</span>
                                </div>
                                <div class="flex items-start">
                                    <i class="ph ph-file-text text-blue-500 mr-2 mt-1 flex-shrink-0"></i>
                                    <span>Program of Activities</span>
                                </div>
                                <div class="flex items-start">
                                    <i class="ph ph-file-text text-blue-500 mr-2 mt-1 flex-shrink-0"></i>
                                    <span>Adviser Commitment Form</span>
                                </div>
                                <div class="flex items-start">
                                    <i class="ph ph-file-text text-blue-500 mr-2 mt-1 flex-shrink-0"></i>
                                    <span>Certification of Officers</span>
                                </div>
                                <div class="flex items-start sm:col-span-2">
                                    <i class="ph ph-file-text text-blue-500 mr-2 mt-1 flex-shrink-0"></i>
                                    <span>List of Officers & Members</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <a href="{{ route('system-request.form', ['type' => 'apply']) }}" 
                       class="block w-full bg-gradient-to-r from-[#C4973B] to-[#a67d2e] text-white font-bold py-3 sm:py-4 px-4 sm:px-6 rounded-lg hover:opacity-90 transition text-center mt-auto text-sm sm:text-base">
                        <i class="ph ph-arrow-right mr-2"></i> Continue with Application
                    </a>
                </div>
            </div>
        </div>

        <!-- Back to Login -->
        <div class="text-center">
            <a href="{{ route('org.login') }}" class="inline-flex items-center text-[#26488C] hover:text-[#1a3461] font-semibold text-sm sm:text-base">
                <i class="ph ph-arrow-left mr-2"></i> Back to Login
            </a>
        </div>
    </div>
</body>
</html>
