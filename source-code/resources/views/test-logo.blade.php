<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logo Test - {{ organization_name() }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-8 text-center">🧪 Logo Loading Test</h1>
        
        <!-- Test Results -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Cloudflare Logo Test -->
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-semibold mb-4">📡 Cloudflare Logo</h2>
                <div class="text-center">
                    <img src="{{ organization_logo() }}" 
                         alt="{{ organization_name() }} Logo" 
                         class="w-32 h-32 object-contain mx-auto mb-4"
                         id="cloudflare-logo"
                         onerror="document.getElementById('cloudflare-status').innerHTML='❌ FAILED'; document.getElementById('cloudflare-status').className='text-red-600 font-bold'; this.style.border='3px solid red';"
                         onload="document.getElementById('cloudflare-status').innerHTML='✅ SUCCESS'; document.getElementById('cloudflare-status').className='text-green-600 font-bold';">
                    <p id="cloudflare-status" class="text-gray-500">Loading...</p>
                    <p class="text-sm text-gray-600 mt-2 break-all">{{ organization_logo() }}</p>
                </div>
            </div>
            
            <!-- Fallback Logo Test -->
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-semibold mb-4">🏠 Fallback Logo</h2>
                <div class="text-center">
                    <img src="{{ asset('images/soccsyncLogo-removebg-preview.png') }}" 
                         alt="Fallback Logo" 
                         class="w-32 h-32 object-contain mx-auto mb-4"
                         id="fallback-logo"
                         onerror="document.getElementById('fallback-status').innerHTML='❌ FAILED'; document.getElementById('fallback-status').className='text-red-600 font-bold';"
                         onload="document.getElementById('fallback-status').innerHTML='✅ SUCCESS'; document.getElementById('fallback-status').className='text-green-600 font-bold';">
                    <p id="fallback-status" class="text-gray-500">Loading...</p>
                    <p class="text-sm text-gray-600 mt-2">{{ asset('images/soccsyncLogo-removebg-preview.png') }}</p>
                </div>
            </div>
        </div>
        
        <!-- Debug Information -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold mb-4">🔍 Debug Information</h2>
            <div class="space-y-2 text-sm">
                <p><strong>Organization Name:</strong> {{ organization_name() }}</p>
                <p><strong>Current Organization ID:</strong> {{ session('current_organization_id', 'Not Set') }}</p>
                <p><strong>Storage Disk:</strong> {{ config('filesystems.default') }}</p>
                <p><strong>AWS URL:</strong> {{ config('filesystems.disks.s3.url', 'Not Set') }}</p>
                
                @php
                    $currentOrgId = session('current_organization_id');
                    $settings = null;
                    if ($currentOrgId) {
                        $settings = \App\Models\OrganizationSettings::where('organization_id', $currentOrgId)->first();
                    }
                @endphp
                
                @if($settings)
                    <p><strong>Database logo_url:</strong> {{ $settings->getRawOriginal('logo_url') ?? 'NULL' }}</p>
                    <p><strong>Processed logo_url:</strong> {{ $settings->logo_url }}</p>
                @else
                    <p><strong>Organization Settings:</strong> <span class="text-red-600">Not Found</span></p>
                @endif
            </div>
        </div>
        
        <!-- Console Logs -->
        <div class="bg-gray-800 text-green-400 p-4 rounded-lg mt-6">
            <h3 class="text-lg font-semibold mb-2">📋 Console Logs (Check Browser DevTools)</h3>
            <p class="text-sm">Open your browser's Developer Tools (F12) and check the Console tab for detailed loading information.</p>
        </div>
    </div>
    
    <script>
        console.log('🧪 Logo Test Page Loaded');
        console.log('📡 Cloudflare Logo URL:', '{{ organization_logo() }}');
        console.log('🏠 Fallback Logo URL:', '{{ asset('images/soccsyncLogo-removebg-preview.png') }}');
        console.log('⚙️ Storage Disk:', '{{ config('filesystems.default') }}');
        console.log('🌐 AWS URL:', '{{ config('filesystems.disks.s3.url', 'Not Set') }}');
    </script>
</body>
</html>