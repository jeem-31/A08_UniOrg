<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Cache-Control" content="public, max-age=31536000">
    <title>@yield('title', 'OSAS - UniOrg')</title>
    <link rel="icon" type="image/png" href="https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/public_image/LSPU_LOGO.png">
    <link rel="preload" as="image" href="https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/public_image/LSPU_LOGO.png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
                        primary: '#26488C',
                        secondary: '#1a3461',
                        accent: '#C4973B',
                    }
                }
            }
        }
    </script>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        .ph-spin {
            display: inline-block;
            animation: ph-spin 1s linear infinite;
        }
        @keyframes ph-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        [x-cloak] { 
            display: none !important; 
        }

        .modal-backdrop {
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
        }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            overflow-x: hidden;
            background-color: #F9FBFF;
        }
        
        .gold-icon {
            color: #C4973B;
        }
        
        /* Sidebar Styles - Architectural Pattern matching admin.blade.php */
        .sidebar {
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            will-change: width;
            overflow: visible !important;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            background: white;
            border-right: 1px solid rgba(38, 72, 140, 0.05);
            z-index: 50;
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

        /* OSAS navigation feedback: keep the current shell visible while the next page is fetched. */
        .osas-navigation-skeleton {
            display: none;
            position: absolute;
            inset: 72px 0 0;
            z-index: 30;
            min-height: calc(100vh - 72px);
            padding: 2rem;
            overflow: auto;
            background: rgba(249, 251, 255, 0.97);
        }

        .osas-navigation-skeleton.is-visible {
            display: block;
        }

        .osas-skeleton-block {
            background: #e5e7eb;
            border-radius: 0.5rem;
        }

        .osas-skeleton-pulse {
            animation: osas-skeleton-pulse 1.4s ease-in-out infinite;
        }

        @keyframes osas-skeleton-pulse {
            0%, 100% { opacity: 0.55; }
            50% { opacity: 1; }
        }
        
        .main-content-collapsed {
            margin-left: 80px;
        }
        
        .main-content-expanded {
            margin-left: 280px;
        }
        
        /* Bubble/Pill Navigation Item - Pattern matching admin.blade.php */
        .nav-item {
            position: relative;
            display: flex;
            align-items: center;
            padding: 12px 16px;
            margin: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            color: #64748b;
            white-space: nowrap;
        }
        
        .nav-item:hover {
            background: #F1F5F9;
            color: #26488C;
            transform: translateX(4px);
        }
        
        .nav-item.active {
            background: linear-gradient(135deg, #26488C 0%, #1a3461 100%);
            color: white;
            box-shadow: 0 8px 16px rgba(38, 72, 140, 0.2);
            transform: translateX(4px);
        }
        
        .nav-item.active .nav-icon {
            color: #C4973B;
        }
        
        .nav-item.active .nav-text {
            color: white;
            font-weight: 700;
        }
        
        .nav-icon {
            min-width: 24px;
            font-size: 20px;
            transition: all 0.3s ease;
            color: #26488C;
            flex-shrink: 0;
            display: flex;
            justify-content: center;
        }
        
        .nav-text {
            margin-left: 16px;
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
            transition: all 0.3s ease;
            color: #4b5563;
        }

        /* Tooltip Pattern matching admin.blade.php */
        .sidebar-collapsed .nav-text {
            display: none;
        }
        
        .sidebar-collapsed .nav-item:hover .nav-text {
            display: inline-block !important;
            position: absolute !important;
            left: 50px !important;
            background: #26488C !important;
            color: white !important;
            padding: 8px 16px !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 12px rgba(38, 72, 140, 0.4) !important;
            z-index: 1000 !important;
            white-space: nowrap !important;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .sidebar-expanded .nav-text {
            display: inline-block !important;
        }
        
        .nav-badge {
            margin-left: auto;
            padding: 2px 8px;
            background: #C4973B;
            color: white;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .sidebar-expanded .nav-badge {
            opacity: 1;
        }

        /* Submenu Pattern matching admin.blade.php */
        .nav-submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-left: 12px;
            margin-right: 12px;
            border-left: 2px solid rgba(38, 72, 140, 0.1);
        }
        
        .nav-submenu.open {
            max-height: 500px;
        }
        
        .nav-subitem {
            padding: 10px 16px 10px 44px;
            margin: 4px 0;
border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #64748b;
            font-size: 14px;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .nav-subitem:hover {
            background: #F1F5F9;
            color: #26488C;
            transform: translateX(4px);
        }
        
        .nav-subitem.active {
            background: rgba(38, 72, 140, 0.05);
            color: #26488C;
            font-weight: 700;
        }
        
        /* Logo */
        .sidebar-logo {
            display: flex;
            align-items: center;
            padding: 24px 16px;
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
            font-weight: 800;
            color: #26488C;
            white-space: nowrap;
            transition: all 0.3s ease;
            letter-spacing: -0.025em;
            display: inline-block;
        }
        
        .sidebar-collapsed .logo-text {
            display: none;
        }
        
        .sidebar-expanded .logo-text {
            display: inline-block;
        }

        /* Top Bar - Architectural Pattern matching admin.blade.php */
        .top-bar {
            height: 72px;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(38, 72, 140, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            position: sticky;
            top: 0;
            z-index: 40;
        }
        
        .hamburger-btn {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 8px;
            z-index: 100;
            position: relative;
        }
        
        .hamburger-btn:hover {
            background: rgba(38, 72, 140, 0.05);
            transform: scale(1.05);
        }
        
        .hamburger-btn i {
            color: #26488C;
            font-size: 24px;
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

            .top-bar {
                z-index: 60;
                gap: 0.5rem;
                padding-inline: 0.5rem;
            }

            .top-bar > :last-child {
                gap: 0.5rem;
            }

            main {
                width: 100%;
                padding: 0.75rem;
            }
        }
    </style>
    
    @stack('styles')
</head>
<body class="bg-[#F9FBFF]"
      data-osas-notification-state-url="{{ route('osas.notifications.state') }}"
      x-data="{
    sidebarOpen: localStorage.getItem('osasSidebarOpen') === 'true'
}" x-init="
    if (localStorage.getItem('osasSidebarOpen') === null) {
        localStorage.setItem('osasSidebarOpen', 'true');
        sidebarOpen = true;
    }
" x-cloak>
    @php
        // The composer supplies these for normal OSAS requests. Keep direct/controller
        // renders and minimal test schemas usable when no authenticated app request
        // is available to the composer.
        $unreadCount = $unreadCount ?? 0;
        $osasDropdownNotifications = $osasDropdownNotifications ?? collect();
        $osasLayoutUser = $osasUser ?? auth()->user();
        $pendingProposalsCount = $pendingProposalsCount ?? 0;
    @endphp

    <!-- Sidebar -->
    <aside 
        class="sidebar shadow-xl"
        :class="sidebarOpen ? 'sidebar-expanded' : 'sidebar-collapsed'"
        style="overflow-y: auto; overflow-x: visible;"
    >
        <!-- Logo -->
        <div class="sidebar-logo">
            <img src="https://pub-68766b2dc45a4355b4995d25937a69ec.r2.dev/public_image/LSPU_LOGO.png" alt="LSPU Logo" class="logo-icon" loading="eager">
            <span class="logo-text">OSAS</span>
        </div>
        
        <!-- Navigation -->
        <nav class="pb-6">
            <!-- Dashboard -->
            <a href="{{ route('osas.dashboard') }}" data-osas-navigation class="nav-item {{ request()->routeIs('osas.dashboard') ? 'active' : '' }}">
                <i class="ph ph-house nav-icon"></i>
                <span class="nav-text">Dashboard</span>
            </a>
            
            <!-- Accreditation -->
            <div x-data="{ open: {{ request()->routeIs('osas.apply-org.*') ? 'true' : 'false' }} }" data-osas-navigation-group="accreditation" class="nav-group">
                <div 
                    @click="if (sidebarOpen) { open = !open } else { window.navigateOsasPage('{{ route('osas.apply-org.index') }}') }"
                    class="nav-item {{ request()->routeIs('osas.apply-org.*') ? 'active' : '' }}">
                    <i class="ph ph-certificate nav-icon"></i>
                    <span class="nav-text">Accreditation</span>
                    <i x-show="sidebarOpen" class="ph ph-caret-down ml-auto text-[10px] transition-transform" :class="open ? 'rotate-180' : ''"></i>
                </div>
                <div x-show="sidebarOpen" class="nav-submenu" :class="open ? 'open' : ''">
                    <a href="{{ route('osas.apply-org.index') }}" 
                       data-osas-navigation
                       class="nav-subitem {{ request()->routeIs('osas.apply-org.*') && !request('type') ? 'active' : '' }}">
                        All Requests
                    </a>
                    <a href="{{ route('osas.apply-org.index', ['type' => 'apply']) }}" 
                       data-osas-navigation
                       class="nav-subitem {{ request()->routeIs('osas.apply-org.*') && request('type') == 'apply' ? 'active' : '' }}">
                        Recognition
                    </a>
                    <a href="{{ route('osas.apply-org.index', ['type' => 'request']) }}" 
                       data-osas-navigation
                       class="nav-subitem {{ request()->routeIs('osas.apply-org.*') && request('type') == 'request' ? 'active' : '' }}">
                        Renewal
                    </a>
                </div>
            </div>
            
            <!-- Organizations -->
            <a href="{{ route('osas.organizations.index') }}" data-osas-navigation class="nav-item {{ request()->routeIs('osas.organizations.*') ? 'active' : '' }}">
                <i class="ph ph-buildings nav-icon"></i>
                <span class="nav-text">Organizations</span>
            </a>

            <!-- System Announcements -->
            <a href="{{ route('osas.announcements.index') }}" data-osas-navigation class="nav-item {{ request()->routeIs('osas.announcements.*') ? 'active' : '' }}">
                <i class="ph ph-megaphone nav-icon"></i>
                <span class="nav-text">Announcements</span>
            </a>

            <!-- Master Calendar -->
            <a href="{{ route('osas.calendar') }}" data-osas-navigation class="nav-item {{ request()->routeIs('osas.calendar') ? 'active' : '' }}">
                <i class="ph ph-calendar-blank nav-icon"></i>
                <span class="nav-text">Master Calendar</span>
            </a>

            <!-- Event Proposals -->
            <a href="{{ route('osas.proposals.index') }}" data-osas-navigation class="nav-item {{ request()->routeIs('osas.proposals.*') ? 'active' : '' }}">
                <i class="ph ph-file-text nav-icon"></i>
                <span class="nav-text">Event Proposals</span>
                <span data-osas-proposal-pending-badge
                      @class(['nav-badge', 'hidden' => $pendingProposalsCount === 0])
                      style="opacity: 1;"
                      @if($pendingProposalsCount === 0) hidden @endif>
                    <span data-osas-proposal-pending-count>{{ $pendingProposalsCount }}</span>
                    <span class="sr-only"> pending event proposals</span>
                </span>
            </a>
            
            <!-- Analytics -->
            <a href="{{ route('osas.analytics') }}" data-osas-navigation class="nav-item {{ request()->routeIs('osas.analytics') ? 'active' : '' }}">
                <i class="ph ph-chart-line-up nav-icon"></i>
                <span class="nav-text">Analytics</span>
            </a>
            
            <!-- Document Library -->
            <a href="{{ route('osas.smart-search') }}" data-osas-navigation class="nav-item {{ request()->routeIs('osas.smart-search') ? 'active' : '' }}">
                <i class="ph ph-folder-open nav-icon"></i>
                <span class="nav-text">Document Library</span>
            </a>

            <!-- Reports Compliance -->
            <a href="{{ route('osas.required-documents.index') }}" data-osas-navigation class="nav-item {{ request()->routeIs('osas.required-documents.*') ? 'active' : '' }}">
                <i class="ph ph-clipboard-text nav-icon"></i>
                <span class="nav-text">Reports Compliance</span>
            </a>
            
            <!-- Org Chat (replaced by the floating chat widget) -->

            <!-- Settings -->
            <a href="{{ route('osas.settings') }}" data-osas-navigation class="nav-item {{ request()->routeIs('osas.settings') ? 'active' : '' }}">
                <i class="ph ph-gear nav-icon"></i>
                <span class="nav-text">Settings</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <div 
        class="main-content min-h-screen"
        :class="sidebarOpen ? 'main-content-expanded' : 'main-content-collapsed'"
    >
        <!-- Top Bar -->
        <div class="top-bar">
            <!-- Hamburger Menu -->
            <div class="hamburger-btn" @click="sidebarOpen = !sidebarOpen; localStorage.setItem('osasSidebarOpen', sidebarOpen)">
                <i class="ph ph-list"></i>
            </div>

            <div class="flex items-center space-x-6">
                <!-- Notifications -->
                <div x-data="{ open: false }" class="relative">
                    <button type="button"
                            @click="open = !open"
                            aria-label="Notifications"
                            aria-controls="osas-notification-dropdown"
                            :aria-expanded="open.toString()"
                            class="relative p-3 text-gray-400 hover:text-[#26488C] transition-colors bg-white rounded-lg shadow-sm border border-gray-100">
                        <i class="ph ph-bell text-xl" aria-hidden="true"></i>
                        <span data-osas-notification-unread-badge
                              @class([
                                  'absolute top-2 right-2 w-5 h-5 bg-red-500 text-white text-[10px] font-black flex items-center justify-center rounded-full border-2 border-white shadow-sm',
                                  'hidden' => $unreadCount === 0,
                              ])>
                            <span data-osas-notification-unread-count>{{ $unreadCount }}</span>
                            <span class="sr-only"> unread notifications</span>
                        </span>
                    </button>

                    <!-- Notifications Dropdown -->
                    <div id="osas-notification-dropdown"
                         x-cloak
                         x-show="open"
                         @click.away="open = false"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="absolute right-0 mt-3 w-80 bg-white rounded-lg shadow-2xl border border-gray-100 z-50 overflow-hidden">

                        <div data-osas-notification-dropdown-content>
                            <x-osas.notification-dropdown-content
                                :unread-count="$unreadCount"
                                :notifications="$osasDropdownNotifications" />
                        </div>
                    </div>
                </div>

                <!-- Profile -->
                <a href="{{ route('osas.settings') }}" data-osas-navigation class="flex items-center gap-3 hover:opacity-80 transition">
                    <div class="text-right hidden md:block">
                        <p class="text-sm font-bold text-gray-900 leading-none">{{ $osasLayoutUser->short_name }}</p>
                        <p class="text-[10px] font-black text-[#C4973B] uppercase tracking-widest mt-1">OSAS Administrator</p>
                    </div>
                    <span class="nav-avatar-wrapper">
                        @if($osasLayoutUser->photo)
                            <img src="{{ storage_url($osasLayoutUser->photo) }}" alt="Profile" class="h-10 w-10 rounded-lg object-cover border-2 border-white shadow-sm">
                        @else
                            <div class="h-10 w-10 rounded-lg bg-blue-50 text-[#26488C] flex items-center justify-center font-black">
                                {{ substr($osasLayoutUser->first_name, 0, 1) }}
                            </div>
                        @endif
                    </span>
                </a>
                
                <!-- Logout -->
                <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
                    @csrf
                </form>
                <button type="button" 
                        onclick="confirmAction('Are you sure you want to log out?', 'Sign Out').then(confirmed => { if(confirmed) document.getElementById('logout-form').submit(); })"
                        aria-label="Sign out"
                        class="p-3 text-gray-400 hover:text-red-500 transition-colors">
                    <i class="ph ph-sign-out text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Page Content -->
        <main class="p-6 lg:p-7 relative">
            @yield('content')
        </main>

        <!-- OSAS navigation skeleton: shown immediately while a safe page fragment loads. -->
        <div id="osas-navigation-skeleton"
             class="osas-navigation-skeleton"
             aria-hidden="true"
             aria-live="polite">
            <span data-osas-skeleton-title class="sr-only">Loading page...</span>
            <div data-osas-skeleton-template="queue" class="max-w-[1700px] mx-auto">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <div class="osas-skeleton-block osas-skeleton-pulse h-9 w-72 mb-3"></div>
                        <div class="osas-skeleton-block osas-skeleton-pulse h-4 w-96"></div>
                    </div>
                    <div class="osas-skeleton-block osas-skeleton-pulse h-11 w-44"></div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="osas-skeleton-block osas-skeleton-pulse h-24"></div>
                    <div class="osas-skeleton-block osas-skeleton-pulse h-24"></div>
                    <div class="osas-skeleton-block osas-skeleton-pulse h-24"></div>
                    <div class="osas-skeleton-block osas-skeleton-pulse h-24"></div>
                </div>

                <div class="bg-white rounded-lg border border-gray-100 overflow-hidden">
                    <div class="osas-skeleton-block osas-skeleton-pulse h-14 rounded-none"></div>
                    <div class="divide-y divide-gray-100">
                        <div class="flex items-center gap-5 p-6">
                            <div class="osas-skeleton-block osas-skeleton-pulse h-11 w-11 flex-shrink-0"></div>
                            <div class="flex-1 space-y-3"><div class="osas-skeleton-block osas-skeleton-pulse h-4 w-2/5"></div><div class="osas-skeleton-block osas-skeleton-pulse h-3 w-1/4"></div></div>
                            <div class="osas-skeleton-block osas-skeleton-pulse h-4 w-24"></div>
                            <div class="osas-skeleton-block osas-skeleton-pulse h-8 w-20"></div>
                        </div>
                        <div class="flex items-center gap-5 p-6">
                            <div class="osas-skeleton-block osas-skeleton-pulse h-11 w-11 flex-shrink-0"></div>
                            <div class="flex-1 space-y-3"><div class="osas-skeleton-block osas-skeleton-pulse h-4 w-3/5"></div><div class="osas-skeleton-block osas-skeleton-pulse h-3 w-1/3"></div></div>
                            <div class="osas-skeleton-block osas-skeleton-pulse h-4 w-28"></div>
                            <div class="osas-skeleton-block osas-skeleton-pulse h-8 w-20"></div>
                        </div>
                        <div class="flex items-center gap-5 p-6">
                            <div class="osas-skeleton-block osas-skeleton-pulse h-11 w-11 flex-shrink-0"></div>
                            <div class="flex-1 space-y-3"><div class="osas-skeleton-block osas-skeleton-pulse h-4 w-1/2"></div><div class="osas-skeleton-block osas-skeleton-pulse h-3 w-1/4"></div></div>
                            <div class="osas-skeleton-block osas-skeleton-pulse h-4 w-24"></div>
                            <div class="osas-skeleton-block osas-skeleton-pulse h-8 w-20"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div data-osas-skeleton-template="detail" hidden class="mx-auto max-w-[1700px] space-y-4">
                <div class="flex items-end justify-between gap-4 border-b border-slate-200 pb-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="osas-skeleton-block osas-skeleton-pulse h-9 w-9 flex-shrink-0"></div>
                        <div class="osas-skeleton-block osas-skeleton-pulse h-11 w-11 flex-shrink-0 rounded-md"></div>
                        <div class="min-w-0 space-y-2">
                            <div class="osas-skeleton-block osas-skeleton-pulse h-3 w-32"></div>
                            <div class="osas-skeleton-block osas-skeleton-pulse h-8 w-80 max-w-[55vw]"></div>
                            <div class="osas-skeleton-block osas-skeleton-pulse h-3 w-56"></div>
                        </div>
                    </div>
                    <div class="osas-skeleton-block osas-skeleton-pulse h-9 w-32 flex-shrink-0"></div>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <div class="osas-skeleton-block osas-skeleton-pulse h-20"></div>
                    <div class="osas-skeleton-block osas-skeleton-pulse h-20"></div>
                    <div class="osas-skeleton-block osas-skeleton-pulse h-20"></div>
                    <div class="osas-skeleton-block osas-skeleton-pulse h-20"></div>
                </div>

                <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <div class="space-y-4">
                        <div class="rounded-lg border border-slate-200 bg-white p-4">
                            <div class="mb-5 flex items-center justify-between border-b border-slate-100 pb-3"><div class="space-y-2"><div class="osas-skeleton-block osas-skeleton-pulse h-2.5 w-28"></div><div class="osas-skeleton-block osas-skeleton-pulse h-4 w-64"></div></div><div class="osas-skeleton-block osas-skeleton-pulse h-5 w-5"></div></div>
                            <div class="grid grid-cols-2 gap-4"><div class="col-span-2 flex items-center gap-3"><div class="osas-skeleton-block osas-skeleton-pulse h-10 w-10 rounded-full"></div><div class="space-y-2"><div class="osas-skeleton-block osas-skeleton-pulse h-2.5 w-28"></div><div class="osas-skeleton-block osas-skeleton-pulse h-4 w-48"></div></div></div><div class="osas-skeleton-block osas-skeleton-pulse h-9"></div><div class="osas-skeleton-block osas-skeleton-pulse h-9"></div><div class="osas-skeleton-block osas-skeleton-pulse h-9"></div><div class="osas-skeleton-block osas-skeleton-pulse h-9"></div></div>
                        </div>
                        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white"><div class="flex items-center justify-between border-b border-slate-100 p-4"><div class="space-y-2"><div class="osas-skeleton-block osas-skeleton-pulse h-2.5 w-24"></div><div class="osas-skeleton-block osas-skeleton-pulse h-4 w-52"></div></div><div class="osas-skeleton-block osas-skeleton-pulse h-7 w-20"></div></div><div class="space-y-3 p-4"><div class="osas-skeleton-block osas-skeleton-pulse h-11 w-full"></div><div class="osas-skeleton-block osas-skeleton-pulse h-11 w-full"></div><div class="osas-skeleton-block osas-skeleton-pulse h-11 w-full"></div><div class="osas-skeleton-block osas-skeleton-pulse h-11 w-full"></div><div class="osas-skeleton-block osas-skeleton-pulse h-11 w-full"></div></div></div>
                        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white"><div class="flex items-center justify-between border-b border-slate-100 p-4"><div class="space-y-2"><div class="osas-skeleton-block osas-skeleton-pulse h-2.5 w-24"></div><div class="osas-skeleton-block osas-skeleton-pulse h-4 w-44"></div></div><div class="osas-skeleton-block osas-skeleton-pulse h-7 w-20"></div></div><div class="space-y-3 p-4"><div class="osas-skeleton-block osas-skeleton-pulse h-10 w-full"></div><div class="osas-skeleton-block osas-skeleton-pulse h-10 w-full"></div><div class="osas-skeleton-block osas-skeleton-pulse h-10 w-full"></div></div></div>
                    </div>
                    <div class="space-y-4">
                        <div class="rounded-lg border border-slate-200 bg-white p-4"><div class="mb-5 space-y-2"><div class="osas-skeleton-block osas-skeleton-pulse h-2.5 w-20"></div><div class="osas-skeleton-block osas-skeleton-pulse h-4 w-36"></div></div><div class="osas-skeleton-block osas-skeleton-pulse h-5 w-full"></div><div class="mt-3 grid grid-cols-2 gap-2"><div class="osas-skeleton-block osas-skeleton-pulse h-16"></div><div class="osas-skeleton-block osas-skeleton-pulse h-16"></div></div><div class="mt-4 space-y-2"><div class="osas-skeleton-block osas-skeleton-pulse h-10 w-full"></div><div class="osas-skeleton-block osas-skeleton-pulse h-9 w-full"></div></div></div>
                        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white"><div class="bg-[#26488C] p-4"><div class="osas-skeleton-block osas-skeleton-pulse h-3 w-36 bg-white/20"></div><div class="mt-2 osas-skeleton-block osas-skeleton-pulse h-5 w-48 bg-white/20"></div><div class="mt-3 osas-skeleton-block osas-skeleton-pulse h-3 w-full bg-white/20"></div></div><div class="border-b border-slate-100 p-3"><div class="osas-skeleton-block osas-skeleton-pulse h-10 w-full"></div></div><div class="space-y-2 bg-slate-50/30 p-3"><div class="osas-skeleton-block osas-skeleton-pulse h-24 w-full"></div><div class="osas-skeleton-block osas-skeleton-pulse h-24 w-full"></div></div></div>
                    </div>
                </div>
            </div>

            <div data-osas-skeleton-template="library" hidden class="mx-auto max-w-7xl space-y-5 [overflow-wrap:anywhere]">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div class="min-w-0">
                            <div class="osas-skeleton-block osas-skeleton-pulse h-3 w-28"></div>
                            <div class="mt-2 osas-skeleton-block osas-skeleton-pulse h-8 w-56"></div>
                            <div class="mt-2 osas-skeleton-block osas-skeleton-pulse h-4 w-80 max-w-[60vw]"></div>
                        </div>
                        <div class="w-full lg:max-w-xl">
                            <div class="mb-1 osas-skeleton-block osas-skeleton-pulse h-4 w-32"></div>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <div class="osas-skeleton-block osas-skeleton-pulse h-11 flex-1"></div>
                                <div class="osas-skeleton-block osas-skeleton-pulse h-11 w-full sm:w-28"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <div class="osas-skeleton-block osas-skeleton-pulse h-4 w-24"></div>
                        <i class="ph ph-caret-right text-[9px] text-gray-300"></i>
                        <div class="osas-skeleton-block osas-skeleton-pulse h-4 w-32"></div>
                        <i class="ph ph-caret-right text-[9px] text-gray-300"></i>
                        <div class="osas-skeleton-block osas-skeleton-pulse h-4 w-40"></div>
                    </div>
                    <div class="osas-skeleton-block osas-skeleton-pulse h-9 w-20 self-start sm:self-auto"></div>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                        <div class="min-w-0">
                            <div class="osas-skeleton-block osas-skeleton-pulse h-4 w-44"></div>
                            <div class="mt-1 osas-skeleton-block osas-skeleton-pulse h-3 w-72"></div>
                        </div>
                    </div>
                    <div class="border-t border-gray-200 p-4 sm:p-5">
                        <div class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="space-y-1.5"><div class="osas-skeleton-block osas-skeleton-pulse h-4 w-20"></div><div class="osas-skeleton-block osas-skeleton-pulse h-9 w-full"></div></div>
                            <div class="space-y-1.5"><div class="osas-skeleton-block osas-skeleton-pulse h-4 w-28"></div><div class="osas-skeleton-block osas-skeleton-pulse h-9 w-full"></div></div>
                            <div class="space-y-1.5"><div class="osas-skeleton-block osas-skeleton-pulse h-4 w-16"></div><div class="osas-skeleton-block osas-skeleton-pulse h-9 w-full"></div></div>
                            <div class="osas-skeleton-block osas-skeleton-pulse h-9 w-full"></div>
                        </div>
                    </div>
                </div>

                <div class="min-w-0 space-y-3">
                    <div class="flex flex-wrap items-end justify-between gap-2">
                        <div class="min-w-0">
                            <div class="osas-skeleton-block osas-skeleton-pulse h-5 w-24"></div>
                            <div class="mt-1 osas-skeleton-block osas-skeleton-pulse h-3 w-64"></div>
                        </div>
                        <div class="osas-skeleton-block osas-skeleton-pulse h-4 w-20"></div>
                    </div>
                    <div class="grid min-w-0 grid-cols-1 gap-3">
                        <div class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm"><div class="osas-skeleton-block osas-skeleton-pulse h-11 w-11 shrink-0 rounded-lg"></div><div class="min-w-0 flex-1 space-y-2"><div class="osas-skeleton-block osas-skeleton-pulse h-4 w-2/5"></div><div class="osas-skeleton-block osas-skeleton-pulse h-3 w-1/4"></div></div><div class="osas-skeleton-block osas-skeleton-pulse h-6 w-20 rounded-full"></div></div>
                        <div class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm"><div class="osas-skeleton-block osas-skeleton-pulse h-11 w-11 shrink-0 rounded-lg"></div><div class="min-w-0 flex-1 space-y-2"><div class="osas-skeleton-block osas-skeleton-pulse h-4 w-1/2"></div><div class="osas-skeleton-block osas-skeleton-pulse h-3 w-1/3"></div></div><div class="osas-skeleton-block osas-skeleton-pulse h-6 w-16 rounded-full"></div></div>
                        <div class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm"><div class="osas-skeleton-block osas-skeleton-pulse h-11 w-11 shrink-0 rounded-lg"></div><div class="min-w-0 flex-1 space-y-2"><div class="osas-skeleton-block osas-skeleton-pulse h-4 w-2/3"></div><div class="osas-skeleton-block osas-skeleton-pulse h-3 w-1/4"></div></div><div class="osas-skeleton-block osas-skeleton-pulse h-6 w-24 rounded-full"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Custom Notification & Confirmation System -->
    <style>
        /* Toast animations from Auth system */
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        .toast-notification {
            animation: slideInRight 0.3s ease-out;
        }
        .toast-notification.hiding {
            animation: slideOutRight 0.3s ease-out forwards;
        }
    </style>

    <div x-data="{ 
            visible: false, 
            title: '', 
            message: '', 
            resolve: null,
            showConfirmation(detail) {
                this.visible = true;
                this.title = detail.title;
                this.message = detail.message;
                this.resolve = detail.resolve;
            },
            cancel() {
                if(this.resolve) this.resolve(false);
                this.visible = false;
            },
            confirm() {
                if(this.resolve) this.resolve(true);
                this.visible = false;
            }
         }" 
         @confirm-action.window="showConfirmation($event.detail)"
         class="fixed inset-0 z-[9999] pointer-events-none">
        
        <!-- Confirmation Modal (Kept Alpine for simplicity, but design matches) -->
        <div x-show="visible" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 flex items-center justify-center p-6 pointer-events-auto bg-gray-900/40 modal-backdrop">
            
            <div @click.away="cancel()"
                 x-show="visible"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 class="bg-white rounded-lg shadow-2xl max-w-md w-full p-10 text-center relative overflow-hidden">
                <div class="relative z-10">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 mb-6">
                        <i class="ph ph-sign-out text-2xl text-gray-400"></i>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-4" x-text="title"></h3>
                    <p class="text-gray-500 leading-relaxed mb-10 text-sm font-medium" x-text="message"></p>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button @click="cancel()" class="flex-1 py-4 bg-gray-50 text-gray-400 font-black text-xs uppercase tracking-widest rounded-lg hover:bg-gray-100 transition-all">Cancel</button>
                        <button @click="confirm()" class="flex-1 py-4 bg-[#26488C] text-white font-black text-xs uppercase tracking-widest rounded-lg hover:bg-[#1a3461] transition-all shadow-lg shadow-[#26488C]/20">Confirm</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Exact showToast from auth.js
        window.showToast = function(message, type = 'success') {
            const existingToasts = document.querySelectorAll('.toast-notification');
            existingToasts.forEach(toast => toast.remove());
            
            const toast = document.createElement('div');
            toast.className = 'toast-notification';
            
            const borderClass = type === 'error' ? 'border-red-500' : 'border-green-500';
            const iconClass = type === 'error' ? 'ph-warning-circle text-red-600' : 'ph-check-circle text-green-600';
            const iconBgClass = type === 'error' ? 'bg-red-100' : 'bg-green-100';
            const title = type === 'error' ? 'Error!' : 'Success!';
            
            toast.className = `toast-notification fixed top-6 right-6 z-[10001] bg-white border-l-4 ${borderClass} rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md`;
            
            toast.innerHTML = `
                <div class="flex-shrink-0">
                    <div class="h-10 w-10 rounded-full ${iconBgClass} flex items-center justify-center">
                        <i class="ph ${iconClass} text-xl"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-gray-900">${title}</p>
                    <p class="text-sm text-gray-600">${message}</p>
                </div>
                <button onclick="this.parentElement.remove()" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition">
                    <i class="ph ph-x text-lg"></i>
                </button>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('hiding');
                setTimeout(() => { if (toast.parentElement) toast.remove(); }, 300);
            }, 4000);
        };

        window.confirmAction = (message, title = 'Confirmation') => {
            return new Promise((resolve) => {
                window.dispatchEvent(new CustomEvent('confirm-action', { 
                    detail: { message, title, resolve } 
                }));
            });
        };

        document.addEventListener('DOMContentLoaded', () => {
            @if(session('success')) window.showToast("{{ session('success') }}", 'success'); @endif
            @if(session('error')) window.showToast("{{ session('error') }}", 'error'); @endif
            @if(session('info')) window.showToast("{{ session('info') }}", 'info'); @endif
        });
    </script>

    @vite(['resources/js/app.js', 'resources/js/osas-notifications.js'])
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        (function () {
            'use strict';

            if (window.__osasNavigationInitialized) return;
            window.__osasNavigationInitialized = true;

            const safeOsasPages = new Set([
                'dashboard',
                'calendar',
                'proposals',
                'apply-org',
                'organizations',
                'analytics',
                'settings',
                'smart-search',
                'ocr-test-lab',
                'announcements',
                'document-testing'
            ]);
            const pageCache = new Map();
            const pageCacheLimit = 12;
            const pageCacheTtl = 120000;
            let pageCacheVersion = 0;
            let activeNavigationController = null;
            let navigationRequestId = 0;
            let mainContent = document.querySelector('.main-content > main');
            let activePageRuntime = null;
            const loadedExternalScripts = new Set();

            function normalizeUrl(url) {
                return new URL(url, window.location.href);
            }

            function getOsasSegment(url) {
                const targetUrl = normalizeUrl(url);
                const parts = targetUrl.pathname.replace(/\/+$/, '').split('/');
                const osasIndex = parts.lastIndexOf('osas');

                if (osasIndex === -1 || !parts[osasIndex + 1]) return null;
                return parts[osasIndex + 1];
            }

            function isAccreditationDetailUrl(url) {
                const targetUrl = normalizeUrl(url);
                const parts = targetUrl.pathname.replace(/\/+$/, '').split('/');
                const applyOrgIndex = parts.lastIndexOf('apply-org');

                return getOsasSegment(targetUrl.href) === 'apply-org'
                    && applyOrgIndex !== -1
                    && Boolean(parts[applyOrgIndex + 1]);
            }

            function isSafeOsasPage(url) {
                const targetUrl = normalizeUrl(url);
                return targetUrl.origin === window.location.origin
                    && !targetUrl.hash
                    && safeOsasPages.has(getOsasSegment(targetUrl.href));
            }

            function getPageCacheKey(url) {
                const targetUrl = normalizeUrl(url);
                return `${targetUrl.pathname}${targetUrl.search}`;
            }

            function trimPageCache() {
                while (pageCache.size > pageCacheLimit) {
                    pageCache.delete(pageCache.keys().next().value);
                }
            }

            function cachePage(url, html, title) {
                const key = getPageCacheKey(url);
                pageCache.delete(key);
                pageCache.set(key, { html, title, cachedAt: Date.now() });
                trimPageCache();
            }

            function getCachedPage(url) {
                const key = getPageCacheKey(url);
                const cachedPage = pageCache.get(key);
                if (!cachedPage) return null;

                if (Date.now() - cachedPage.cachedAt > pageCacheTtl) {
                    pageCache.delete(key);
                    return null;
                }

                return cachedPage;
            }

            function cacheCurrentPage() {
                const currentUrl = normalizeUrl(window.location.href);
                if (!mainContent || !isSafeOsasPage(currentUrl.href)) return;

                // Announcement pages contain Alpine-generated x-for/x-if DOM. Caching the
                // already-initialized DOM causes those nodes to be rendered again when the
                // cached page is initialized after navigating back.
                if (getOsasSegment(currentUrl.href) === 'announcements') {
                    pageCache.delete(getPageCacheKey(currentUrl.href));
                    return;
                }

                cachePage(currentUrl.href, mainContent.innerHTML, document.title);
            }

            window.clearOsasPageCache = function () {
                pageCache.clear();
                pageCacheVersion++;
            };

            if (!window.__osasPageCacheFetchWrapped) {
                const nativeFetch = window.fetch.bind(window);
                window.fetch = function (resource, options = {}) {
                    const method = (options.method || (resource && resource.method) || 'GET').toUpperCase();

                    if (method !== 'GET' && method !== 'HEAD') {
                        const socketId = window.Echo && typeof window.Echo.socketId === 'function'
                            ? window.Echo.socketId()
                            : null;

                        if (socketId) {
                            const headers = new Headers(options.headers || (resource instanceof Request ? resource.headers : undefined));
                            if (!headers.has('X-Socket-ID')) headers.set('X-Socket-ID', socketId);
                            options = { ...options, headers };
                        }

                        window.clearOsasPageCache();
                        if (typeof window.clearOsasAccreditationCache === 'function') {
                            window.clearOsasAccreditationCache();
                        }
                    }

                    return nativeFetch(resource, options);
                };
                window.__osasPageCacheFetchWrapped = true;
            }

            function showNavigationSkeleton(label, url = null) {
                const skeleton = document.getElementById('osas-navigation-skeleton');
                if (!skeleton) return;

                const targetUrl = url ? normalizeUrl(url) : null;
                const isDetail = targetUrl ? isAccreditationDetailUrl(targetUrl.href) : false;
                const isLibrary = targetUrl ? getOsasSegment(targetUrl.href) === 'smart-search' : false;
                const activeTemplate = isDetail ? 'detail' : (isLibrary ? 'library' : 'queue');
                skeleton.querySelectorAll('[data-osas-skeleton-template]').forEach(template => {
                    template.hidden = template.getAttribute('data-osas-skeleton-template') !== activeTemplate;
                });
                skeleton.setAttribute('data-skeleton-view', activeTemplate);

                const title = skeleton.querySelector('[data-osas-skeleton-title]');
                if (title) title.textContent = `Loading ${isDetail ? 'request review' : (isLibrary ? 'document library' : (label || 'page'))}...`;

                skeleton.classList.add('is-visible');
                skeleton.setAttribute('aria-hidden', 'false');
                document.body.setAttribute('aria-busy', 'true');
            }

            function hideNavigationSkeleton() {
                const skeleton = document.getElementById('osas-navigation-skeleton');
                if (!skeleton) return;

                skeleton.classList.remove('is-visible');
                skeleton.setAttribute('aria-hidden', 'true');
                document.body.removeAttribute('aria-busy');
            }

            window.showOsasNavigationSkeleton = showNavigationSkeleton;
            window.hideOsasNavigationSkeleton = hideNavigationSkeleton;

            function getNavigationLabel(link) {
                if (!link) return 'page';

                const label = link.querySelector('.nav-text')?.textContent
                    || link.getAttribute('aria-label')
                    || link.textContent;

                return label.trim().replace(/\s+/g, ' ') || 'page';
            }

            function getNavigationLink(url) {
                const targetUrl = normalizeUrl(url);
                return Array.from(document.querySelectorAll('a[data-osas-navigation]'))
                    .find(link => {
                        const linkUrl = normalizeUrl(link.href);
                        return linkUrl.pathname === targetUrl.pathname
                            && (getOsasSegment(targetUrl.href) !== 'apply-org' || linkUrl.search === targetUrl.search);
                    }) || null;
            }

            function setActiveNavigation(url) {
                const targetUrl = normalizeUrl(url);
                const targetSegment = getOsasSegment(targetUrl.href);

                document.querySelectorAll('.sidebar a[data-osas-navigation]').forEach(link => {
                    const linkUrl = normalizeUrl(link.href);
                    let isActive;

                    // Accreditation tabs are query-specific; detail pages highlight only the parent group.
                    if (targetSegment === 'apply-org') {
                        isActive = linkUrl.pathname === targetUrl.pathname
                            && (linkUrl.searchParams.get('type') || '') === (targetUrl.searchParams.get('type') || '');
                    } else {
                        // Section detail pages (e.g. /osas/organizations/{id}) keep their parent item highlighted.
                        isActive = linkUrl.pathname === targetUrl.pathname
                            || targetUrl.pathname.startsWith(linkUrl.pathname + '/');
                    }

                    link.classList.toggle('active', isActive);
                    if (isActive) link.setAttribute('aria-current', 'page');
                    else link.removeAttribute('aria-current');
                });

                document.querySelectorAll('[data-osas-navigation-group="accreditation"]').forEach(group => {
                    const parentLink = group.querySelector('.nav-item');
                    const active = targetSegment === 'apply-org';
                    if (!parentLink) return;

                    parentLink.classList.toggle('active', active);
                    if (active) parentLink.setAttribute('aria-current', 'page');
                    else parentLink.removeAttribute('aria-current');
                });
            }

            window.setOsasActiveNavigation = setActiveNavigation;

            function createPageRuntime() {
                let active = true;
                let capturing = false;
                const cleanupCallbacks = [];
                const originalAddEventListener = EventTarget.prototype.addEventListener;
                const originalRemoveEventListener = EventTarget.prototype.removeEventListener;
                const originalSetInterval = window.setInterval;
                const originalClearInterval = window.clearInterval;
                const originalSetTimeout = window.setTimeout;
                const originalClearTimeout = window.clearTimeout;

                function startCapture() {
                    if (capturing) return;
                    capturing = true;

                    EventTarget.prototype.addEventListener = function (type, listener, options) {
                        if (!active) return undefined;

                        if (this === document && (type === 'DOMContentLoaded' || type === 'alpine:init')) {
                            if (typeof listener === 'function') {
                                listener.call(this, new Event(type));
                            } else if (listener && typeof listener.handleEvent === 'function') {
                                listener.handleEvent(new Event(type));
                            }
                            return undefined;
                        }

                        const result = originalAddEventListener.call(this, type, listener, options);
                        cleanupCallbacks.push(() => originalRemoveEventListener.call(this, type, listener, options));
                        return result;
                    };

                    window.setInterval = function () {
                        const id = originalSetInterval.apply(window, arguments);
                        cleanupCallbacks.push(() => originalClearInterval(id));
                        return id;
                    };

                    window.setTimeout = function () {
                        const id = originalSetTimeout.apply(window, arguments);
                        cleanupCallbacks.push(() => originalClearTimeout(id));
                        return id;
                    };
                }

                function stopCapture() {
                    if (!capturing) return;
                    EventTarget.prototype.addEventListener = originalAddEventListener;
                    window.setInterval = originalSetInterval;
                    window.setTimeout = originalSetTimeout;
                    capturing = false;
                }

                return {
                    startCapture,
                    stopCapture,
                    cleanup() {
                        active = false;
                        stopCapture();
                        cleanupCallbacks.reverse().forEach(callback => {
                            try { callback(); } catch (error) { console.warn('OSAS page cleanup failed:', error); }
                        });
                        EventTarget.prototype.removeEventListener = originalRemoveEventListener;
                        window.clearInterval = originalClearInterval;
                        window.clearTimeout = originalClearTimeout;
                    }
                };
            }

            function cleanupCurrentPage() {
                window.__osasAccreditationPopstate = null;
                window.__osasAccreditationNavigate = null;

                if (typeof window.__osasChatCleanup === 'function') {
                    try { window.__osasChatCleanup(); } catch (error) { console.warn('OSAS chat cleanup failed:', error); }
                    window.__osasChatCleanup = null;
                }

                if (activePageRuntime) {
                    activePageRuntime.cleanup();
                    activePageRuntime = null;
                }

                if (mainContent && window.Alpine && typeof window.Alpine.destroyTree === 'function') {
                    try { window.Alpine.destroyTree(mainContent); } catch (error) { console.warn('OSAS Alpine cleanup failed:', error); }
                }
            }

            function isKnownExternalScriptAvailable(src) {
                const source = src.toLowerCase();
                if (loadedExternalScripts.has(src)) return true;
                if (source.includes('chart.js') && typeof window.Chart !== 'undefined') return true;
                if (source.includes('fullcalendar') && typeof window.FullCalendar !== 'undefined') return true;
                if (source.includes('jquery') && typeof window.jQuery !== 'undefined') return true;
                return false;
            }

            function copyScriptAttributes(source, target) {
                Array.from(source.attributes).forEach(attribute => {
                    if (attribute.name !== 'async' && attribute.name !== 'defer') {
                        target.setAttribute(attribute.name, attribute.value);
                    }
                });
                target.async = false;
            }

            async function executePageScripts(container, runtime) {
                const scripts = Array.from(container.querySelectorAll('script'));

                for (const script of scripts) {
                    const type = (script.getAttribute('type') || '').toLowerCase();
                    if (type && type !== 'text/javascript' && type !== 'application/javascript' && type !== 'module') {
                        continue;
                    }

                    const executableScript = document.createElement('script');
                    copyScriptAttributes(script, executableScript);

                    if (script.src) {
                        const source = new URL(script.src, window.location.href).href;
                        if (isKnownExternalScriptAvailable(source)) continue;

                        await new Promise(resolve => {
                            executableScript.onload = () => {
                                loadedExternalScripts.add(source);
                                resolve();
                            };
                            executableScript.onerror = () => {
                                console.warn('OSAS page script failed to load:', source);
                                resolve();
                            };
                            executableScript.src = source;
                            document.head.appendChild(executableScript);
                        });
                        continue;
                    }

                    executableScript.textContent = script.textContent;
                    runtime.startCapture();
                    try {
                        document.head.appendChild(executableScript);
                    } catch (error) {
                        console.error('OSAS page script failed:', error);
                    } finally {
                        runtime.stopCapture();
                    }
                    executableScript.remove();
                }
            }

            async function renderPageContent(html, title, requestId) {
                if (!mainContent) mainContent = document.querySelector('.main-content > main');
                if (!mainContent) throw new Error('OSAS page content container was not found');

                cleanupCurrentPage();
                mainContent.innerHTML = html;
                const runtime = createPageRuntime();
                activePageRuntime = runtime;

                await executePageScripts(mainContent, runtime);

                if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                    try { window.Alpine.initTree(mainContent); } catch (error) { console.warn('OSAS Alpine initialization failed:', error); }
                }

                if (title) document.title = title;
                window.dispatchEvent(new CustomEvent('osas:navigation-ready', {
                    detail: { requestId, url: window.location.href }
                }));
            }

            async function fetchPageFragment(url, signal) {
                const fragmentUrl = normalizeUrl(url);
                fragmentUrl.searchParams.set('fragment', '1');

                const requestOptions = {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html'
                    }
                };
                if (signal) requestOptions.signal = signal;

                const response = await fetch(fragmentUrl.href, requestOptions);
                if (!response.ok) throw new Error(`Failed to load OSAS page (${response.status})`);

                const pageHtml = await response.text();
                const pageDocument = new DOMParser().parseFromString(pageHtml, 'text/html');
                const nextMain = pageDocument.querySelector('main');
                if (!nextMain) throw new Error('OSAS page content was not found');

                return {
                    html: nextMain.innerHTML,
                    title: pageDocument.title
                };
            }

            async function refreshPageCache(url) {
                const refreshVersion = pageCacheVersion;
                try {
                    const page = await fetchPageFragment(url, null);
                    if (refreshVersion === pageCacheVersion) cachePage(url, page.html, page.title);
                } catch (error) {
                    console.warn('OSAS page background refresh failed:', error);
                }
            }

            let currentPageRefreshPromise = null;

            // Mutating OSAS pages use this after a successful AJAX request.
            // It retrieves fresh, server-authorized content and swaps only the
            // current main area, keeping the OSAS shell and browser URL intact.
            window.refreshCurrentOsasPage = function () {
                if (currentPageRefreshPromise) return currentPageRefreshPromise;

                const currentUrl = normalizeUrl(window.location.href);
                if (!isSafeOsasPage(currentUrl.href)) return Promise.resolve(false);

                const requestId = ++navigationRequestId;
                currentPageRefreshPromise = fetchPageFragment(currentUrl.href, null)
                    .then(async page => {
                        cachePage(currentUrl.href, page.html, page.title);
                        await renderPageContent(page.html, page.title, requestId);
                        setActiveNavigation(currentUrl.href);
                        return true;
                    })
                    .catch(error => {
                        console.warn('OSAS page in-place refresh failed:', error);
                        return false;
                    })
                    .finally(() => {
                        currentPageRefreshPromise = null;
                    });

                return currentPageRefreshPromise;
            };

            const isOsasRealtimeRoute = function (topic) {
                const pathname = normalizeUrl(window.location.href).pathname.replace(/\/+$/, '') || '/';

                if (topic === 'announcements') return pathname === '/osas/announcements';
                if (topic === 'events') {
                    return pathname === '/osas/dashboard'
                        || pathname === '/osas/calendar'
                        || pathname === '/osas/proposals'
                        || pathname.startsWith('/osas/proposals/');
                }

                return false;
            };

            let realtimeRefreshPromise = null;
            let realtimeRefreshQueued = false;

            window.refreshCurrentRealtimePage = function (topic) {
                if (!isOsasRealtimeRoute(topic)) return Promise.resolve(false);

                if (realtimeRefreshPromise) {
                    realtimeRefreshQueued = true;
                    return realtimeRefreshPromise;
                }

                realtimeRefreshPromise = (async function () {
                    let refreshed = false;
                    do {
                        realtimeRefreshQueued = false;
                        refreshed = await window.refreshCurrentOsasPage();
                    } while (realtimeRefreshQueued);

                    return refreshed;
                }()).finally(() => {
                    realtimeRefreshPromise = null;
                });

                return realtimeRefreshPromise;
            };

            function listenForOsasAnnouncementUpdates() {
                if (window.__osasAnnouncementListenerInitialized || !window.AnnouncementEcho) return;

                window.__osasAnnouncementListenerInitialized = true;
                window.AnnouncementEcho.channel('announcements')
                    .listen('.announcement-created', () => window.refreshCurrentRealtimePage('announcements'))
                    .listen('.announcement-updated', () => window.refreshCurrentRealtimePage('announcements'))
                    .listen('.announcement-deleted', () => window.refreshCurrentRealtimePage('announcements'));
            }

            window.addEventListener('announcement-echo-ready', listenForOsasAnnouncementUpdates);
            listenForOsasAnnouncementUpdates();

            async function navigateOsasPage(url, updateHistory, requestId, controller) {
                const targetUrl = normalizeUrl(url);
                const isLatest = () => requestId === navigationRequestId;
                const cachedPage = getCachedPage(targetUrl.href);

                if (!isSafeOsasPage(targetUrl.href) || !isLatest()) return false;
                setActiveNavigation(targetUrl.href);
                showNavigationSkeleton(getNavigationLabel(getNavigationLink(targetUrl.href)), targetUrl.href);

                try {
                    const page = cachedPage || await fetchPageFragment(targetUrl.href, controller && controller.signal);
                    if (!isLatest()) return false;

                    if (!cachedPage) cachePage(targetUrl.href, page.html, page.title);
                    await renderPageContent(page.html, page.title, requestId);
                    if (!isLatest()) return false;

                    if (updateHistory) window.history.pushState({ osasNavigation: true }, '', targetUrl.href);
                    window.scrollTo({ top: 0, behavior: 'auto' });
                    hideNavigationSkeleton();

                    if (cachedPage) refreshPageCache(targetUrl.href);
                    return true;
                } catch (error) {
                    if (error.name === 'AbortError' || !isLatest()) return false;

                    console.error('OSAS navigation failed; restoring normal navigation:', error);
                    hideNavigationSkeleton();
                    window.location.assign(targetUrl.href);
                    return false;
                } finally {
                    if (activeNavigationController === controller) activeNavigationController = null;
                }
            }

            function beginOsasNavigation(url, updateHistory = true, sourceLink = null, force = false) {
                const targetUrl = normalizeUrl(url);
                if (!isSafeOsasPage(targetUrl.href)) {
                    window.location.assign(targetUrl.href);
                    return Promise.resolve(false);
                }

                if (!force && targetUrl.pathname === window.location.pathname && targetUrl.search === window.location.search) {
                    return Promise.resolve(false);
                }

                const requestId = ++navigationRequestId;
                if (activeNavigationController) activeNavigationController.abort();
                activeNavigationController = new AbortController();
                // On popstate, the browser URL already points at the target page while the DOM
                // still contains the previous page, so caching here would associate stale HTML
                // with the wrong URL.
                if (!force) cacheCurrentPage();
                setActiveNavigation(targetUrl.href);
                showNavigationSkeleton(getNavigationLabel(sourceLink || getNavigationLink(targetUrl.href)), targetUrl.href);

                return navigateOsasPage(targetUrl.href, updateHistory, requestId, activeNavigationController);
            }

            window.navigateOsasPage = beginOsasNavigation;

            document.addEventListener('click', function (event) {
                const link = event.target instanceof Element
                    ? event.target.closest('a')
                    : null;

                if (!link || event.defaultPrevented || event.button !== 0
                    || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
                    || link.target === '_blank' || link.hasAttribute('download')
                    || link.closest('form')) {
                    return;
                }

                const href = link.getAttribute('href');
                if (!href || href.startsWith('#')) return;

                const targetUrl = normalizeUrl(link.href);
                if (!isSafeOsasPage(targetUrl.href)) return;
                if (targetUrl.pathname === window.location.pathname && targetUrl.search === window.location.search) return;

                if (getOsasSegment(targetUrl.href) === 'smart-search'
                    && getOsasSegment(window.location.href) === 'smart-search') {
                    // Inside the Document Library its own navigation handles
                    // folder/document links; entering it from another page goes
                    // through the SPA shell so the transition matches the rest.
                    return;
                }

                if (targetUrl.pathname.endsWith('/osas/apply-org') && typeof window.__osasAccreditationNavigate === 'function') {
                    event.preventDefault();
                    window.__osasAccreditationNavigate(targetUrl.href, true);
                    return;
                }

                event.preventDefault();
                beginOsasNavigation(targetUrl.href, true, link);
            });

            window.addEventListener('popstate', function () {
                const currentUrl = normalizeUrl(window.location.href);
                const targetSegment = getOsasSegment(currentUrl.href);
                if (targetSegment === 'smart-search'
                    && document.querySelector('[data-document-library-region]')) {
                    // The Document Library restores its own state on back/forward.
                    return;
                }
                if (!isSafeOsasPage(currentUrl.href)) {
                    window.location.reload();
                    return;
                }

                if (targetSegment === 'apply-org' && typeof window.__osasAccreditationPopstate === 'function') {
                    window.__osasAccreditationPopstate(currentUrl);
                    return;
                }

                beginOsasNavigation(currentUrl.href, false, null, true);
            });

            setActiveNavigation(window.location.href);
        }());
    </script>
    @stack('scripts')
    <x-osas.floating-chat />
</body>
</html>
