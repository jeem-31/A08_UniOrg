@if(request()->boolean('fragment'))
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Student')</title>
    @stack('styles')
</head>
<body class="bg-gray-50">
    <main class="py-4 sm:py-8 min-h-screen">
        @yield('content')
        @stack('scripts')
    </main>
</body>
</html>
@else
<!DOCTYPE html>
<html lang="en">
<head>
    <x-theme-provider />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Cache-Control" content="public, max-age=31536000">
    <title>@yield('title', organization_name() . ' - CCS LSPU')</title>
    <link rel="icon" type="image/png" href="{{ organization_logo() }}">
    
    <!-- Preload critical images to prevent reloading -->
    <link rel="preload" href="{{ organization_logo() }}" as="image" fetchpriority="high">    @auth
        @if(auth()->user()->photo)
            <link rel="preload" href="{{ storage_url(auth()->user()->photo) }}" as="image" fetchpriority="high">
        @endif
    @endauth
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Global Storage URL Helper
        window.storageUrl = function(path) {
            if (!path) return '';
            path = path.replace(/^storage\//, '');
            @if(config('filesystems.default') === 's3')
                return '{{ config('filesystems.disks.s3.url') }}/' + path;
            @else
                return '{{ asset('storage') }}/' + path;
            @endif
        };

        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#7c3aed',
                        secondary: '#6d28d9',
                    }
                }
            }
        }
    </script>
    <!-- Preconnect to CDNs for faster loading -->
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://code.jquery.com">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
    <link rel="stylesheet" href="{{ asset('css/neumorphism.css') }}">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="{{ asset('js/webauthn.js') }}"></script>
    
    @if(auth()->check() && auth()->user()->isEffectivelyAdmin())
        <link rel="stylesheet" href="{{ asset('css/admin-floating-chat.css') }}">
    @endif
    <style>
        .btn-primary {
            background-color: #7c3aed;
            border-radius: 0.5rem;
        }
        .btn-primary:hover {
            background-color: #6d28d9;
        }
        /* Tab underline animation */
        .nav-tabs-container {
            position: relative;
        }
        .nav-link {
            transition: color 0.3s ease;
        }
        .tab-indicator {
            position: absolute;
            bottom: 0;
            height: 2px;
            background-color: var(--primary-color);
            transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        img {
            backface-visibility: hidden;
            transform: translateZ(0);
            will-change: transform;
        }
        
        /* Specifically for navigation images */
        nav img,
        .nav img,
        .navigation img,
        .sticky img {
            pointer-events: auto;
            -webkit-backface-visibility: hidden;
            -moz-backface-visibility: hidden;
            -ms-backface-visibility: hidden;
            backface-visibility: hidden;
            -webkit-transform: translate3d(0, 0, 0);
            -moz-transform: translate3d(0, 0, 0);
            -ms-transform: translate3d(0, 0, 0);
            transform: translate3d(0, 0, 0);
        }
        
        /* Specific to organization logo */
        img[alt*="Logo"] {
            -webkit-backface-visibility: hidden;
            -moz-backface-visibility: hidden;
            -ms-backface-visibility: hidden;
            backface-visibility: hidden;
            -webkit-transform: translate3d(0, 0, 0);
            -moz-transform: translate3d(0, 0, 0);
            -ms-transform: translate3d(0, 0, 0);
            transform: translate3d(0, 0, 0);
            will-change: transform;
        }
        
        /* Navigation logo and profile image optimization */
        .nav-logo,
        .nav-profile-img {
            pointer-events: auto;
            -webkit-backface-visibility: hidden;
            -moz-backface-visibility: hidden;
            -ms-backface-visibility: hidden;
            backface-visibility: hidden;
            -webkit-transform: translate3d(0, 0, 0);
            -moz-transform: translate3d(0, 0, 0);
            -ms-transform: translate3d(0, 0, 0);
            transform: translate3d(0, 0, 0);
            will-change: transform;
            mix-blend-mode: multiply;
            filter: contrast(1.1); /* Slightly sharpen without artifacts */
            /* Prevent image from disappearing during load */
            opacity: 1 !important;
            visibility: visible !important;
            display: inline-block !important;
        }
        
        /* Ensure logo container maintains space */
        .nav-logo {
            min-width: 2.5rem;
            min-height: 2.5rem;
        }

        /* Student active state uses color only; no underline indicators. */
        #mainNavTabs .tab-indicator,
        .student-dropdown-nav-item::after {
            display: none !important;
        }

        a[data-student-navigation].active,
        a[data-student-navigation].student-navigation-active,
        a[data-student-navigation].active .tab-text,
        a[data-student-navigation].student-navigation-active .tab-text,
        button[data-student-dropdown-parent].student-navigation-active,
        button[data-student-dropdown-parent].student-navigation-active .tab-text {
            color: var(--primary-color) !important;
        }

        a[data-student-navigation].active i,
        a[data-student-navigation].student-navigation-active i,
        button[data-student-dropdown-parent].student-navigation-active i {
            color: var(--primary-color) !important;
        }

        /* Keep icon color in sync with the active route during SPA navigation. */
        nav.bg-white a[data-student-navigation].active > .ph,
        nav.bg-white a[data-student-navigation].student-navigation-active > .ph,
        nav.bg-white button[data-student-dropdown-parent].student-navigation-active > .ph {
            color: var(--primary-color) !important;
        }

        nav.bg-white a[data-student-navigation]:not(.active):not(.student-navigation-active) > .ph {
            color: var(--complementary-color) !important;
        }

        .student-dropdown-nav-item {
            height: auto !important;
        }

        .student-dropdown-bridge {
            top: 100% !important;
            height: 0.5rem !important;
        }

        .student-dropdown-menu {
            top: calc(100% - 8rem) !important;
            margin-top: 0 !important;
            z-index: 20 !important;
        }

        @media (min-width: 640px) {
            .student-dropdown-bridge {
                height: 3rem !important;
            }

            .student-dropdown-menu {
                top: calc(100% + 1rem) !important;
                transform: none !important;
            }
        }

        .student-dropdown-bridge {
            top: 100% !important;
            height: 2rem !important;
        }

        .student-dropdown-menu {
            margin-top: 0 !important;
            z-index: 20 !important;
            transform: none !important;
        }

        @media (max-width: 639px) {
            .student-dropdown-menu {
                transform: translateY(-0.75rem) !important;
            }
        }

        .student-dropdown-nav-item .student-dropdown-menu {
            transform: translateY(-1.5rem) !important;
        }

        @media (min-width: 640px) {
            .student-dropdown-nav-item .student-dropdown-menu {
                transform: translateY(1rem) !important;
            }
        }

        .student-dropdown-nav-item .student-dropdown-menu {
            position: absolute !important;
            top: calc(100% - 0.5rem) !important;
            right: auto !important;
            left: 0 !important;
            margin-top: 0 !important;
            transform: none !important;
        }

        @media (max-width: 1024px) {
            .student-dropdown-nav-item .student-dropdown-menu {
                position: absolute !important;
                top: calc(100% - 0.5rem) !important;
                right: auto !important;
                left: 0 !important;
                transform: none !important;
            }
        }

        button[data-student-dropdown-parent] {
            border: 0 !important;
            border-bottom: 0 !important;
        }

        button[data-student-dropdown-parent].student-navigation-active {
            color: var(--primary-color) !important;
        }

        button[data-student-dropdown-parent].student-dropdown-inactive {
            color: #374151 !important;
            border-bottom: none !important;
        }

        button[data-student-dropdown-parent].student-navigation-active .tab-text,
        button[data-student-dropdown-parent].student-navigation-active i.ph-caret-down,
        button[data-student-dropdown-parent].student-navigation-active i.ph-caret-down {
            color: var(--primary-color) !important;
        }

        button[data-student-dropdown-parent].student-dropdown-inactive .tab-text,
        button[data-student-dropdown-parent].student-dropdown-inactive i.ph-caret-down,
        button[data-student-dropdown-parent].student-dropdown-inactive i.ph-caret-down {
            color: #374151 !important;
        }

        a[data-student-navigation].student-navigation-active.flex {
            background-color: #f9fafb !important;
            border-right: 3px solid var(--primary-color) !important;
        }

        /* Student SPA navigation skeleton. */
        .student-navigation-skeleton {
            display: none;
            position: fixed;
            top: 4rem;
            right: 0;
            bottom: 0;
            left: 0;
            z-index: 40;
            overflow-y: auto;
            padding: 2rem 1rem;
            background: rgba(249, 250, 251, 0.97);
        }

        .student-navigation-skeleton.is-visible {
            display: block;
        }

        body:has(#student-navigation-skeleton.is-visible),
        main:has(#student-navigation-skeleton.is-visible) {
            background: #f5f5f7 !important;
        }

        .student-navigation-skeleton.is-visible {
            background: #f5f5f7 !important;
        }

        .student-navigation-skeleton-page {
            display: none;
        }

        .student-navigation-skeleton-page.is-active {
            display: block;
        }

        .student-navigation-skeleton-block {
            background: #e5e7eb;
            border-radius: 0.5rem;
        }

        .student-navigation-skeleton-pulse {
            animation: student-navigation-skeleton-pulse 1.4s ease-in-out infinite;
        }

        .student-election-skeleton {
            background: #f5f5f7 !important;
            min-height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Segoe UI", sans-serif;
        }

        .student-election-skeleton .student-navigation-skeleton-block {
            background: #e5e5ea;
            border-radius: 10px;
        }

        .student-election-skeleton .rounded-xl,
        .student-election-skeleton .rounded-lg {
            border-radius: 14px !important;
        }

        .student-election-skeleton .rounded-full {
            border-radius: 6px !important;
        }

        .student-edushare-skeleton {
            background: #E9EDF3 !important;
            min-height: 100%;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Segoe UI", sans-serif;
        }

        .student-navigation-skeleton:has(.student-edushare-skeleton.is-active) {
            background: #E9EDF3 !important;
        }

        .student-edushare-skeleton .student-navigation-skeleton-block {
            background: rgba(163, 177, 198, .55);
            border-radius: .6rem;
        }

        .student-edushare-skeleton .bg-white.rounded-lg {
            border: 0 !important;
            background: #E9EDF3 !important;
            box-shadow: 8px 8px 16px rgba(163, 177, 198, .6), -8px -8px 16px rgba(255, 255, 255, .85) !important;
            border-radius: 1.25rem !important;
        }

        .student-neu-skeleton {
            min-height: 100%;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Segoe UI", sans-serif;
        }

        .student-navigation-skeleton:has(.student-neu-skeleton.is-active) {
            background: #E9EDF3 !important;
        }

        .student-neu-skeleton .student-navigation-skeleton-block {
            background: rgba(163, 177, 198, .55);
            border-radius: .6rem;
        }

        .student-neu-skeleton .rounded-full { border-radius: 999px !important; }

        .student-neu-skeleton .bg-gray-50,
        .student-neu-skeleton .bg-slate-50,
        .student-neu-skeleton .bg-slate-50\/70 {
            background: transparent !important;
        }

        .student-neu-skeleton .bg-white.rounded-lg,
        .student-neu-skeleton .bg-white.rounded-xl,
        .student-neu-skeleton .bg-white.rounded-2xl,
        .student-neu-skeleton .bg-white.rounded-3xl,
        .student-neu-skeleton .bg-white\/70.rounded-3xl,
        .student-neu-skeleton .bg-gray-200.rounded-lg {
            border: 0 !important;
            background: #E9EDF3 !important;
            box-shadow: 8px 8px 16px rgba(163, 177, 198, .6), -8px -8px 16px rgba(255, 255, 255, .85) !important;
            border-radius: 1.25rem !important;
        }

        .student-neu-skeleton .border-gray-100,
        .student-neu-skeleton .border-gray-200,
        .student-neu-skeleton .border-slate-100,
        .student-neu-skeleton .border-slate-200 {
            border-color: rgba(163, 177, 198, .45) !important;
        }

        @media (prefers-reduced-motion: reduce) {
            .student-navigation-skeleton-pulse {
                animation: none;
            }
        }

        @keyframes student-navigation-skeleton-pulse {
            0%, 100% { opacity: 0.55; }
            50% { opacity: 1; }
        }

        @media (min-width: 640px) {
            .student-navigation-skeleton {
                padding: 2rem;
            }
        }

        .ph-spin {
            display: inline-block;
            animation: ph-spin 1s linear infinite;
        }
        @keyframes ph-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* ===== Neumorphic student navigation ===== */
        nav.bg-white {
            --neu-bg: #E9EDF3;
            --neu-text: #2E3A4C;
            --neu-muted: #5A6B80;
            --neu-faint: #7C8A9E;
            --neu-divider: rgba(163, 177, 198, .45);
            --neu-dark-shadow: rgba(163, 177, 198, .6);
            --neu-light-shadow: rgba(255, 255, 255, .85);
            background: var(--neu-bg) !important;
            box-shadow: 0 8px 20px rgba(163, 177, 198, .45), 0 2px 6px rgba(255, 255, 255, .85) !important;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        nav.bg-white a[data-student-navigation] {
            border-radius: 999px;
            padding: .5rem .9rem !important;
            transition: box-shadow .2s ease, background-color .2s ease, color .2s ease;
        }

        nav.bg-white a[data-student-navigation]:not(.active):not(.student-navigation-active) { color: var(--neu-text); }
        nav.bg-white a[data-student-navigation].active,
        nav.bg-white a[data-student-navigation].student-navigation-active {
            background: var(--neu-bg);
            box-shadow: inset 3px 3px 7px rgba(163, 177, 198, .45), inset -3px -3px 7px rgba(255, 255, 255, .85) !important;
        }

        nav.bg-white a[data-student-navigation]:not(.active):not(.student-navigation-active):hover {
            background: rgba(var(--primary-color-rgb, 124, 58, 237), .08);
            box-shadow: 3px 3px 7px rgba(163, 177, 198, .4), -3px -3px 7px rgba(255, 255, 255, .9);
        }

        nav.bg-white [data-notification-bell] {
            border-radius: 999px;
            background: var(--neu-bg);
            box-shadow: 3px 3px 7px rgba(163, 177, 198, .5), -3px -3px 7px rgba(255, 255, 255, .9);
            color: var(--neu-text) !important;
            transition: box-shadow .2s ease, color .2s ease;
        }

        nav.bg-white [data-notification-bell]:hover {
            box-shadow: inset 2px 2px 5px rgba(163, 177, 198, .5), inset -2px -2px 5px rgba(255, 255, 255, .85);
            color: var(--primary-color) !important;
        }

        nav.bg-white button[aria-label="Toggle navigation menu"] {
            border-radius: 999px;
            background: var(--neu-bg);
            box-shadow: 3px 3px 7px rgba(163, 177, 198, .5), -3px -3px 7px rgba(255, 255, 255, .9);
            transition: box-shadow .2s ease;
        }

        nav.bg-white button[aria-label="Toggle navigation menu"]:hover {
            box-shadow: inset 2px 2px 5px rgba(163, 177, 198, .5), inset -2px -2px 5px rgba(255, 255, 255, .85);
        }

        nav.bg-white button[data-student-dropdown-parent] {
            border-radius: 999px;
            padding: .3rem .6rem .3rem .3rem;
            transition: box-shadow .2s ease, background-color .2s ease;
        }

        nav.bg-white button[data-student-dropdown-parent].student-dropdown-inactive { color: var(--neu-text) !important; }
        nav.bg-white button[data-student-dropdown-parent].student-navigation-active {
            background: var(--neu-bg);
            box-shadow: inset 3px 3px 7px rgba(163, 177, 198, .45), inset -3px -3px 7px rgba(255, 255, 255, .85) !important;
        }
        nav.bg-white button[data-student-dropdown-parent]:hover {
            background: var(--neu-bg);
            box-shadow: 3px 3px 7px rgba(163, 177, 198, .5), -3px -3px 7px rgba(255, 255, 255, .9);
        }

        nav.bg-white .student-dropdown-menu,
        nav.bg-white .student-nav-dropdown {
            background: var(--neu-bg) !important;
            border-radius: 1rem !important;
            box-shadow: 5px 5px 12px rgba(163, 177, 198, .28), -5px -5px 12px rgba(255, 255, 255, .45), 0 10px 22px rgba(17, 17, 19, .07) !important;
            overflow: hidden;
        }

        nav.bg-white .student-dropdown-menu a,
        nav.bg-white .student-dropdown-menu form button {
            color: var(--neu-text) !important;
        }
        nav.bg-white .student-dropdown-menu hr { border-color: var(--neu-divider) !important; }
        nav.bg-white .student-dropdown-menu a:hover,
        nav.bg-white .student-dropdown-menu form button:hover {
            background: rgba(var(--primary-color-rgb, 124, 58, 237), .07) !important;
            color: var(--primary-color) !important;
        }

        nav.bg-white .student-nav-dropdown .border-b,
        nav.bg-white .student-nav-dropdown .border-gray-200,
        nav.bg-white .student-nav-dropdown .divide-gray-200 > :not([hidden]) ~ :not([hidden]) {
            border-color: var(--neu-divider) !important;
        }
        nav.bg-white .student-nav-dropdown h3 { color: var(--neu-text) !important; }
        nav.bg-white .student-nav-dropdown [data-notification-item] { transition: background-color .2s ease; }
        nav.bg-white .student-nav-dropdown [data-notification-item]:hover { background: rgba(var(--primary-color-rgb, 124, 58, 237), .05) !important; }
        nav.bg-white .student-nav-dropdown [data-notification-item].notif-unread { background: rgba(var(--primary-color-rgb, 124, 58, 237), .06) !important; }

        nav.bg-white .student-mobile-drawer {
            background: var(--neu-bg) !important;
            border-left: 1px solid var(--neu-divider) !important;
            box-shadow: -10px 0 24px rgba(163, 177, 198, .4) !important;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        nav.bg-white .student-mobile-drawer a[data-student-navigation] {
            border-radius: 0;
            padding: .75rem 1rem !important;
            color: var(--neu-text) !important;
            border-right: 3px solid transparent !important;
        }
        nav.bg-white .student-mobile-drawer a[data-student-navigation].active,
        nav.bg-white .student-mobile-drawer a[data-student-navigation].student-navigation-active {
            background: rgba(var(--primary-color-rgb, 124, 58, 237), .07) !important;
            color: var(--primary-color) !important;
            border-right: 3px solid var(--primary-color) !important;
        }
        nav.bg-white .student-mobile-drawer a:hover { background: rgba(var(--primary-color-rgb, 124, 58, 237), .06) !important; }
        nav.bg-white .student-mobile-drawer a.bg-gray-50 { background: rgba(var(--primary-color-rgb, 124, 58, 237), .07) !important; }
        nav.bg-white .student-mobile-drawer .border-gray-100 { border-color: var(--neu-divider) !important; }

        nav.bg-white .student-logout-modal {
            background: var(--neu-bg) !important;
            border-radius: 1.25rem !important;
            box-shadow: 0 18px 44px rgba(17, 17, 19, .18) !important;
        }
        nav.bg-white .student-logout-modal h3 { color: var(--neu-text) !important; }
        nav.bg-white .student-logout-modal p { color: var(--neu-muted) !important; }
        nav.bg-white .student-logout-modal .bg-gray-100 {
            background: var(--neu-bg) !important;
            color: var(--neu-text) !important;
            box-shadow: 4px 4px 8px rgba(163, 177, 198, .5), -4px -4px 8px rgba(255, 255, 255, .85) !important;
        }
        nav.bg-white .student-logout-modal .bg-gray-100:hover {
            box-shadow: inset 3px 3px 6px rgba(163, 177, 198, .5), inset -3px -3px 6px rgba(255, 255, 255, .85) !important;
        }
        nav.bg-white .student-logout-modal [style*="background-color: rgba(130, 56, 179, 0.1)"] {
            box-shadow: 4px 4px 8px rgba(var(--primary-color-rgb, 130, 56, 179), .22), -4px -4px 8px rgba(255, 255, 255, .85) !important;
        }

        nav.bg-white a:focus-visible,
        nav.bg-white button:focus-visible {
            outline: 3px solid rgba(var(--primary-color-rgb, 124, 58, 237), .35);
            outline-offset: 2px;
        }

        @media (prefers-reduced-motion: reduce) {
            nav.bg-white a[data-student-navigation],
            nav.bg-white [data-notification-bell],
            nav.bg-white button[aria-label="Toggle navigation menu"],
            nav.bg-white button[data-student-dropdown-parent] { transition-duration: .01ms !important; }
        }
    </style>
    @stack('styles')
    
    <script>
        // Aggressive image caching using localStorage
        (function() {
            const cacheImages = {
                logo: "{{ organization_logo() }}",
                @auth
                    @if(auth()->user()->photo)
                    profile: "{{ storage_url(auth()->user()->photo) }}",
                    @endif
                @endauth
            };
            
            // Force browser to cache images aggressively
            Object.values(cacheImages).forEach(src => {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.src = src;
                
                // Add to browser cache with multiple requests
                setTimeout(() => {
                    const img2 = new Image();
                    img2.src = src;
                }, 10);
                
                setTimeout(() => {
                    const img3 = new Image();
                    img3.src = src;
                }, 50);
            });
            
            // Store in sessionStorage for persistence
            sessionStorage.setItem('navImagesCached', JSON.stringify(cacheImages));
        })();
    </script>
</head>
<body class="bg-gray-50">
    @include('layouts.navigation')

    <!-- Success Toast Notification -->
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

    <main class="py-4 sm:py-8 min-h-screen">
        @yield('content')
    </main>

    <div id="student-navigation-skeleton" class="student-navigation-skeleton" aria-hidden="true" aria-live="polite">
        <span data-student-skeleton-title class="sr-only">Loading page...</span>

        <!-- Generic fallback for other student pages -->
        <div data-student-skeleton-page="default" class="student-navigation-skeleton-page">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-8 w-64 mb-3"></div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-80 max-w-full"></div>
                    </div>
                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-10 w-36"></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    @for($card = 0; $card < 3; $card++)
                        <div class="bg-white rounded-lg shadow p-5 space-y-4">
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-40 w-full"></div>
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-5 w-3/4"></div>
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-1/2"></div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        <!-- Student homepage -->
        <div data-student-skeleton-page="dashboard" class="student-navigation-skeleton-page">
            <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-5 sm:py-7">
                <div class="mb-6 space-y-2">
                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-40"></div>
                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-10 w-72 max-w-full"></div>
                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-80 max-w-full"></div>
                </div>

                <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-36 sm:h-40 w-full rounded-2xl mb-5"></div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-7">
                    @for($action = 0; $action < 4; $action++)
                        <div class="bg-white border border-gray-200 p-4 rounded-2xl flex items-center gap-3">
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse w-10 h-10 rounded-xl flex-shrink-0"></div>
                            <div class="space-y-2 min-w-0"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-20 max-w-full"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-24 max-w-full"></div></div>
                        </div>
                    @endfor
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1.55fr)_minmax(330px,.85fr)] gap-5 items-start">
                    <div class="space-y-5">
                        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                            <div class="px-5 sm:px-6 py-4 border-b border-gray-100 flex justify-between"><div class="space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-20"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-6 w-48"></div></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-14"></div></div>
                            @for($announcement = 0; $announcement < 3; $announcement++)
                                <div class="px-5 sm:px-6 py-4 flex gap-3.5 border-b border-gray-100 last:border-0"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse w-14 h-14 rounded-xl flex-shrink-0"></div><div class="flex-1 space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-28"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-5 w-3/4 max-w-full"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-full"></div></div></div>
                            @endfor
                        </div>
                        <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6"><div class="flex justify-between mb-5"><div class="space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-24"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-6 w-44"></div></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-20"></div></div><div class="space-y-5"><div class="flex gap-4"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse w-4 h-4 rounded-full"></div><div class="flex-1 space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-24"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-5 w-2/3"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-1/2"></div></div></div><div class="flex gap-4"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse w-4 h-4 rounded-full"></div><div class="flex-1 space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-24"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-5 w-3/4"></div></div></div></div></div>
                    </div>
                    <aside class="space-y-5"><div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 space-y-5"><div class="flex gap-3 items-center"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse w-11 h-11 rounded-xl"></div><div class="space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-20"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-6 w-36"></div></div></div><div class="space-y-4"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-full"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-full"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-full"></div></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-11 w-full rounded-xl"></div></div><div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 space-y-4"><div class="flex justify-between"><div class="space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-24"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-6 w-40"></div></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse w-8 h-8 rounded-lg"></div></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-16 w-full rounded-xl"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-12 w-full"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-12 w-full"></div></div></aside>
                </div>
            </div>
        </div>

        <!-- Student payments -->
        <div data-student-skeleton-page="payments" class="student-navigation-skeleton-page student-neu-skeleton">
            <div class="min-h-screen -my-4 bg-gray-50 py-4 sm:-my-8 sm:py-8">
                <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-8 sm:py-12">
                    <div class="mb-9 flex flex-col gap-5 sm:mb-12 sm:flex-row sm:items-center sm:justify-between">
                        <div class="space-y-3">
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-28"></div>
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-14 w-72 max-w-full rounded-xl"></div>
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-96 max-w-full"></div>
                        </div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-11 w-44 rounded-full"></div>
                    </div>
                    <section class="rounded-3xl border border-gray-200 bg-white/70 p-5 shadow-sm sm:p-8">
                        <div class="mb-6 space-y-3">
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-24"></div>
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-8 w-56 max-w-full"></div>
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-96 max-w-full"></div>
                        </div>
                        <div class="grid gap-4 sm:max-w-md">
                            <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
                                <div class="mb-6 flex items-start justify-between gap-3">
                                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-6 w-36"></div>
                                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-6 w-16 rounded-full"></div>
                                </div>
                                <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse mb-6 h-28 w-full rounded-2xl"></div>
                                <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-12 w-full rounded-xl"></div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <!-- Student election overview: mirrors the featured election and history layout. -->
        <div data-student-skeleton-page="elections-index" class="student-navigation-skeleton-page student-election-skeleton student-neu-skeleton">
            <div class="min-h-screen -mt-8 bg-slate-50">
                <div class="mx-auto max-w-7xl px-3 py-6 sm:px-6 sm:py-8 lg:px-8">
                    <div class="mb-6 border-b border-slate-200 pb-5 sm:mb-7">
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse mb-3 h-3 w-28"></div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse mb-2 h-9 w-48 max-w-full"></div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-96 max-w-full"></div>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" style="border-top: 1px solid rgba(var(--primary-color-rgb), 0.38);">
                        <div class="border-b border-slate-100 px-5 py-5 sm:px-7 sm:py-6">
                            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0 flex-1 space-y-3">
                                    <div class="flex gap-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-5 w-28 rounded-full" style="background-color: rgba(var(--primary-color-rgb), 0.14);"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-5 w-24 rounded-full"></div></div>
                                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-8 w-3/4 max-w-xl"></div>
                                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-36"></div>
                                    <div class="space-y-2 pt-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-full max-w-3xl"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-4/5 max-w-2xl"></div></div>
                                </div>
                                <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-10 w-44 rounded-lg" style="background-color: rgba(var(--primary-color-rgb), 0.14);"></div>
                            </div>
                        </div>
                        <div class="grid gap-4 bg-slate-50/70 px-5 py-4 sm:grid-cols-2 sm:px-7 lg:grid-cols-4">
                            @for($detail = 0; $detail < 4; $detail++)
                                <div class="space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-20"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-28"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-16"></div></div>
                            @endfor
                        </div>
                    </div>

                    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 px-5 py-4 sm:px-6"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse mb-2 h-3 w-28"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-6 w-48"></div></div>
                        @for($row = 0; $row < 3; $row++)
                            <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4 last:border-b-0 sm:px-6"><div class="min-w-0 flex-1 space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-2/5 max-w-xs"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-56 max-w-full"></div></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-24"></div></div>
                        @endfor
                    </div>

                    <div class="mt-7 space-y-3"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-28"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-6 w-44"></div></div>
                    <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        @for($history = 0; $history < 3; $history++)
                            <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between sm:px-6"><div class="min-w-0 flex-1 space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-2/5 max-w-xs"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-64 max-w-full"></div></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-24"></div></div>
                        @endfor
                    </div>
                </div>
            </div>
        </div>

        <!-- Student election voting detail: mirrors the header, wizard and ballot rail. -->
        <div data-student-skeleton-page="elections-show" class="student-navigation-skeleton-page student-election-skeleton student-neu-skeleton">
            <div class="min-h-screen -mt-8 bg-slate-50">
                <div class="mx-auto max-w-7xl px-3 py-5 sm:px-6 sm:py-8 lg:px-8">
                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse mb-5 h-4 w-32"></div>
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" style="border-top: 1px solid rgba(var(--primary-color-rgb), 0.38);">
                        <div class="border-b border-slate-100 px-5 py-5 sm:px-7 sm:py-6">
                            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                                <div class="flex min-w-0 flex-1 items-start gap-4"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-12 w-12 shrink-0 rounded-lg" style="background-color: rgba(var(--primary-color-rgb), 0.14);"></div><div class="min-w-0 flex-1 space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-36"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-8 w-3/4 max-w-xl"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-28"></div></div></div>
                                <div class="flex gap-6 border-t border-slate-100 pt-4 sm:border-t-0 sm:pt-0"><div class="space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-14"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-24"></div></div><div class="space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-14"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-24"></div></div></div>
                            </div>
                            <div class="mt-5 space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-full max-w-3xl"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-4/5 max-w-2xl"></div></div>
                        </div>
                    </div>

                    <div class="my-5 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm sm:px-5"><div class="flex items-center justify-between gap-4"><div class="space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-44"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-64 max-w-full"></div></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-2 w-28 rounded-full sm:w-36" style="background-color: rgba(var(--primary-color-rgb), 0.20);"></div></div></div>

                    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
                        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-5 sm:px-7"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-10 w-10 rounded-lg"></div><div class="space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-20"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-6 w-40"></div></div></div>
                            <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 sm:gap-4 sm:p-6 xl:grid-cols-3">
                                @for($candidate = 0; $candidate < 4; $candidate++)
                                    <div class="rounded-lg border border-slate-200 p-4"><div class="flex gap-3"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-12 w-12 shrink-0 rounded-md"></div><div class="min-w-0 flex-1 space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-4/5"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-1/2"></div></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-5 w-5 rounded-full"></div></div></div>
                                @endfor
                            </div>
                            <div class="flex justify-between border-t border-slate-100 bg-slate-50 px-5 py-4 sm:px-7"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-24"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-9 w-32 rounded-lg"></div></div>
                        </div>
                        <aside class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-100 px-5 py-4 space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-5 w-28"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-full"></div></div><div class="space-y-3 p-4">@for($position = 0; $position < 5; $position++)<div class="flex items-center gap-3"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-7 w-7 rounded-full"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 flex-1"></div></div>@endfor</div><div class="border-t border-slate-100 bg-slate-50 p-4"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-10 w-full rounded-lg"></div></div></aside>
                    </div>
                </div>
            </div>
        </div>

        <!-- Announcement list -->
        <div data-student-skeleton-page="announcements-index" class="student-navigation-skeleton-page student-neu-skeleton">
            <div class="min-h-screen -my-4 bg-gray-50 py-4 sm:-my-8 sm:py-8">
                <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-8 sm:py-12">
                    <div class="mb-9 space-y-3 sm:mb-12">
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-9 w-56 rounded-xl"></div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-96 max-w-full"></div>
                    </div>
                    <section class="rounded-3xl border border-gray-200 bg-white/70 p-5 shadow-sm sm:p-8">
                        <div class="mb-6 space-y-3">
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-28"></div>
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-96 max-w-full"></div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                            @for($card = 0; $card < 6; $card++)
                                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-48 w-full rounded-none"></div>
                                    <div class="space-y-3 p-5">
                                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-6 w-4/5"></div>
                                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-full"></div>
                                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-3/4"></div>
                                        <div class="flex justify-between gap-3 border-t border-gray-100 pt-3">
                                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-2/5"></div>
                                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-1/4"></div>
                                        </div>
                                    </div>
                                </div>
                            @endfor
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <!-- Announcement detail -->
        <div data-student-skeleton-page="announcements-show" class="student-navigation-skeleton-page student-neu-skeleton">
            <div class="min-h-screen -my-4 bg-gray-50 py-4 sm:-my-8 sm:py-8">
                <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-8 sm:py-12">
                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse mb-6 h-9 w-48 rounded-full"></div>
                    <div class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm">
                        <div class="grid grid-cols-1 gap-0 lg:h-[600px] lg:grid-cols-2">
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-64 w-full rounded-none sm:h-80 md:h-96 lg:h-full"></div>
                            <div class="space-y-5 p-5 sm:p-8 lg:p-10">
                                <div class="flex gap-4">
                                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-32"></div>
                                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-7 w-7 rounded-full"></div>
                                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-28"></div>
                                </div>
                                <div class="space-y-3 border-b border-gray-200 pb-5">
                                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-9 w-full"></div>
                                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-9 w-4/5"></div>
                                </div>
                                <div class="space-y-3">
                                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-full"></div>
                                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-full"></div>
                                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-3/4"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Event list (calendar is the default view) -->
        <div data-student-skeleton-page="events-index" class="student-navigation-skeleton-page student-neu-skeleton">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-6 sm:mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                    <div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-8 w-36 mb-3"></div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-80 max-w-full"></div>
                    </div>
                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-10 w-28"></div>
                </div>
                <div class="bg-white rounded-lg shadow-lg p-3 sm:p-6 mb-6 sm:mb-8 overflow-hidden">
                    <div class="flex justify-between items-center mb-5">
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-6 w-36"></div>
                        <div class="flex gap-2">
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-8 w-8"></div>
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-8 w-8"></div>
                        </div>
                    </div>
                    <div class="grid grid-cols-7 gap-px bg-gray-200 min-h-[360px]">
                        @for($day = 0; $day < 35; $day++)
                            <div class="bg-white p-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-4"></div></div>
                        @endfor
                    </div>
                </div>
            </div>
        </div>

        <!-- Event detail -->
        <div data-student-skeleton-page="events-show" class="student-navigation-skeleton-page student-neu-skeleton">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-4 sm:mb-6">
                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-5 w-32"></div>
                </div>
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-0 lg:h-[600px]">
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-64 sm:h-80 md:h-96 lg:h-full rounded-none"></div>
                        <div class="p-5 sm:p-8 lg:p-10 space-y-5">
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-44"></div>
                            <div class="space-y-3">
                                <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-9 w-full"></div>
                                <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-9 w-3/4"></div>
                            </div>
                            <div class="pb-5 border-b border-gray-200 space-y-4">
                                <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-12 w-full"></div>
                                <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-12 w-full"></div>
                                <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-12 w-full"></div>
                            </div>
                            <div class="space-y-3">
                                <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-full"></div>
                                <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-4/5"></div>
                            </div>
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-12 w-full"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Events calendar -->
        <div data-student-skeleton-page="events-calendar" class="student-navigation-skeleton-page student-neu-skeleton">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-6 sm:mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                    <div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-8 w-56 mb-3"></div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-80 max-w-full"></div>
                    </div>
                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-10 w-28"></div>
                </div>
                <div class="bg-white rounded-lg shadow-lg p-3 sm:p-6 overflow-hidden">
                    <div class="flex justify-between items-center mb-5">
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-6 w-36"></div>
                        <div class="flex gap-2">
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-8 w-8"></div>
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-8 w-8"></div>
                        </div>
                    </div>
                    <div class="grid grid-cols-7 gap-px bg-gray-200 min-h-[420px]">
                        @for($day = 0; $day < 35; $day++)
                            <div class="bg-white p-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-4"></div></div>
                        @endfor
                    </div>
                </div>
            </div>
        </div>

        <!-- EduShare -->
        <div data-student-skeleton-page="edushare" class="student-navigation-skeleton-page student-edushare-skeleton">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-6 sm:mb-8">
                    <div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-8 w-40 mb-3"></div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-56 max-w-full"></div>
                    </div>
                    <div class="flex gap-2 sm:gap-3">
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-10 w-40"></div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-11 w-24"></div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-2 mb-6 sm:mb-8">
                    <div class="flex gap-1 sm:gap-2">
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-10 flex-1"></div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-10 flex-1"></div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-10 flex-1"></div>
                    </div>
                </div>
                <div class="mb-8">
                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-6 w-28 mb-4"></div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
                        @for($folder = 0; $folder < 4; $folder++)
                            <div class="bg-white rounded-lg shadow p-4 border border-gray-200 space-y-3">
                                <div class="flex items-center gap-3"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-10 w-10 rounded-lg"></div><div class="flex-1 space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-3/4"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-1/2"></div></div></div>
                                <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-1/3"></div>
                            </div>
                        @endfor
                    </div>
                </div>
                <div>
                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-6 w-20 mb-4"></div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                        @for($file = 0; $file < 6; $file++)
                            <div class="bg-white rounded-lg shadow p-4 border border-gray-200 space-y-4">
                                <div class="flex items-start gap-3"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-10 w-10 rounded-lg"></div><div class="flex-1 space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-4/5"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-1/3"></div></div></div>
                                <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-1/2"></div>
                                <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-9 w-full rounded-lg"></div>
                            </div>
                        @endfor
                    </div>
                </div>
            </div>
        </div>

        <!-- FAQ and chat -->
        <div data-student-skeleton-page="chatbot" class="student-navigation-skeleton-page student-neu-skeleton">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-6 sm:mb-8">
                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-8 w-56 mb-3"></div>
                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-80 max-w-full"></div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 lg:gap-8">
                    <div class="lg:col-span-2 order-2 lg:order-1 bg-white rounded-lg shadow-lg p-4 sm:p-6">
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-6 w-56 mb-4"></div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-72 sm:h-96 w-full rounded-lg mb-4"></div>
                        <div class="border-2 border-gray-200 rounded-lg p-4 text-center space-y-3">
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-64 max-w-full mx-auto"></div>
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-11 w-56 max-w-full mx-auto rounded-lg"></div>
                        </div>
                    </div>
                    <div class="lg:col-span-1 order-1 lg:order-2">
                        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-6 w-40 mb-4"></div>
                            <div class="space-y-3 h-60 sm:h-80 overflow-hidden">
                                @for($faq = 0; $faq < 5; $faq++)
                                    <div class="border border-gray-200 rounded-lg p-3 space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-full"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-20"></div></div>
                                @endfor
                            </div>
                        </div>
                        <div class="bg-gray-200 rounded-lg p-6 mt-6 space-y-3"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-5 w-32"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-full"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-10 w-full"></div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- e-Library -->
        <div data-student-skeleton-page="library" class="student-navigation-skeleton-page student-neu-skeleton">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-6 sm:mb-8">
                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-8 w-40 mb-3"></div>
                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-96 max-w-full"></div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 sm:p-6 mb-6 sm:mb-8">
                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-10 w-full rounded-lg"></div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                    @for($resource = 0; $resource < 6; $resource++)
                        <div class="bg-white rounded-lg shadow p-4 sm:p-6 space-y-4">
                            <div class="flex items-center gap-3 sm:gap-4"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-12 w-12 rounded-lg"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-5 w-3/4"></div></div>
                            <div class="space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-full"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-4/5"></div></div>
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-10 w-full rounded-lg"></div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        <!-- My Complaints -->
        <div data-student-skeleton-page="complaints" class="student-navigation-skeleton-page student-neu-skeleton">
            <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-6 sm:mb-8">
                    <div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-8 w-56 mb-3"></div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-96 max-w-full"></div>
                    </div>
                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-11 w-40"></div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                    @for($submission = 0; $submission < 6; $submission++)
                        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-2 w-full rounded-none"></div>
                            <div class="p-6 space-y-4">
                                <div class="flex items-center justify-between"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-6 w-24 rounded-lg"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-6 w-8 rounded-lg"></div></div>
                                <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-6 w-4/5"></div>
                                <div class="space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-full"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-3/4"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-1/2"></div></div>
                                <div class="pt-4 border-t border-gray-100 flex justify-between"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-20"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-16"></div></div>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        <!-- Student profile -->
        <div data-student-skeleton-page="profile" class="student-navigation-skeleton-page student-neu-skeleton">
            <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-6 sm:mb-8">
                    <div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-8 w-48 mb-3"></div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-96 max-w-full"></div>
                    </div>
                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-11 w-36"></div>
                </div>
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-24 sm:h-32 w-full rounded-none"></div>
                    <div class="px-4 sm:px-8 pb-6 sm:pb-8">
                        <div class="flex flex-col sm:flex-row sm:items-end -mt-16 sm:-mt-[5.5rem] mb-6 gap-4">
                            <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-24 w-24 sm:h-32 sm:w-32 rounded-full border-4 border-white"></div>
                            <div class="space-y-3"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-7 w-64 max-w-full"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-32"></div></div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                            @for($field = 0; $field < 6; $field++)
                                <div class="space-y-2"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-3 w-24"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-10 w-full rounded-lg"></div></div>
                            @endfor
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manage documents -->
        <div data-student-skeleton-page="documents" class="student-navigation-skeleton-page student-neu-skeleton">
            <div class="min-h-screen py-4 bg-gray-50">
                <div class="max-w-7xl mx-auto px-4">
                    <div class="mb-8 sm:mb-12 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-8 w-56 mb-2"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-72"></div></div>
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-11 w-44"></div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden mb-8">
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-14 w-full rounded-none"></div>
                        <div class="p-4 sm:p-6 space-y-4"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-6 w-56"></div><div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-24 rounded-xl"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-24 rounded-xl"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-24 rounded-xl"></div></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-11 w-full rounded-lg"></div></div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-16 w-full rounded-none"></div>
                        <div class="p-4 sm:p-6"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-7 w-64 mb-6"></div><div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                            @for($document = 0; $document < 4; $document++)
                                <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-48 w-full rounded-none"></div><div class="p-4 space-y-3"><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-5 w-4/5"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-4 w-1/2"></div><div class="student-navigation-skeleton-block student-navigation-skeleton-pulse h-9 w-full rounded-lg"></div></div></div>
                            @endfor
                        </div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('layouts.footer')

    @if(auth()->check() && auth()->user()->isEffectivelyAdmin())
        <!-- Admin Floating Chat Widget -->
        <div id="admin-chat-widget" data-admin-id="{{ auth()->id() }}" data-org-id="{{ session('current_organization_id') }}">
            <div id="admin-chat-button">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2ZM20 16H5.17L4 17.17V4H20V16Z" fill="currentColor"/>
                    <circle cx="7" cy="10" r="1" fill="currentColor"/>
                    <circle cx="12" cy="10" r="1" fill="currentColor"/>
                    <circle cx="17" cy="10" r="1" fill="currentColor"/>
                </svg>
                <span>Messages</span>
                <span id="admin-chat-badge" class="hidden">0</span>
            </div>
            
            <div id="admin-chat-window" class="admin-chat-window-hidden">
                <!-- Left Panel: Student List -->
                <div class="admin-chat-left-panel">
                    <div class="admin-chat-header">
                        <h3>Student Messages</h3>
                        <div class="admin-chat-controls">
                            <button id="close-admin-chat" class="admin-chat-control-btn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M19 6.41L17.59 5L12 10.59L6.41 5L5 6.41L10.59 12L5 17.59L6.41 19L12 13.41L17.59 19L19 17.59L13.41 12L19 6.41Z" fill="currentColor"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div class="admin-chat-contacts">
                        <!-- Students will be loaded here -->
                    </div>
                </div>
                
                <!-- Right Panel: Conversation or Welcome -->
                <div class="admin-chat-right-panel">
                    <div class="admin-chat-welcome">
                        <div class="admin-welcome-icon">
                            <svg width="80" height="60" viewBox="0 0 100 75" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="10" y="15" width="80" height="50" rx="8" fill="#f3f4f6" stroke="#8238B3" stroke-width="2"/>
                                <rect x="15" y="20" width="70" height="30" rx="4" fill="white"/>
                                <rect x="20" y="25" width="25" height="3" rx="1.5" fill="#8238B3"/>
                                <rect x="20" y="30" width="35" height="3" rx="1.5" fill="#8238B3"/>
                                <rect x="20" y="35" width="20" height="3" rx="1.5" fill="#8238B3"/>
                                <circle cx="65" cy="35" r="8" fill="#8238B3"/>
                                <circle cx="65" cy="35" r="3" fill="white"/>
                                <circle cx="67" cy="33" r="1" fill="#8238B3"/>
                                <circle cx="69" cy="35" r="1" fill="#8238B3"/>
                                <circle cx="67" cy="37" r="1" fill="#8238B3"/>
                            </svg>
                        </div>
                        <h4>Student Messages</h4>
                        <p>Respond to student inquiries</p>
                    </div>
                </div>
            </div>
        </div>

        <script src="{{ asset('js/admin-floating-chat.js') }}?v={{ filemtime(public_path('js/admin-floating-chat.js')) }}&build=osas-logo-r2"></script>
    @endif

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Vite Assets for Echo/WebSocket and CSS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const userId = @js(auth()->id());
            if (!userId || !window.Echo || typeof window.Echo.private !== 'function') return;

            window.Echo.private(`student-payments.${userId}`)
                .listen('.student-payment-updated', function () {
                    if (!/^\/payments(?:\/|$)/.test(window.location.pathname)) return;

                    window.clearStudentPageCache?.();
                    window.refreshCurrentStudentPage?.();
                });
        });
    </script>
    
    <!-- Instant Navigation -->
    {{-- <script src="{{ asset('js/instant-navigation.js') }}"></script> --}}
    
    @auth
        @if(auth()->user()->isEffectivelyStudent())
            <script src="{{ asset('js/student-chat-badge.js') }}"></script>
            <script>
                // Notification functions
                function markAsReadAndNavigate(event, notificationId, url) {
                    event.preventDefault();
                    
                    // Mark as read via AJAX
                    fetch(`/notifications/${notificationId}/read`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    }).then(() => {
                        // Navigate to the URL
                        window.location.href = url;
                    });
                }
                
                function deleteNotification(event, notificationId) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    // Delete notification via AJAX (no confirmation)
                    fetch(`/notifications/${notificationId}/delete`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    }).then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Remove notification from DOM with fade effect
                            const notifElement = document.getElementById(`notif-${notificationId}`);
                            if (notifElement) {
                                notifElement.style.transition = 'opacity 0.3s ease';
                                notifElement.style.opacity = '0';
                                setTimeout(() => {
                                    notifElement.remove();
                                }, 300);
                            }
                            
                            // Update badge count
                            const badge = document.querySelector('.ph-bell').parentElement.querySelector('span');
                            if (badge) {
                                const currentCount = parseInt(badge.textContent);
                                const newCount = currentCount - 1;
                                if (newCount > 0) {
                                    badge.textContent = newCount > 9 ? '9+' : newCount;
                                } else {
                                    badge.remove();
                                }
                            }
                        }
                    });
                }
            </script>
        @endif
    @endauth
    
    @stack('scripts')
    
    <!-- Tab indicator initialization -->
    <script>
    function getStudentTabPosition(tab, navTabs) {
        if (!tab || !navTabs) return null;

        const textSpan = tab.querySelector('.tab-text');
        if (!textSpan) return null;

        const textRect = textSpan.getBoundingClientRect();
        const containerRect = navTabs.getBoundingClientRect();

        return {
            left: textRect.left - containerRect.left,
            width: textRect.width
        };
    }

    window.updateStudentTabIndicator = function (pathname = window.location.pathname) {
        const navTabs = document.getElementById('mainNavTabs');
        if (!navTabs) return;

        const indicator = navTabs.querySelector('.tab-indicator');
        const navLinks = Array.from(navTabs.querySelectorAll('a.nav-link'));
        if (!indicator) return;

        const targetPath = new URL(pathname, window.location.origin).pathname;
        const activeTab = navLinks.find(link => {
            return new URL(link.href, window.location.origin).pathname === targetPath;
        });
        const position = getStudentTabPosition(activeTab, navTabs);

        if (!position) {
            indicator.style.opacity = '0';
            return;
        }

        indicator.style.opacity = '1';
        indicator.style.left = `${position.left}px`;
        indicator.style.width = `${position.width}px`;
    };

    function initTabIndicator() {
        const navTabs = document.getElementById('mainNavTabs');
        if (!navTabs) return;

        // Keep the indicator tied to the active route. Hovering another tab
        // must not temporarily move it away from the selected page.
        window.updateStudentTabIndicator(window.__studentPendingNavigationPath || window.location.pathname);
        window.addEventListener('resize', () => {
            window.updateStudentTabIndicator(window.__studentPendingNavigationPath || window.location.pathname);
        });
    }

    document.addEventListener('DOMContentLoaded', initTabIndicator);
    </script>
    
    <!-- Global Form Loading Handler -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle all forms with loading animation (except those with data-no-loading attribute)
        const forms = document.querySelectorAll('form:not([data-no-loading])');
        
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitButton = this.querySelector('button[type="submit"]');
                
                // Only apply if button exists and is not already disabled
                if (submitButton && !submitButton.disabled && !submitButton.hasAttribute('data-loading-applied')) {
                    // Mark as applied to prevent double-application
                    submitButton.setAttribute('data-loading-applied', 'true');
                    
                    // Disable button permanently to prevent double-click
                    submitButton.disabled = true;
                    submitButton.style.opacity = '0.6';
                    submitButton.style.cursor = 'not-allowed';
                    
                    // Get the icon element (first <i> tag in button)
                    const icon = submitButton.querySelector('i');
                    
                    if (icon) {
                        // Store original icon classes
                        const originalClasses = icon.className;
                        submitButton.setAttribute('data-original-icon', originalClasses);
                        
                        // Change to spinning loader
                        icon.className = 'ph ph-spinner ph-spin mr-2';
                    }
                    
                    // Button stays disabled until page reloads/redirects
                    // No timeout - prevents double submission completely
                }
            });
        });
    });
    </script>
</body>
</html>

@auth
<script>
    // Prevent back button to cached pages after logout
    window.addEventListener('pageshow', function(event) {
        if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
            // Page was loaded from cache (back button) - reload to check auth
            window.location.reload();
        }
    });
    
    // Real-time theme color updates via WebSocket
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🔍 [Student] Checking Echo availability:', typeof window.Echo);
        console.log('🔍 [Student] Current organization ID:', '{{ session("current_organization_id") }}');
        
        // Initialize dynamic hover styles on page load with a small delay to ensure CSS variables are loaded
        setTimeout(function() {
            const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim();
            const complementaryColor = getComputedStyle(document.documentElement).getPropertyValue('--complementary-color').trim();
            
            console.log('🎨 [Student] Initializing hover styles - Primary:', primaryColor, 'Complementary:', complementaryColor);
            
            if (primaryColor && primaryColor !== '') {
                updateDynamicHoverStyles(primaryColor, complementaryColor);
                console.log('✅ [Student] Dynamic hover styles initialized on page load');
            } else {
                console.warn('⚠️ [Student] CSS variables not loaded yet, retrying...');
                // Retry after another delay
                setTimeout(function() {
                    const retryPrimary = getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim();
                    const retryComplementary = getComputedStyle(document.documentElement).getPropertyValue('--complementary-color').trim();
                    updateDynamicHoverStyles(retryPrimary, retryComplementary);
                    console.log('✅ [Student] Dynamic hover styles initialized on retry');
                }, 200);
            }
        }, 100);
        
        if (window.Echo) {
            const orgId = '{{ session("current_organization_id") }}';
            
            if (orgId) {
                console.log('🎨 [Student] Listening for theme updates on organization.' + orgId);
                
                window.Echo.channel('organization.' + orgId)
                    .listen('.theme.colors.updated', (event) => {
                        console.log('🎨 Theme colors updated!', event);
                        
                        // Update CSS variables in real-time
                        if (event.primary_color) {
                            document.documentElement.style.setProperty('--primary-color', event.primary_color);
                            
                            // Convert hex to RGB
                            const rgb = hexToRgb(event.primary_color);
                            if (rgb) {
                                document.documentElement.style.setProperty('--primary-color-rgb', `${rgb.r}, ${rgb.g}, ${rgb.b}`);
                            }
                            
                            console.log('✅ Primary color updated to:', event.primary_color);
                        }
                        
                        if (event.complementary_color) {
                            document.documentElement.style.setProperty('--complementary-color', event.complementary_color);
                            
                            // Convert hex to RGB
                            const rgb = hexToRgb(event.complementary_color);
                            if (rgb) {
                                document.documentElement.style.setProperty('--complementary-color-rgb', `${rgb.r}, ${rgb.g}, ${rgb.b}`);
                            }
                            
                            console.log('✅ Complementary color updated to:', event.complementary_color);
                        }
                        
                        // Update logo if provided
                        if (event.logo_url) {
                            console.log('🖼️ [Student] Updating logo to:', event.logo_url);
                            updateAllLogos(event.logo_url);
                        }
                        
                        // Force browser to recompute styles for elements using CSS variables
                        // This is needed because inline styles with var() don't auto-update
                        forceStyleRecomputation();
                        
                        // Show a subtle notification
                        showThemeUpdateNotification();
                    });
            }
        }
    });
    
    // Update all logos on the page
    function updateAllLogos(logoUrl) {
        // Add cache-busting timestamp
        const timestamp = new Date().getTime();
        const logoUrlWithCache = logoUrl + (logoUrl.includes('?') ? '&' : '?') + 'v=' + timestamp;
        
        // Update all logo images on the page
        document.querySelectorAll('img[alt*="Logo"], img[src*="logo"], .logo-icon, .nav-logo img').forEach(img => {
            img.src = logoUrlWithCache;
            console.log('✅ [Student] Logo updated:', img);
        });
        
        // Update favicon
        const favicon = document.querySelector('link[rel="icon"]');
        if (favicon) {
            favicon.href = logoUrlWithCache;
        }
    }
    
    // Helper function to convert hex to RGB
    function hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }
    
    // Force browser to recompute styles for elements using CSS variables in inline styles
    function forceStyleRecomputation() {
        // Get current colors
        const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim();
        const complementaryColor = getComputedStyle(document.documentElement).getPropertyValue('--complementary-color').trim();
        
        console.log('🔄 [Student] Updating styles - Primary:', primaryColor, 'Complementary:', complementaryColor);
        
        // Method 1: Force recomputation of inline styles with var()
        const elementsWithVarStyles = document.querySelectorAll('[style*="var("]');
        elementsWithVarStyles.forEach(element => {
            const currentStyle = element.getAttribute('style');
            element.removeAttribute('style');
            requestAnimationFrame(() => {
                element.setAttribute('style', currentStyle);
            });
        });
        
        // Method 2: Update navigation elements directly
        // Update active tab text colors
        document.querySelectorAll('nav.bg-white .nav-link.active').forEach(el => {
            el.style.color = primaryColor;
        });
        
        // Update active tab icons
        document.querySelectorAll('nav.bg-white .nav-link.active i.ph').forEach(el => {
            el.style.color = primaryColor;
        });
        
        // Update inactive tab icons
        document.querySelectorAll('nav.bg-white .nav-link:not(.active) i.ph').forEach(el => {
            el.style.color = complementaryColor;
        });
        
        // Update organization name
        document.querySelectorAll('nav.bg-white .text-2xl.font-bold').forEach(el => {
            el.style.color = primaryColor;
        });
        
        // Method 3: Inject dynamic CSS for hover states
        updateDynamicHoverStyles(primaryColor, complementaryColor);
        
        // Method 4: Force repaint
        void document.body.offsetHeight;
    }
    
    // Inject or update dynamic CSS for hover states
    function updateDynamicHoverStyles(primaryColor, complementaryColor) {
        let styleEl = document.getElementById('dynamic-theme-styles');
        
        if (!styleEl) {
            styleEl = document.createElement('style');
            styleEl.id = 'dynamic-theme-styles';
            document.head.appendChild(styleEl);
        }
        
        styleEl.textContent = `
            /* Dynamic hover styles - updated in real-time - TEXT */
            nav .nav-link:not(.active):hover {
                color: ${primaryColor} !important;
            }
            
            nav.bg-white .nav-link:not(.active):hover {
                color: ${primaryColor} !important;
            }
            
            nav.bg-white .nav-link:not(.active):hover .tab-text {
                color: ${primaryColor} !important;
            }
            
            /* Dynamic hover styles - updated in real-time - ICONS */
            nav.bg-white .nav-link:not(.active):hover i.ph,
            nav.bg-white .nav-link:not(.active):hover i.fa,
            nav.bg-white .nav-link:not(.active):hover i {
                color: ${primaryColor} !important;
            }
            
            /* Ensure all child elements change color on hover */
            nav.bg-white .nav-link:not(.active):hover * {
                color: ${primaryColor} !important;
            }
            
            .nav-link-hover:hover {
                color: ${primaryColor} !important;
            }
            
            nav button:hover i.ph {
                color: ${primaryColor} !important;
            }
        `;
        
        console.log('✅ [Student] Dynamic hover styles updated (text + icons)');
    }
    
    // Show subtle notification when theme updates
    function showThemeUpdateNotification() {
        const notification = document.createElement('div');
        notification.innerHTML = '<i class="ph ph-palette mr-2"></i>Theme settings updated successfully!';
        notification.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--primary-color);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 9999;
            font-size: 14px;
            font-weight: 500;
            animation: slideIn 0.3s ease-out;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    // Add animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(400px); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
</script>
@endauth
@endif


<script>
function positionStudentDropdown(dropdown) {
    if (!dropdown) return;

    const button = dropdown.querySelector('[data-student-dropdown-parent]');
    const menu = dropdown.querySelector('.student-dropdown-menu');
    if (!button || !menu) return;

    const parentRect = dropdown.getBoundingClientRect();
    const buttonRect = button.getBoundingClientRect();
    const navigation = dropdown.closest('nav');
    const navigationRect = navigation ? navigation.getBoundingClientRect() : null;
    const anchorBottom = Math.max(
        buttonRect.bottom,
        navigationRect ? navigationRect.bottom : buttonRect.bottom
    );
    const top = anchorBottom - parentRect.top + 8;

    menu.style.setProperty('top', `${top}px`, 'important');
    menu.style.setProperty('margin-top', '0px', 'important');
    menu.style.setProperty('transform', 'none', 'important');

    const bridge = dropdown.querySelector('.student-dropdown-bridge');
    if (bridge) {
        const bridgeHeight = Math.max(32, top - parentRect.height + 8);
        bridge.style.setProperty('height', `${bridgeHeight}px`, 'important');
    }
}

(function () {
    'use strict';

    if (window.__studentNavigationInitialized) return;
    window.__studentNavigationInitialized = true;

    const pageCache = new Map();
    const maxCachedPages = 12;
    let pageCacheVersion = 0;
    let navigationRequestId = 0;
    let activeNavigationController = null;
    let mainContent = document.querySelector('main');
    // Preserve the unprocessed fragment for the page cache. Alpine expands
    // x-for/x-if templates in the live DOM, so caching main.innerHTML after
    // initialization would duplicate those elements when the page is restored.
    let currentPageSource = mainContent?.innerHTML || '';

    function getPageCacheKey(url) {
        const targetUrl = new URL(url, window.location.origin);
        targetUrl.searchParams.delete('fragment');
        return `${targetUrl.pathname}${targetUrl.search}`;
    }

    function trimPageCache() {
        while (pageCache.size > maxCachedPages) {
            pageCache.delete(pageCache.keys().next().value);
        }
    }

    function cachePage(url, html, title) {
        const key = getPageCacheKey(url);
        pageCache.delete(key);
        pageCache.set(key, { html, title, cachedAt: Date.now() });
        trimPageCache();
    }

    function cacheCurrentPage() {
        const currentUrl = new URL(window.location.href);
        if (!getStudentNavigationLink(currentUrl.href) || !mainContent) return;
        cachePage(currentUrl.href, currentPageSource || mainContent.innerHTML, document.title);
    }

    window.clearStudentPageCache = function () {
        pageCache.clear();
        pageCacheVersion++;
    };

    if (!window.__studentPageCacheFetchWrapped) {
        const nativeFetch = window.fetch.bind(window);
        window.fetch = function (resource, options = {}) {
            const method = (options.method || (resource instanceof Request ? resource.method : 'GET')).toUpperCase();

            if (method !== 'GET' && method !== 'HEAD') {
                window.clearStudentPageCache();
            }

            return nativeFetch(resource, options);
        };
        window.__studentPageCacheFetchWrapped = true;
    }

    function isStudentContentNavigationPath(pathname) {
        return /^\/(announcements|events)(?:\/|$)/.test(pathname);
    }

    function getStudentNavigationLink(url) {
        const targetUrl = new URL(url, window.location.origin);
        const matchingLink = Array.from(document.querySelectorAll('a[data-student-navigation], [data-student-navigation-pagination] a'))
            .find(link => new URL(link.href, window.location.origin).pathname === targetUrl.pathname);

        if (matchingLink) return matchingLink;
        if (!isStudentContentNavigationPath(targetUrl.pathname)) return null;

        return {
            textContent: targetUrl.pathname.startsWith('/events') ? 'Events' : 'Announcements'
        };
    }

    function getNavigationLabel(link) {
        if (!link) return 'page';
        const label = link.querySelector?.('.tab-text')?.textContent
            || link.getAttribute?.('aria-label')
            || link.textContent;
        return label.trim().replace(/\s+/g, ' ') || 'page';
    }

    function setActiveStudentNavigation(pathname) {
        const targetPath = new URL(pathname, window.location.origin).pathname;

        // Clear the dropdown state first so a previous page can never leave
        // a second underline behind after SPA navigation.
        document.querySelectorAll('.student-dropdown-nav-item').forEach(dropdown => {
            dropdown.classList.remove('student-dropdown-active');
            const parent = dropdown.querySelector('button[data-student-dropdown-parent]');
            if (parent) {
                parent.classList.remove('student-navigation-active', 'active');
                parent.classList.add('student-dropdown-inactive');
                parent.removeAttribute('aria-current');
            }
        });

        document.querySelectorAll('a[data-student-navigation]').forEach(link => {
            const linkPath = new URL(link.href, window.location.origin).pathname;
            const isActive = linkPath === targetPath
                || (isStudentContentNavigationPath(targetPath)
                    && (linkPath === '/announcements' || linkPath === '/events')
                    && targetPath.startsWith(`${linkPath}/`));
            link.classList.toggle('student-navigation-active', isActive);
            link.classList.toggle('active', isActive);

            if (isActive) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
        });

        // Profile, e-Library, Complaints, and Documents live in the Student
        // dropdown, so their active underline belongs to the dropdown button.
        document.querySelectorAll('button[data-student-dropdown-parent]').forEach(parent => {
            const dropdown = parent.closest('.student-dropdown-nav-item');
            const isActive = Boolean(dropdown && Array.from(
                dropdown.querySelectorAll('a[data-student-navigation]')
            ).some(link => new URL(link.href, window.location.origin).pathname === targetPath));

            parent.classList.toggle('student-navigation-active', isActive);
            parent.classList.toggle('student-dropdown-inactive', !isActive);
            parent.classList.toggle('active', isActive);
            if (dropdown) {
                dropdown.classList.toggle('student-dropdown-active', isActive);
            }

            if (isActive) {
                parent.setAttribute('aria-current', 'page');
            } else {
                parent.removeAttribute('aria-current');
            }
        });

        if (window.updateStudentTabIndicator) {
            window.updateStudentTabIndicator(targetPath);
        }
    }

    function closeMobileNavigation() {
        const navigation = document.querySelector('nav[x-data]');
        if (!navigation || !window.Alpine || typeof window.Alpine.$data !== 'function') return;

        const navigationState = window.Alpine.$data(navigation);
        if (navigationState && Object.prototype.hasOwnProperty.call(navigationState, 'mobileMenuOpen')) {
            navigationState.mobileMenuOpen = false;
        }
    }

    function getStudentSkeletonPage(pathname) {
        const targetPath = new URL(pathname || window.location.pathname, window.location.origin)
            .pathname
            .replace(/\/+$/, '') || '/';

        if (targetPath === '/dashboard') return 'dashboard';
        if (targetPath === '/payments') return 'payments';
        if (targetPath === '/elections') return 'elections-index';
        if (targetPath.startsWith('/elections/')) return 'elections-show';
        if (targetPath === '/announcements') return 'announcements-index';
        if (targetPath.startsWith('/announcements/')) return 'announcements-show';
        if (targetPath === '/events') return 'events-index';
        if (targetPath === '/events/calendar') return 'events-calendar';
        if (targetPath.startsWith('/events/')) return 'events-show';
        if (targetPath === '/edushare') return 'edushare';
        if (targetPath === '/chatbot') return 'chatbot';
        if (targetPath === '/library') return 'library';
        if (targetPath === '/complaints') return 'complaints';
        if (targetPath === '/profile') return 'profile';
        if (targetPath === '/documents/my-documents') return 'documents';

        return 'default';
    }

    function showStudentNavigationSkeleton(label, pathname) {
        const skeleton = document.getElementById('student-navigation-skeleton');
        if (!skeleton) return;

        const title = skeleton.querySelector('[data-student-skeleton-title]');
        if (title) title.textContent = `Loading ${label || 'page'}...`;

        const activePage = getStudentSkeletonPage(pathname);
        skeleton.querySelectorAll('[data-student-skeleton-page]').forEach(page => {
            page.classList.toggle('is-active', page.dataset.studentSkeletonPage === activePage);
        });

        skeleton.classList.add('is-visible');
        skeleton.setAttribute('aria-hidden', 'false');
        document.body.setAttribute('aria-busy', 'true');
    }

    function hideStudentNavigationSkeleton() {
        const skeleton = document.getElementById('student-navigation-skeleton');
        if (!skeleton) return;

        skeleton.classList.remove('is-visible');
        skeleton.setAttribute('aria-hidden', 'true');
        document.body.removeAttribute('aria-busy');
    }

    function hashAsset(value) {
        let hash = 0;
        for (let index = 0; index < value.length; index++) {
            hash = ((hash << 5) - hash) + value.charCodeAt(index);
            hash |= 0;
        }
        return String(hash);
    }

    function installPageHeadAssets(pageDocument) {
        pageDocument.head.querySelectorAll('link[rel="stylesheet"], style').forEach(asset => {
            const source = asset.tagName.toLowerCase() === 'link'
                ? asset.href
                : asset.textContent;
            const key = `${asset.tagName.toLowerCase()}:${hashAsset(source || '')}`;
            const alreadyInstalled = Array.from(document.head.querySelectorAll('[data-student-fragment-asset]'))
                .some(existing => existing.getAttribute('data-student-fragment-asset') === key);

            if (alreadyInstalled) return;

            const clone = asset.cloneNode(true);
            clone.setAttribute('data-student-fragment-asset', key);
            document.head.appendChild(clone);
        });
    }

    function executeStudentPageScripts(container) {
        container.querySelectorAll('script').forEach(script => {
            const executableScript = document.createElement('script');
            Array.from(script.attributes).forEach(attribute => {
                executableScript.setAttribute(attribute.name, attribute.value);
            });

            if (script.src) {
                executableScript.async = false;
            } else {
                // Fragment scripts are inserted after DOMContentLoaded already fired.
                // Redirect those page-ready callbacks to the custom navigation event.
                executableScript.textContent = script.textContent.replace(/DOMContentLoaded/g, 'student:navigation-ready');
            }

            script.replaceWith(executableScript);
        });
    }

    function initializeStudentForms() {
        document.querySelectorAll('main form:not([data-no-loading])').forEach(form => {
            if (form.hasAttribute('data-student-form-loading-bound')) return;
            form.setAttribute('data-student-form-loading-bound', 'true');

            form.addEventListener('submit', function () {
                const submitButton = this.querySelector('button[type="submit"]');
                if (!submitButton || submitButton.disabled) return;

                submitButton.disabled = true;
                submitButton.style.opacity = '0.6';
                submitButton.style.cursor = 'not-allowed';

                const icon = submitButton.querySelector('i');
                if (icon) icon.className = 'ph ph-spinner ph-spin mr-2';
            });
        });
    }

    function scrollStudentPageToTop() {
        // The shared <main> element stays in the DOM during SPA navigation.
        // Scrolling it into view can preserve/restore a mid-page offset, so
        // reset the document scroll position explicitly instead.
        window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
        document.documentElement.scrollTop = 0;
        document.body.scrollTop = 0;
    }

    function renderStudentPage(html, title, url, preserveScroll = false) {
        if (!mainContent) mainContent = document.querySelector('main');
        if (!mainContent) return;

        if (window.Alpine && typeof window.Alpine.destroyTree === 'function') {
            window.Alpine.destroyTree(mainContent);
        }

        mainContent.innerHTML = html;
        currentPageSource = html;
        executeStudentPageScripts(mainContent);

        if (window.Alpine && typeof window.Alpine.initTree === 'function') {
            window.Alpine.initTree(mainContent);
        }

        initializeStudentForms();
        document.dispatchEvent(new CustomEvent('student:navigation-ready', {
            detail: { url: new URL(url, window.location.origin).href }
        }));

        if (title) document.title = title;
        if (!preserveScroll) {
            scrollStudentPageToTop();
            requestAnimationFrame(scrollStudentPageToTop);
        }
    }

    async function fetchStudentPageFragment(url, signal) {
        const fragmentUrl = new URL(url, window.location.origin);
        fragmentUrl.searchParams.set('fragment', '1');

        const requestOptions = {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            },
            credentials: 'same-origin'
        };
        if (signal) requestOptions.signal = signal;

        const response = await fetch(fragmentUrl.href, requestOptions);
        if (!response.ok) throw new Error('Failed to load Student page');

        const pageHtml = await response.text();
        const pageDocument = new DOMParser().parseFromString(pageHtml, 'text/html');
        const nextMain = pageDocument.querySelector('main');
        if (!nextMain) throw new Error('Student page content was not found');

        installPageHeadAssets(pageDocument);
        return { html: nextMain.innerHTML, title: pageDocument.title };
    }

    const studentRealtimePagePaths = {
        events: /^\/events(?:\/|$)/,
        payments: /^\/payments(?:\/|$)/,
        elections: /^\/elections(?:\/|$)/,
        edushare: /^\/edushare(?:\/|$)/,
        library: /^\/library(?:\/|$)/,
        complaints: /^\/complaints(?:\/|$)/,
        documents: /^\/documents(?:\/|$)/,
        chatbot: /^\/chatbot(?:\/|$)/,
    };
    let studentCurrentPageRefreshPromise = null;

    // Refresh the current student page inside the existing application shell.
    // Mutating pages use this after a successful AJAX request instead of a
    // browser reload, so the URL, shell, and current scroll position remain.
    window.refreshCurrentStudentPage = function () {
        if (studentCurrentPageRefreshPromise) return studentCurrentPageRefreshPromise;

        const currentUrl = window.location.href;
        studentCurrentPageRefreshPromise = fetchStudentPageFragment(currentUrl)
            .then(page => {
                cachePage(currentUrl, page.html, page.title);
                renderStudentPage(page.html, page.title, currentUrl, true);
                return true;
            })
            .catch(error => {
                console.warn('Student page in-place refresh failed:', error);
                return false;
            })
            .finally(() => {
                studentCurrentPageRefreshPromise = null;
            });

        return studentCurrentPageRefreshPromise;
    };

    // Realtime events update only the shared <main> content. This keeps the
    // current URL, browser history, and scroll position intact while still
    // fetching server-authorized data for the active student page.
    window.refreshCurrentRealtimePage = function (topic) {
        const pagePattern = studentRealtimePagePaths[topic];
        if (!pagePattern || !pagePattern.test(window.location.pathname)) return true;
        window.refreshCurrentStudentPage();
        return true;
    };

    async function refreshStudentPageCache(url) {
        const refreshVersion = pageCacheVersion;
        try {
            const page = await fetchStudentPageFragment(url);
            if (refreshVersion === pageCacheVersion) cachePage(url, page.html, page.title);
        } catch (error) {
            console.warn('Student page background refresh failed:', error);
        }
    }

    async function navigateStudentPage(url, updateHistory, requestId, controller) {
        const targetUrl = new URL(url, window.location.origin);
        const navigationLink = getStudentNavigationLink(targetUrl.href);
        const cachedPage = pageCache.get(getPageCacheKey(targetUrl.href));
        const isLatestNavigation = () => requestId === navigationRequestId;

        if (!mainContent || !navigationLink || !isLatestNavigation()) return false;

        setActiveStudentNavigation(targetUrl.pathname);

        if (cachedPage) {
            renderStudentPage(cachedPage.html, cachedPage.title, targetUrl.href);
            if (updateHistory) {
                window.history.pushState({ studentNavigation: true }, '', targetUrl.href);
                window.__studentPendingNavigationPath = null;
                setActiveStudentNavigation(targetUrl.pathname);
                window.updateStudentTabIndicator?.(targetUrl.pathname);
            }
            hideStudentNavigationSkeleton();
            refreshStudentPageCache(targetUrl.href);
            if (activeNavigationController === controller) activeNavigationController = null;
            return true;
        }

        showStudentNavigationSkeleton(getNavigationLabel(navigationLink), targetUrl.pathname);

        try {
            const page = await fetchStudentPageFragment(
                targetUrl.href,
                controller ? controller.signal : null
            );

            if (!isLatestNavigation()) return false;

            cachePage(targetUrl.href, page.html, page.title);
            renderStudentPage(page.html, page.title, targetUrl.href);
            if (updateHistory) {
                window.history.pushState({ studentNavigation: true }, '', targetUrl.href);
                window.__studentPendingNavigationPath = null;
                setActiveStudentNavigation(targetUrl.pathname);
                window.updateStudentTabIndicator?.(targetUrl.pathname);
            }
            hideStudentNavigationSkeleton();
            return true;
        } catch (error) {
            if (error.name === 'AbortError' || !isLatestNavigation()) return false;

            console.error('Student navigation failed:', error);
            hideStudentNavigationSkeleton();
            setActiveStudentNavigation(window.location.pathname);
            window.location.assign(targetUrl.href);
            return false;
        } finally {
            if (activeNavigationController === controller) activeNavigationController = null;
        }
    }

    document.addEventListener('click', function (event) {
        const clickedElement = event.target instanceof Element
            ? event.target.closest('a[data-student-navigation], [data-student-navigation-pagination] a, a[href]')
            : null;
        const link = clickedElement && (
            clickedElement.matches('a[data-student-navigation], [data-student-navigation-pagination] a')
            || isStudentContentNavigationPath(new URL(clickedElement.href, window.location.origin).pathname)
                ? clickedElement
                : null
        );

        if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank') return;

        // Close the mobile drawer on any navigation-tab click, including
        // clicks on the tab of the page we're already on.
        closeMobileNavigation();

        const targetUrl = new URL(link.href, window.location.origin);
        if (targetUrl.href === window.location.href) return;

        event.preventDefault();

        const requestId = ++navigationRequestId;
        if (activeNavigationController) activeNavigationController.abort();

        activeNavigationController = new AbortController();
        window.__studentPendingNavigationPath = targetUrl.pathname;
        cacheCurrentPage();
        setActiveStudentNavigation(targetUrl.pathname);
        window.updateStudentTabIndicator?.(targetUrl.pathname);

        const hasCachedPage = pageCache.has(getPageCacheKey(targetUrl.href));
        if (!hasCachedPage) showStudentNavigationSkeleton(getNavigationLabel(link), targetUrl.pathname);

        requestAnimationFrame(function () {
            if (requestId !== navigationRequestId) return;
            navigateStudentPage(targetUrl.href, true, requestId, activeNavigationController);
        });
    });

    window.addEventListener('popstate', function () {
        const navigationLink = getStudentNavigationLink(window.location.href);
        if (!navigationLink) {
            window.location.reload();
            return;
        }

        if (activeNavigationController) activeNavigationController.abort();
        const requestId = ++navigationRequestId;
        activeNavigationController = new AbortController();
        navigateStudentPage(window.location.href, false, requestId, activeNavigationController);
    });

    setActiveStudentNavigation(window.location.pathname);
    initializeStudentForms();
})();
</script>
