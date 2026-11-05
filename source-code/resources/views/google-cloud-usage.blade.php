<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Cloud Usage - UniOrg</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">
                <i class="ph ph-chart-bar mr-2 text-blue-600"></i>
                Google Cloud Usage Dashboard
            </h1>
            <p class="text-gray-600 mt-2">{{ $usage['month'] }}</p>
        </div>

        <!-- Overall Status -->
        <div class="mb-6 p-6 rounded-lg {{ $withinFreeTier['both'] ? 'bg-green-50 border-2 border-green-500' : 'bg-red-50 border-2 border-red-500' }}">
            <div class="flex items-center">
                <i class="ph {{ $withinFreeTier['both'] ? 'ph-check-circle text-green-600' : 'ph-warning text-red-600' }} text-3xl mr-4"></i>
                <div>
                    <h2 class="text-xl font-bold {{ $withinFreeTier['both'] ? 'text-green-900' : 'text-red-900' }}">
                        {{ $withinFreeTier['both'] ? 'Within Free Tier' : 'Free Tier Exceeded' }}
                    </h2>
                    <p class="{{ $withinFreeTier['both'] ? 'text-green-700' : 'text-red-700' }}">
                        {{ $withinFreeTier['both'] ? 'You are not being charged' : 'You may incur charges' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Usage Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Cloud Vision Usage -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-900">
                        <i class="ph ph-eye mr-2 text-blue-600"></i>
                        Cloud Vision API
                    </h3>
                    @if($usage['vision']['exceeded'])
                        <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-semibold">
                            Exceeded
                        </span>
                    @else
                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-semibold">
                            Free Tier
                        </span>
                    @endif
                </div>

                <!-- Progress Bar -->
                <div class="mb-4">
                    <div class="flex justify-between text-sm text-gray-600 mb-2">
                        <span>{{ number_format($usage['vision']['used']) }} / {{ number_format($usage['vision']['limit']) }} units</span>
                        <span>{{ $usage['vision']['percentage'] }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-4">
                        <div class="h-4 rounded-full transition-all {{ $usage['vision']['exceeded'] ? 'bg-red-500' : ($usage['vision']['percentage'] > 80 ? 'bg-yellow-500' : 'bg-green-500') }}" 
                             style="width: {{ min($usage['vision']['percentage'], 100) }}%"></div>
                    </div>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-2 gap-4 mt-6">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-600">Used</p>
                        <p class="text-2xl font-bold text-blue-600">{{ number_format($usage['vision']['used']) }}</p>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-600">Remaining</p>
                        <p class="text-2xl font-bold text-green-600">{{ number_format($usage['vision']['remaining']) }}</p>
                    </div>
                </div>

                @if($usage['vision']['exceeded'])
                    <div class="mt-4 p-4 bg-red-50 rounded-lg">
                        <p class="text-sm text-red-700 font-semibold">
                            <i class="ph ph-currency-dollar mr-1"></i>
                            Estimated Cost: ${{ number_format($usage['vision']['estimated_cost'], 2) }}
                        </p>
                        <p class="text-xs text-red-600 mt-1">
                            Overage: {{ number_format($usage['vision']['used'] - $usage['vision']['limit']) }} units × $1.50/1000
                        </p>
                    </div>
                @endif
            </div>

            <!-- Cloud NLP Usage -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-900">
                        <i class="ph ph-brain mr-2 text-purple-600"></i>
                        Cloud NLP API
                    </h3>
                    @if($usage['nlp']['exceeded'])
                        <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-semibold">
                            Exceeded
                        </span>
                    @else
                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-semibold">
                            Free Tier
                        </span>
                    @endif
                </div>

                <!-- Progress Bar -->
                <div class="mb-4">
                    <div class="flex justify-between text-sm text-gray-600 mb-2">
                        <span>{{ number_format($usage['nlp']['used']) }} / {{ number_format($usage['nlp']['limit']) }} units</span>
                        <span>{{ $usage['nlp']['percentage'] }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-4">
                        <div class="h-4 rounded-full transition-all {{ $usage['nlp']['exceeded'] ? 'bg-red-500' : ($usage['nlp']['percentage'] > 80 ? 'bg-yellow-500' : 'bg-purple-500') }}" 
                             style="width: {{ min($usage['nlp']['percentage'], 100) }}%"></div>
                    </div>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-2 gap-4 mt-6">
                    <div class="bg-purple-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-600">Used</p>
                        <p class="text-2xl font-bold text-purple-600">{{ number_format($usage['nlp']['used']) }}</p>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-600">Remaining</p>
                        <p class="text-2xl font-bold text-green-600">{{ number_format($usage['nlp']['remaining']) }}</p>
                    </div>
                </div>

                @if($usage['nlp']['exceeded'])
                    <div class="mt-4 p-4 bg-red-50 rounded-lg">
                        <p class="text-sm text-red-700 font-semibold">
                            <i class="ph ph-currency-dollar mr-1"></i>
                            Estimated Cost: ${{ number_format($usage['nlp']['estimated_cost'], 2) }}
                        </p>
                        <p class="text-xs text-red-600 mt-1">
                            Overage: {{ number_format($usage['nlp']['used'] - $usage['nlp']['limit']) }} units × $1.00/1000
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Info Box -->
        <div class="mt-8 bg-blue-50 border-l-4 border-blue-500 p-6 rounded-lg">
            <h3 class="text-lg font-bold text-blue-900 mb-2">
                <i class="ph ph-info mr-2"></i>
                About Usage Tracking
            </h3>
            <ul class="text-sm text-blue-800 space-y-2">
                <li><i class="ph ph-check mr-2"></i> Usage is tracked automatically when you use the APIs</li>
                <li><i class="ph ph-check mr-2"></i> Resets at the beginning of each month</li>
                <li><i class="ph ph-check mr-2"></i> Cloud Vision: 1 unit = 1 page processed</li>
                <li><i class="ph ph-check mr-2"></i> Cloud NLP: 1 unit = 1 text analysis request</li>
                <li><i class="ph ph-check mr-2"></i> Check logs for detailed usage information</li>
            </ul>
        </div>

        <!-- Actions -->
        <div class="mt-6 flex gap-4">
            <a href="{{ url('/') }}" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                <i class="ph ph-house mr-2"></i>
                Back to Dashboard
            </a>
            <a href="https://console.cloud.google.com/billing" target="_blank" class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                <i class="ph ph-arrow-square-out mr-2"></i>
                View in Google Cloud Console
            </a>
        </div>
    </div>
</body>
</html>
