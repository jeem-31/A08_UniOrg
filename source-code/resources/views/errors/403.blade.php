<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
    <style>
        .ph-spin {
            display: inline-block;
            animation: ph-spin 1s linear infinite;
        }
        @keyframes ph-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
    <style>
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .error-modal {
            animation: slideDown 0.3s ease-out;
        }
        
        .gold-icon {
            color: #D7AC28;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="error-modal max-w-md w-full bg-white shadow-2xl rounded-2xl overflow-hidden">
            <!-- Header with gradient -->
            <div class="bg-gradient-to-r from-red-500 to-red-600 p-6 text-center">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-white rounded-full mb-4">
                    <i class="ph ph-shield text-4xl text-red-500"></i>
                </div>
                <h1 class="text-2xl font-bold text-white">Access Denied</h1>
            </div>
            
            <!-- Content -->
            <div class="p-8 text-center">
                <div class="mb-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
                        <i class="ph ph-prohibit text-3xl text-red-500"></i>
                    </div>
                    <p class="text-gray-700 text-lg mb-2 font-semibold">
                        Permission Required
                    </p>
                    <p class="text-gray-600 text-sm">
                        {{ $exception->getMessage() ?: 'You do not have permission to access this module' }}
                    </p>
                </div>
                
                <!-- Info Box -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-left">
                    <div class="flex items-start">
                        <i class="ph ph-info text-blue-500 mt-1 mr-3"></i>
                        <div class="text-sm text-gray-700">
                            <p class="font-semibold mb-1">Why am I seeing this?</p>
                            <p class="text-xs text-gray-600">Your current role or position does not have access to this module. Please contact your organization administrator if you believe this is an error.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="flex flex-col space-y-3">
                    @if(str_contains($exception->getMessage() ?: '', 'accreditation is renewed'))
                    <a href="{{ route('admin.renew.index') }}" class="w-full bg-gradient-to-r from-red-500 to-red-600 text-white px-6 py-3 rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-200 flex items-center justify-center font-semibold shadow-lg">
                        <i class="ph ph-arrows-clockwise mr-2"></i>
                        Renew Accreditation Now
                    </a>
                    @endif
                    <button onclick="goBack()" class="w-full bg-gradient-to-r from-purple-600 to-purple-700 text-white px-6 py-3 rounded-lg hover:from-purple-700 hover:to-purple-800 transition-all duration-200 flex items-center justify-center font-semibold shadow-lg">
                        <i class="ph ph-arrow-left mr-2 gold-icon"></i>
                        Go Back
                    </button>
                    <a href="{{ route('dashboard') }}" class="w-full bg-gray-100 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-200 transition-all duration-200 flex items-center justify-center font-semibold">
                        <i class="ph ph-house mr-2"></i>
                        Go to Dashboard
                    </a>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-4 text-center border-t border-gray-200">
                <p class="text-xs text-gray-500">
                    <i class="ph ph-lock mr-1"></i>
                    This action has been logged for security purposes
                </p>
            </div>
        </div>
    </div>
    
    <script>
        function goBack() {
            // Check if there's a previous page in history
            if (window.history.length > 1) {
                window.history.back();
            } else {
                // If no history, go to dashboard
                window.location.href = "{{ route('dashboard') }}";
            }
        }
        
        // Auto-redirect after 10 seconds
        let countdown = 10;
        const autoRedirect = setInterval(() => {
            countdown--;
            if (countdown <= 0) {
                clearInterval(autoRedirect);
                goBack();
            }
        }, 1000);
        
        // Clear auto-redirect if user interacts
        document.addEventListener('click', () => {
            clearInterval(autoRedirect);
        });
    </script>
</body>
</html>
