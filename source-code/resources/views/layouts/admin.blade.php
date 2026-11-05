<!DOCTYPE html>
<html lang="en">
<head>
    <x-theme-provider />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Cache-Control" content="public, max-age=31536000">
    <title>@yield('title', 'Admin - ' . organization_name())</title>
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
    
    <style>
        [x-cloak] { 
            display: none !important; 
        }

        .modal-backdrop {
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
        }
        
        /* Pre-load critical positioning for chat to prevent jumping */
        #admin-chat-widget {
            position: fixed !important;
            bottom: 20px !important;
            right: 20px !important;
            z-index: 50 !important;
        }
        
        #admin-chat-button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
        }

        .gold-icon {
            color: var(--complementary-color, #D7AC28);
        }
    </style>

    <!-- Admin chat CSS -->
    <link rel="stylesheet" href="{{ asset('css/admin-floating-chat.css') }}">

    <style>
        /* Prevent layout shift on page load */
        body {
            overflow-x: hidden;
        }

        .ph-spin {
            display: inline-block;
            animation: ph-spin 1s linear infinite;
        }
        @keyframes ph-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
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
            position: relative;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            will-change: margin-left;
            min-height: 100vh;
        }

        /* Immediate feedback while loading the announcements/events page. */
        .admin-navigation-skeleton {
            display: none;
            position: absolute;
            inset: 64px 0 0;
            z-index: 30;
            min-height: calc(100vh - 64px);
            padding: 2rem;
            background: rgba(249, 250, 251, 0.97);
        }

        .admin-navigation-skeleton.is-visible {
            display: block;
        }

        .admin-navigation-skeleton-block {
            background: #e5e7eb;
            border-radius: 0.5rem;
        }

        .admin-navigation-skeleton-pulse {
            animation: admin-navigation-skeleton-pulse 1.4s ease-in-out infinite;
        }

        .admin-navigation-skeleton-page {
            display: none;
        }

        .admin-navigation-skeleton-page.is-active {
            display: block;
        }

        @keyframes admin-navigation-skeleton-pulse {
            0%, 100% { opacity: 0.55; }
            50% { opacity: 1; }
        }
        
        .main-content-collapsed {
            margin-left: 80px;
        }
        
        .main-content-expanded {
            margin-left: 280px;
        }
        
        /* Bubble/Pill Navigation Item */
        .nav-item {
            position: relative;
            display: flex;
            align-items: center;
            padding: 11px 16px;
            margin: 5px 12px;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            color: #6b7280;
            white-space: nowrap;
            overflow: visible;
        }
        
        .nav-item:hover {
            background: linear-gradient(135deg, var(--primary-color-rgb, 130, 56, 179), var(--primary-color, #8238B3));
            background: linear-gradient(135deg, rgba(var(--primary-color-rgb, 130, 56, 179), 0.1) 0%, rgba(var(--primary-color-rgb, 130, 56, 179), 0.15) 100%);
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(var(--primary-color-rgb, 130, 56, 179), 0.15);
        }
        
        .nav-item.active {
            background: linear-gradient(135deg, var(--primary-color, #8238B3), var(--primary-color, #8238B3));
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
            margin-left: 14px;
            font-weight: 500;
            font-size: 14.5px;
            white-space: nowrap;
            transition: all 0.3s ease;
            color: #4b5563;
            display: inline-block;
        }
        
        /* Hide text in collapsed state */
        .sidebar-collapsed .nav-text {
            display: none;
        }

        /* Keep the icon centered within the collapsed 80px sidebar. */
        .sidebar-collapsed .nav-item {
            justify-content: center;
            align-items: center;
            padding-left: 0;
            padding-right: 0;
            width: 56px;
            margin: 5px auto;
            box-sizing: border-box;
            display: flex;
        }

        /* Ensure Renew button (button.nav-item) matches link width in both states */
        button.nav-item {
            appearance: none;
            border: none;
            background: transparent;
            width: calc(100% - 24px);
            box-sizing: border-box;
            display: flex;
            align-items: center;
        }
        .sidebar-collapsed button.nav-item {
            width: 56px;
            margin: 5px auto;
            justify-content: center;
        }
        .sidebar-collapsed .nav-icon {
            margin: 0 auto;
            display: block;
        }

        .sidebar-collapsed .nav-item:hover,
        .sidebar-collapsed .nav-item.active {
            transform: none;
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
            background: var(--complementary-color, #D7AC28);
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
        
        /* Submenu Styles */
        .nav-submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .nav-submenu.open {
            max-height: 500px;
        }
        
        .nav-submenu-item {
            padding: 8px 16px 8px 56px;
            margin: 4px 12px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #6b7280;
            font-size: 14px;
        }
        
        .nav-submenu-item:hover {
            background: #f3f4f6;
            color: var(--primary-color, #8238B3);
            transform: translateX(4px);
        }
        
        .nav-submenu-item.active {
            background: rgba(var(--primary-color-rgb, 130, 56, 179), 0.1);
            color: var(--primary-color, #8238B3);
            font-weight: 600;
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
            box-shadow: 0 2px 8px rgba(var(--primary-color-rgb, 130, 56, 179), 0.3);
            transition: all 0.3s ease;
            z-index: 10;
        }
        
        .sidebar-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(var(--primary-color-rgb, 130, 56, 179), 0.4);
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
        
        .top-bar:empty {
            display: none;
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
    sidebarOpen: localStorage.getItem('adminSidebarOpen') === 'true'
}" x-init="
    localStorage.getItem('adminSidebarOpen') === null && (localStorage.setItem('adminSidebarOpen', 'false'), sidebarOpen = false)
" x-cloak>
    <div id="toast-container" class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none"></div>
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
                 class="logo-icon" 
                 loading="eager"
                 fetchpriority="high"
                 style="opacity: 1 !important; visibility: visible !important; display: inline-block !important;"
                 onerror="this.src='{{ asset('images/soccsyncLogo-removebg-preview.png') }}'; console.error('❌ Logo failed to load from Cloudflare:', '{{ organization_logo() }}');"
                 onload="console.log('✅ Logo loaded successfully from:', this.src);">
            <span class="logo-text">{{ organization_acronym() }}</span>
        </div>
        
        <!-- Navigation -->
        <nav class="pb-6" style="display: flex; flex-direction: column; min-height: calc(100vh - 80px);">
            <div style="flex: 1;">
                <!-- Dashboard -->
                <a href="{{ route('dashboard') }}" data-admin-navigation-loading class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="ph ph-house nav-icon"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
                
                {{-- OPTIMIZATION: Use pre-cached permissions from View Composer --}}
                {{-- Permissions are checked ONCE per login and cached in session --}}
                {{-- This eliminates repeated database queries on every page load --}}
                
                <!-- Announcements -->
                @if($navPermissions['modules']['announcements'] ?? false)
                <a href="{{ route('admin.announcements.index') }}" data-admin-navigation-loading class="nav-item {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}">
                    <i class="ph ph-megaphone nav-icon"></i>
                    <span class="nav-text">Announcements</span>
                    @if($navPermissions['readOnly']['announcements'] ?? false)
                        <span class="nav-badge">View</span>
                    @endif
                </a>
                @endif
                
                <!-- Events -->
                @if($navPermissions['modules']['events'] ?? false)
                <a href="{{ route('admin.events.index') }}" data-admin-navigation-loading class="nav-item {{ request()->routeIs('admin.events.*') ? 'active' : '' }}">
                    <i class="ph ph-calendar-blank nav-icon"></i>
                    <span class="nav-text">Events</span>
                    @if($navPermissions['readOnly']['events'] ?? false)
                        <span class="nav-badge">View</span>
                    @endif
                </a>
                @endif
                
                <!-- Payments -->
                @if($navPermissions['modules']['payments'] ?? false)
                <a href="{{ route('admin.payments.index') }}" data-admin-navigation-loading class="nav-item {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}">
                    <i class="ph ph-credit-card nav-icon"></i>
                    <span class="nav-text">Payments</span>
                    @if($navPermissions['readOnly']['payments'] ?? false)
                        <span class="nav-badge">View</span>
                    @endif
                </a>
                @endif
                
                <!-- Elections -->
                @if($navPermissions['modules']['elections'] ?? false)
                <a href="{{ route('admin.elections.index') }}" data-admin-navigation-loading class="nav-item {{ request()->routeIs('admin.elections.*') ? 'active' : '' }}">
                    <i class="ph ph-check-square nav-icon"></i>
                    <span class="nav-text">Elections</span>
                </a>
                @endif
                
                <!-- Library -->
                @if($navPermissions['modules']['library'] ?? false)
                <a href="{{ route('admin.library.index') }}" data-admin-navigation-loading class="nav-item {{ request()->routeIs('admin.library.*') ? 'active' : '' }}">
                    <i class="ph ph-book nav-icon"></i>
                    <span class="nav-text">e-Library</span>
                    @if($navPermissions['readOnly']['library'] ?? false)
                        <span class="nav-badge">View</span>
                    @endif
                </a>
                @endif
                
                <!-- EduShare -->
                @if($navPermissions['modules']['edushare'] ?? false)
                <a href="{{ route('admin.edushare.index') }}" data-admin-navigation-loading class="nav-item {{ request()->routeIs('admin.edushare.*') ? 'active' : '' }}">
                    <i class="ph ph-share-network nav-icon"></i>
                    <span class="nav-text">EduShare</span>
                    @if($navPermissions['readOnly']['edushare'] ?? false)
                        <span class="nav-badge">View</span>
                    @endif
                </a>
                @endif
                
                <!-- Documents -->
                @if($navPermissions['modules']['documents'] ?? false)
                <a href="{{ route('admin.documents.student-documents') }}" data-admin-navigation-loading class="nav-item {{ request()->routeIs('admin.documents.*') ? 'active' : '' }}">
                    <i class="ph ph-file-text nav-icon"></i>
                    <span class="nav-text">Documents</span>
                    @if($navPermissions['readOnly']['documents'] ?? false)
                        <span class="nav-badge">View</span>
                    @endif
                </a>
                @endif
                
                <!-- Manage FAQ -->
                @if($navPermissions['modules']['chatbot'] ?? false)
                <a href="{{ route('admin.chatbot.faqs') }}" data-admin-navigation-loading class="nav-item {{ request()->routeIs('admin.chatbot.*') ? 'active' : '' }}">
                    <i class="ph ph-question nav-icon"></i>
                    <span class="nav-text">Manage FAQ</span>
                    @if($navPermissions['readOnly']['chatbot'] ?? false)
                        <span class="nav-badge">View</span>
                    @endif
                </a>
                @endif
                
                <!-- Complaints -->
                @if($navPermissions['modules']['complaints'] ?? false)
                <a href="{{ route('admin.complaints.index') }}" data-admin-navigation-loading class="nav-item {{ request()->routeIs('admin.complaints.*') ? 'active' : '' }}" data-prefetch-delay="300">
                    <i class="ph ph-warning-circle nav-icon"></i>
                    <span class="nav-text">Complaints</span>
                    @if($navPermissions['readOnly']['complaints'] ?? false)
                        <span class="nav-badge">View</span>
                    @endif
                </a>
                @endif
                
                <!-- Renew Organization (only for users with an organization context) -->
                @if(!auth()->user()->isComelec() && auth()->user()->organizations()->exists())
                    @php
                        $navOrgId = session('current_organization_id');
                        $navOrg = $navOrgId ? auth()->user()->organizations()->find($navOrgId) : auth()->user()->organization;
                        $navBlock = method_exists(\App\Models\Organization::class, 'renewBlockInfo') ? \App\Models\Organization::renewBlockInfo($navOrg) : ['blocked'=>false,'title'=>'','message'=>''];
                        $navIsRenewBlocked = $navBlock['blocked'] ?? false;
                        $navRenewBlockedTitle = $navBlock['title'] ?? 'Accreditation Still Active';
                        $navRenewBlockedMsg = $navBlock['message'] ?? 'Please wait for an official announcement.';
                    @endphp

                    @if($navIsRenewBlocked)
                        <button type="button"
                           onclick="if(typeof window.showToast==='function'){ window.showToast(@js($navRenewBlockedMsg), 'warning', @js($navRenewBlockedTitle), 'renew-gate'); } if(window.Alpine){ window.dispatchEvent(new CustomEvent('open-renew-blocked-modal', { detail: { title: @js($navRenewBlockedTitle), message: @js($navRenewBlockedMsg) } })) } else { alert(@js($navRenewBlockedTitle.': '.$navRenewBlockedMsg)) }"
                           class="nav-item {{ request()->routeIs('admin.renew.*') ? 'active' : '' }}">
                            <i class="ph ph-arrows-clockwise nav-icon"></i>
                            <span class="nav-text">Renew Organization</span>
                        </button>
                    @else
                        <a href="{{ route('admin.renew.index') }}" data-admin-navigation-loading class="nav-item {{ request()->routeIs('admin.renew.*') ? 'active' : '' }}">
                            <i class="ph ph-arrows-clockwise nav-icon"></i>
                            <span class="nav-text">Renew Organization</span>
                        </a>
                    @endif
                @endif

                <!-- Report Submissions (reports required by OSAS) -->
                @if(!auth()->user()->isComelec() && auth()->user()->organizations()->exists())
                    <a href="{{ route('admin.required-submissions.index') }}" data-admin-navigation-loading class="nav-item {{ request()->routeIs('admin.required-submissions.*') ? 'active' : '' }}">
                        <i class="ph ph-clipboard-text nav-icon"></i>
                        <span class="nav-text">Report Submissions</span>
                    </a>
                @endif
            </div>
            
            <!-- Bottom Section: Profile Only -->
            <div style="margin-top: auto;">
                <!-- Divider -->
                <div style="border-top: 1px solid #e5e7eb; margin: 16px 12px;"></div>
                
                <!-- Profile Section -->
                <a href="{{ route('admin.profile.show') }}" data-admin-navigation-loading class="nav-item {{ request()->routeIs('admin.profile.*') ? 'active' : '' }}" style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);">
                    <span class="nav-avatar-wrapper nav-icon">
                        @if(auth()->user()->photo)
                            <img src="{{ storage_url(auth()->user()->photo) }}" alt="Profile" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                        @else
                            <i class="ph ph-user-circle" style="font-size: 36px;"></i>
                        @endif
                    </span>
                    <div class="nav-text">
                        <div style="display: flex; flex-direction: column; line-height: 1.3;">
                            <span style="font-weight: 600; color: #1f2937;">{{ auth()->user()->short_name }}</span>
                            <span style="font-size: 11px; color: #6b7280;">
                                @if(auth()->user()->isSuperAdmin())
                                    Super Admin
                                @else
                                    @php
                                        $currentOrgId = session('current_organization_id');
                                        $userPosition = $currentOrgId ? auth()->user()->getPositionInOrganization($currentOrgId) : null;

                                        // Format the position: strip *2 slot suffix, remove underscores, capitalise
                                        if ($userPosition) {
                                            $userPosition = \App\Models\OrganizationUser::formatDisplayLabel($userPosition);
                                            $formattedPosition = str_replace('_', ' ', $userPosition);
                                            $formattedPosition = ucwords(strtolower($formattedPosition));
                                            echo $formattedPosition;
                                        } else {
                                            echo 'Admin';
                                        }
                                    @endphp
                                @endif
                            </span>
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
                <div class="hamburger-btn" @click="sidebarOpen = !sidebarOpen; localStorage.setItem('adminSidebarOpen', sidebarOpen)">
                    <i class="ph ph-list"></i>
                </div>
                
                @if(auth()->user()->isAdmin() && !auth()->user()->isSuperAdmin() && !auth()->user()->isSystemlessFallback())
                    @php
                        $userOrganizations = auth()->user()->activeOrganizations;
                        $currentOrgId = session('current_organization_id');
                        $currentOrg = $userOrganizations->firstWhere('id', $currentOrgId);
                    @endphp
                    @if($userOrganizations->count() > 0)
                        <x-organization-switcher 
                            :organizations="$userOrganizations" 
                            :currentOrganization="$currentOrg" 
                        />
                    @endif
                @endif
            </div>
            
            <div class="flex items-center space-x-4">
                <!-- Settings Button (Only for Presidents and Advisers) -->
                @php
                    $currentOrgId = session('current_organization_id');
                    $userPosition = $currentOrgId ? auth()->user()->getPositionInOrganization($currentOrgId) : null;
                    $canAccessSettings = in_array($userPosition, ['President', 'Organization President', 'Adviser', 'Organization Adviser', 'Senior Adviser', 'Junior Adviser']);
                @endphp
                @if($canAccessSettings)
                <div class="relative" x-data="{ showTooltip: false }">
                    <a href="{{ route('admin.settings.index') }}" data-admin-navigation-loading aria-label="Settings"
                       
                       @mouseenter="showTooltip = true" 
                       @mouseleave="showTooltip = false"
                       class="flex items-center justify-center transition-colors">
                        <i class="ph ph-gear text-xl gold-icon"></i>
                    </a>
                    <!-- Tooltip -->
                    <div x-show="showTooltip" 
                         x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 transform translate-y-1"
                         x-transition:enter-end="opacity-100 transform translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 transform translate-y-0"
                         x-transition:leave-end="opacity-0 transform translate-y-1"
                         class="absolute top-full mt-2 left-1/2 transform -translate-x-1/2 px-3 py-2 text-white text-xs rounded-lg shadow-lg whitespace-nowrap z-50"
                         style="background-color: var(--primary-color);">
                        Settings
                        <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent" style="border-bottom-color: var(--primary-color);"></div>
                    </div>
                </div>
                @endif

                <!-- Notification Bell -->
                <div class="relative" x-data="notificationBell()" @click.outside="closeDropdown()">
                    <button @click="toggleDropdown()" class="relative flex items-center justify-center p-2 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="ph ph-bell text-xl gold-icon"></i>
                        <template x-if="unreadCount > 0">
                            <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold text-white bg-red-500 rounded-full min-w-[18px] min-h-[18px]" x-text="unreadCount"></span>
                        </template>
                    </button>

                    <div x-show="open"
                         x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 transform translate-y-1"
                         x-transition:enter-end="opacity-100 transform translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 transform translate-y-0"
                         x-transition:leave-end="opacity-0 transform translate-y-1"
                         class="absolute right-0 mt-2 w-96 bg-white rounded-lg shadow-xl border border-gray-200 z-50 max-h-[500px] flex flex-col">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-800">Notifications</h3>
                            <template x-if="unreadCount > 0">
                                <button @click="markAllRead()" class="text-xs text-blue-600 hover:text-blue-800 font-medium transition">Mark all as read</button>
                            </template>
                        </div>

                        <div class="overflow-y-auto flex-1" x-ref="list">
                            <template x-if="notifications.length === 0">
                                <div class="flex flex-col items-center justify-center py-10 text-gray-400">
                                    <i class="ph ph-bell-slash text-3xl mb-2"></i>
                                    <p class="text-sm">No notifications yet</p>
                                </div>
                            </template>
                            <template x-for="notif in notifications" :key="notif.id">
                                <a :href="notif.data.url || '#'"
                                   @click.prevent="markRead(notif)"
                                   class="flex items-start px-4 py-3 hover:bg-gray-50 transition cursor-pointer border-b border-gray-50 last:border-b-0"
                                   :class="{ 'bg-blue-50/50': notif.read_at === null }">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                         :class="{
                                             'bg-green-100': notif.data.color === 'green',
                                             'bg-red-100': notif.data.color === 'red',
                                             'bg-blue-100': notif.data.color === 'blue',
                                             'bg-amber-100': notif.data.color === 'amber',
                                             'bg-gray-100': !notif.data.color
                                         }">
                                        <i class="ph text-sm"
                                           :class="notif.data.icon || 'ph-bell'"
                                           :style="{ color: notif.data.color === 'green' ? '#16a34a' : notif.data.color === 'red' ? '#dc2626' : notif.data.color === 'blue' ? '#2563eb' : notif.data.color === 'amber' ? '#d97706' : '#6b7280' }"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate" x-text="notif.data.title || 'Notification'"></p>
                                        <p class="text-xs text-gray-500 mt-0.5 line-clamp-2" x-text="notif.data.message"></p>
                                        <p class="text-xs text-gray-400 mt-1" x-text="timeAgo(notif.created_at)"></p>
                                    </div>
                                    <template x-if="notif.read_at === null">
                                        <span class="flex-shrink-0 w-2 h-2 rounded-full bg-blue-500 mt-2"></span>
                                    </template>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>

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
                                        localStorage.removeItem('adminSidebarOpen');
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

        <div id="admin-navigation-skeleton"
             class="admin-navigation-skeleton"
             aria-hidden="true"
             aria-live="polite">
            <span data-admin-skeleton-title class="sr-only">Loading page...</span>

        <!-- Dashboard -->
        <div data-admin-skeleton-page="dashboard" class="admin-navigation-skeleton-page px-6 py-6">
            <div class="flex items-center justify-between mb-5"><div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-6 w-48 mb-2"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-72"></div></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-28"></div></div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">@for($stat = 0; $stat < 5; $stat++)<div class="bg-white border border-gray-200 rounded-lg px-4 py-3 flex items-center gap-3"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-9 w-9 rounded-md"></div><div class="space-y-2 flex-1"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-20"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-5 w-10"></div></div></div>@endfor</div>
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5"><div class="xl:col-span-2 bg-white rounded-lg border border-gray-200 p-5 h-72"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-5 w-48 mb-6"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-48 w-full"></div></div><div class="bg-white rounded-lg border border-gray-200 p-5 h-72"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-5 w-40 mb-6"></div><div class="space-y-4">@for($row = 0; $row < 4; $row++)<div class="flex gap-3"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-8 rounded-full"></div><div class="flex-1 space-y-2"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-3/4"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-1/2"></div></div></div>@endfor</div></div></div>
        </div>

        <!-- Announcements -->
        <div data-admin-skeleton-page="announcements" class="admin-navigation-skeleton-page max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6"><div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-64 mb-3"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-80"></div></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-10 w-40"></div></div>
            <div class="bg-white rounded-lg shadow overflow-hidden"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-12 rounded-none"></div><div class="divide-y divide-gray-100">@for($row = 0; $row < 5; $row++)<div class="flex items-center gap-4 p-5"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-12 w-12 rounded-lg"></div><div class="flex-1 space-y-2"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-2/5"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-3/5"></div></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-20"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-20"></div></div>@endfor</div></div>
        </div>

        <!-- Events -->
        <div data-admin-skeleton-page="events" class="admin-navigation-skeleton-page max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6"><div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-48 mb-3"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-80"></div></div><div class="flex gap-2"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-10 w-28"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-10 w-32"></div></div></div>
            <div class="bg-white rounded-lg shadow overflow-hidden"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-12 rounded-none"></div><div class="divide-y divide-gray-100">@for($row = 0; $row < 5; $row++)<div class="flex items-center gap-4 p-5"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-12 w-12 rounded-lg"></div><div class="flex-1 space-y-2"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-3/5"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-2/5"></div></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-24"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-20"></div></div>@endfor</div></div>
        </div>

        <!-- Payments -->
        <div data-admin-skeleton-page="payments" class="admin-navigation-skeleton-page max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center mb-6"><div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-48 mb-3"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-80"></div></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-10 w-40"></div></div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">@for($card = 0; $card < 3; $card++)<div class="bg-white rounded-lg border border-gray-200 p-5 space-y-3"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-24"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-32"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-20"></div></div>@endfor</div>
            <div class="bg-white rounded-lg shadow overflow-hidden"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-12 rounded-none"></div>@for($row = 0; $row < 5; $row++)<div class="flex items-center gap-4 p-5 border-b border-gray-100"><div class="flex-1 space-y-2"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-1/2"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-1/3"></div></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-6 w-20 rounded-full"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-20"></div></div>@endfor</div>
        </div>

        <!-- Elections -->
        <div data-admin-skeleton-page="elections" class="admin-navigation-skeleton-page px-6 py-6"><div class="flex justify-between items-center mb-5"><div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-6 w-48 mb-2"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-80"></div></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-9 w-32"></div></div><div class="space-y-4">@for($election = 0; $election < 3; $election++)<div class="bg-white border border-gray-200 rounded-lg p-5 space-y-4"><div class="flex justify-between"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-5 w-56"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-6 w-20 rounded-full"></div></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-full"></div><div class="flex gap-2"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-20"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-20"></div></div></div>@endfor</div></div>

        <!-- Library and EduShare -->
        <div data-admin-skeleton-page="library" class="admin-navigation-skeleton-page max-w-full mx-auto px-4 sm:px-6 lg:px-8"><div class="flex justify-between items-center mb-8"><div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-9 w-64 mb-3"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-80"></div></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-12 w-40"></div></div><div class="bg-white rounded-lg shadow overflow-hidden"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-12 rounded-none"></div>@for($row = 0; $row < 6; $row++)<div class="flex items-center gap-4 p-5 border-b border-gray-100"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-12 w-12 rounded-lg"></div><div class="flex-1 space-y-2"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-2/5"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-1/3"></div></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-20"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-20"></div></div>@endfor</div></div>
        <div data-admin-skeleton-page="edushare" class="admin-navigation-skeleton-page max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"><div class="flex justify-between items-center mb-6"><div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-48 mb-3"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-56"></div></div><div class="flex gap-3"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-10 w-32"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-10 w-24"></div></div></div><div class="mb-6 bg-white rounded-lg border p-2"><div class="flex gap-2"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-10 flex-1"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-10 flex-1"></div></div></div><div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-8">@for($folder = 0; $folder < 4; $folder++)<div class="bg-white rounded-lg border p-4 space-y-3"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-12 w-12 rounded-lg"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-3/4"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-1/2"></div></div>@endfor</div><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">@for($file = 0; $file < 6; $file++)<div class="bg-white rounded-lg border p-4 space-y-4"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-12 w-12 rounded-lg"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-4/5"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-9 w-full"></div></div>@endfor</div></div>
        <!-- Documents -->
        <div data-admin-skeleton-page="documents" class="admin-navigation-skeleton-page min-h-screen bg-gray-50 py-4"><div class="max-w-7xl mx-auto px-4 sm:px-6"><div class="flex justify-between items-center mb-8"><div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-64 mb-2"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-80"></div></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-10 w-40"></div></div><div class="bg-white rounded-xl shadow overflow-hidden"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-16 rounded-none"></div><div class="p-5"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-6 w-56 mb-5"></div><div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">@for($student = 0; $student < 6; $student++)<div class="bg-white border border-gray-200 rounded-xl p-4 space-y-3"><div class="flex justify-between"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-5 w-32"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-6 w-16 rounded-full"></div></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-2/3"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-9 w-full rounded-lg"></div></div>@endfor</div></div></div></div></div>

        <!-- FAQ, Complaints, Renewal -->
        <div data-admin-skeleton-page="chatbot" class="admin-navigation-skeleton-page max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"><div class="flex justify-between items-center border-b border-gray-200 pb-5 mb-5"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-48"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-10 w-28"></div></div><div class="bg-white rounded-lg border shadow-sm overflow-hidden">@for($faq = 0; $faq < 6; $faq++)<div class="flex items-center gap-4 p-5 border-b border-gray-100"><div class="flex-1 space-y-2"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-3/5"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-1/3"></div></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-7 w-20 rounded-full"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-16"></div></div>@endfor</div></div>
        <div data-admin-skeleton-page="complaints" class="admin-navigation-skeleton-page max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"><div class="mb-5"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-40 mb-2"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-72 mb-2"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-96"></div></div><div class="bg-white rounded-lg border shadow-sm overflow-hidden"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-14 rounded-none"></div>@for($row = 0; $row < 6; $row++)<div class="flex items-center gap-4 p-5 border-b border-gray-100"><div class="flex-1 space-y-2"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-2/5"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-3/5"></div></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-6 w-20 rounded-full"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-20"></div></div>@endfor</div></div>
        <div data-admin-skeleton-page="renew" class="admin-navigation-skeleton-page max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"><div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between mb-4"><div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-40 mb-2"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-72 mb-2"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-96"></div></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-6 w-28 rounded-full"></div></div><div class="grid grid-cols-2 lg:grid-cols-4 border border-gray-200 bg-white rounded-lg divide-x divide-gray-200 mb-4">@for($stat = 0; $stat < 4; $stat++)<div class="px-4 py-3 sm:px-5"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-20 mb-2"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-5 w-24"></div></div>@endfor</div><div class="bg-white border border-gray-200 rounded-lg px-4 py-3 mb-4"><div class="flex gap-2">@for($step = 0; $step < 6; $step++)<div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-7 w-7 rounded-full"></div>@if($step < 5)<div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-1 flex-1 self-center rounded"></div>@endif @endfor</div></div><div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_280px] gap-4"><div class="bg-white border border-gray-200 rounded-lg p-4 space-y-4">@for($field = 0; $field < 4; $field++)<div class="space-y-2"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-28"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-10 w-full rounded-lg"></div></div>@endfor</div><div class="space-y-3"><div class="bg-white border border-gray-200 rounded-lg p-4 space-y-2"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-24"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-full"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-5/6"></div></div></div></div></div>

        <!-- Profile and Settings -->
        <div data-admin-skeleton-page="profile" class="admin-navigation-skeleton-page min-h-full bg-slate-50/60 px-4 py-5 sm:px-6 lg:px-8"><div class="mx-auto max-w-7xl"><div class="mb-5 border-b border-slate-200 pb-4"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-40 mb-3"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-48 mb-2"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-96"></div></div><div class="bg-white rounded-lg border p-6"><div class="flex items-center gap-5 mb-8"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-20 w-20 rounded-full"></div><div class="space-y-3"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-6 w-56"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-32"></div></div></div><div class="grid grid-cols-1 md:grid-cols-2 gap-5">@for($field = 0; $field < 6; $field++)<div class="space-y-2"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-24"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-10 w-full rounded-lg"></div></div>@endfor</div></div></div></div>
        <div data-admin-skeleton-page="settings" class="admin-navigation-skeleton-page max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"><div class="mb-6"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-40 mb-3"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-80"></div></div><div class="bg-white rounded-lg border shadow-sm p-6"><div class="flex gap-2 mb-8"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-9 w-24"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-9 w-24"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-9 w-24"></div></div><div class="space-y-5">@for($setting = 0; $setting < 5; $setting++)<div class="flex items-center justify-between border-b border-gray-100 pb-4"><div class="space-y-2"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-48"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-72"></div></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-10 w-32 rounded-lg"></div></div>@endfor</div></div></div>

        <!-- Final results: mirrors the neutral gray/white results layout while its fragment loads. -->
        <div data-admin-skeleton-page="election-results" class="admin-navigation-skeleton-page px-6 py-6">
            <div class="mb-4">
                <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-32 mb-3"></div>
                <div class="rounded-lg border border-gray-200 bg-white px-5 py-4">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex-1 space-y-2">
                            <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-5 w-56"></div>
                            <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-80 max-w-full"></div>
                        </div>
                        <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-6 w-16 rounded-full"></div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                @for($stat = 0; $stat < 4; $stat++)
                    <div class="bg-white border border-gray-200 rounded-lg px-4 py-3 flex items-center gap-3">
                        <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-9 w-9 rounded-md"></div>
                        <div class="flex-1 space-y-2">
                            <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-20"></div>
                            <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-5 w-10"></div>
                        </div>
                    </div>
                @endfor
            </div>

            <div class="mb-4 flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <div class="flex-1 space-y-2">
                    <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-28"></div>
                    <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-52 max-w-full"></div>
                </div>
                <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-24 rounded-md"></div>
            </div>

            <div class="mb-3 flex items-center justify-between">
                <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-5 w-32"></div>
                <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-20"></div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @for($position = 0; $position < 6; $position++)
                    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                        <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-2.5">
                            <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-32"></div>
                            <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-14 rounded-full"></div>
                        </div>
                        <div class="space-y-2 p-3">
                            @for($candidate = 0; $candidate < 2; $candidate++)
                                <div class="flex items-center gap-2.5 rounded-md border border-gray-100 bg-white px-3 py-2">
                                    <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-8 rounded-md"></div>
                                    <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-8 rounded-full"></div>
                                    <div class="flex-1 space-y-1.5"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-3/5"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-2.5 w-1/4"></div></div>
                                    <div class="space-y-1.5"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-8"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-2.5 w-6"></div></div>
                                </div>
                            @endfor
                        </div>
                    </div>
                @endfor
            </div>
        </div>

        </div>

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

        <!-- Page Content -->
        <main class="p-8 relative" style="transition: opacity 0.15s ease-in-out;">
            @yield('content')
        </main>

        <!-- Legacy fallback skeleton retained for unmapped pages; active navigation uses the primary registry above. -->
        <div id="admin-navigation-skeleton-fallback"
             class="admin-navigation-skeleton"
             aria-hidden="true"
             aria-live="polite">
            <span data-admin-skeleton-title class="sr-only">Loading page...</span>
            <div data-admin-skeleton-page="default" class="admin-navigation-skeleton-page is-active max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-9 w-72 mb-3"></div>
                        <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-96"></div>
                    </div>
                    <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-12 w-48"></div>
                </div>

                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-12 rounded-none"></div>
                    <div class="divide-y divide-gray-100">
                        <div class="flex items-center gap-6 p-6">
                            <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-12 w-12 flex-shrink-0"></div>
                            <div class="flex-1 space-y-3"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-2/5"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-1/4"></div></div>
                            <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-24"></div>
                            <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-20"></div>
                        </div>
                        <div class="flex items-center gap-6 p-6">
                            <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-12 w-12 flex-shrink-0"></div>
                            <div class="flex-1 space-y-3"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-3/5"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-1/3"></div></div>
                            <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-28"></div>
                            <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-20"></div>
                        </div>
                        <div class="flex items-center gap-6 p-6">
                            <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-12 w-12 flex-shrink-0"></div>
                            <div class="flex-1 space-y-3"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-1/2"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-1/4"></div></div>
                            <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-24"></div>
                            <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-20"></div>
                        </div>
                        <div class="flex items-center gap-6 p-6">
                            <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-12 w-12 flex-shrink-0"></div>
                            <div class="flex-1 space-y-3"><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-2/3"></div><div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-3 w-1/3"></div></div>
                            <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-4 w-20"></div>
                            <div class="admin-navigation-skeleton-block admin-navigation-skeleton-pulse h-8 w-20"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
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
                <div class="admin-chat-contacts"></div>
            </div>
            <div class="admin-chat-right-panel">
                <div class="admin-chat-welcome">
                    <div class="admin-welcome-icon">
                        <svg width="80" height="60" viewBox="0 0 100 75" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="10" y="15" width="80" height="50" rx="8" fill="#f3f4f6" stroke="var(--primary-color)" stroke-width="2"/>
                            <rect x="15" y="20" width="70" height="30" rx="4" fill="white"/>
                            <rect x="20" y="25" width="25" height="3" rx="1.5" fill="var(--primary-color)"/>
                            <rect x="20" y="30" width="35" height="3" rx="1.5" fill="var(--primary-color)"/>
                            <rect x="20" y="35" width="20" height="3" rx="1.5" fill="var(--primary-color)"/>
                            <circle cx="65" cy="35" r="8" fill="var(--primary-color)"/>
                            <circle cx="65" cy="35" r="3" fill="white"/>
                            <circle cx="67" cy="33" r="1" fill="var(--primary-color)"/>
                            <circle cx="69" cy="35" r="1" fill="var(--primary-color)"/>
                            <circle cx="67" cy="37" r="1" fill="var(--primary-color)"/>
                        </svg>
                    </div>
                    <h4>Student Messages</h4>
                    <p>Respond to student inquiries</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Defer admin floating chat script to prevent blocking page load -->
    <script defer src="{{ asset('js/admin-floating-chat.js') }}?v={{ filemtime(public_path('js/admin-floating-chat.js')) }}&build=osas-logo-r2"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    @vite(['resources/js/app.js'])

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const organizationId = @js(session('current_organization_id'));
            if (!organizationId || !window.Echo || typeof window.Echo.private !== 'function') return;

            window.Echo.private(`organization-payments.${organizationId}`)
                .listen('.student-payment-updated', function () {
                    if (!/^\/admin\/payments(?:\/|$)/.test(window.location.pathname)) return;

                    window.clearAdminPageCache?.();
                    window.refreshCurrentAdminPage?.();
                });
        });
    </script>
    
    @php
        // Frontend-only renew gate flag (expired only). No backend behavior changes:
        // this only drives the bounce-back toast below. Security stays server-side.
        $gateOrgForToast = null;
        try {
            $gateOrgIdForToast = session('current_organization_id');
            $gateOrgForToast = $gateOrgIdForToast ? auth()->user()->organizations()->find($gateOrgIdForToast) : auth()->user()->organization;
        } catch (\Throwable $e) { $gateOrgForToast = null; }
        $gateExpiredForToast = false;
        try {
            $gateExpiredForToast = $gateOrgForToast && method_exists($gateOrgForToast, 'accreditationStatus')
                ? $gateOrgForToast->accreditationStatus() === 'expired'
                : false;
        } catch (\Throwable $e) { $gateExpiredForToast = false; }
    @endphp
    <script>
        // Expired-accreditation flag for the frontend renew gate (toast + bounce-back only).
        window.__adminRenewGate = Object.assign(window.__adminRenewGate || {}, { expired: @json($gateExpiredForToast) });
    </script>

    <script>
        // ============================================
        // INSTANT PAGE TRANSITION WITH SKELETON LOADING
        // ============================================

        // Frontend-only renew gate (expired orgs only): dashboard clicks that leave
        // the allowed pages stay on dashboard, renew clicks stay on renew, with a
        // toast explaining renewal is required first. Capture phase so it runs
        // before the SPA navigation handler below. UX only — not a security gate.
        (function initAdminRenewGateToast() {
            if (window.__adminRenewGateBound) return;
            window.__adminRenewGateBound = true;
            const allowedPaths = ['/dashboard', '/admin/renew', '/admin/profile'];
            const isAllowedPath = (pathname) => allowedPaths.some((p) => pathname === p || pathname.startsWith(p + '/'));
            document.addEventListener('click', function (event) {
                const gate = window.__adminRenewGate || {};
                if (!gate.expired) return;
                const link = event.target instanceof Element
                    ? event.target.closest('a[data-admin-navigation-loading]')
                    : null;
                if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank') {
                    return;
                }
                let targetUrl = null;
                try { targetUrl = new URL(link.href, window.location.origin); } catch (e) { return; }
                if (isAllowedPath(targetUrl.pathname)) return;
                if (targetUrl.pathname === window.location.pathname && targetUrl.search === window.location.search) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                if (typeof window.showToast === 'function') {
                    window.showToast('You need to renew before going to that page. Please complete your accreditation renewal first.', 'warning', 'Renew Required', 'renew-gate');
                }
            }, true);
        })();

        // OPTIMIZATION: Reduce DOM queries by caching main content element
        let mainContentCache = null;
        
        // Configure for optimal performance
        document.addEventListener('DOMContentLoaded', function() {
            // Cache main content element
            mainContentCache = document.querySelector('main');
            
            // Defer non-critical resources
            deferNonCriticalResources();
            lazyLoadMedia();
        });
        
        
        
        // OPTIMIZED: Show skeleton loader for instant feedback
        function showSkeletonLoader() {
            const mainContent = mainContentCache || document.querySelector('main');
            if (mainContent) {
                mainContent.style.opacity = '0.3';
                mainContent.style.pointerEvents = 'none';
            }
        }

        let activeAdminNavigationController = null;
        let adminNavigationRequestId = 0;
        const adminPageCache = new Map();
        const adminPageCacheLimit = 12;
        let adminPageCacheVersion = 0;

        function getAdminPageCacheKey(url) {
            const targetUrl = new URL(url, window.location.origin);
            return `${targetUrl.pathname}${targetUrl.search}`;
        }

        function trimAdminPageCache() {
            while (adminPageCache.size > adminPageCacheLimit) {
                adminPageCache.delete(adminPageCache.keys().next().value);
            }
        }

        function cacheAdminPage(url, html, title) {
            adminPageCache.delete(getAdminPageCacheKey(url));
            adminPageCache.set(getAdminPageCacheKey(url), {
                html,
                title,
                cachedAt: Date.now()
            });
            trimAdminPageCache();
        }

        // Keep cached pages as raw server-rendered fragments. Caching main.innerHTML
        // after Alpine has initialized it also caches x-for clones and causes Alpine
        // to hydrate the same tree twice when the page is visited again.

        window.clearAdminPageCache = function() {
            adminPageCache.clear();
            adminPageCacheVersion++;
        };

        // Any write request can change the data rendered by a cached Admin page.
        // Clear cached fragments before the request so returning never shows stale mutations.
        if (!window.__adminPageCacheFetchWrapped) {
            const nativeFetch = window.fetch.bind(window);
            window.fetch = function(resource, options = {}) {
                const method = (options.method || (resource && resource.method) || 'GET').toUpperCase();

                if (method !== 'GET' && method !== 'HEAD') {
                    window.clearAdminPageCache();
                }

                return nativeFetch(resource, options);
            };
            window.__adminPageCacheFetchWrapped = true;
        }

        // Show a visible skeleton while the target page is fetched in the background.
        window.showAdminNavigationSkeleton = function(type, label = 'page') {
            const skeleton = document.getElementById('admin-navigation-skeleton');
            if (!skeleton) return;

            const titleBlock = skeleton.querySelector('[data-admin-skeleton-title]');
            if (titleBlock) {
                titleBlock.textContent = `Loading ${label}...`;
            }

            const pathname = new URL(type || window.location.pathname, window.location.origin).pathname.replace(/\/+$/, '') || '/';
            let skeletonPage = 'default';
            if (pathname === '/dashboard') skeletonPage = 'dashboard';
            else if (pathname === '/admin/announcements') skeletonPage = 'announcements';
            else if (pathname === '/admin/events') skeletonPage = 'events';
            else if (pathname === '/admin/payments') skeletonPage = 'payments';
            else if (/^\/admin\/elections\/[^/]+\/results$/.test(pathname)) skeletonPage = 'election-results';
            else if (pathname === '/admin/elections') skeletonPage = 'elections';
            else if (pathname === '/admin/library') skeletonPage = 'library';
            else if (pathname === '/admin/edushare') skeletonPage = 'edushare';
            else if (pathname === '/admin/documents/student-documents') skeletonPage = 'documents';
            else if (pathname === '/admin/chatbot/faqs') skeletonPage = 'chatbot';
            else if (pathname === '/admin/complaints') skeletonPage = 'complaints';
            else if (pathname === '/admin/renew') skeletonPage = 'renew';
            else if (pathname === '/admin/profile') skeletonPage = 'profile';
            else if (pathname === '/admin/settings') skeletonPage = 'settings';

            skeleton.querySelectorAll('[data-admin-skeleton-page]').forEach(page => {
                page.classList.toggle('is-active', page.dataset.adminSkeletonPage === skeletonPage);
            });

            skeleton.classList.add('is-visible');
            skeleton.setAttribute('aria-hidden', 'false');
            document.body.setAttribute('aria-busy', 'true');
        };

        window.hideAdminNavigationSkeleton = function() {
            const skeleton = document.getElementById('admin-navigation-skeleton');
            if (!skeleton) return;

            skeleton.classList.remove('is-visible');
            skeleton.setAttribute('aria-hidden', 'true');
            document.body.removeAttribute('aria-busy');
        };

        // Return the label shown in the clicked Admin navigation item for the loading state.
        function getAdminNavigationLabel(link) {
            if (!link) return 'page';

            const label = link.querySelector('.nav-text')?.textContent
                || link.getAttribute('aria-label')
                || link.textContent;

            return label.trim().replace(/\s+/g, ' ') || 'page';
        }

        function getAdminNavigationLink(url) {
            const targetUrl = new URL(url, window.location.origin);

            return Array.from(document.querySelectorAll('a[data-admin-navigation-loading]'))
                .find(link => new URL(link.href, window.location.origin).pathname === targetUrl.pathname) || null;
        }

        function getAdminNavigationType(url) {
            const targetUrl = new URL(url, window.location.origin);
            return getAdminNavigationLink(targetUrl.href) ? targetUrl.pathname : null;
        }

        function setAdminNavigationActive(type) {
            document.querySelectorAll('a[data-admin-navigation-loading]').forEach(link => {
                const linkUrl = new URL(link.href, window.location.origin);
                const isElectionPage = linkUrl.pathname === '/admin/elections'
                    && type !== null
                    && (type === '/admin/elections' || type.startsWith('/admin/elections/'));
                const isActive = linkUrl.pathname === type || isElectionPage;
                link.classList.toggle('active', isActive);

                if (isActive) {
                    link.setAttribute('aria-current', 'page');
                } else {
                    link.removeAttribute('aria-current');
                }
            });
        }

        // Scripts inside fetched HTML are inert when assigned through innerHTML.
        // Recreate them so page-specific helpers (such as event toasts) initialize.
        function executeAdminPageScripts(container) {
            container.querySelectorAll('script').forEach(script => {
                const executableScript = document.createElement('script');
                Array.from(script.attributes).forEach(attribute => {
                    executableScript.setAttribute(attribute.name, attribute.value);
                });
                executableScript.textContent = script.textContent;
                script.replaceWith(executableScript);
            });
        }

        function renderAdminPageContent(mainContent, html, title) {
            if (window.Alpine && typeof window.Alpine.destroyTree === 'function') {
                window.Alpine.destroyTree(mainContent);
            }

            mainContent.innerHTML = html;
            mainContentCache = mainContent;
            executeAdminPageScripts(mainContent);

            if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                window.Alpine.initTree(mainContent);
            }

            if (title) {
                document.title = title;
            }
        }

        async function fetchAdminPageFragment(url, signal = null) {
            const fragmentUrl = new URL(url, window.location.origin);
            fragmentUrl.searchParams.set('fragment', '1');

            const requestOptions = {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                },
                credentials: 'same-origin'
            };

            if (signal) {
                requestOptions.signal = signal;
            }

            const response = await fetch(fragmentUrl.href, requestOptions);

            if (!response.ok) throw new Error('Failed to load Admin page');

            const pageHtml = await response.text();
            const pageDocument = new DOMParser().parseFromString(pageHtml, 'text/html');
            const nextMainContent = pageDocument.querySelector('main');

            if (!nextMainContent) throw new Error('Admin page content was not found');

            return {
                html: nextMainContent.innerHTML,
                title: pageDocument.title
            };
        }

        const adminRealtimePagePaths = {
            events: /^\/admin\/events(?:\/|$)/,
            payments: /^\/admin\/payments(?:\/|$)/,
            elections: /^\/admin\/elections(?:\/|$)/,
            edushare: /^\/admin\/edushare(?:\/|$)/,
            library: /^\/admin\/library(?:\/|$)/,
            complaints: /^\/admin\/complaints(?:\/|$)/,
            documents: /^\/admin\/documents(?:\/|$)/,
            chatbot: /^\/admin\/chatbot\/faqs(?:\/|$)/,
        };
        let adminRealtimeRefreshPromise = null;

        function replaceAdminPageInPlace(url, historyMode = 'none') {
            if (adminRealtimeRefreshPromise) return adminRealtimeRefreshPromise;

            const targetUrl = new URL(url, window.location.origin);
            const mainContent = mainContentCache || document.querySelector('main');
            if (!mainContent) return Promise.resolve(false);

            adminRealtimeRefreshPromise = fetchAdminPageFragment(targetUrl.href)
                .then(page => {
                    cacheAdminPage(targetUrl.href, page.html, page.title);
                    renderAdminPageContent(mainContent, page.html, page.title);

                    if (historyMode === 'push') {
                        window.history.pushState({ adminNavigation: true }, '', targetUrl.href);
                        window.scrollTo({ top: 0, behavior: 'auto' });
                    } else if (historyMode === 'replace') {
                        window.history.replaceState({ adminNavigation: true }, '', targetUrl.href);
                    }

                    return true;
                })
                .catch(error => {
                    console.warn('Admin in-place page refresh failed:', error);
                    return false;
                })
                .finally(() => {
                    adminRealtimeRefreshPromise = null;
                });

            return adminRealtimeRefreshPromise;
        }

        // Page actions call this after a successful AJAX mutation. It keeps the
        // URL and shell intact while retrieving fresh, server-authorized content.
        window.refreshCurrentAdminPage = function() {
            return replaceAdminPageInPlace(window.location.href);
        };

        // Used only when a successful form previously redirected to another
        // Admin page (for example, FAQ create/edit back to the FAQ list).
        window.navigateAdminPageInPlace = function(url, replaceHistory = false) {
            return replaceAdminPageInPlace(url, replaceHistory ? 'replace' : 'push');
        };

        // Keep Admin realtime updates inside the existing SPA shell instead
        // of doing a browser reload. The refreshed fragment remains subject
        // to the current user's server-side authorization and organization.
        window.refreshCurrentRealtimePage = function (topic) {
            const pagePattern = adminRealtimePagePaths[topic];
            if (!pagePattern || !pagePattern.test(window.location.pathname)) return true;
            window.refreshCurrentAdminPage();
            return true;
        };

        async function refreshAdminPageCache(url) {
            const refreshVersion = adminPageCacheVersion;

            try {
                const page = await fetchAdminPageFragment(url);
                if (refreshVersion === adminPageCacheVersion) {
                    cacheAdminPage(url, page.html, page.title);
                }
            } catch (error) {
                console.warn('Admin page background refresh failed:', error);
            }
        }

        async function navigateAdminPage(url, updateHistory = true, requestId = adminNavigationRequestId, controller = null) {
            const targetUrl = new URL(url, window.location.origin);
            const mainContent = mainContentCache || document.querySelector('main');
            const navigationLink = getAdminNavigationLink(targetUrl.href);
            const navigationType = navigationLink ? targetUrl.pathname : null;
            const navigationLabel = getAdminNavigationLabel(navigationLink);
            const cacheKey = getAdminPageCacheKey(targetUrl.href);
            const cachedPage = adminPageCache.get(cacheKey);

            const isLatestNavigation = () => requestId === adminNavigationRequestId;

            if (!mainContent || !navigationType || !isLatestNavigation()) return false;

            setAdminNavigationActive(navigationType);

            if (cachedPage) {
                // Restore immediately from memory so returning to a page feels instant.
                renderAdminPageContent(mainContent, cachedPage.html, cachedPage.title);

                if (updateHistory) {
                    window.history.pushState({ adminNavigation: true }, '', targetUrl.href);
                }

                window.scrollTo({ top: 0, behavior: 'auto' });
                window.hideAdminNavigationSkeleton();

                if (activeAdminNavigationController === controller) {
                    activeAdminNavigationController = null;
                }

                // Refresh the cache silently for the next visit without replacing content
                // the user may already be interacting with.
                refreshAdminPageCache(targetUrl.href);
                return true;
            }

            window.showAdminNavigationSkeleton(navigationType, navigationLabel);

            try {
                const page = await fetchAdminPageFragment(
                    targetUrl.href,
                    controller ? controller.signal : null
                );

                // A slower response from a previous click must never replace the latest page.
                if (!isLatestNavigation()) return false;

                cacheAdminPage(targetUrl.href, page.html, page.title);
                renderAdminPageContent(mainContent, page.html, page.title);

                if (updateHistory) {
                    window.history.pushState({ adminNavigation: true }, '', targetUrl.href);
                }

                window.scrollTo({ top: 0, behavior: 'auto' });
                window.hideAdminNavigationSkeleton();
                return true;
            } catch (error) {
                if (error.name === 'AbortError' || !isLatestNavigation()) {
                    return false;
                }

                console.error('Admin navigation failed:', error);
                setAdminNavigationActive(getAdminNavigationType(window.location.href));
                window.hideAdminNavigationSkeleton();
                return false;
            } finally {
                if (activeAdminNavigationController === controller) {
                    activeAdminNavigationController = null;
                }
            }
        }

        // Intercept only the two requested Admin tabs and swap their content without a reload.
        document.addEventListener('click', function(event) {
            const link = event.target instanceof Element
                ? event.target.closest('a[data-admin-navigation-loading]')
                : null;

            if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank') {
                return;
            }

            const targetUrl = new URL(link.href, window.location.origin);
            if (targetUrl.pathname === window.location.pathname && targetUrl.search === window.location.search) return;

            event.preventDefault();

            // Latest click wins: cancel the previous request and invalidate its response.
            const requestId = ++adminNavigationRequestId;
            if (activeAdminNavigationController) {
                activeAdminNavigationController.abort();
            }

            activeAdminNavigationController = new AbortController();

            const navigationType = getAdminNavigationType(targetUrl.href);
            const navigationLink = getAdminNavigationLink(targetUrl.href);
            setAdminNavigationActive(navigationType);

            if (!adminPageCache.has(getAdminPageCacheKey(targetUrl.href))) {
                window.showAdminNavigationSkeleton(navigationType, getAdminNavigationLabel(navigationLink));
            }

            requestAnimationFrame(async () => {
                // Ignore a queued animation frame if another tab was clicked first.
                if (requestId !== adminNavigationRequestId) return;

                await navigateAdminPage(
                    targetUrl.href,
                    true,
                    requestId,
                    activeAdminNavigationController
                );
            });
        });

        // Opt-in forms use the same Admin shell instead of a browser navigation.
        // Their regular Laravel redirect response is rendered directly, preserving
        // flash messages, validation, and the server's existing redirect target.
        document.addEventListener('submit', async function(event) {
            const form = event.target instanceof HTMLFormElement
                ? event.target.closest('form[data-admin-inplace-form]')
                : null;

            if (!form || event.defaultPrevented || form.dataset.adminInplaceSubmitting === 'true') return;

            event.preventDefault();
            form.dataset.adminInplaceSubmitting = 'true';
            form.setAttribute('aria-busy', 'true');

            const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
            if (submitter) submitter.disabled = true;

            try {
                const formData = new FormData(form);
                if (submitter?.getAttribute('name') && !formData.has(submitter.getAttribute('name'))) {
                    formData.append(submitter.getAttribute('name'), submitter.getAttribute('value') || '');
                }

                const action = submitter?.getAttribute('formaction') || form.action;
                const response = await fetch(action, {
                    method: (form.method || 'POST').toUpperCase(),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html'
                    },
                    body: formData,
                    credentials: 'same-origin'
                });
                const contentType = response.headers.get('content-type') || '';

                if (contentType.includes('application/json')) {
                    const data = await response.json();
                    if (!response.ok || data.success === false) {
                        throw new Error(data.message || 'The change could not be saved.');
                    }

                    const destination = form.dataset.adminInplaceSuccessUrl || window.location.href;
                    await window.navigateAdminPageInPlace(destination, destination === window.location.href);
                    return;
                }

                const pageHtml = await response.text();
                if (!response.ok) throw new Error('The change could not be saved.');

                const pageDocument = new DOMParser().parseFromString(pageHtml, 'text/html');
                const nextMainContent = pageDocument.querySelector('main');
                const mainContent = mainContentCache || document.querySelector('main');
                if (!nextMainContent || !mainContent) throw new Error('Updated Admin page content was not found.');

                const destination = new URL(response.url || form.dataset.adminInplaceSuccessUrl || window.location.href, window.location.origin);
                cacheAdminPage(destination.href, nextMainContent.innerHTML, pageDocument.title);
                renderAdminPageContent(mainContent, nextMainContent.innerHTML, pageDocument.title);

                if (destination.href === window.location.href) {
                    window.history.replaceState({ adminNavigation: true }, '', destination.href);
                } else {
                    window.history.pushState({ adminNavigation: true }, '', destination.href);
                    window.scrollTo({ top: 0, behavior: 'auto' });
                }

                setAdminNavigationActive(getAdminNavigationType(destination.href));
            } catch (error) {
                console.error('Admin in-place form submission failed:', error);
                alert(error.message || 'The change could not be saved. Please try again.');
            } finally {
                form.dataset.adminInplaceSubmitting = 'false';
                form.removeAttribute('aria-busy');
                if (submitter) submitter.disabled = false;

                const alpineScopes = [form, form.closest('[x-data]')]
                    .filter((scope, index, scopes) => scope && scopes.indexOf(scope) === index);
                const loadingKeys = ['submitting', 'isSubmitting', 'isDeleting', 'isUpdating', 'isAdding', 'isRemoving', 'draftLoading', 'publishLoading', 'updateLoading'];

                alpineScopes.forEach(scope => {
                    const state = window.Alpine?.$data?.(scope);
                    loadingKeys.forEach(key => {
                        if (state && key in state) state[key] = false;
                    });
                });
            }
        });

        window.addEventListener('popstate', async function() {
            const navigationType = getAdminNavigationType(window.location.href);

            if (!navigationType) {
                window.location.reload();
                return;
            }

            if (activeAdminNavigationController) {
                activeAdminNavigationController.abort();
            }

            const requestId = ++adminNavigationRequestId;
            activeAdminNavigationController = new AbortController();
            await navigateAdminPage(
                window.location.href,
                false,
                requestId,
                activeAdminNavigationController
            );
        });
        
        // OPTIMIZED: Hide skeleton loader when content is ready
        function hideSkeletonLoader() {
            const mainContent = mainContentCache || document.querySelector('main');
            if (mainContent) {
                mainContent.style.opacity = '1';
                mainContent.style.pointerEvents = 'auto';
            }
        }
        
        // OPTIMIZED: Defer non-critical resources (images, iframes, etc.)
        function deferNonCriticalResources() {
            // Use requestIdleCallback for better performance
            if ('requestIdleCallback' in window) {
                requestIdleCallback(() => {
                    markImagesLazy();
                });
            } else {
                setTimeout(markImagesLazy, 100);
            }
        }
        
        function markImagesLazy() {
            const images = document.querySelectorAll('img:not([loading])');
            images.forEach(img => {
                if (!img.hasAttribute('loading')) {
                    img.setAttribute('loading', 'lazy');
                }
            });
            
            const iframes = document.querySelectorAll('iframe:not([loading])');
            iframes.forEach(iframe => {
                if (!iframe.hasAttribute('loading')) {
                    iframe.setAttribute('loading', 'lazy');
                }
            });
        }
        
        // OPTIMIZED: Lazy load media using Intersection Observer
        function lazyLoadMedia() {
            if ('IntersectionObserver' in window) {
                const lazyElements = document.querySelectorAll('[data-lazy-src]');
                
                if (lazyElements.length === 0) return; // Early exit if no lazy elements
                
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
    </script>
    
    @stack('scripts')
    
    <!-- Real-time Theme Color Updates via WebSocket -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Setup theme color listener
            setupThemeColorListener();
        });
        
        function setupThemeColorListener() {
            const organizationId = '{{ session("current_organization_id") }}';
            
            console.log('🔍 [Admin] Checking Echo availability:', typeof Echo);
            console.log('🔍 [Admin] Current organization ID:', organizationId);
            
            if (typeof Echo === 'undefined') {
                console.log('⏳ Echo not ready, waiting...');
                let attempts = 0;
                const interval = setInterval(() => {
                    attempts++;
                    if (typeof Echo !== 'undefined') {
                        clearInterval(interval);
                        console.log('✅ Echo is now ready after ' + attempts + ' attempts');
                        setupThemeColorListener();
                    }
                    if (attempts > 50) { // 5 seconds
                        clearInterval(interval);
                        console.log('❌ Echo failed to load in time');
                    }
                }, 100);
                return;
            }
            
            if (!organizationId) {
                console.log('⚠️ Organization ID not available for theme updates');
                return;
            }
            
            console.log('🎨 Setting up real-time theme color listener for organization:', organizationId);
            
            // Listen for theme color updates
            const channel = Echo.channel('organization.' + organizationId);
            
            console.log('📡 [Admin] Listening on channel: organization.' + organizationId);
            
            channel.listen('.theme.colors.updated', (event) => {
                console.log('🎨 Theme settings updated via WebSocket:', event);
                
                // If this client just updated the theme locally within the last 5 seconds, 
                // ignore the websocket event to prevent overwriting local blob previews with cached server URLs
                if (window._lastLocalThemeUpdate && (Date.now() - window._lastLocalThemeUpdate < 5000)) {
                    console.log('🛑 Ignoring WebSocket theme update because local update was just applied');
                    return;
                }
                
                if (window.applyThemeUpdate) {
                    window.applyThemeUpdate(event.primary_color, event.complementary_color, event.logo_url);
                } else {
                    // Fallback if global function not available
                    const root = document.documentElement;
                    if (event.primary_color) root.style.setProperty('--primary-color', event.primary_color);
                    if (event.complementary_color) root.style.setProperty('--complementary-color', event.complementary_color);
                    if (event.logo_url) updateAllLogos(event.logo_url);
                }
                
                // Show subtle notification
                showThemeUpdateNotification();
            });
        }
        
        // Global function to apply theme updates (called by Echo or settings page)
        window.applyThemeUpdate = function(primaryColor, complementaryColor, logoUrl) {
            console.log('🎨 Applying theme update:', { primaryColor, complementaryColor, logoUrl });
            
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
            
            // Force repaint
            void document.body.offsetHeight;
            
            console.log('✅ Theme update applied successfully');
        };
        
        function updateAllLogos(logoUrl) {
            // Add cache-busting timestamp only for non-blob URLs
            const timestamp = new Date().getTime();
            const isBlob = logoUrl.startsWith('blob:');
            const logoUrlWithCache = isBlob ? logoUrl : logoUrl + (logoUrl.includes('?') ? '&' : '?') + 'v=' + timestamp;
            
            // Update all logo images on the page
            document.querySelectorAll('img[alt*="Logo"], img[src*="logo"], .logo-icon').forEach(img => {
                img.src = logoUrlWithCache;
                console.log('✅ Logo updated:', img);
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
            
            console.log('🔄 [Admin] Updating styles - Primary:', primaryColor, 'Complementary:', complementaryColor);
            
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
            
            // Method 3: Force floating chat to update by toggling a class
            const chatButton = document.getElementById('admin-chat-button');
            if (chatButton) {
                chatButton.classList.add('theme-updating');
                requestAnimationFrame(() => {
                    chatButton.classList.remove('theme-updating');
                });
            }
            
            // Method 4: Inject dynamic CSS for hover states
            updateDynamicHoverStyles(primaryColor, complementaryColor);
            
            // Method 5: Force repaint
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
            
            console.log('✅ [Admin] Dynamic hover styles updated');
        }
        
        window.showThemeUpdateNotification = function() {
            // Remove any existing theme update notifications
            const existingNotifications = document.querySelectorAll('.theme-update-notification');
            existingNotifications.forEach(notif => notif.remove());
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'theme-update-notification toast-notification';
            notification.innerHTML = `
                <div class="bg-white rounded-lg shadow-2xl border-l-4 p-4" style="border-left-color: var(--primary-color);">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--primary-color-rgb), 0.1);">
                                <i class="ph ph-palette" style="color: var(--primary-color);"></i>
                            </div>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-sm font-semibold text-gray-900 mb-1">
                                Theme Settings Updated
                            </h3>
                            <p class="text-sm text-gray-600">
                                Theme settings updated successfully!
                            </p>
                        </div>
                        <button onclick="this.closest('.theme-update-notification').remove()" class="ml-3 flex-shrink-0 text-gray-400 hover:text-gray-600">
                            <i class="ph ph-x"></i>
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                notification.classList.add('hiding');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        document.addEventListener('alpine:init', () => {
            Alpine.data('notificationBell', () => ({
                open: false,
                notifications: [],
                unreadCount: 0,
                polling: null,

                init() {
                    this.fetchNotifications();
                    this.polling = setInterval(() => this.fetchNotifications(), 30000);
                },

                destroy() {
                    if (this.polling) clearInterval(this.polling);
                },

                fetchNotifications() {
                    fetch('/notifications')
                        .then(r => r.json())
                        .then(d => {
                            this.notifications = d.notifications || [];
                            this.unreadCount = d.unread_count || 0;
                        })
                        .catch(() => {});
                },

                toggleDropdown() {
                    this.open = !this.open;
                    if (this.open) this.fetchNotifications();
                },

                closeDropdown() {
                    this.open = false;
                },

                markRead(notif) {
                    if (notif.read_at === null) {
                        fetch(`/notifications/${notif.id}/read`, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } })
                            .then(() => {
                                notif.read_at = now();
                                this.unreadCount = Math.max(0, this.unreadCount - 1);
                            })
                            .catch(() => {});
                    }
                    if (notif.data.url) {
                        window.location.href = notif.data.url;
                    }
                },

                markAllRead() {
                    fetch('/notifications/mark-all-read', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } })
                        .then(() => {
                            this.notifications.forEach(n => n.read_at = now());
                            this.unreadCount = 0;
                        })
                        .catch(() => {});
                },

                timeAgo(date) {
                    const diff = Date.now() - new Date(date).getTime();
                    const mins = Math.floor(diff / 60000);
                    if (mins < 1) return 'Just now';
                    if (mins < 60) return mins + 'm ago';
                    const hours = Math.floor(mins / 60);
                    if (hours < 24) return hours + 'h ago';
                    const days = Math.floor(hours / 24);
                    if (days < 7) return days + 'd ago';
                    return new Date(date).toLocaleDateString();
                }
            }));
        });
    </script>
    
    <!-- RBAC Error Handler -->
    <script>
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
    <script>
        // Global toast for admin (used by renew cert prefill, members, etc.)
        // Supports both showToast(message) and showToast(message, type, title).
        // Optional 4th arg `key`: when given, repeat calls reuse the same toast
        // (timer resets + subtle pulse) instead of stacking duplicates.
        window.showToast = function(message, type = 'success', title = null, key = null) {
            const container = document.getElementById('toast-container');
            if (!container) return;
            if (key) {
                const existing = container.querySelector(`.toast-notification[data-toast-key="${key}"]`);
                if (existing) {
                    if (existing.dataset.toastTimeout) clearTimeout(Number(existing.dataset.toastTimeout));
                    existing.classList.remove('hiding');
                    const card = existing.firstElementChild;
                    if (card) {
                        card.style.animation = 'none';
                        void card.offsetWidth;
                        card.style.animation = '';
                    }
                    existing.dataset.toastTimeout = setTimeout(() => { existing.classList.add('hiding'); setTimeout(() => existing.remove(), 300); }, 4000);
                    return;
                }
            }
            const typeMap = {
                success: { bg: 'border-green-500', icon: 'ph-check-circle', color: 'text-green-500', bgLight: 'bg-green-100' },
                error: { bg: 'border-red-500', icon: 'ph-x-circle', color: 'text-red-500', bgLight: 'bg-red-100' },
                warning: { bg: 'border-amber-500', icon: 'ph-warning-circle', color: 'text-amber-500', bgLight: 'bg-amber-100' },
                info: { bg: 'border-blue-500', icon: 'ph-info', color: 'text-blue-500', bgLight: 'bg-blue-100' },
            };
            const cfg = typeMap[type] || typeMap['info'];
            const toast = document.createElement('div');
            toast.className = 'toast-notification pointer-events-auto';
            const heading = title || (type === 'success' ? 'Success' : type === 'error' ? 'Error' : type === 'warning' ? 'Warning' : 'Info');
            toast.innerHTML = `
                <div class="bg-white rounded-lg shadow-2xl border-l-4 ${cfg.bg} p-4 min-w-[320px] max-w-[420px]">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 ${cfg.bgLight} rounded-full flex items-center justify-center shrink-0"><i class="ph ${cfg.icon} ${cfg.color}"></i></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-gray-900">${heading}</p>
                            <p class="text-sm text-gray-600 leading-snug">${message}</p>
                        </div>
                        <button onclick="this.closest('.toast-notification').remove()" class="text-gray-400 hover:text-gray-600 shrink-0"><i class="ph ph-x"></i></button>
                    </div>
                </div>`;
            if (key) toast.dataset.toastKey = key;
            container.appendChild(toast);
            toast.dataset.toastTimeout = setTimeout(() => { toast.classList.add('hiding'); setTimeout(() => toast.remove(), 300); }, 4000);
        };
    </script>
    <!-- Global Renew Blocked Modal (High z-index to overlay header and sidebar properly) -->
    <div x-data="{ 
            open: false, 
            title: 'Accreditation Still Active', 
            message: 'The accreditation renewal period has not been set or opened yet by the OSAS. Please wait for an official announcement.' 
         }" 
         @open-renew-blocked-modal.window="
            title = $event.detail.title || title;
            message = $event.detail.message || message;
            open = true;
         "
         x-show="open" 
         x-cloak 
         class="fixed inset-0 z-[99999] overflow-y-auto" 
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <!-- Full viewport backdrop covering top header and sidebar with blur -->
            <div @click="open = false" class="fixed inset-0 bg-black/50 z-[99998] modal-backdrop backdrop-blur-sm"></div>
            
            <div class="relative bg-white rounded-lg shadow-2xl max-w-md w-full p-6 z-[99999] text-center transform transition"
                 x-show="open"
                 x-transition:enter="transition ease-out duration-200" 
                 x-transition:enter-start="opacity-0 scale-95" 
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150" 
                 x-transition:leave-start="opacity-100 scale-100" 
                 x-transition:leave-end="opacity-0 scale-95">
                <div class="flex items-center mb-4">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full" style="background-color: rgba(var(--primary-color-rgb, 130, 56, 179), 0.1);">
                        <i class="ph text-2xl ph-arrows-clockwise gold-icon"></i>
                    </div>
                </div>
                <div class="text-center">
                    <h3 class="text-lg leading-6 font-semibold text-gray-900 mb-2" x-text="title">
                    </h3>
                    <p class="text-sm text-gray-500 mb-6 leading-relaxed" x-text="message">
                    </p>
                </div>
                <div class="flex justify-center">
                    <button type="button" @click="open = false"
                            class="px-6 py-2 text-white rounded-lg hover:opacity-90 transition-colors font-medium text-sm shadow-sm"
                            style="background-color: var(--primary-color)">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
