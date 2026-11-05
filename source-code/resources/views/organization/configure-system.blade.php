<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configure System - {{ $organization->name }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/LSPU_LOGO.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'osas-blue': '#26488C',
                        'osas-blue-dark': '#1a3461',
                        'osas-gold': '#C4973B',
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        
        .module-card {
            transition: all 0.3s ease;
        }
        .module-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(38, 72, 140, 0.15);
        }
        .color-preview {
            width: 64px;
            height: 64px;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .color-preview:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .gradient-bg {
            background: linear-gradient(135deg, #26488C 0%, #1a3461 100%);
        }
        .gold-accent {
            background: linear-gradient(135deg, #C4973B 0%, #b8873a 100%);
        }
        .step-indicator {
            transition: all 0.3s ease;
        }
        .step-indicator.active {
            background-color: #26488C;
            color: white;
        }
        .step-indicator.completed {
            background-color: #C4973B;
            color: white;
        }
    </style>
</head>
<body class="bg-gray-50" x-data="configWizard()">
    <!-- Header Banner -->
    <div class="gradient-bg text-white py-8 shadow-lg">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-4xl font-bold mb-2">Configure Your Organization Platform</h1>
                <p class="text-xl text-blue-100">{{ $organization->name }} ({{ $organization->org_acronym }})</p>
                <div class="mt-3 inline-block px-4 py-2 bg-white/10 backdrop-blur-sm rounded-full">
                    <span class="text-sm font-medium">Organization Type: </span>
                    <span class="font-bold text-osas-gold">{{ $organization->type }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Indicator -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mt-8">
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <!-- Step 1 -->
                <div class="flex items-center flex-1">
                    <div class="step-indicator flex items-center justify-center w-10 h-10 rounded-full font-bold text-sm transition-all"
                         :class="currentStep === 1 ? 'active' : (currentStep > 1 ? 'completed' : 'bg-gray-200 text-gray-600')">
                        <span x-show="currentStep <= 1">1</span>
                        <i x-show="currentStep > 1" class="ph ph-check"></i>
                    </div>
                    <div class="ml-3 hidden sm:block">
                        <p class="text-xs font-semibold text-gray-900">Step 1</p>
                        <p class="text-xs text-gray-600">Verification</p>
                    </div>
                </div>
                <div class="flex-1 h-1 mx-2" :class="currentStep > 1 ? 'bg-osas-gold' : 'bg-gray-200'"></div>
                
                <!-- Step 2 -->
                <div class="flex items-center flex-1">
                    <div class="step-indicator flex items-center justify-center w-10 h-10 rounded-full font-bold text-sm transition-all"
                         :class="currentStep === 2 ? 'active' : (currentStep > 2 ? 'completed' : 'bg-gray-200 text-gray-600')">
                        <span x-show="currentStep <= 2">2</span>
                        <i x-show="currentStep > 2" class="ph ph-check"></i>
                    </div>
                    <div class="ml-3 hidden sm:block">
                        <p class="text-xs font-semibold text-gray-900">Step 2</p>
                        <p class="text-xs text-gray-600">Logo</p>
                    </div>
                </div>
                <div class="flex-1 h-1 mx-2" :class="currentStep > 2 ? 'bg-osas-gold' : 'bg-gray-200'"></div>
                
                <!-- Step 3 -->
                <div class="flex items-center flex-1">
                    <div class="step-indicator flex items-center justify-center w-10 h-10 rounded-full font-bold text-sm transition-all"
                         :class="currentStep === 3 ? 'active' : (currentStep > 3 ? 'completed' : 'bg-gray-200 text-gray-600')">
                        <span x-show="currentStep <= 3">3</span>
                        <i x-show="currentStep > 3" class="ph ph-check"></i>
                    </div>
                    <div class="ml-3 hidden sm:block">
                        <p class="text-xs font-semibold text-gray-900">Step 3</p>
                        <p class="text-xs text-gray-600">Modules</p>
                    </div>
                </div>
                <div class="flex-1 h-1 mx-2" :class="currentStep > 3 ? 'bg-osas-gold' : 'bg-gray-200'"></div>
                
                <!-- Step 4 -->
                <div class="flex items-center flex-1">
                    <div class="step-indicator flex items-center justify-center w-10 h-10 rounded-full font-bold text-sm transition-all"
                         :class="currentStep === 4 ? 'active' : (currentStep > 4 ? 'completed' : 'bg-gray-200 text-gray-600')">
                        <span>4</span>
                    </div>
                    <div class="ml-3 hidden sm:block">
                        <p class="text-xs font-semibold text-gray-900">Step 4</p>
                        <p class="text-xs text-gray-600">Theme</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">
            <!-- Success/Error Messages -->
            @if(session('success'))
                <div class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-800 px-6 py-4 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <i class="ph ph-check-circle text-green-500 mr-3"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-800 px-6 py-4 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <i class="ph ph-warning-circle text-red-500 mr-3"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-800 px-6 py-4 rounded-lg shadow-md">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Configuration Form -->
            <form action="{{ route('organization.save-configuration') }}" method="POST" id="configForm" class="bg-white shadow-xl rounded-xl overflow-hidden border border-gray-200" enctype="multipart/form-data" @submit="validateBeforeSubmit($event)">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <!-- STEP 1: Organization Verification -->
                <div x-show="currentStep === 1" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-x-4" x-transition:enter-end="opacity-100 transform translate-x-0">
                    <div class="p-8 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-blue-100/30">
                        <div class="flex items-center mb-4">
                            <div class="w-1 h-8 bg-osas-gold rounded-full mr-4"></div>
                            <h2 class="text-2xl font-bold text-osas-blue">Organization Verification</h2>
                        </div>
                        <p class="text-gray-600 mb-6">Verify or update your organization's acronym.</p>
                        
                        <div class="bg-white p-6 rounded-xl border-2 border-osas-blue/20 shadow-sm">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-bold text-osas-blue mb-2">
                                        <i class="ph ph-buildings mr-2 text-osas-gold"></i>Organization Name
                                    </label>
                                    <input type="text" value="{{ $organization->name }}" readonly 
                                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg bg-gray-50 text-gray-700 font-semibold cursor-not-allowed">
                                </div>
                                <div>
                                    <label for="confirm_acronym" class="block text-sm font-bold text-osas-blue mb-2">
                                        <i class="ph ph-shield mr-2 text-osas-gold"></i>Organization Acronym *
                                    </label>
                                    <input type="text" name="confirm_acronym" id="confirm_acronym" required 
                                        x-model="acronymInput"
                                        @input="checkAcronymChanged()"
                                        placeholder="Enter organization acronym"
                                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-osas-blue focus:ring-2 focus:ring-osas-blue/20 font-semibold uppercase"
                                        data-original="{{ $organization->org_acronym }}">
                                    <p class="mt-2 text-xs text-gray-600">
                                        <i class="ph ph-info text-osas-blue mr-1"></i>
                                        Current acronym: <span class="font-bold">{{ $organization->org_acronym }}</span>
                                    </p>
                                    <div x-show="acronymChanged" class="mt-2">
                                        <p class="text-sm text-amber-600 flex items-center">
                                            <i class="ph ph-warning mr-2 flex-shrink-0"></i>
                                            <span>You are changing the acronym from "<span class="font-bold">{{ $organization->org_acronym }}</span>" to "<span class="font-bold uppercase" x-text="acronymInput"></span>"</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 1 Navigation -->
                    <div class="p-8 bg-gradient-to-r from-gray-50 to-blue-50/30 flex justify-end">
                        <button type="button" @click="proceedToNextStep()" :disabled="!acronymInput || acronymInput.trim() === ''"
                            class="px-8 py-4 gold-accent text-white font-bold rounded-xl hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-osas-gold/50 transition-all transform hover:scale-105 flex items-center disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none">
                            Next: Upload Logo
                            <i class="ph ph-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Acronym Change Confirmation Modal -->
                <div x-show="showAcronymModal" 
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="fixed inset-0 z-50 overflow-y-auto" 
                     style="display: none;">
                    <!-- Backdrop -->
                    <div class="fixed inset-0 bg-black/50 modal-backdrop transition-opacity" @click="showAcronymModal = false"></div>
                    
                    <!-- Modal Content -->
                    <div class="flex items-center justify-center min-h-screen p-4">
                        <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full p-6"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 transform scale-95"
                             x-transition:enter-end="opacity-100 transform scale-100"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100 transform scale-100"
                             x-transition:leave-end="opacity-0 transform scale-95">
                            
                            <!-- Icon -->
                            <div class="flex items-center justify-center w-16 h-16 mx-auto bg-amber-100 rounded-full mb-4">
                                <i class="ph ph-warning text-3xl text-amber-600"></i>
                            </div>
                            
                            <!-- Title -->
                            <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Confirm Acronym Change</h3>
                            
                            <!-- Message -->
                            <div class="text-center mb-6">
                                <p class="text-gray-600 mb-4">Are you sure you want to change the organization acronym?</p>
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                    <div class="flex items-center justify-center space-x-3">
                                        <span class="text-lg font-bold text-red-600">{{ $organization->org_acronym }}</span>
                                        <i class="ph ph-arrow-right text-gray-400"></i>
                                        <span class="text-lg font-bold text-green-600 uppercase" x-text="acronymInput"></span>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mt-3">
                                    <i class="ph ph-info mr-1"></i>
                                    This will update your organization's acronym in the system.
                                </p>
                            </div>
                            
                            <!-- Buttons -->
                            <div class="flex space-x-3">
                                <button type="button" @click="showAcronymModal = false"
                                    class="flex-1 px-4 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition-all">
                                    <i class="ph ph-x mr-2"></i>Cancel
                                </button>
                                <button type="button" @click="confirmAcronymChange()"
                                    class="flex-1 px-4 py-3 bg-osas-blue text-white font-semibold rounded-lg hover:bg-osas-blue-dark transition-all">
                                    <i class="ph ph-check mr-2"></i>Confirm
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Logo Upload -->
                <div x-show="currentStep === 2" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-x-4" x-transition:enter-end="opacity-100 transform translate-x-0">
                    <div class="p-8 border-b border-gray-200">
                        <div class="flex items-center mb-4">
                            <div class="w-1 h-8 bg-osas-gold rounded-full mr-4"></div>
                            <h2 class="text-2xl font-bold text-osas-blue">Organization Logo</h2>
                        </div>
                        <p class="text-gray-600 mb-6">Upload your organization's logo. This will be displayed throughout your system.</p>
                        
                        <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                            <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
                                <!-- Logo Preview -->
                                <div class="flex-shrink-0">
                                    <div class="w-32 h-32 border-2 border-dashed border-gray-300 rounded-xl flex items-center justify-center bg-white overflow-hidden">
                                        <template x-if="logoPreview">
                                            <img :src="logoPreview" alt="Organization Logo" class="w-full h-full object-contain">
                                        </template>
                                        <template x-if="!logoPreview">
                                            <div class="text-center">
                                                <i class="ph ph-image text-4xl text-gray-400 mb-2"></i>
                                                <p class="text-xs text-gray-500">No logo</p>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- Upload Controls -->
                                <div class="flex-1">
                                    <label for="logo" class="block text-sm font-bold text-osas-blue mb-2">
                                        <i class="ph ph-upload-simple mr-2 text-osas-gold"></i>Upload Logo *
                                    </label>
                                    <input 
                                        type="file" 
                                        name="logo" 
                                        id="logo"
                                        accept="image/png"
                                        @change="handleLogoUpload($event)"
                                        class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-osas-gold file:text-white hover:file:bg-osas-gold/90 file:cursor-pointer cursor-pointer border border-gray-300 rounded-lg"
                                    >
                                    <p class="mt-2 text-xs text-gray-600">
                                        <i class="ph ph-info text-osas-blue mr-1"></i>
                                        Required format: PNG. Recommended: Square image (500x500px). Max size: 2MB
                                    </p>
                                    <div x-show="!logoPreview" class="mt-2">
                                        <p class="text-sm text-red-600 flex items-center">
                                            <i class="ph ph-warning-circle mr-2"></i>
                                            Please upload a logo to continue
                                        </p>
                                    </div>
                                    <template x-if="logoPreview && logoPreview !== '{{ $currentSettings && $currentSettings->logo_url ? (config('filesystems.default') === 's3' ? config('filesystems.disks.s3.url') . '/' . str_replace(config('filesystems.disks.s3.url') . '/', '', $currentSettings->logo_url) : asset('storage/' . $currentSettings->logo_url)) : '' }}'">
                                        <button 
                                            type="button" 
                                            @click="removeLogo()"
                                            class="mt-3 text-sm text-red-600 hover:text-red-800 font-medium"
                                        >
                                            <i class="ph ph-trash mr-1"></i>Remove logo
                                        </button>
                                    </template>
                                    <input type="hidden" name="remove_logo" x-model="removeLogoFlag" value="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 2 Navigation -->
                    <div class="p-8 bg-gradient-to-r from-gray-50 to-blue-50/30 flex justify-between">
                        <button type="button" @click="prevStep()"
                            class="px-8 py-4 bg-gray-500 text-white font-bold rounded-xl hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-gray-500/50 transition-all transform hover:scale-105 flex items-center">
                            <i class="ph ph-arrow-left mr-2"></i>
                            Previous
                        </button>
                        <button type="button" @click="nextStep()" :disabled="!logoPreview"
                            class="px-8 py-4 gold-accent text-white font-bold rounded-xl hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-osas-gold/50 transition-all transform hover:scale-105 flex items-center disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none">
                            Next: Select Modules
                            <i class="ph ph-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 3: Module Selection -->
                <div x-show="currentStep === 3" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-x-4" x-transition:enter-end="opacity-100 transform translate-x-0">
                    <!-- Permanent Modules -->
                    <div class="p-8 border-b border-gray-200">
                        <div class="flex items-center mb-4">
                            <div class="w-1 h-8 bg-osas-gold rounded-full mr-4"></div>
                            <h2 class="text-2xl font-bold text-osas-blue">Permanent Modules</h2>
                        </div>
                        <p class="text-gray-600 mb-6">These modules are always enabled and cannot be disabled.</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($permanentModules as $module)
                                <div class="module-card bg-gradient-to-br from-blue-50 to-blue-100/50 border-2 border-osas-blue/30 rounded-xl p-5 shadow-sm">
                                    <div class="flex items-center">
                                        <div class="w-6 h-6 bg-osas-blue rounded-md flex items-center justify-center">
                                            <i class="ph ph-check text-white text-xs"></i>
                                        </div>
                                        <label class="ml-3 text-lg font-bold text-osas-blue">
                                            {{ ucfirst($module === 'chatbot' ? 'Chat/Manage FAQs' : $module) }}
                                        </label>
                                    </div>
                                    <p class="mt-3 text-sm text-gray-700 ml-9">
                                        @if($module === 'announcements')
                                            Post and manage organization announcements
                                        @elseif($module === 'events')
                                            Create and manage organization events
                                        @elseif($module === 'payments')
                                            Track and manage member payments
                                        @elseif($module === 'chatbot')
                                            Student chat interface and admin FAQ management
                                        @endif
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Optional Modules -->
                    <div class="p-8 border-b border-gray-200 bg-gray-50/50">
                        <div class="flex items-center mb-4">
                            <div class="w-1 h-8 bg-osas-gold rounded-full mr-4"></div>
                            <h2 class="text-2xl font-bold text-osas-blue">Optional Modules</h2>
                        </div>
                        <p class="text-gray-600 mb-6">Select the modules you want to enable for your organization.</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($optionalModules as $module)
                                <div class="module-card bg-white border-2 border-gray-200 rounded-xl p-5 hover:border-osas-gold hover:shadow-lg cursor-pointer transition-all">
                                    <div class="flex items-center">
                                        <input 
                                            type="checkbox" 
                                            name="modules[]" 
                                            value="{{ $module }}"
                                            id="module_{{ $module }}"
                                            class="w-5 h-5 text-osas-blue rounded focus:ring-osas-gold focus:ring-2 border-gray-300 cursor-pointer"
                                            {{ $currentSettings && $currentSettings->{'has_' . $module} ? 'checked' : '' }}
                                        >
                                        <label for="module_{{ $module }}" class="ml-3 text-lg font-bold text-gray-900 cursor-pointer">
                                            {{ ucfirst($module === 'elibrary' ? 'E-Library' : $module) }}
                                        </label>
                                    </div>
                                    <p class="mt-3 text-sm text-gray-600 ml-8">
                                        @if($module === 'elections')
                                            Conduct online elections for organization positions
                                        @elseif($module === 'elibrary')
                                            Manage and share digital library resources
                                        @elseif($module === 'edushare')
                                            Share educational materials and resources
                                        @elseif($module === 'complaints')
                                            Collect and manage member feedback and complaints
                                        @elseif($module === 'documents')
                                            Store and manage organization documents
                                        @endif
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    
                    <!-- Step 3 Navigation -->
                    <div class="p-8 bg-gradient-to-r from-gray-50 to-blue-50/30 flex justify-between">
                        <button type="button" @click="prevStep()"
                            class="px-8 py-4 bg-gray-500 text-white font-bold rounded-xl hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-gray-500/50 transition-all transform hover:scale-105 flex items-center">
                            <i class="ph ph-arrow-left mr-2"></i>
                            Previous
                        </button>
                        <button type="button" @click="nextStep()"
                            class="px-8 py-4 gold-accent text-white font-bold rounded-xl hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-osas-gold/50 transition-all transform hover:scale-105 flex items-center">
                            Next: Theme Colors
                            <i class="ph ph-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 4: Theme Colors -->
                <div x-show="currentStep === 4" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-x-4" x-transition:enter-end="opacity-100 transform translate-x-0">
                    <div class="p-8 border-b border-gray-200">
                        <div class="flex items-center mb-4">
                            <div class="w-1 h-8 bg-osas-gold rounded-full mr-4"></div>
                            <h2 class="text-2xl font-bold text-osas-blue">Theme Colors</h2>
                        </div>
                        <p class="text-gray-600 mb-6">Customize your organization's color scheme.</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Primary Color -->
                            <div class="bg-gray-50 p-5 rounded-xl border border-gray-200">
                                <label class="block text-sm font-bold text-osas-blue mb-3">
                                    <i class="ph ph-palette mr-2 text-osas-gold"></i>Primary Color
                                </label>
                                <div class="flex items-start space-x-3 mb-3">
                                    <!-- Color Preview -->
                                    <div class="flex-shrink-0">
                                        <div class="color-preview" :style="`background-color: ${primaryColor}`" @click="$refs.primaryColorInput.click()"></div>
                                        <input type="color" x-ref="primaryColorInput" x-model="primaryColor" name="primary_color" class="hidden">
                                    </div>
                                    
                                    <!-- Hex Input and Random Button -->
                                    <div class="flex-1 min-w-0">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Hex Value</label>
                                        <div class="flex flex-col sm:flex-row gap-2">
                                            <input type="text" x-model="primaryColorHex" @input="updatePrimaryFromHex()" placeholder="#RRGGBB"
                                                class="flex-1 min-w-0 px-3 py-2 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-osas-blue focus:ring-2 focus:ring-osas-blue/20 font-mono text-sm uppercase">
                                            <button type="button" @click="generateRandomPrimary()" class="flex-shrink-0 px-3 py-2 bg-osas-blue text-white rounded-lg font-semibold transition-all hover:bg-osas-blue-dark shadow-md hover:shadow-lg text-sm flex items-center justify-center whitespace-nowrap">
                                                <i class="ph ph-shuffle mr-1.5 text-osas-gold"></i>
                                                <span>Random</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-600">Used for buttons, headers, and primary elements</p>
                            </div>

                            <!-- Complementary Color -->
                            <div class="bg-gray-50 p-5 rounded-xl border border-gray-200">
                                <label class="block text-sm font-bold text-osas-blue mb-3 flex items-center flex-wrap gap-2">
                                    <span><i class="ph ph-star mr-2 text-osas-gold"></i>Complementary Color</span>
                                    <span x-show="!manualComplementary" class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">Auto</span>
                                    <span x-show="manualComplementary" class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded">Manual</span>
                                </label>
                                <div class="flex items-start space-x-3 mb-3">
                                    <!-- Color Preview -->
                                    <div class="flex-shrink-0">
                                        <div class="color-preview transition-all" :class="manualComplementary ? '' : 'opacity-50 cursor-not-allowed'" :style="`background-color: ${complementaryColor}`" @click="manualComplementary && $refs.complementaryColorInput.click()"></div>
                                        <input type="color" x-ref="complementaryColorInput" x-model="complementaryColor" class="hidden">
                                        <input type="hidden" name="complementary_color" :value="complementaryColor">
                                    </div>
                                    
                                    <!-- Hex Input and Manual/Auto Toggle -->
                                    <div class="flex-1 min-w-0">
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Hex Value</label>
                                        <div class="flex flex-col sm:flex-row gap-2">
                                            <input type="text" x-model="complementaryColorHex" @input="manualComplementary && updateComplementaryFromHex()" :readonly="!manualComplementary" :class="manualComplementary ? '' : 'bg-gray-100 cursor-not-allowed'" placeholder="#RRGGBB"
                                                class="flex-1 min-w-0 px-3 py-2 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-osas-blue focus:ring-2 focus:ring-osas-blue/20 font-mono text-sm uppercase">
                                            <button type="button" @click="toggleManualComplementary()" class="flex-shrink-0 bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded-lg font-semibold transition-all shadow-md hover:shadow-lg text-sm flex items-center justify-center whitespace-nowrap">
                                                <i class="ph mr-1.5" :class="manualComplementary ? 'ph-magic-wand' : 'ph-hand-pointing'"></i>
                                                <span x-text="manualComplementary ? 'Auto' : 'Manual'"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-600">Used for accents and secondary elements</p>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="p-8 bg-gradient-to-r from-gray-50 to-blue-50/30 flex justify-between items-center">
                        <button type="button" @click="prevStep()"
                            class="px-8 py-4 bg-gray-500 text-white font-bold rounded-xl hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-gray-500/50 transition-all transform hover:scale-105 flex items-center">
                            <i class="ph ph-arrow-left mr-2"></i>
                            Previous
                        </button>
                        <div class="flex items-center gap-4">
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="ph ph-info text-osas-blue mr-2"></i>
                                <span>Your platform will be automatically activated.</span>
                            </div>
                            <button 
                                type="submit"
                                class="px-8 py-4 gold-accent text-white font-bold rounded-xl hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-osas-gold/50 transition-all transform hover:scale-105 flex items-center"
                            >
                                <i class="ph ph-rocket mr-2"></i>
                                Activate Platform
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Footer Info -->
            <div class="mt-8 text-center text-sm text-gray-500">
                <p>Powered by <span class="font-bold text-osas-blue">UniOrg</span> - University Organization Management System</p>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
    
    <script>
        function configWizard() {
            return {
                currentStep: 1,
                
                // Step 1: Acronym verification/editing
                acronymInput: '{{ $organization->org_acronym }}',
                originalAcronym: '{{ $organization->org_acronym }}',
                acronymChanged: false,
                showAcronymModal: false,
                
                // Step 2: Logo upload
                logoPreview: '{{ $currentSettings && $currentSettings->logo_url ? (config('filesystems.default') === 's3' ? config('filesystems.disks.s3.url') . '/' . str_replace(config('filesystems.disks.s3.url') . '/', '', $currentSettings->logo_url) : asset('storage/' . $currentSettings->logo_url)) : '' }}',
                removeLogoFlag: '0',
                
                // Step 4: Theme colors
                primaryColor: '{{ old('primary_color', $currentSettings->primary_color ?? '#8238B3') }}',
                primaryColorHex: '{{ old('primary_color', $currentSettings->primary_color ?? '#8238B3') }}',
                complementaryColor: '{{ old('complementary_color', $currentSettings->complementary_color ?? '#D7AC28') }}',
                complementaryColorHex: '{{ old('complementary_color', $currentSettings->complementary_color ?? '#D7AC28') }}',
                manualComplementary: false,
                
                init() {
                    // Watch primary color changes to auto-calculate complementary
                    this.$watch('primaryColor', (value) => {
                        this.primaryColorHex = value.toUpperCase();
                        if (!this.manualComplementary) {
                            this.complementaryColor = this.calculateComplementary(value);
                            this.complementaryColorHex = this.complementaryColor;
                        }
                    });
                    
                    this.$watch('complementaryColor', (value) => {
                        this.complementaryColorHex = value.toUpperCase();
                    });
                },
                
                // Step 1: Acronym handling
                checkAcronymChanged() {
                    this.acronymChanged = this.acronymInput.toUpperCase() !== this.originalAcronym.toUpperCase();
                },
                
                proceedToNextStep() {
                    if (this.acronymChanged) {
                        // Show confirmation modal
                        this.showAcronymModal = true;
                    } else {
                        // No change, proceed directly
                        this.nextStep();
                    }
                },
                
                confirmAcronymChange() {
                    this.showAcronymModal = false;
                    this.nextStep();
                },
                
                // Step 2: Logo handling
                handleLogoUpload(event) {
                    const file = event.target.files[0];
                    if (!file) return;
                    
                    // Validate file type - PNG only
                    if (file.type !== 'image/png') {
                        alert('Please upload a PNG image file only');
                        event.target.value = '';
                        return;
                    }
                    
                    // Validate file size (2MB max)
                    if (file.size > 2 * 1024 * 1024) {
                        alert('File size must be less than 2MB');
                        event.target.value = '';
                        return;
                    }
                    
                    // Show preview
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        this.logoPreview = e.target.result;
                        this.removeLogoFlag = '0';
                    };
                    reader.readAsDataURL(file);
                },
                
                removeLogo() {
                    if (confirm('Are you sure you want to remove the logo?')) {
                        this.logoPreview = null;
                        this.removeLogoFlag = '1';
                        document.getElementById('logo').value = '';
                    }
                },
                
                // Step 4: Color picker functions
                updatePrimaryFromHex() {
                    if (/^#[0-9A-Fa-f]{6}$/.test(this.primaryColorHex)) {
                        this.primaryColor = this.primaryColorHex.toUpperCase();
                    }
                },
                
                updateComplementaryFromHex() {
                    if (/^#[0-9A-Fa-f]{6}$/.test(this.complementaryColorHex)) {
                        this.complementaryColor = this.complementaryColorHex.toUpperCase();
                    }
                },
                
                generateRandomPrimary() {
                    const randomColor = '#' + Math.floor(Math.random()*16777215).toString(16).padStart(6, '0').toUpperCase();
                    this.primaryColor = randomColor;
                    this.primaryColorHex = randomColor;
                },
                
                toggleManualComplementary() {
                    this.manualComplementary = !this.manualComplementary;
                    if (!this.manualComplementary) {
                        // Switch to auto mode - recalculate
                        this.complementaryColor = this.calculateComplementary(this.primaryColor);
                        this.complementaryColorHex = this.complementaryColor;
                    }
                },
                
                calculateComplementary(hexColor) {
                    // Remove # if present
                    const hex = hexColor.replace('#', '');
                    
                    // Convert to RGB
                    const r = parseInt(hex.substr(0, 2), 16);
                    const g = parseInt(hex.substr(2, 2), 16);
                    const b = parseInt(hex.substr(4, 2), 16);
                    
                    // Convert to HSL
                    const rNorm = r / 255;
                    const gNorm = g / 255;
                    const bNorm = b / 255;
                    
                    const max = Math.max(rNorm, gNorm, bNorm);
                    const min = Math.min(rNorm, gNorm, bNorm);
                    let h, s, l = (max + min) / 2;
                    
                    if (max === min) {
                        h = s = 0;
                    } else {
                        const d = max - min;
                        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
                        
                        switch (max) {
                            case rNorm: h = ((gNorm - bNorm) / d + (gNorm < bNorm ? 6 : 0)) / 6; break;
                            case gNorm: h = ((bNorm - rNorm) / d + 2) / 6; break;
                            case bNorm: h = ((rNorm - gNorm) / d + 4) / 6; break;
                        }
                    }
                    
                    // Rotate hue by 180 degrees for complementary color
                    h = (h + 0.5) % 1;
                    
                    // Convert back to RGB
                    const hue2rgb = (p, q, t) => {
                        if (t < 0) t += 1;
                        if (t > 1) t -= 1;
                        if (t < 1/6) return p + (q - p) * 6 * t;
                        if (t < 1/2) return q;
                        if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
                        return p;
                    };
                    
                    let rComp, gComp, bComp;
                    if (s === 0) {
                        rComp = gComp = bComp = l;
                    } else {
                        const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
                        const p = 2 * l - q;
                        rComp = hue2rgb(p, q, h + 1/3);
                        gComp = hue2rgb(p, q, h);
                        bComp = hue2rgb(p, q, h - 1/3);
                    }
                    
                    // Convert to hex
                    const toHex = (x) => {
                        const hex = Math.round(x * 255).toString(16);
                        return hex.length === 1 ? '0' + hex : hex;
                    };
                    
                    return '#' + toHex(rComp) + toHex(gComp) + toHex(bComp);
                },
                
                // Navigation
                nextStep() {
                    if (this.currentStep < 4) {
                        this.currentStep++;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                },
                
                prevStep() {
                    if (this.currentStep > 1) {
                        this.currentStep--;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                },
                
                // Form validation before submit
                validateBeforeSubmit(event) {
                    // Validate acronym is not empty
                    if (!this.acronymInput || this.acronymInput.trim() === '') {
                        event.preventDefault();
                        alert('Please enter an organization acronym.');
                        this.currentStep = 1;
                        return false;
                    }
                    
                    // Validate colors
                    if (!/^#[0-9A-Fa-f]{6}$/.test(this.primaryColorHex)) {
                        event.preventDefault();
                        alert('Please enter a valid hex color for Primary Color (e.g., #8238B3)');
                        this.currentStep = 4;
                        return false;
                    }
                    
                    if (!/^#[0-9A-Fa-f]{6}$/.test(this.complementaryColorHex)) {
                        event.preventDefault();
                        alert('Please enter a valid hex color for Complementary Color (e.g., #D7AC28)');
                        this.currentStep = 4;
                        return false;
                    }
                    
                    return true;
                }
            }
        }
    </script>
</body>
</html>
