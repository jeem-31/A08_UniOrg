<!DOCTYPE html>
<html lang="en">
<head>
    <x-theme-provider />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dean Dashboard - ' . organization_name())</title>
    <link rel="icon" type="image/png" href="{{ organization_logo() }}">
    
    {{-- OPTIMIZATION: Aggressively preload and cache organization logo --}}
    <link rel="preload" as="image" href="{{ organization_logo() }}" fetchpriority="high">    
    {{-- Store logo URL in localStorage for instant access --}}
    <script>
        (function() {
            const logoUrl = "{{ organization_logo() }}";
            const orgId = "{{ session('current_organization_id') }}";
            
            // Store in localStorage for persistence across sessions
            if (orgId) {
                localStorage.setItem('org_logo_' + orgId, logoUrl);
                localStorage.setItem('org_logo_cached_at', Date.now());
            }
            
            // Aggressively preload logo image
            const img = new Image();
            img.src = logoUrl;
            
            // Force browser to cache with multiple requests
            setTimeout(() => {
                const img2 = new Image();
                img2.src = logoUrl;
            }, 10);
        })();
    </script>
    
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
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
    
    <!-- Alpine.js for x-data and removing x-cloak -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    @vite(['resources/js/app.js'])
    
    <style>
        [x-cloak] { 
            display: none !important; 
        }

        .modal-backdrop {
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
        }
        
        .gold-icon {
            color: var(--complementary-color);
        }
        
        /* Prevent layout shift on page load */
        body {
            overflow-x: hidden;
        }
        
        /* Toast Notification Styles */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
            animation: slideInRight 0.3s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideOutRight {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
        
        .toast-notification.hiding {
            animation: slideOutRight 0.3s ease-in forwards;
        }
        
        /* Sidebar Styles */
        .sidebar {
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            will-change: width;
            overflow: visible !important;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
        }
        
        .sidebar-collapsed {
            width: 80px;
        }
        
        .sidebar-expanded {
            width: 280px;
        }
        
        .main-content {
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            will-change: margin-left;
            min-height: 100vh;
        }
        
        .main-content-collapsed {
            margin-left: 80px;
        }
        
        .main-content-expanded {
            margin-left: 280px;
        }

        /* Mobile: the fixed sidebar overlays content instead of pushing it. */
        @media (max-width: 767px) {
            .main-content-expanded,
            .main-content-collapsed {
                margin-left: 0;
                width: 100%;
            }

            .sidebar {
                top: 72px;
                height: calc(100dvh - 72px);
                max-width: calc(100vw - 3rem);
                transform: translateX(-105%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .sidebar-expanded {
                width: min(280px, calc(100vw - 3rem));
                transform: translateX(0);
            }

            main {
                width: 100%;
                padding: 0.75rem;
            }
        }
        
        /* Bubble/Pill Navigation Item */
        .nav-item {
            position: relative;
            display: flex;
            align-items: center;
            padding: 12px 16px;
            margin: 6px 12px;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            color: #6b7280;
            white-space: nowrap;
            overflow: visible;
        }
        
        .nav-item:hover {
            background: linear-gradient(135deg, rgba(var(--primary-color-rgb, 130, 56, 179), 0.1) 0%, rgba(var(--primary-color-rgb, 130, 56, 179), 0.15) 100%);
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(var(--primary-color-rgb, 130, 56, 179), 0.15);
        }
        
        .nav-item.active {
            background: var(--primary-color, #8238B3);
            color: white;
            box-shadow: 0 8px 16px rgba(var(--primary-color-rgb, 130, 56, 179), 0.3);
            transform: translateX(4px);
        }
        
        .nav-item.active .nav-icon {
            color: var(--complementary-color, #D7AC28);
        }
        
        .nav-item.active .nav-text {
            color: white;
        }
        
        .nav-icon {
            min-width: 24px;
            font-size: 20px;
            transition: all 0.3s ease;
            color: var(--primary-color, #8238B3);
            flex-shrink: 0;
        }
        
        .nav-text {
            margin-left: 16px;
            font-weight: 500;
            white-space: nowrap;
            transition: all 0.3s ease;
            color: #4b5563;
            display: inline-block;
        }
        
        /* Hide text in collapsed state */
        .sidebar-collapsed .nav-text {
            display: none;
        }
        
        /* Show text on hover in collapsed state - expand the item */
        .sidebar-collapsed .nav-item:hover .nav-text {
            display: inline-block !important;
            position: absolute !important;
            left: 50px !important;
            background: var(--primary-color, #8238B3) !important;
            color: white !important;
            padding: 8px 16px !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 12px rgba(var(--primary-color-rgb, 130, 56, 179), 0.4) !important;
            z-index: 1000 !important;
            white-space: nowrap !important;
        }
        
        /* Force white color for all text inside collapsed tooltip */
        .sidebar-collapsed .nav-item:hover .nav-text * {
            color: white !important;
        }
        
        .sidebar-collapsed .nav-item:hover .nav-text span {
            color: white !important;
        }
        
        /* Show text in expanded state */
        .sidebar-expanded .nav-text {
            display: inline-block !important;
        }
        
        .nav-badge {
            margin-left: auto;
            padding: 2px 8px;
            background: #ef4444;
            color: white;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .sidebar-expanded .nav-badge {
            opacity: 1;
        }
        
        /* Toggle Button */
        .sidebar-toggle {
            position: absolute;
            right: -12px;
            top: 24px;
            width: 24px;
            height: 24px;
            background: var(--primary-color, #8238B3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
            transition: all 0.3s ease;
            z-index: 10;
        }
        
        .sidebar-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
        }
        
        .sidebar-toggle i {
            color: white;
            font-size: 12px;
            transition: transform 0.3s ease;
        }
        
        .sidebar-collapsed .sidebar-toggle i {
            transform: rotate(180deg);
        }
        
        /* Logo */
        .sidebar-logo {
            display: flex;
            align-items: center;
            padding: 20px 16px;
            margin-bottom: 8px;
        }
        
        .logo-icon {
            min-width: 40px;
            width: 40px;
            height: 40px;
            object-fit: contain;
        }
        
        .logo-text {
            margin-left: 12px;
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color, #8238B3);
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .sidebar-expanded .logo-text {
            opacity: 1;
        }
        
        /* Scrollbar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }
        
        /* Top Bar */
        .top-bar {
            min-height: 64px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            position: sticky;
            top: 0;
            z-index: 40;
        }
        
        /* Hamburger Menu Button */
        .hamburger-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .hamburger-btn:hover {
            transform: scale(1.1);
        }
        
        .hamburger-btn i {
            color: var(--primary-color, #8238B3);
            font-size: 24px;
        }
    </style>
    
    @stack('styles')
</head>
<body class="bg-gray-50" x-data="{ 
    sidebarOpen: localStorage.getItem('deanSidebarOpen') === 'true'
}" x-init="
    // Initialize sidebar state immediately - default to collapsed
    if (localStorage.getItem('deanSidebarOpen') === null) {
        localStorage.setItem('deanSidebarOpen', 'false');
        sidebarOpen = false;
    }
" x-cloak>
    <!-- Sidebar -->
    <aside 
        class="sidebar fixed left-0 top-0 h-screen bg-white shadow-xl z-40"
        :class="sidebarOpen ? 'sidebar-expanded' : 'sidebar-collapsed'"
        style="overflow-y: auto; overflow-x: visible;"
    >
        <!-- Logo -->
        <div class="sidebar-logo" data-navigate-once>
            <img src="{{ organization_logo() }}" 
                 alt="{{ organization_name() }} Logo" 
                 class="logo-icon h-10 w-10 object-contain" 
                 loading="eager"
                 fetchpriority="high"
                 style="opacity: 1 !important; visibility: visible !important; display: inline-block !important;"
                 onerror="this.src='{{ asset('images/soccsyncLogo-removebg-preview.png') }}'; console.error('❌ Dean Logo failed to load:', '{{ organization_logo() }}'); console.log('✅ Fallback to local asset');"
                 onload="console.log('✅ Dean Logo loaded successfully from:', this.src);">
            <span class="logo-text">{{ organization_acronym() }} - Dean</span>
        </div>
        
        <!-- Navigation -->
        <nav class="pb-6" style="display: flex; flex-direction: column; min-height: calc(100vh - 80px);">
            <div style="flex: 1;">
                <!-- Dashboard -->
                <a href="{{ route('dean.dashboard') }}" data-dean-navigation data-dean-skeleton="dashboard" class="nav-item {{ request()->routeIs('dean.dashboard') ? 'active' : '' }}">
                    <i class="ph ph-house nav-icon"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
                
                <!-- Event Proposals -->
                <a href="{{ route('dean.proposals.index') }}" data-dean-navigation data-dean-skeleton="proposals" class="nav-item {{ request()->routeIs('dean.proposals.*') ? 'active' : '' }}">
                    <i class="ph ph-file-text nav-icon"></i>
                    <span class="nav-text">Event Proposals</span>
                    @php
                        // Dept-scoped: only pending events from this dean's own
                        // department orgs (matches the proposals page + dashboard metric).
                        $badgeDeptId = auth()->user()->department_id;
                        $badgeOrgIds = $badgeDeptId
                            ? \App\Models\Organization::where('department_id', $badgeDeptId)->pluck('id')
                            : collect();
                        $pendingCount = $badgeOrgIds->isNotEmpty()
                            ? \App\Models\Event::whereIn('organization_id', $badgeOrgIds)->where('approval_status', 'pending')->count()
                            : 0;
                    @endphp
                    @if($pendingCount > 0)
                        <span class="nav-badge">{{ $pendingCount }}</span>
                    @endif
                </a>
                
                <!-- Financial Reports -->
                <a href="{{ route('dean.financial-reports.index') }}" data-dean-navigation data-dean-skeleton="financial-reports" class="nav-item {{ request()->routeIs('dean.financial-reports.*') ? 'active' : '' }}">
                    <i class="ph ph-chart-line-up nav-icon"></i>
                    <span class="nav-text">Financial Reports</span>
                </a>
                
                <!-- Registration Reports -->
                <a href="{{ route('dean.registration-reports') }}" data-dean-navigation data-dean-skeleton="registration-reports" class="nav-item {{ request()->routeIs('dean.registration-reports') || request()->routeIs('dean.event-registration-details') ? 'active' : '' }}">
                    <i class="ph ph-users nav-icon"></i>
                    <span class="nav-text">Registration Reports</span>
                </a>
                
                <!-- Content Overview -->
                <a href="{{ route('dean.content-overview') }}" data-dean-navigation data-dean-skeleton="content-overview" class="nav-item {{ request()->routeIs('dean.content-overview') ? 'active' : '' }}">
                    <i class="ph ph-eye nav-icon"></i>
                    <span class="nav-text">Content Overview</span>
                </a>
                
                <!-- Users Management -->
                <a href="{{ route('dean.users.index') }}" data-dean-navigation data-dean-skeleton="users" class="nav-item {{ request()->routeIs('dean.users.*') ? 'active' : '' }}">
                    <i class="ph ph-user-gear nav-icon"></i>
                    <span class="nav-text">Users</span>
                </a>
            </div>
            
            <!-- Bottom Section: Profile Only -->
            <div style="margin-top: auto;">
                <!-- Divider -->
                <div style="border-top: 1px solid #e5e7eb; margin: 16px 12px;"></div>
                
                <!-- Profile Section -->
                <a href="{{ route('dean.profile.show') }}" data-dean-navigation data-dean-skeleton="profile" class="nav-item {{ request()->routeIs('dean.profile.*') ? 'active' : '' }}" style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);">
                    <span class="nav-avatar-wrapper nav-icon">
                        @if(auth()->user()->photo)
                            <img src="{{ storage_url(auth()->user()->photo) }}" alt="Profile" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                        @else
                            <i class="ph ph-user-circle" style="font-size: 32px;"></i>
                        @endif
                    </span>
                    <div class="nav-text">
                        <div style="display: flex; flex-direction: column; line-height: 1.3;">
                            <span style="font-weight: 600; color: #1f2937;">{{ auth()->user()->short_name }}</span>
                            <span style="font-size: 11px; color: #6b7280;">Dean</span>
                        </div>
                    </div>
                </a>
            </div>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <div 
        class="main-content min-h-screen"
        :class="sidebarOpen ? 'main-content-expanded' : 'main-content-collapsed'"
    >
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="flex items-center space-x-4">
                <!-- Hamburger Menu -->
                <div class="hamburger-btn" @click="sidebarOpen = !sidebarOpen; localStorage.setItem('deanSidebarOpen', sidebarOpen)">
                    <i class="ph ph-list"></i>
                </div>
            </div>
            
            <div class="flex items-center space-x-4">
                <!-- Premise-use + general notifications -->
                <div class="relative" x-data="{ deanNotifOpen: false }">
                    @php
                        $deanNotifications = auth()->check() ? auth()->user()->notifications()->latest()->take(10)->get() : collect();
                        $deanUnreadCount = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0;
                    @endphp
                    <button @click="deanNotifOpen = !deanNotifOpen" class="relative flex items-center p-2 rounded-lg hover:bg-gray-100 transition-colors" aria-label="Notifications">
                        <i class="ph ph-bell text-xl" style="color: var(--primary-color);"></i>
                        @if($deanUnreadCount > 0)
                            <span class="absolute top-0.5 right-0.5 inline-flex items-center justify-center text-xs font-bold leading-none text-white rounded-full" style="min-width: 18px; height: 18px; font-size: 10px; padding: 2px 4px; background: #ef4444;">
                                {{ $deanUnreadCount > 9 ? '9+' : $deanUnreadCount }}
                            </span>
                        @endif
                    </button>
                    <div x-show="deanNotifOpen" @click.away="deanNotifOpen = false" x-cloak
                         class="origin-top-right absolute right-0 mt-2 w-80 max-w-[calc(100vw-1rem)] rounded-lg shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"
                         style="display: none;">
                        <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
                            @if($deanUnreadCount > 0)
                                <button onclick="deanMarkAllRead(event)" class="text-xs font-medium hover:opacity-80" style="color: var(--primary-color);">Mark all as read</button>
                            @endif
                        </div>
                        <div class="divide-y divide-gray-200 max-h-80 overflow-y-auto">
                            @forelse($deanNotifications as $deanNotification)
                                @php
                                    $deanNotifUrl = $deanNotification->data['url'] ?? route('dean.proposals.index');
                                    $deanNotifColor = $deanNotification->data['color'] ?? 'gray';
                                @endphp
                                <a href="{{ $deanNotifUrl }}"
                                   onclick="deanReadAndGo(event, '{{ $deanNotification->id }}', '{{ $deanNotifUrl }}')"
                                   class="block p-4 hover:bg-gray-50 transition {{ $deanNotification->read_at ? 'bg-white' : 'bg-purple-50' }}">
                                    <div class="flex items-start">
                                        <i class="ph {{ $deanNotification->data['icon'] ?? 'ph-info' }} text-2xl
                                           {{ $deanNotifColor === 'green' ? 'text-green-500' : '' }}
                                           {{ $deanNotifColor === 'red' ? 'text-red-500' : '' }}
                                           {{ $deanNotifColor === 'blue' ? 'text-blue-500' : '' }}"></i>
                                        <div class="ml-3 flex-1">
                                            <p class="text-sm font-semibold text-gray-900">{{ $deanNotification->data['title'] ?? 'Notification' }}</p>
                                            <p class="text-sm text-gray-600 mt-1">{{ $deanNotification->data['message'] ?? '' }}</p>
                                            <p class="text-xs text-gray-400 mt-1">{{ $deanNotification->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                </a>
                            @empty
                                <div class="p-8 text-center text-gray-500">
                                    <i class="ph ph-bell-slash text-4xl mb-2"></i>
                                    <p class="text-sm">No notifications</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
                <script>
                    function deanSmoothToHash(hash) {
                        if (!hash) return false;
                        const el = document.querySelector(hash);
                        if (!el) return false;
                        // UX: land on top first, then glide down to the section
                        // so the heading is fully visible below the sticky bar.
                        window.scrollTo(0, 0);
                        setTimeout(() => {
                            const target = document.querySelector(hash);
                            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }, 450);
                        return true;
                    }
                    async function deanReadAndGo(e, id, url) {
                        e.preventDefault();
                        try {
                            await fetch(`/notifications/${id}/read`, {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                                credentials: 'same-origin'
                            });
                        } catch (_) {}
                        const target = new URL(url, window.location.origin);
                        // Same page + anchor: smooth-scroll in place (no reload,
                        // no instant jump) and reflect the hash in the URL.
                        if (target.pathname === window.location.pathname && target.hash) {
                            history.replaceState(null, '', target.hash);
                            deanSmoothToHash(target.hash);
                            return;
                        }
                        window.location.href = url;
                    }
                    async function deanMarkAllRead(e) {
                        e.preventDefault();
                        try {
                            await fetch('/notifications/mark-all-read', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                                credentials: 'same-origin'
                            });
                            window.location.reload();
                        } catch (_) {}
                    }
                </script>
                <!-- Logout Button -->
                <div x-data="{ showLogoutModal: false }">
                    <button @click="showLogoutModal = true" class="flex items-center space-x-2 px-4 py-2 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="ph ph-sign-out gold-icon text-xl"></i>
                        <span class="text-sm font-medium text-gray-700">Logout</span>
                    </button>
                    
                    <!-- Logout Modal -->
                    <div x-show="showLogoutModal" x-cloak class="fixed inset-0 z-[9999] overflow-y-auto" style="display: none;" x-data="{ isLoggingOut: false }">
                        <div class="flex items-center justify-center min-h-screen px-4">
                            <div @click="!isLoggingOut && (showLogoutModal = false)" class="fixed inset-0 bg-black/50 z-[9998] modal-backdrop"></div>
                            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6 z-[9999]">
                                <div class="flex items-center mb-4">
                                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full" style="background-color: rgba(var(--primary-color-rgb, 130, 56, 179), 0.1);">
                                        <i class="ph ph-sign-out text-xl gold-icon"></i>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-2">
                                        Are you sure you want to logout?
                                    </h3>
                                    <p class="text-sm text-gray-500 mb-6">
                                        You will be logged out of your account and will need to login again to access the system.
                                    </p>
                                </div>
                                <div class="flex justify-center space-x-3">
                                    <button @click="showLogoutModal = false" :disabled="isLoggingOut" 
                                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                                            :class="{ 'opacity-50 cursor-not-allowed': isLoggingOut }">
                                        Cancel
                                    </button>
                                    <form id="logout-form" method="POST" action="{{ route('logout') }}" data-no-loading @submit.prevent="
                                        isLoggingOut = true;
                                        localStorage.removeItem('deanSidebarOpen');
                                        sessionStorage.removeItem('systemless_banner_dismissed');
                                        fetch('{{ route('logout') }}', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                'Accept': 'application/json'
                                            },
                                            credentials: 'same-origin'
                                        }).then(response => {
                                            if (response.ok || response.redirected) {
                                                window.location.href = '{{ route('org.login') }}';
                                            } else {
                                                window.location.href = '{{ route('org.login') }}';
                                            }
                                        }).catch(() => {
                                            window.location.href = '{{ route('org.login') }}';
                                        });
                                    ">
                                        @csrf
                                        <button type="submit" :disabled="isLoggingOut" 
                                                class="px-4 py-2 text-white rounded-lg hover:opacity-90 transition-all font-medium flex items-center space-x-2"
                                                style="background-color: var(--primary-color);"
                                                :class="{ 'opacity-50 cursor-not-allowed': isLoggingOut }">
                                            <i class="ph" :class="isLoggingOut ? 'ph-spinner ph-spin' : 'ph-sign-out'"></i>
                                            <span x-text="isLoggingOut ? 'Logging out...' : 'Logout'"></span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Page Content -->
        <main>
            @yield('content')
        </main>

        <div id="dean-skeleton" hidden aria-hidden="true" aria-live="polite">
            <span class="sr-only" data-dean-skeleton-title>Loading page...</span>
            <div data-dean-skeleton-page="default" class="dean-skeleton-page is-active"><div class="dean-skeleton-block h-9 w-72 mb-3"></div><div class="dean-skeleton-block h-4 w-96 mb-8"></div><div class="dean-skeleton-panel h-64 w-full"></div></div>

            <div data-dean-skeleton-page="dashboard" class="dean-skeleton-page"><div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-10"><div><div class="dean-skeleton-block h-10 w-96 mb-3"></div><div class="dean-skeleton-block h-4 w-[32rem]"></div></div><div class="dean-skeleton-block h-4 w-28"></div></div><div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">@for($card=0;$card<3;$card++)<div class="dean-skeleton-panel p-7 h-40"><div class="dean-skeleton-block h-12 w-12 rounded-lg mb-5"></div><div class="dean-skeleton-block h-8 w-24 mb-2"></div><div class="dean-skeleton-block h-3 w-36"></div></div>@endfor</div><div class="grid grid-cols-1 lg:grid-cols-2 gap-6"><div class="dean-skeleton-panel h-72 p-6"><div class="dean-skeleton-block h-6 w-56 mb-6"></div><div class="dean-skeleton-block h-48 w-full"></div></div><div class="dean-skeleton-panel h-72 p-6"><div class="dean-skeleton-block h-6 w-48 mb-6"></div>@for($row=0;$row<4;$row++)<div class="flex gap-3 mb-4"><div class="dean-skeleton-block h-9 w-9 rounded-full"></div><div class="flex-1"><div class="dean-skeleton-block h-3 w-3/4 mb-2"></div><div class="dean-skeleton-block h-3 w-1/2"></div></div></div>@endfor</div></div></div></div>

            <div data-dean-skeleton-page="proposals" class="dean-skeleton-page"><div class="flex justify-between items-center gap-4 mb-8"><div><div class="dean-skeleton-block h-9 w-64 mb-3"></div><div class="dean-skeleton-block h-4 w-80"></div></div><div class="dean-skeleton-block h-10 w-32"></div></div><div class="dean-skeleton-panel h-14 w-full mb-6"></div><div class="dean-skeleton-panel overflow-hidden">@for($row=0;$row<5;$row++)<div class="flex items-center gap-4 p-5 border-b border-gray-100"><div class="dean-skeleton-block h-12 w-12 rounded-lg"></div><div class="flex-1"><div class="dean-skeleton-block h-4 w-3/5 mb-2"></div><div class="dean-skeleton-block h-3 w-2/5"></div></div><div class="dean-skeleton-block h-7 w-20 rounded-full"></div><div class="dean-skeleton-block h-9 w-24"></div></div>@endfor</div></div>

            <div data-dean-skeleton-page="financial-reports" class="dean-skeleton-page bg-white"><div class="mb-8"><div class="dean-skeleton-block h-9 w-64 mb-3"></div><div class="dean-skeleton-block h-4 w-80"></div></div><div class="dean-skeleton-panel p-5 mb-6"><div class="flex gap-3 mb-5"><div class="dean-skeleton-block h-10 w-28"></div><div class="dean-skeleton-block h-10 w-28"></div><div class="dean-skeleton-block h-10 w-28"></div></div><div class="flex gap-3"><div class="dean-skeleton-block h-10 flex-1"></div><div class="dean-skeleton-block h-10 flex-1"></div><div class="dean-skeleton-block h-10 w-32"></div></div></div><div class="dean-skeleton-panel overflow-hidden">@for($row=0;$row<7;$row++)<div class="flex gap-4 p-5 border-b border-gray-100"><div class="dean-skeleton-block h-4 w-2/5"></div><div class="dean-skeleton-block h-4 w-1/4"></div><div class="dean-skeleton-block h-4 w-20"></div></div>@endfor</div></div>

            <div data-dean-skeleton-page="registration-reports" class="dean-skeleton-page bg-white"><div class="flex justify-between items-center mb-8"><div><div class="dean-skeleton-block h-10 w-80 mb-3"></div><div class="dean-skeleton-block h-4 w-96"></div></div><div class="dean-skeleton-block h-11 w-32"></div></div><div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">@for($stat=0;$stat<2;$stat++)<div class="dean-skeleton-panel h-32 p-6 flex justify-between"><div><div class="dean-skeleton-block h-3 w-28 mb-4"></div><div class="dean-skeleton-block h-9 w-20"></div></div><div class="dean-skeleton-block h-14 w-14 rounded-xl"></div></div>@endfor</div><div class="dean-skeleton-panel h-72 p-6"><div class="dean-skeleton-block h-6 w-56 mb-5"></div><div class="dean-skeleton-block h-44 w-full"></div></div></div>

            <div data-dean-skeleton-page="content-overview" class="dean-skeleton-page bg-white"><div class="mb-8"><div class="dean-skeleton-block h-10 w-72 mb-3"></div><div class="dean-skeleton-block h-4 w-96"></div></div><div class="dean-skeleton-panel overflow-hidden"><div class="dean-skeleton-block h-14 w-full rounded-none"></div><div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-6">@for($card=0;$card<3;$card++)<div class="border border-gray-200 rounded-lg p-5 h-48"><div class="dean-skeleton-block h-5 w-3/4 mb-5"></div><div class="dean-skeleton-block h-4 w-full mb-3"></div><div class="dean-skeleton-block h-4 w-4/5"></div></div>@endfor</div></div></div>

            <div data-dean-skeleton-page="users" class="dean-skeleton-page dean-users-skeleton-page">
                <div class="dean-users-skeleton-shell">
                    <div class="dean-users-skeleton-header">
                        <div class="dean-users-skeleton-heading">
                            <div class="dean-skeleton-block dean-users-skeleton-eyebrow"></div>
                            <div class="dean-skeleton-block dean-users-skeleton-title"></div>
                            <div class="dean-skeleton-block dean-users-skeleton-subtitle"></div>
                        </div>
                        <div class="dean-skeleton-block dean-users-skeleton-back"></div>
                    </div>

                    <div class="dean-users-skeleton-metrics">
                        @for($card=0;$card<4;$card++)
                            <div class="dean-skeleton-panel dean-users-skeleton-metric">
                                <div class="dean-skeleton-block dean-users-skeleton-metric-icon"></div>
                                <div class="dean-users-skeleton-metric-copy"><div class="dean-skeleton-block dean-users-skeleton-label"></div><div class="dean-skeleton-block dean-users-skeleton-value"></div></div>
                            </div>
                        @endfor
                    </div>

                    <div class="dean-skeleton-panel dean-users-skeleton-toolbar">
                        <div class="dean-users-skeleton-toolbar-controls"><div class="dean-skeleton-block dean-users-skeleton-search"></div><div class="dean-skeleton-block dean-users-skeleton-filter"></div></div>
                        <div class="dean-skeleton-block dean-users-skeleton-meta"></div>
                    </div>

                    <div class="dean-users-skeleton-grid">
                        @foreach(['admins', 'students'] as $panel)
                            <div class="dean-skeleton-panel dean-users-skeleton-panel">
                                <div class="dean-users-skeleton-panel-header"><div><div class="dean-skeleton-block dean-users-skeleton-panel-eyebrow"></div><div class="dean-skeleton-block dean-users-skeleton-panel-title"></div><div class="dean-skeleton-block dean-users-skeleton-panel-description"></div></div><div class="dean-skeleton-block dean-users-skeleton-count"></div></div>
                                <div class="dean-users-skeleton-table">
                                    @for($row=0;$row<7;$row++)
                                        <div class="dean-users-skeleton-row"><div class="dean-skeleton-block dean-users-skeleton-avatar"></div><div class="dean-users-skeleton-identity"><div class="dean-skeleton-block dean-users-skeleton-name"></div><div class="dean-skeleton-block dean-users-skeleton-email"></div></div><div class="dean-skeleton-block dean-users-skeleton-position"></div><div class="dean-skeleton-block dean-users-skeleton-status"></div><div class="dean-skeleton-block dean-users-skeleton-action"></div></div>
                                    @endfor
                                </div>
                                <div class="dean-users-skeleton-footer"><div class="dean-skeleton-block dean-users-skeleton-footer-label"></div><div class="dean-users-skeleton-pagination"><div class="dean-skeleton-block"></div><div class="dean-skeleton-block"></div><div class="dean-skeleton-block"></div></div></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div data-dean-skeleton-page="profile" class="dean-skeleton-page max-w-4xl mx-auto"><div class="flex justify-between items-center mb-8"><div><div class="dean-skeleton-block h-9 w-48 mb-3"></div><div class="dean-skeleton-block h-4 w-96"></div></div><div class="dean-skeleton-block h-11 w-36"></div></div><div class="dean-skeleton-panel overflow-hidden"><div class="dean-skeleton-block h-32 w-full rounded-none"></div><div class="p-8"><div class="flex items-center gap-5 -mt-16 mb-8"><div class="dean-skeleton-block h-32 w-32 rounded-full border-4 border-white"></div><div><div class="dean-skeleton-block h-7 w-64 mb-3"></div><div class="dean-skeleton-block h-4 w-32"></div></div></div><div class="grid grid-cols-1 md:grid-cols-2 gap-5">@for($field=0;$field<6;$field++)<div><div class="dean-skeleton-block h-3 w-24 mb-2"></div><div class="dean-skeleton-block h-10 w-full rounded-lg"></div></div>@endfor</div></div></div></div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // ============================================
        // INSTANT PAGE TRANSITION WITH SKELETON LOADING
        // ============================================
        
        // Configure for optimal performance
        // Show skeleton loader for instant feedback
        function showSkeletonLoader() {
            const mainContent = document.querySelector('main');
            if (mainContent) {
                // The page-specific overlay handles loading feedback. Keep the page visible.
                mainContent.style.opacity = '1';
                mainContent.style.pointerEvents = 'auto';
            }
        }
        
        // Hide skeleton loader when content is ready
        function hideSkeletonLoader() {
            const mainContent = document.querySelector('main');
            if (mainContent) {
                // Fade in the new content
                mainContent.style.opacity = '1';
                mainContent.style.pointerEvents = 'auto';
            }
        }
        
        // Defer non-critical resources (images, iframes, etc.)
        function deferNonCriticalResources() {
            // Mark images for lazy loading
            const images = document.querySelectorAll('img:not([loading])');
            images.forEach(img => {
                if (!img.hasAttribute('loading')) {
                    img.setAttribute('loading', 'lazy');
                }
            });
            
            // Mark iframes for lazy loading
            const iframes = document.querySelectorAll('iframe:not([loading])');
            iframes.forEach(iframe => {
                if (!iframe.hasAttribute('loading')) {
                    iframe.setAttribute('loading', 'lazy');
                }
            });
        }
        
        // Lazy load media using Intersection Observer
        function lazyLoadMedia() {
            if ('IntersectionObserver' in window) {
                const lazyElements = document.querySelectorAll('[data-lazy-src]');
                
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const element = entry.target;
                            const lazySrc = element.dataset.lazySrc;
                            
                            if (lazySrc) {
                                element.src = lazySrc;
                                element.removeAttribute('data-lazy-src');
                                observer.unobserve(element);
                            }
                        }
                    });
                }, {
                    rootMargin: '50px' // Start loading 50px before element is visible
                });
                
                lazyElements.forEach(element => observer.observe(element));
            }
        }
        
        // Initial lazy load on page load
        document.addEventListener('DOMContentLoaded', function() {
            deferNonCriticalResources();
            lazyLoadMedia();
        });
    </script>
    
    <style id="dean-skeleton-styles">
        #dean-skeleton[hidden] { display: none !important; }
        #dean-skeleton {
            position: fixed;
            inset: 64px 0 0 80px;
            z-index: 35;
            padding: 32px;
            background: rgba(249, 250, 251, 0.98);
            pointer-events: all;
            overflow: auto;
        }
        .dean-skeleton-page { display: none; }
        .dean-skeleton-page.is-active { display: block; }
        .dean-skeleton-block {
            background: #e5e7eb;
            border-radius: 10px;
            animation: deanSkeletonPulse 1.3s ease-in-out infinite;
        }
        .dean-skeleton-panel {
            background: #fff;
            border: 1px solid #eef0f2;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
        }
        .dean-users-skeleton-page { min-height: calc(100vh - 64px); background: #f7f7fa; color: #20212b; }
        .dean-users-skeleton-shell { width: min(100%, 1680px); margin: 0 auto; }
        .dean-users-skeleton-header { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; margin-bottom: 20px; }
        .dean-users-skeleton-heading { flex: 1; min-width: 0; }
        .dean-users-skeleton-eyebrow { width: 116px; height: 10px; margin-bottom: 8px; border-radius: 3px; }
        .dean-users-skeleton-title { width: 205px; height: 31px; border-radius: 6px; }
        .dean-users-skeleton-subtitle { width: min(380px, 75%); height: 12px; margin-top: 8px; border-radius: 4px; }
        .dean-users-skeleton-back { width: 96px; height: 32px; border-radius: 7px; }
        .dean-users-skeleton-metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 16px; }
        .dean-users-skeleton-metric { display: flex; align-items: center; gap: 11px; height: 62px; padding: 13px 14px; border-radius: 8px; }
        .dean-users-skeleton-metric-icon { width: 34px; height: 34px; flex: 0 0 34px; border-radius: 9px; }
        .dean-users-skeleton-metric-copy { flex: 1; min-width: 0; }
        .dean-users-skeleton-label { width: 78px; height: 9px; margin-bottom: 6px; border-radius: 3px; }
        .dean-users-skeleton-value { width: 35px; height: 19px; border-radius: 4px; }
        .dean-users-skeleton-toolbar { display: flex; align-items: center; justify-content: flex-end; gap: 14px; height: 50px; margin-bottom: 12px; padding: 9px; border-radius: 8px; }
        .dean-users-skeleton-toolbar-controls { display: flex; align-items: center; min-width: 0; width: min(100%, 440px); flex: 0 1 440px; gap: 9px; }
        .dean-users-skeleton-search { flex: 1; min-width: 100px; height: 13px; margin-left: 3px; border-radius: 4px; }
        .dean-users-skeleton-filter { width: 66px; height: 32px; border-radius: 6px; }
        .dean-users-skeleton-meta { width: 118px; height: 11px; margin-left: 5px; border-radius: 4px; }
        .dean-users-skeleton-grid { display: grid; grid-template-columns: minmax(320px, .78fr) minmax(520px, 1.22fr); gap: 12px; align-items: start; }
        .dean-users-skeleton-panel { overflow: hidden; min-width: 0; border-radius: 8px; }
        .dean-users-skeleton-panel-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; min-height: 74px; padding: 16px 17px 13px; border-bottom: 1px solid #eff0f3; }
        .dean-users-skeleton-panel-eyebrow { width: 110px; height: 8px; margin-bottom: 6px; border-radius: 3px; }
        .dean-users-skeleton-panel-title { width: 68px; height: 16px; margin-bottom: 6px; border-radius: 4px; }
        .dean-users-skeleton-panel-description { width: 170px; max-width: 100%; height: 9px; border-radius: 3px; }
        .dean-users-skeleton-count { width: 29px; height: 25px; flex: 0 0 29px; border-radius: 6px; }
        .dean-users-skeleton-table { overflow: hidden; }
        .dean-users-skeleton-row { display: flex; align-items: center; gap: 9px; height: 51px; padding: 10px 14px; border-bottom: 1px solid #f0f1f3; }
        .dean-users-skeleton-avatar { width: 30px; height: 30px; flex: 0 0 30px; border-radius: 7px; }
        .dean-users-skeleton-identity { flex: 1; min-width: 74px; }
        .dean-users-skeleton-name { width: min(104px, 80%); height: 9px; margin-bottom: 5px; border-radius: 3px; }
        .dean-users-skeleton-email { width: min(132px, 95%); height: 7px; border-radius: 3px; }
        .dean-users-skeleton-position { width: 61px; height: 19px; flex: 0 0 61px; border-radius: 5px; }
        .dean-users-skeleton-status { width: 49px; height: 19px; flex: 0 0 49px; border-radius: 5px; }
        .dean-users-skeleton-action { width: 14px; height: 14px; flex: 0 0 14px; border-radius: 3px; }
        .dean-users-skeleton-footer { display: flex; align-items: center; justify-content: space-between; gap: 10px; min-height: 43px; padding: 7px 14px; }
        .dean-users-skeleton-footer-label { width: 92px; height: 8px; border-radius: 3px; }
        .dean-users-skeleton-pagination { display: flex; gap: 2px; }
        .dean-users-skeleton-pagination .dean-skeleton-block { width: 23px; height: 23px; border-radius: 4px; }
        @media (max-width: 1180px) { .dean-users-skeleton-grid { grid-template-columns: 1fr; } }
        @media (max-width: 760px) { #dean-skeleton { padding: 20px 14px 30px; } .dean-users-skeleton-header { align-items: flex-start; flex-direction: column; } .dean-users-skeleton-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); } .dean-users-skeleton-toolbar { align-items: stretch; justify-content: flex-start; flex-wrap: wrap; gap: 7px; height: auto; min-height: 50px; } .dean-users-skeleton-toolbar-controls { width: 100%; flex-basis: 100%; } .dean-users-skeleton-meta { margin-left: 3px; } }
        @media (max-width: 460px) { .dean-users-skeleton-metrics { gap: 7px; } .dean-users-skeleton-metric { padding: 11px; } .dean-users-skeleton-metric-icon { width: 31px; height: 31px; flex-basis: 31px; } .dean-users-skeleton-panel-header { padding-left: 13px; padding-right: 13px; } .dean-users-skeleton-footer { align-items: flex-start; flex-direction: column; } }
        @keyframes deanSkeletonPulse {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }
    </style>

    <script>
        (function() {
            if (window.__deanPageNavigation) return;
            window.__deanPageNavigation = true;

            const pageCache = new Map();
            let activeController = null;
            let navigationId = 0;
            let realtimeRefreshPromise = null;
            let realtimeRefreshQueued = false;

            const pageKey = (url) => {
                const target = new URL(url, window.location.origin);
                return target.pathname + target.search;
            };

            const isUsersDirectoryUrl = (url) => {
                const target = new URL(url, window.location.origin);
                return target.pathname.replace(/\/+$/, '') === '/dean/users';
            };

            const skeletonPageFor = (url) => {
                const pathname = new URL(url || window.location.href, window.location.origin)
                    .pathname.replace(/\/+$/, '') || '/';

                if (pathname === '/dean/dashboard') return 'dashboard';
                if (pathname === '/dean/proposals' || pathname.startsWith('/dean/proposals/')) return 'proposals';
                if (pathname === '/dean/financial-reports' || pathname.startsWith('/dean/financial-reports/')) return 'financial-reports';
                if (pathname === '/dean/registration-reports' || pathname.startsWith('/dean/registration-reports/')) return 'registration-reports';
                if (pathname === '/dean/content-overview') return 'content-overview';
                if (pathname === '/dean/users' || pathname.startsWith('/dean/users/')) return 'users';
                if (pathname === '/dean/profile' || pathname.startsWith('/dean/profile/')) return 'profile';

                return 'default';
            };

            const showDeanSkeleton = (url, requestedPage = null) => {
                const skeleton = document.getElementById('dean-skeleton');
                if (!skeleton) return;

                const urlPage = skeletonPageFor(url);
                // The URL is the source of truth. It keeps the right skeleton even
                // if a clicked icon/span does not expose the link data attribute.
                const page = urlPage !== 'default' ? urlPage : (requestedPage || 'default');
                const templates = Array.from(skeleton.querySelectorAll('[data-dean-skeleton-page]'));
                const activeTemplate = templates.find((template) => template.getAttribute('data-dean-skeleton-page') === page)
                    || templates.find((template) => template.getAttribute('data-dean-skeleton-page') === 'default')
                    || templates[0];

                // Never leave the overlay empty. Older links and detail pages may not
                // have a dedicated layout key, so they intentionally use the default.
                templates.forEach((template) => {
                    const isActive = template === activeTemplate;
                    template.classList.toggle('is-active', isActive);
                    template.style.display = isActive ? 'block' : 'none';
                    template.setAttribute('aria-hidden', isActive ? 'false' : 'true');
                });

                skeleton.hidden = false;
                skeleton.setAttribute('aria-hidden', 'false');
                skeleton.style.display = 'block';
                document.body.setAttribute('aria-busy', 'true');

                const main = document.querySelector('.main-content main');
                if (main) {
                    main.style.opacity = '1';
                    main.style.pointerEvents = 'auto';
                }
            };

            const hideDeanSkeleton = () => {
                const skeleton = document.getElementById('dean-skeleton');
                if (skeleton) {
                    skeleton.hidden = true;
                    skeleton.setAttribute('aria-hidden', 'true');
                    skeleton.style.display = 'none';
                }

                document.body.removeAttribute('aria-busy');
                const main = document.querySelector('.main-content main');
                if (main) {
                    main.style.opacity = '1';
                    main.style.pointerEvents = 'auto';
                }
            };

            const setActiveNavigation = (pathname) => {
                document.querySelectorAll('a[data-dean-navigation]').forEach((link) => {
                    const isActive = new URL(link.href, window.location.origin).pathname === pathname;
                    link.classList.toggle('active', isActive);
                    if (isActive) link.setAttribute('aria-current', 'page');
                    else link.removeAttribute('aria-current');
                });
            };

            const executeDeanPageScripts = (container) => {
                container.querySelectorAll('script').forEach((script) => {
                    const executableScript = document.createElement('script');
                    Array.from(script.attributes).forEach((attribute) => {
                        executableScript.setAttribute(attribute.name, attribute.value);
                    });
                    executableScript.textContent = script.textContent;
                    script.replaceWith(executableScript);
                });
            };

            const renderPage = (html, title, { preserveScroll = false } = {}) => {
                const currentMain = document.querySelector('.main-content main');
                const documentFragment = new DOMParser().parseFromString(html, 'text/html');
                const nextMain = documentFragment.querySelector('.main-content main');
                if (!currentMain || !nextMain) throw new Error('Dean page content was not found');

                if (window.Alpine && typeof window.Alpine.destroyTree === 'function') {
                    window.Alpine.destroyTree(currentMain);
                }

                currentMain.innerHTML = nextMain.innerHTML;
                executeDeanPageScripts(currentMain);

                if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                    window.Alpine.initTree(currentMain);
                }

                if (title) document.title = title;
                if (!preserveScroll) window.scrollTo({ top: 0, behavior: 'auto' });
                hideDeanSkeleton();
            };

            const renderUsersDirectory = (html) => {
                const currentDirectory = document.querySelector('.main-content main .dean-users-page');
                const documentFragment = new DOMParser().parseFromString(html, 'text/html');
                const nextDirectory = documentFragment.querySelector('.main-content main .dean-users-page');
                if (!currentDirectory || !nextDirectory) throw new Error('Dean users content was not found');

                if (window.Alpine && typeof window.Alpine.destroyTree === 'function') {
                    window.Alpine.destroyTree(currentDirectory);
                }

                currentDirectory.outerHTML = nextDirectory.outerHTML;
                const renderedDirectory = document.querySelector('.main-content main .dean-users-page');

                if (window.Alpine && renderedDirectory && typeof window.Alpine.initTree === 'function') {
                    window.Alpine.initTree(renderedDirectory);
                }

                return renderedDirectory;
            };

            const navigateUsersDirectory = async (url, pushHistory, requestId, controller) => {
                const target = new URL(url, window.location.origin);
                const currentDirectory = document.querySelector('.main-content main .dean-users-page');
                if (requestId !== navigationId || !currentDirectory) return;

                currentDirectory.classList.add('is-loading');
                currentDirectory.setAttribute('aria-busy', 'true');

                try {
                    const response = await fetch(target.href, {
                        signal: controller.signal,
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html'
                        }
                    });
                    if (!response.ok) throw new Error(`Dean users request failed: ${response.status}`);

                    const html = await response.text();
                    if (requestId !== navigationId) return;

                    const renderedDirectory = renderUsersDirectory(html);
                    if (pushHistory) history.pushState({ dean: true }, '', target.href);
                    if (renderedDirectory) {
                        renderedDirectory.removeAttribute('aria-busy');
                        renderedDirectory.classList.remove('is-loading');
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        console.error('Dean users navigation failed:', error);
                        window.location.href = target.href;
                    }
                } finally {
                    const activeDirectory = document.querySelector('.main-content main .dean-users-page');
                    if (requestId === navigationId && activeDirectory) {
                        activeDirectory.removeAttribute('aria-busy');
                        activeDirectory.classList.remove('is-loading');
                    }
                }
            };

            const startUsersDirectoryNavigation = (url, pushHistory) => {
                activeController?.abort();
                activeController = new AbortController();
                navigationId += 1;
                navigateUsersDirectory(url, pushHistory, navigationId, activeController);
            };

            const navigate = async (url, pushHistory, requestId, controller) => {
                const target = new URL(url, window.location.origin);
                if (requestId !== navigationId) return;

                setActiveNavigation(target.pathname);

                try {
                    let cachedPage = pageCache.get(pageKey(target));
                    if (!cachedPage) {
                        const response = await fetch(target.href, {
                            signal: controller.signal,
                            credentials: 'same-origin',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'text/html'
                            }
                        });
                        if (!response.ok) throw new Error(`Dean page request failed: ${response.status}`);

                        const html = await response.text();
                        const parsed = new DOMParser().parseFromString(html, 'text/html');
                        cachedPage = { html, title: parsed.title };
                        pageCache.set(pageKey(target), cachedPage);
                    }

                    if (requestId !== navigationId) return;
                    renderPage(cachedPage.html, cachedPage.title);
                    if (pushHistory) history.pushState({ dean: true }, '', target.href);
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        console.error('Dean navigation failed:', error);
                        window.location.href = target.href;
                    }
                } finally {
                    if (requestId === navigationId) hideDeanSkeleton();
                }
            };

            const isDeanRealtimeRoute = (topic, url = window.location.href) => {
                const pathname = new URL(url, window.location.origin).pathname.replace(/\/+$/, '') || '/';
                const isProposal = pathname === '/dean/proposals' || pathname.startsWith('/dean/proposals/');
                const isFinancialReport = pathname === '/dean/financial-reports' || pathname.startsWith('/dean/financial-reports/');
                const isRegistrationReport = pathname === '/dean/registration-reports'
                    || /^\/dean\/events\/[^/]+\/registrations$/.test(pathname);

                if (topic === 'announcements') {
                    return pathname === '/dean/dashboard' || pathname === '/dean/content-overview';
                }

                if (topic === 'events') {
                    return pathname === '/dean/dashboard'
                        || pathname === '/dean/content-overview'
                        || isProposal
                        || isFinancialReport
                        || isRegistrationReport;
                }

                if (topic === 'complaints') {
                    return pathname === '/dean/dashboard'
                        || pathname === '/dean/content-overview'
                        || pathname === '/dean/approvals';
                }

                return false;
            };

            window.refreshCurrentDeanPage = async () => {
                const target = new URL(window.location.href, window.location.origin);
                const refreshNavigationId = navigationId;
                const response = await fetch(target.href, {
                    cache: 'no-store',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html'
                    }
                });

                if (!response.ok) throw new Error(`Dean real-time refresh failed: ${response.status}`);

                const html = await response.text();
                if (refreshNavigationId !== navigationId
                    || target.pathname !== window.location.pathname
                    || target.search !== window.location.search) {
                    return false;
                }

                const parsed = new DOMParser().parseFromString(html, 'text/html');
                pageCache.set(pageKey(target), { html, title: parsed.title });
                renderPage(html, parsed.title, { preserveScroll: true });
                setActiveNavigation(target.pathname);
                return true;
            };

            window.refreshCurrentRealtimePage = function (topic) {
                if (!isDeanRealtimeRoute(topic)) return Promise.resolve(false);

                if (realtimeRefreshPromise) {
                    realtimeRefreshQueued = true;
                    return realtimeRefreshPromise;
                }

                realtimeRefreshPromise = (async function () {
                    let refreshed = false;
                    do {
                        realtimeRefreshQueued = false;
                        try {
                            refreshed = await window.refreshCurrentDeanPage();
                        } catch (error) {
                            console.warn('Dean real-time in-place refresh failed:', error);
                            refreshed = false;
                        }
                    } while (realtimeRefreshQueued);

                    return refreshed;
                }()).finally(() => {
                    realtimeRefreshPromise = null;
                });

                return realtimeRefreshPromise;
            };

            function listenForDeanAnnouncementUpdates() {
                if (window.__deanAnnouncementListenerInitialized || !window.AnnouncementEcho) return;

                window.__deanAnnouncementListenerInitialized = true;
                window.AnnouncementEcho.channel('announcements')
                    .listen('.announcement-created', () => window.refreshCurrentRealtimePage('announcements'))
                    .listen('.announcement-updated', () => window.refreshCurrentRealtimePage('announcements'))
                    .listen('.announcement-deleted', () => window.refreshCurrentRealtimePage('announcements'));
            }

            window.addEventListener('announcement-echo-ready', listenForDeanAnnouncementUpdates);
            listenForDeanAnnouncementUpdates();

            document.addEventListener('click', (event) => {
                const link = event.target instanceof Element
                    ? event.target.closest('a[data-dean-navigation]')
                    : null;

                if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target === '_blank') return;

                const target = new URL(link.href, window.location.origin);
                if (target.pathname === window.location.pathname && target.search === window.location.search) return;

                event.preventDefault();

                if (link.dataset.deanPartial === 'users'
                    && isUsersDirectoryUrl(window.location.href)
                    && isUsersDirectoryUrl(target.href)) {
                    startUsersDirectoryNavigation(target.href, true);
                    return;
                }

                activeController?.abort();
                activeController = new AbortController();
                navigationId += 1;
                setActiveNavigation(target.pathname);
                showDeanSkeleton(target.href, link.dataset.deanSkeleton);

                requestAnimationFrame(() => navigate(target.href, true, navigationId, activeController));
            });

            document.addEventListener('submit', (event) => {
                const form = event.target instanceof HTMLFormElement
                    ? event.target.closest('form[data-dean-users-search]')
                    : null;

                if (!form || !isUsersDirectoryUrl(window.location.href)) return;

                event.preventDefault();
                const target = new URL(form.action, window.location.origin);
                const searchParams = new URLSearchParams(new FormData(form));
                const search = (searchParams.get('search') || '').trim();
                target.search = search ? `?search=${encodeURIComponent(search)}` : '';
                startUsersDirectoryNavigation(target.href, true);
            });

            window.addEventListener('popstate', () => {
                activeController?.abort();
                activeController = new AbortController();
                navigationId += 1;

                if (isUsersDirectoryUrl(window.location.href)
                    && isUsersDirectoryUrl(document.location.href)
                    && document.querySelector('.main-content main .dean-users-page')) {
                    navigateUsersDirectory(window.location.href, false, navigationId, activeController);
                    return;
                }

                showDeanSkeleton(window.location.href);
                navigate(window.location.href, false, navigationId, activeController);
            });

            setActiveNavigation(window.location.pathname);
        })();
    </script>

    @stack('scripts')
    
    <!-- RBAC Error Handler -->
    <script>
        // Global error handler for RBAC denials
        (function() {
            // Handle regular fetch/axios errors
            window.addEventListener('unhandledrejection', (event) => {
                if (event.reason && event.reason.response && event.reason.response.status === 403) {
                    event.preventDefault();
                    
                    let message = 'You do not have permission to access this resource.';
                    
                    if (event.reason.response.data && event.reason.response.data.message) {
                        message = event.reason.response.data.message;
                    }
                    
                    showAccessDeniedToast(message);
                }
            });
        })();
        
        // Show access denied toast notification
        function showAccessDeniedToast(message) {
            // Remove any existing toasts
            const existingToasts = document.querySelectorAll('.toast-notification');
            existingToasts.forEach(toast => toast.remove());
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = 'toast-notification';
            toast.innerHTML = `
                <div class="bg-white rounded-lg shadow-2xl border-l-4 border-red-500 p-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                                <i class="ph ph-shield-check text-red-500"></i>
                            </div>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-sm font-semibold text-gray-900 mb-1">
                                Access Denied
                            </h3>
                            <p class="text-sm text-gray-600">
                                ${message}
                            </p>
                        </div>
                        <button onclick="this.closest('.toast-notification').remove()" class="ml-3 flex-shrink-0 text-gray-400 hover:text-gray-600">
                            <i class="ph ph-x"></i>
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                toast.classList.add('hiding');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }
        
        // Handle regular fetch/axios errors
        window.addEventListener('unhandledrejection', (event) => {
            if (event.reason && event.reason.response && event.reason.response.status === 403) {
                event.preventDefault();
                
                let message = 'You do not have permission to access this resource.';
                
                if (event.reason.response.data && event.reason.response.data.message) {
                    message = event.reason.response.data.message;
                }
                
                showAccessDeniedToast(message);
            }
        });
    </script>
    
    <!-- Real-time theme color updates via WebSocket -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setupThemeColorListener();
        });
        
        function setupThemeColorListener() {
            const orgId = '{{ session("current_organization_id") }}';
            
            console.log('🔍 [Dean] Checking Echo availability:', typeof window.Echo);
            console.log('🔍 [Dean] Current organization ID:', orgId);
            
            if (!window.Echo || typeof window.Echo.channel !== 'function') {
                console.log('⏳ [Dean] Echo not ready, waiting...');
                let attempts = 0;
                const interval = setInterval(() => {
                    attempts++;
                    if (window.Echo && typeof window.Echo.channel === 'function') {
                        clearInterval(interval);
                        console.log('✅ [Dean] Echo is now ready after ' + attempts + ' attempts');
                        setupThemeColorListener();
                    }
                    if (attempts > 50) { // 5 seconds
                        clearInterval(interval);
                        console.log('❌ [Dean] Echo failed to load in time');
                    }
                }, 100);
                return;
            }
            
            if (orgId) {
                console.log('🎨 [Dean] Listening for theme updates on organization.' + orgId);
                
                window.Echo.channel('organization.' + orgId)
                    .listen('.theme.colors.updated', (event) => {
                        console.log('🎨 [Dean] Theme colors updated!', event);
                        
                        if (window.applyThemeUpdate) {
                            window.applyThemeUpdate(event.primary_color, event.complementary_color, event.logo_url);
                        } else {
                            // Fallback
                            if (event.primary_color) document.documentElement.style.setProperty('--primary-color', event.primary_color);
                            if (event.complementary_color) document.documentElement.style.setProperty('--complementary-color', event.complementary_color);
                            if (event.logo_url) updateAllLogos(event.logo_url);
                        }
                        
                        showThemeUpdateNotification();
                    });
            }
        }
        
        // Global function to apply theme updates
        window.applyThemeUpdate = function(primaryColor, complementaryColor, logoUrl) {
            const root = document.documentElement;
            
            if (primaryColor) {
                root.style.setProperty('--primary-color', primaryColor);
                const rgb = hexToRgb(primaryColor);
                if (rgb) {
                    root.style.setProperty('--primary-color-rgb', `${rgb.r}, ${rgb.g}, ${rgb.b}`);
                }
            }
            
            if (complementaryColor) {
                root.style.setProperty('--complementary-color', complementaryColor);
                const rgb = hexToRgb(complementaryColor);
                if (rgb) {
                    root.style.setProperty('--complementary-color-rgb', `${rgb.r}, ${rgb.g}, ${rgb.b}`);
                }
            }
            
            if (logoUrl) {
                updateAllLogos(logoUrl);
            }
            
            // Update dynamic hover styles
            updateDynamicHoverStyles(primaryColor, complementaryColor);
            
            // Force recomputation
            forceStyleRecomputation();
            
            // Force repaint
            void document.body.offsetHeight;
        };
        
        // Update all logos on the page
        function updateAllLogos(logoUrl) {
            // Add cache-busting timestamp only for non-blob URLs
            const timestamp = new Date().getTime();
            const isBlob = logoUrl.startsWith('blob:');
            const logoUrlWithCache = isBlob ? logoUrl : logoUrl + (logoUrl.includes('?') ? '&' : '?') + 'v=' + timestamp;
            
            // Update all logo images on the page
            document.querySelectorAll('img[alt*="Logo"], img[src*="logo"], .logo-icon, .nav-logo img').forEach(img => {
                img.src = logoUrlWithCache;
                console.log('✅ [Dean] Logo updated:', img);
            });
            
            // Update favicon
            const favicon = document.querySelector('link[rel="icon"]');
            if (favicon) {
                favicon.href = logoUrlWithCache;
            }
        }
        
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
            
            console.log('🔄 [Dean] Updating styles - Primary:', primaryColor, 'Complementary:', complementaryColor);
            
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
            document.querySelectorAll('nav .nav-item.active i.ph, nav .nav-item.active .nav-icon').forEach(el => {
                el.style.color = complementaryColor;
            });
            
            document.querySelectorAll('nav .nav-item:not(.active) i.ph, nav .nav-item:not(.active) .nav-icon').forEach(el => {
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
                /* Dynamic hover styles - updated in real-time */
                nav .nav-link:not(.active):hover,
                .sidebar .nav-item:not(.active):hover {
                    color: var(--primary-color) !important;
                }
                
                nav .nav-link:not(.active):hover i.ph,
                nav .nav-link:not(.active):hover i.fa,
                .sidebar .nav-item:not(.active):hover i.ph,
                .sidebar .nav-item:not(.active):hover .nav-icon {
                    color: var(--primary-color) !important;
                }
                
                .nav-link-hover:hover {
                    color: var(--primary-color) !important;
                }
                
                nav button:hover i.ph,
                .sidebar button:hover i.ph {
                    color: var(--primary-color) !important;
                }
            `;
            
            console.log('✅ [Dean] Dynamic hover styles updated');
        }
        
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
</body>
</html>
