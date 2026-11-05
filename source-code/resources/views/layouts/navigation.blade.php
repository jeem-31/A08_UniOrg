<nav x-data="{ mobileMenuOpen: false }" class="shadow-lg sticky top-0 z-50 {{ auth()->user()->isEffectivelyStudent() ? 'bg-white' : '' }}" @if(!auth()->user()->isEffectivelyStudent()) style="background-color: var(--primary-color);" @endif>
    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex min-w-0 flex-1 sm:flex-initial">
                <div class="flex-shrink-0 flex items-center sm:mr-8 min-w-0">
                    <a href="{{ route('home') }}" class="flex items-center min-w-0">
                        @if(auth()->check() && auth()->user()->role === 'osas_admin')
                            <div class="h-9 w-9 sm:h-10 sm:w-10 mr-2 nav-logo logo-container flex-shrink-0" data-logo-src="{{ asset('images/LSPU_LOGO.png') }}" style="background-image: url('{{ asset('images/LSPU_LOGO.png') }}'); background-size: contain; background-repeat: no-repeat; background-position: center;"></div>
                            <span class="text-xl sm:text-2xl font-bold truncate" style="color: #26488C;">OSAS</span>
                        @else
                            <img src="{{ organization_logo() }}" 
                                 alt="Logo" 
                                 class="h-9 w-9 sm:h-10 sm:w-10 object-contain mr-2 nav-logo flex-shrink-0"
                                 onerror="this.src='{{ asset('images/soccsyncLogo-removebg-preview.png') }}'; console.error('❌ Student Logo failed to load:', '{{ organization_logo() }}'); console.log('✅ Fallback to local asset');"
                                 onload="console.log('✅ Student Logo loaded successfully from:', this.src);">
                            <span class="text-xl sm:text-2xl font-bold truncate {{ auth()->user()->isEffectivelyStudent() ? '' : 'text-white' }}" @if(auth()->user()->isEffectivelyStudent()) style="color: var(--primary-color);" @endif>{{ organization_acronym() }}</span>
                        @endif
                    </a>
                </div>
                @auth
                <div class="hidden sm:flex sm:justify-between sm:items-center sm:flex-1 nav-tabs-container" id="mainNavTabs">
                    <div class="flex justify-center items-center flex-1">
                        <a href="{{ route('dashboard') }}" @if(auth()->user()->isEffectivelyStudent()) data-student-navigation @endif class="nav-link inline-flex items-center px-4 pt-1 text-sm font-medium {{ auth()->user()->isEffectivelyStudent() ? '' : 'text-white' }} {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="ph ph-house mr-1" style="color: {{ request()->routeIs('dashboard') ? 'var(--primary-color)' : 'var(--complementary-color)' }};"></i><span class="tab-text">{{ auth()->user()->isEffectivelyAdmin() ? 'Dashboard' : 'Homepage' }}</span>
                        </a>
                        @if(auth()->user()->role === 'osas_admin')
                        {{-- OSAS Admin Navigation --}}
                        <a href="{{ route('osas.dashboard') }}" class="nav-link inline-flex items-center px-4 pt-1 text-sm font-medium {{ request()->routeIs('osas.dashboard') ? 'active' : 'text-gray-500' }}" style="{{ request()->routeIs('osas.dashboard') ? 'color: #26488C;' : '' }}; {{ !request()->routeIs('osas.dashboard') ? 'hover:color: #26488C;' : '' }}">
                            <i class="ph ph-house mr-1" style="color: #C4973B;"></i><span class="tab-text">Dashboard</span>
                        </a>
                        <a href="{{ route('osas.system-requests.index') }}" class="nav-link inline-flex items-center px-4 pt-1 text-sm font-medium {{ request()->routeIs('osas.system-requests.*') ? 'active' : 'text-gray-500' }}" style="{{ request()->routeIs('osas.system-requests.*') ? 'color: #26488C;' : '' }}">
                            <i class="ph ph-tray mr-1" style="color: #C4973B;"></i><span class="tab-text">System Requests</span>
                        </a>
                        <a href="#" class="nav-link inline-flex items-center px-4 pt-1 text-sm font-medium text-gray-400 cursor-not-allowed">
                            <i class="ph ph-building mr-1" style="color: #C4973B; opacity: 0.5;"></i><span class="tab-text">Organizations</span>
                            <span class="text-xs ml-1">(Coming Soon)</span>
                        </a>
                        @elseif(auth()->user()->isEffectivelyAdmin())
                        @canAccessModule('announcements')
                        <a href="{{ route('admin.announcements.index') }}" class="nav-link inline-flex items-center px-4 pt-1 text-sm font-medium text-white {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}">
                            <i class="ph ph-megaphone mr-1" style="color: {{ request()->routeIs('admin.announcements.*') ? 'var(--primary-color)' : 'var(--complementary-color)' }};"></i><span class="tab-text">Announcements</span>
                            @hasReadOnly('announcements')
                                <span class="text-xs ml-1 text-gray-500">(View Only)</span>
                            @endHasReadOnly
                        </a>
                        @endCanAccessModule
                        @canAccessModule('events')
                        <a href="{{ route('admin.events.index') }}" class="nav-link inline-flex items-center px-4 pt-1 text-sm font-medium text-white {{ request()->routeIs('admin.events.*') ? 'active' : '' }}">
                            <i class="ph ph-calendar mr-1" style="color: {{ request()->routeIs('admin.events.*') ? 'var(--primary-color)' : 'var(--complementary-color)' }};"></i><span class="tab-text">Events</span>
                            @hasReadOnly('events')
                                <span class="text-xs ml-1 text-gray-500">(View Only)</span>
                            @endHasReadOnly
                        </a>
                        @endCanAccessModule
                        @canAccessModule('payments')
                        <a href="{{ route('admin.payments.index') }}" class="nav-link inline-flex items-center px-4 pt-1 text-sm font-medium text-white {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}">
                            <i class="ph ph-credit-card mr-1" style="color: {{ request()->routeIs('admin.payments.*') ? 'var(--primary-color)' : 'var(--complementary-color)' }};"></i><span class="tab-text">Payments</span>
                            @hasReadOnly('payments')
                                <span class="text-xs ml-1 text-gray-500">(View Only)</span>
                            @endHasReadOnly
                        </a>
                        @endCanAccessModule
                        @canAccessModule('elections')
                        <a href="{{ route('admin.elections.index') }}" class="nav-link inline-flex items-center px-4 pt-1 text-sm font-medium text-white {{ request()->routeIs('admin.elections.*') ? 'active' : '' }}">
                            <i class="ph ph-check-square mr-1" style="color: {{ request()->routeIs('admin.elections.*') ? 'var(--primary-color)' : 'var(--complementary-color)' }};"></i><span class="tab-text">Elections</span>
                            @hasReadOnly('elections')
                                <span class="text-xs ml-1 text-gray-500">(View Only)</span>
                            @endHasReadOnly
                        </a>
                        @endCanAccessModule
                        @php
                            // Check if user has access to any item in More Resources dropdown
                            $hasDocumentsAccess = app(\App\Services\PermissionHelper::class)->canAccessModule('documents');
                            $hasComplaintsAccess = app(\App\Services\PermissionHelper::class)->canAccessModule('complaints');
                            $hasLibraryAccess = app(\App\Services\PermissionHelper::class)->canAccessModule('library');
                            $hasEdushareAccess = app(\App\Services\PermissionHelper::class)->canAccessModule('edushare');
                            $hasChatbotAccess = app(\App\Services\PermissionHelper::class)->canAccessModule('chatbot');
                            $showMoreResources = $hasDocumentsAccess || $hasComplaintsAccess || $hasLibraryAccess || $hasEdushareAccess || $hasChatbotAccess;
                        @endphp
                        @if($showMoreResources)
                        <div class="relative inline-flex items-center px-4 pt-1" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
                            <button type="button" class="nav-link text-sm font-medium cursor-default pointer-events-none text-white {{ request()->routeIs('library.*') || request()->routeIs('admin.library.*') || request()->routeIs('edushare.*') || request()->routeIs('admin.edushare.*') || request()->routeIs('admin.chatbot.*') || request()->routeIs('admin.complaints.*') || request()->routeIs('admin.documents.*') ? 'active' : '' }}">
                                <i class="ph ph-folder mr-1" style="color: {{ request()->routeIs('library.*') || request()->routeIs('admin.library.*') || request()->routeIs('edushare.*') || request()->routeIs('admin.edushare.*') || request()->routeIs('admin.chatbot.*') || request()->routeIs('admin.complaints.*') || request()->routeIs('admin.documents.*') ? 'var(--primary-color)' : 'var(--complementary-color)' }};"></i><span class="tab-text">More Resources</span>
                                <i class="ph ph-caret-down ml-1 text-xs"></i>
                            </button>
                            <div x-show="open" x-cloak x-transition class="absolute top-full left-0 mt-1 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50" style="display: none;">
                                <div class="py-1">
                                    @if(auth()->user()->isEffectivelyAdmin())
                                    @canAccessModule('library')
                                    <a href="{{ route('admin.library.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="ph ph-book-open mr-2" style="color: var(--complementary-color);"></i>Manage e-Library
                                    </a>
                                    @endCanAccessModule
                                    @canAccessModule('edushare')
                                    <a href="{{ route('admin.edushare.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="ph ph-share-network mr-2" style="color: var(--complementary-color);"></i>EduShare
                                    </a>
                                    @endCanAccessModule
                                    @canAccessModule('documents')
                                    <a href="{{ route('admin.documents.student-documents') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="ph ph-file-text mr-2" style="color: var(--complementary-color);"></i>Student Documents
                                        @hasReadOnly('documents')
                                            <span class="text-xs text-gray-500">(View Only)</span>
                                        @endHasReadOnly
                                    </a>
                                    @endCanAccessModule
                                    @else
                                    @if(module_enabled('elibrary'))
                                    <a href="{{ route('library.index') }}" data-student-navigation class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="ph ph-book-open mr-2" style="color: var(--complementary-color);"></i>e-Library
                                    </a>
                                    @endif
                                    @if(module_enabled('edushare'))
                                    <a href="{{ route('edushare.index', ['tab' => 'all']) }}" data-student-navigation class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="ph ph-share-network mr-2" style="color: var(--complementary-color);"></i>EduShare
                                    </a>
                                    @endif
                                    @endif
                                    @if(auth()->user()->isEffectivelyAdmin())
                                    @canAccessModule('chatbot')
                                    <a href="{{ route('admin.chatbot.faqs') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="ph ph-robot mr-2" style="color: var(--complementary-color);"></i>Manage Chatbot
                                    </a>
                                    @endCanAccessModule
                                    @canAccessModule('complaints')
                                    <a href="{{ route('admin.complaints.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="ph ph-chat-circle-dots mr-2" style="color: var(--complementary-color);"></i>Complaints
                                        @hasReadOnly('complaints')
                                            <span class="text-xs text-gray-500">(View Only)</span>
                                        @endHasReadOnly
                                    </a>
                                    @endCanAccessModule
                                    @else
                                    @if(module_enabled('complaints'))
                                    <a href="{{ route('complaints.index') }}" data-student-navigation class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="ph ph-chat-circle-dots mr-2" style="color: var(--complementary-color);"></i>My Complaints
                                    </a>
                                    @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif
                        @php
                            $currentOrgId = session('current_organization_id');
                            $userPosition = $currentOrgId ? auth()->user()->getPositionInOrganization($currentOrgId) : null;
                            $canAccessSettings = in_array($userPosition, ['President', 'Organization President', 'Adviser', 'Organization Adviser', 'Senior Adviser', 'Junior Adviser']);
                        @endphp
                        @if($canAccessSettings)
                        @php
                            $topOrg = $currentOrgId ? auth()->user()->organizations()->find($currentOrgId) : auth()->user()->organization;
                            $topAccStatus = $topOrg ? (method_exists($topOrg, 'accreditationStatus') ? $topOrg->accreditationStatus() : 'none') : 'none';
                            $topCurrentPeriod = method_exists(\App\Models\Organization::class, 'currentAccreditationPeriod') ? \App\Models\Organization::currentAccreditationPeriod() : null;
                            $topIsRenewBlocked = false;
                            $topRenewBlockedTitle = 'Accreditation Still Active';
                            $topRenewBlockedMsg = 'The accreditation renewal period has not been set or opened yet by the OSAS. Please wait for an official announcement.';

                            if (! $topCurrentPeriod || ! $topOrg?->accreditation_started_at || ! $topOrg?->accreditation_expires_at) {
                                $topIsRenewBlocked = true;
                                $topRenewBlockedTitle = 'Renewal Period Not Set';
                                $topRenewBlockedMsg = 'The accreditation renewal period has not been set or opened yet by the OSAS. Please wait for an official announcement.';
                            } elseif (method_exists($topOrg, 'isExpired') && ! $topOrg->isExpired()) {
                                $topIsRenewBlocked = true;
                                $topRenewBlockedTitle = 'Accreditation Still Active';
                                $topRenewBlockedMsg = 'Your organization accreditation is still active until ' . ($topOrg->accreditation_expires_at?->format('M d, Y') ?? 'expiry') . '. Renewal is not yet open.';
                            }
                        @endphp
                        @if($topIsRenewBlocked)
                            <button type="button" 
                                    @click="window.dispatchEvent(new CustomEvent('open-renew-blocked-modal', { detail: { title: @js($topRenewBlockedTitle), message: @js($topRenewBlockedMsg) } }))" 
                                    class="nav-link inline-flex items-center px-4 pt-1 text-sm font-medium text-white {{ request()->routeIs('admin.renew.*') ? 'active' : '' }}">
                                <i class="ph ph-arrows-clockwise mr-1" style="color: {{ request()->routeIs('admin.renew.*') ? 'var(--primary-color)' : 'var(--complementary-color)' }};"></i><span class="tab-text">Renew Accreditation</span>
                            </button>
                        @else
                            <a href="{{ route('admin.renew.index') }}" class="nav-link inline-flex items-center px-4 pt-1 text-sm font-medium text-white {{ request()->routeIs('admin.renew.*') ? 'active' : '' }}">
                                <i class="ph ph-arrows-clockwise mr-1" style="color: {{ request()->routeIs('admin.renew.*') ? 'var(--primary-color)' : 'var(--complementary-color)' }};"></i><span class="tab-text">Renew Accreditation</span>
                            </a>
                        @endif
                        <a href="{{ route('admin.settings.index') }}" class="nav-link inline-flex items-center px-4 pt-1 text-sm font-medium text-white {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                            <i class="ph ph-gear mr-1" style="color: {{ request()->routeIs('admin.settings.*') ? 'var(--primary-color)' : 'var(--complementary-color)' }};"></i><span class="tab-text">Settings</span>
                        </a>
                        @endif
                        @else
                        <a href="{{ route('student.announcements.index') }}" data-student-navigation class="nav-link inline-flex items-center px-4 pt-1 text-sm font-medium {{ request()->routeIs('announcements.*') ? 'active' : '' }}">
                            <i class="ph ph-megaphone mr-1" style="color: {{ request()->routeIs('announcements.*') ? 'var(--primary-color)' : 'var(--complementary-color)' }};"></i><span class="tab-text">Announcements</span>
                        </a>
                        <a href="{{ route('events.index') }}" data-student-navigation class="nav-link inline-flex items-center px-4 pt-1 text-sm font-medium {{ request()->routeIs('events.*') ? 'active' : '' }}">
                            <i class="ph ph-calendar mr-1" style="color: {{ request()->routeIs('events.*') ? 'var(--primary-color)' : 'var(--complementary-color)' }};"></i><span class="tab-text">Events</span>
                        </a>
                        <a href="{{ route('payments.index') }}" data-student-navigation class="nav-link inline-flex items-center px-4 pt-1 text-sm font-medium {{ request()->routeIs('payments.*') ? 'active' : '' }}">
                            <i class="ph ph-credit-card mr-1" style="color: {{ request()->routeIs('payments.*') ? 'var(--primary-color)' : 'var(--complementary-color)' }};"></i><span class="tab-text">Payments</span>
                        </a>
@if(module_enabled('elections') && (!auth()->user()->isEffectivelyAdmin() || app(\App\Services\PermissionHelper::class)->canAccessModule('elections')))
                        <a href="{{ route('elections.index') }}" data-student-navigation class="nav-link inline-flex items-center px-4 pt-1 text-sm font-medium {{ request()->routeIs('elections.*') ? 'active' : '' }}">
                            <i class="ph ph-check-square mr-1" style="color: {{ request()->routeIs('elections.*') ? 'var(--primary-color)' : 'var(--complementary-color)' }};"></i><span class="tab-text">Elections</span>
                        </a>
                        @endif
                        @if(module_enabled('edushare'))
                        <a href="{{ route('edushare.index', ['tab' => 'all']) }}" data-student-navigation class="nav-link inline-flex items-center px-4 pt-1 text-sm font-medium {{ request()->routeIs('edushare.*') ? 'active' : '' }}">
                            <i class="ph ph-share-network mr-1" style="color: {{ request()->routeIs('edushare.*') ? 'var(--primary-color)' : 'var(--complementary-color)' }};"></i><span class="tab-text">EduShare</span>
                        </a>
                        @endif
                        <a href="{{ route('chatbot.index') }}" data-student-navigation class="nav-link inline-flex items-center px-4 pt-1 text-sm font-medium {{ request()->routeIs('chatbot.*') ? 'active' : '' }}">
                            <i class="ph ph-chats mr-1" style="color: {{ request()->routeIs('chatbot.*') ? 'var(--primary-color)' : 'var(--complementary-color)' }};"></i>
                            <span class="tab-text relative">
                                Chat
                                <span id="student-chat-badge" class="absolute -top-2 -right-3 px-1.5 py-0.5 text-xs font-bold text-white rounded-full min-w-[18px] text-center" style="display: none; background-color: var(--complementary-color); font-size: 10px; line-height: 1;">0</span>
                            </span>
                        </a>
                        @endif
                    </div>
                    <div class="tab-indicator"></div>
                </div>
                @endauth
            </div>

            <div class="flex items-center">
                @guest
                    <a href="{{ route('login') }}" class="px-3 py-2 rounded-md text-sm font-medium nav-link-hover" style="color: var(--primary-color);">Login</a>
                    <a href="{{ route('register') }}" class="ml-4 px-4 py-2 text-white text-sm font-medium rounded-lg" style="background-color: var(--primary-color);" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">Register</a>
                @else
                    @if(auth()->user()->isEffectivelyAdmin() && !auth()->user()->isSuperAdmin() && !auth()->user()->isSystemlessFallback())
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
                    <div class="ml-1 sm:ml-3 relative">
                        <div class="flex items-center space-x-2 sm:space-x-4">
                            <!-- Notification Bell -->
                            @if(auth()->user()->isEffectivelyStudent())
                            <div class="relative" x-data="{ notifOpen: false, toggleNotif() { if (this.notifOpen) { this.closeNotif(); } else { this.notifOpen = true; hideNotificationBadge(); } }, closeNotif() { if (!this.notifOpen) return; this.notifOpen = false; markAllReadOnClose(); } }">
                                @php
                                    $visibleNotifications = \App\Support\NotificationVisibility::forUser(
                                        auth()->user(),
                                        auth()->user()->notifications()->latest()->get()
                                    );
                                    $unreadCount = $visibleNotifications->whereNull('read_at')->count();
                                @endphp
                                <button data-student-notification-toggle data-notification-bell data-notification-unread-count="{{ $unreadCount }}" @click="toggleNotif()" class="relative p-2 text-gray-600 transition nav-link-hover">
                                    <i class="ph ph-bell relative top-px text-xl"></i>
                                    @if($unreadCount > 0)
                                        <span data-notification-badge class="absolute top-0.5 right-0 inline-flex items-center justify-center text-xs font-bold leading-none text-white rounded-full shadow-md" style="min-width: 18px; height: 18px; font-size: 10px; padding: 2px 4px; background: linear-gradient(to right, var(--complementary-color), var(--complementary-color));">
                                            {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                                        </span>
                                    @endif
                                </button>
                                
                                <!-- Notification Dropdown -->
                                 <div x-show="notifOpen" @click.away="closeNotif()" 
                                      class="student-nav-dropdown origin-top-right absolute right-0 sm:right-0 mt-2 w-72 sm:w-80 max-w-[calc(100vw-1rem)] rounded-lg shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"
                                      style="display: none;">
                                    <!-- Header (Fixed) -->
                                    <div class="p-4 border-b border-gray-200 flex items-center justify-between" data-notification-header>
                                        <h3 class="text-lg font-semibold text-gray-900">Notifications</h3>
                                        @if($unreadCount > 0)
                                            <button onclick="markAllAsRead(event)" data-mark-all-read-btn class="text-xs font-medium transition hover:opacity-80" style="color: var(--primary-color);">Mark all as read</button>
                                        @endif
                                    </div>
                                    
                                    <!-- Scrollable Notifications -->
                                    <div data-notification-list class="divide-y divide-gray-200 max-h-80 overflow-y-auto">
                                        @forelse($visibleNotifications->take(10) as $notification)
                                            @php
                                                $notifUrl = $notification->data['url'] ?? route('payments.index');
                                                $notifColor = $notification->data['color'] ?? 'gray';
                                            @endphp
                                            <div data-notification-item data-notification-unread="{{ $notification->read_at ? 'false' : 'true' }}" class="relative group {{ $notification->read_at ? 'bg-white' : 'notif-unread' }} hover:bg-gray-50 transition" id="notif-{{ $notification->id }}">
                                                <a href="{{ $notifUrl }}" 
                                                   onclick="markAsReadAndNavigate(event, '{{ $notification->id }}', '{{ $notifUrl }}')"
                                                   class="block p-4 pr-12">
                                                    <div class="flex items-start">
                                                        <div class="flex-shrink-0">
                                                            <i class="ph {{ $notification->data['icon'] ?? 'ph-info' }} text-2xl 
                                                               {{ $notifColor === 'green' ? 'text-green-500' : '' }}
                                                               {{ $notifColor === 'red' ? 'text-red-500' : '' }}
                                                               {{ $notifColor === 'blue' ? 'text-blue-500' : '' }}
                                                               {{ $notifColor === 'purple' ? 'text-purple-500' : '' }}"></i>
                                                        </div>
                                                        <div class="ml-3 flex-1">
                                                            <p class="text-sm font-semibold text-gray-900">{{ $notification->data['title'] ?? 'Notification' }}</p>
                                                            <p class="text-sm text-gray-600 mt-1">{{ $notification->data['message'] ?? '' }}</p>
                                                            <p class="text-xs text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                                                        </div>
                                                    </div>
                                                </a>
                                                <!-- Delete Button -->
                                                <button onclick="deleteNotification(event, '{{ $notification->id }}')" 
                                                        class="absolute top-2 right-2 p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-full transition opacity-0 group-hover:opacity-100 z-10"
                                                        style="pointer-events: auto;"
                                                        title="Delete notification">
                                                    <i class="ph ph-x text-sm pointer-events-none"></i>
                                                </button>
                                            </div>
                                        @empty
                                            <div class="p-8 text-center text-gray-500">
                                                <i class="ph ph-bell-slash text-4xl mb-2"></i>
                                                <p>No notifications</p>
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                            @endif
                            
                            @if(auth()->user()->isEffectivelyStudent())
                            {{-- Mobile hamburger button (students only) - placed right after the bell --}}
                            <button @click="mobileMenuOpen = !mobileMenuOpen" 
                                    type="button" 
                                    class="sm:hidden inline-flex items-center justify-center p-2 rounded-md hover:bg-gray-100 transition focus:outline-none"
                                    aria-label="Toggle navigation menu">
                                <i x-show="!mobileMenuOpen" class="ph ph-list text-xl" style="color: var(--primary-color);"></i>
                                <i x-show="mobileMenuOpen" x-cloak class="ph ph-x text-xl" style="color: var(--primary-color); display: none;"></i>
                            </button>
                            @endif
                            
                            @if(auth()->user()->isEffectivelyAdmin())
                            <div class="flex items-center">
                                <span class="nav-avatar-wrapper inline-flex items-center">
                                    @if(auth()->user()->photo)
                                        <img src="{{ storage_url(auth()->user()->photo) }}" alt="" class="h-8 w-8 rounded-full mr-2 nav-profile-img object-cover" onerror="this.remove();this.parentElement.querySelector('.nav-avatar-fallback').classList.remove('hidden')">
                                        <i class="ph ph-user-circle text-2xl mr-2 text-gray-700 nav-avatar-fallback hidden"></i>
                                    @else
                                        <i class="ph ph-user-circle text-2xl mr-2 text-gray-700 nav-avatar-fallback"></i>
                                    @endif
                                </span>
                                <span class="text-sm font-medium text-gray-700">{{ auth()->user()->short_name }}</span>
                            </div>
                            
                            <div class="ml-3 relative" x-data="{ showLogoutModal: false }">
                                <button @click="showLogoutModal = true" class="text-2xl transition-colors" style="color: var(--complementary-color);" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color='var(--complementary-color)'">
                                    <i class="ph ph-sign-out" style="color: var(--complementary-color);"></i>
                                </button>
                                
                                <!-- Admin Logout Confirmation Modal -->
                                <div x-show="showLogoutModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-data="{ isLoggingOut: false }">
                                    <div class="flex items-center justify-center min-h-screen px-4">
                                        <div @click="!isLoggingOut && (showLogoutModal = false)" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
                                        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                                            <div class="flex items-center mb-4">
                                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full" style="background-color: rgba(130, 56, 179, 0.1);">
                                                    <i class="ph ph-sign-out text-xl" style="color: var(--complementary-color);"></i>
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
                                                    class="px-5 py-2 text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-all duration-150 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                                    Cancel
                                                </button>
                        <form method="POST" action="{{ route('logout') }}" data-no-loading @submit="isLoggingOut = true; sessionStorage.removeItem('systemless_banner_dismissed')">
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
                            @else
                            <div class="hidden sm:inline-block relative text-left student-dropdown-nav-item {{ request()->routeIs('profile.*') || request()->routeIs('library.*') || request()->routeIs('complaints.*') || request()->routeIs('documents.*') ? 'student-dropdown-active' : '' }}" x-data="{ open: false, showLogoutModal: false }" @mouseenter="open = true; positionStudentDropdown($el)" @mouseleave="open = false">
                                <button @click="open = !open; if (open) positionStudentDropdown($el.closest('[x-data]'))" data-student-dropdown-parent class="nav-link flex items-center text-sm font-medium {{ request()->routeIs('profile.*') || request()->routeIs('library.*') || request()->routeIs('complaints.*') || request()->routeIs('documents.*') ? 'active student-navigation-active' : 'text-gray-700 student-dropdown-inactive' }}">
                                    <span class="nav-avatar-wrapper inline-flex items-center">
                                        @if(auth()->user()->photo)
                                        <img src="{{ storage_url(auth()->user()->photo) }}" alt="" class="h-8 w-8 rounded-full mr-2 nav-profile-img object-cover" onerror="this.remove();this.parentElement.querySelector('.nav-avatar-fallback').classList.remove('hidden')">
                                        <i class="ph ph-user-circle text-2xl mr-2 nav-avatar-fallback hidden"></i>
                                    @else
                                        <i class="ph ph-user-circle text-2xl mr-2 nav-avatar-fallback"></i>
                                    @endif
                                </span>
                                <span class="tab-text">{{ auth()->user()->short_name }}</span>
                                    <i class="ph ph-caret-down ml-2"></i>
                                </button>
                                
                                <!-- Invisible bridge to prevent dropdown from closing -->
                                <div x-show="open" class="student-dropdown-bridge absolute right-0 w-52 h-2 top-full" style="display: none;"></div>
                                
                                <div x-show="open" x-cloak @click.away="open = false" class="student-dropdown-menu origin-top-right absolute right-0 w-52 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50" style="display: none;">
                                    <div class="py-1">
                                        <a href="{{ route('profile.show') }}" data-student-navigation class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="ph ph-user mr-2" style="color: var(--complementary-color);"></i>Profile
                                        </a>
                                        @if(module_enabled('elibrary'))
                                        <a href="{{ route('library.index') }}" data-student-navigation class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="ph ph-books mr-2" style="color: var(--complementary-color);"></i>e-Library
                                        </a>
                                        @endif
                                        @if(module_enabled('complaints'))
                                        <a href="{{ route('complaints.index') }}" data-student-navigation class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="ph ph-chat-circle-dots mr-2" style="color: var(--complementary-color);"></i>My Complaints
                                        </a>
                                        @endif
                                        @if(module_enabled('documents'))
                                        <a href="{{ route('documents.my-documents') }}" data-student-navigation class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="ph ph-files mr-2" style="color: var(--complementary-color);"></i>Manage Documents
                                        </a>
                                        @endif
                                        <hr class="my-1">
                                        @if(auth()->user()->canSwitchView())
                                            @php
                                                $isSwitchingToAdmin = auth()->user()->getActiveView() === 'student';
                                                $switchOrgId = session('current_organization_id') ?? auth()->user()->organizations()->first()?->id;
                                                $switchHasModules = $switchOrgId ? app(\App\Services\OrganizationPermissionService::class)->hasAnyModuleAccess(auth()->id(), (int) $switchOrgId) : true;
                                            @endphp
                                            @if($isSwitchingToAdmin && !$switchHasModules)
                                                {{-- zero-module admins stay in Student View — switch hidden --}}
                                            @else
                                                <form method="POST" action="{{ route('view.switch') }}">
                                                    @csrf
                                                    <button type="submit" class="block w-full whitespace-nowrap text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        <i class="ph ph-swap mr-2 switch-view-icon" style="color: var(--complementary-color);"></i>{{ auth()->user()->getActiveView() === 'student' ? 'Switch to Admin View' : 'Switch to Student View' }}
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                        <button type="button" @click="showLogoutModal = true" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="ph ph-sign-out mr-2" style="color: var(--complementary-color);"></i>Logout
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Logout Confirmation Modal -->
                                <div x-show="showLogoutModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-data="{ isLoggingOut: false }">
                                    <div class="flex items-center justify-center min-h-screen px-4">
                                         <div @click="!isLoggingOut && (showLogoutModal = false)" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
                                         <div class="student-logout-modal relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                                            <div class="flex items-center mb-4">
                                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full" style="background-color: rgba(130, 56, 179, 0.1);">
                                                    <i class="ph ph-sign-out text-xl" style="color: var(--complementary-color);"></i>
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
                                                    class="px-5 py-2 text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-all duration-150 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                                    Cancel
                                                </button>
                                                 <form method="POST" action="{{ route('logout') }}" id="logoutForm" data-no-loading @submit="isLoggingOut = true; sessionStorage.removeItem('systemless_banner_dismissed')">
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
                            @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endguest
            </div>
        </div>
    </div>

    @auth
    @if(auth()->user()->isEffectivelyStudent())
    {{-- Mobile drawer menu (students only) --}}
    <div x-show="mobileMenuOpen" 
         x-cloak 
         x-transition:enter="transition-opacity ease-linear duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="mobileMenuOpen = false"
         class="sm:hidden fixed inset-0 bg-black bg-opacity-50 modal-backdrop z-40"
         style="display: none; top: 4rem;"></div>
    
    <div x-show="mobileMenuOpen" 
         x-cloak 
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         @click="if ($event.target.closest('a[href]')) mobileMenuOpen = false"
         class="student-mobile-drawer sm:hidden fixed right-0 top-16 bottom-0 w-72 max-w-[85vw] bg-white shadow-2xl z-50 overflow-y-auto border-l border-gray-200"
         style="display: none;">
        
        {{-- User profile header --}}
        <div class="p-4 border-b border-gray-100" style="background: linear-gradient(135deg, rgba(var(--primary-color-rgb, 130, 56, 179), 0.08), rgba(var(--complementary-color-rgb, 218, 165, 32), 0.08));">
            <div class="flex items-center">
                <span class="nav-avatar-wrapper inline-flex items-center">
                    @if(auth()->user()->photo)
                        <img src="{{ storage_url(auth()->user()->photo) }}" alt="" class="h-12 w-12 rounded-full nav-profile-img object-cover flex-shrink-0" onerror="this.remove();this.parentElement.querySelector('.nav-avatar-fallback').classList.remove('hidden')">
                        <i class="ph ph-user-circle text-4xl flex-shrink-0 nav-avatar-fallback hidden" style="color: var(--primary-color);"></i>
                    @else
                        <i class="ph ph-user-circle text-4xl flex-shrink-0 nav-avatar-fallback" style="color: var(--primary-color);"></i>
                    @endif
                </span>
                <div class="ml-3 flex-1 min-w-0">
                    <p class="font-semibold text-gray-900 truncate">{{ auth()->user()->short_name }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ auth()->user()->student_id ?? auth()->user()->email }}</p>
                </div>
            </div>
        </div>

        {{-- Main navigation links --}}
        <nav class="py-2">
            <a href="{{ route('dashboard') }}" @if(auth()->user()->isEffectivelyStudent()) data-student-navigation @endif class="flex items-center px-4 py-3 text-sm font-medium hover:bg-gray-50 transition">
                <i class="ph ph-house w-6 mr-3 text-base"></i>
                Homepage
            </a>
            <a href="{{ route('student.announcements.index') }}" data-student-navigation class="flex items-center px-4 py-3 text-sm font-medium hover:bg-gray-50 transition">
                <i class="ph ph-megaphone w-6 mr-3 text-base"></i>
                Announcements
            </a>
            <a href="{{ route('events.index') }}" data-student-navigation class="flex items-center px-4 py-3 text-sm font-medium hover:bg-gray-50 transition">
                <i class="ph ph-calendar w-6 mr-3 text-base"></i>
                Events
            </a>
            <a href="{{ route('payments.index') }}" data-student-navigation class="flex items-center px-4 py-3 text-sm font-medium hover:bg-gray-50 transition">
                <i class="ph ph-credit-card w-6 mr-3 text-base"></i>
                Payments
            </a>
            @if(module_enabled('elections') && (!auth()->user()->isEffectivelyAdmin() || app(\App\Services\PermissionHelper::class)->canAccessModule('elections')))
            <a href="{{ route('elections.index') }}" data-student-navigation class="flex items-center px-4 py-3 text-sm font-medium hover:bg-gray-50 transition">
                <i class="ph ph-check-square w-6 mr-3 text-base"></i>
                Elections
            </a>
            @endif
            @if(module_enabled('edushare'))
            <a href="{{ route('edushare.index', ['tab' => 'all']) }}" data-student-navigation class="flex items-center px-4 py-3 text-sm font-medium hover:bg-gray-50 transition">
                <i class="ph ph-share-network w-6 mr-3 text-base"></i>
                EduShare
            </a>
            @endif
            <a href="{{ route('chatbot.index') }}" data-student-navigation class="flex items-center px-4 py-3 text-sm font-medium hover:bg-gray-50 transition">
                <i class="ph ph-chats w-6 mr-3 text-base"></i>
                Chat
                <span id="student-chat-badge-mobile" class="ml-auto px-2 py-0.5 text-xs font-bold text-white rounded-full" style="display: none; background-color: var(--complementary-color);">0</span>
            </a>
            
            <div class="my-2 border-t border-gray-100"></div>
            
            <a href="{{ route('profile.show') }}" data-student-navigation class="flex items-center px-4 py-3 text-sm font-medium hover:bg-gray-50 transition">
                <i class="ph ph-user w-6 mr-3 text-base"></i>
                Profile
            </a>
            @if(module_enabled('elibrary'))
            <a href="{{ route('library.index') }}" data-student-navigation class="flex items-center px-4 py-3 text-sm font-medium hover:bg-gray-50 transition">
                <i class="ph ph-books w-6 mr-3 text-base"></i>
                e-Library
            </a>
            @endif
            @if(module_enabled('complaints'))
            <a href="{{ route('complaints.index') }}" data-student-navigation class="flex items-center px-4 py-3 text-sm font-medium hover:bg-gray-50 transition">
                <i class="ph ph-chat-circle-dots w-6 mr-3 text-base"></i>
                My Complaints
            </a>
            @endif
            @if(module_enabled('documents'))
            <a href="{{ route('documents.my-documents') }}" data-student-navigation class="flex items-center px-4 py-3 text-sm font-medium hover:bg-gray-50 transition">
                <i class="ph ph-files w-6 mr-3 text-base"></i>
                Manage Documents
            </a>
            @endif
            
            <div class="my-2 border-t border-gray-100"></div>
            
            <button type="button" 
                    @click="$dispatch('open-mobile-logout')"
                    class="w-full flex items-center px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                <i class="ph ph-sign-out w-6 mr-3 text-base" style="color: var(--complementary-color);"></i>
                Logout
            </button>
        </nav>
    </div>

    {{-- Mobile logout confirmation modal --}}
    <div x-data="{ showMobileLogout: false, isLoggingOut: false }" 
         @open-mobile-logout.window="showMobileLogout = true; mobileMenuOpen = false">
        <div x-show="showMobileLogout" x-cloak class="fixed inset-0 z-[60] overflow-y-auto" style="display: none;">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div @click="!isLoggingOut && (showMobileLogout = false)" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                    <div class="flex items-center mb-4">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full" style="background-color: rgba(130, 56, 179, 0.1);">
                            <i class="ph ph-sign-out text-xl" style="color: var(--complementary-color);"></i>
                        </div>
                    </div>
                    <div class="text-center">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-2">Are you sure you want to logout?</h3>
                        <p class="text-sm text-gray-500 mb-6">You will be logged out of your account.</p>
                    </div>
                    <div class="flex justify-center space-x-3">
                        <button @click="showMobileLogout = false" :disabled="isLoggingOut" 
                                class="px-5 py-2 text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition disabled:opacity-50">
                            Cancel
                        </button>
                        <form method="POST" action="{{ route('logout') }}" data-no-loading @submit="isLoggingOut = true; sessionStorage.removeItem('systemless_banner_dismissed')">
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
    @endif
    @endauth
</nav>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<script>
    // Tab indicator animation
    function initializeTabIndicator() {
        const navTabs = document.getElementById('mainNavTabs');
        if (!navTabs) return;
        
        const indicator = navTabs.querySelector('.tab-indicator');
        const navLinks = navTabs.querySelectorAll('.nav-link');
        
        // Get user dropdown button
        const userDropdownButton = document.querySelector('.relative.inline-block.text-left button.nav-link');
        
        // Find active tab (could be in navTabs or the user dropdown button)
        let activeTab = navTabs.querySelector('.nav-link.active');
        if (!activeTab && userDropdownButton && userDropdownButton.classList.contains('active')) {
            activeTab = userDropdownButton;
        }
        
        function getTabPosition(tab) {
            if (!tab) return null;
            
            const textSpan = tab.querySelector('.tab-text') || tab.querySelector('i');
            if (!textSpan) return null;
            
            const textRect = textSpan.getBoundingClientRect();
            const containerRect = navTabs.getBoundingClientRect();
            
            return {
                left: textRect.left - containerRect.left,
                width: textRect.width
            };
        }
        
        function updateIndicator(tab) {
            const position = getTabPosition(tab);
            if (!position || !indicator) return;
            
            indicator.style.left = position.left + 'px';
            indicator.style.width = position.width + 'px';
        }
        
        // Get previous tab from sessionStorage
        const previousTabIndex = sessionStorage.getItem('mainActiveTabIndex');
        const currentTabIndex = Array.from(navLinks).indexOf(activeTab);
        
        // If there was a previous tab, start from there
        if (previousTabIndex !== null && previousTabIndex !== currentTabIndex.toString()) {
            const previousTab = navLinks[parseInt(previousTabIndex)];
            if (previousTab) {
                // Start at previous position (no transition)
                indicator.style.transition = 'none';
                updateIndicator(previousTab);
                
                // Force reflow
                indicator.offsetHeight;
                
                // Re-enable transition and move to current tab
                indicator.style.transition = 'all 0.15s cubic-bezier(0.4, 0, 0.2, 1)';
                setTimeout(() => {
                    updateIndicator(activeTab);
                }, 20);
            } else {
                updateIndicator(activeTab);
            }
        } else {
            // No previous tab, just show current position
            updateIndicator(activeTab);
        }
        
        // Track if user clicked a tab
        let clickedTab = null;
        let hoverTimeout = null;
        let isOverNav = false;
        let isOverUserDropdown = false;
        
        // Add hover effect to navigation container
        navTabs.addEventListener('mouseenter', function() {
            isOverNav = true;
            // Clear any pending timeout when entering nav area
            if (hoverTimeout) {
                clearTimeout(hoverTimeout);
                hoverTimeout = null;
            }
        });
        
        navTabs.addEventListener('mouseleave', function() {
            isOverNav = false;
            // Only return to active tab when leaving the entire navigation
            hoverTimeout = setTimeout(() => {
                if (!isOverNav && !isOverUserDropdown) {
                    if (activeTab) {
                        updateIndicator(activeTab);
                    }
                }
            }, 50);
        });
        
        // Add hover effect - underline follows hovered tab
        navLinks.forEach((link, index) => {
            // On hover, move indicator to hovered tab
            link.addEventListener('mouseenter', function() {
                updateIndicator(link);
            });
            
            // On click, keep underline on clicked tab
            link.addEventListener('click', function(e) {
                clickedTab = link;
                updateIndicator(link);
                sessionStorage.setItem('mainActiveTabIndex', index);
            });
        });
        
        // Handle user dropdown button
        if (userDropdownButton) {
            userDropdownButton.addEventListener('mouseenter', function() {
                isOverUserDropdown = true;
                if (hoverTimeout) {
                    clearTimeout(hoverTimeout);
                    hoverTimeout = null;
                }
                updateIndicator(userDropdownButton);
            });
            
            userDropdownButton.addEventListener('mouseleave', function() {
                isOverUserDropdown = false;
                hoverTimeout = setTimeout(() => {
                    if (!isOverNav && !isOverUserDropdown) {
                        if (activeTab) {
                            updateIndicator(activeTab);
                        }
                    }
                }, 50);
            });
            
            userDropdownButton.addEventListener('click', function() {
                // Don't set clickedTab, just update indicator
                updateIndicator(userDropdownButton);
            });
        }
        
        // Update on window resize
        window.addEventListener('resize', function() {
            if (activeTab) {
                updateIndicator(activeTab);
            }
        });
        
        // Logo image caching to prevent reload
        const logoContainers = document.querySelectorAll('.logo-container');
        logoContainers.forEach(container => {
            const logoSrc = container.dataset.logoSrc;
            if (logoSrc) {
                // Set background image immediately
                container.style.backgroundImage = `url('${logoSrc}')`;
                
                // Preload and cache the image multiple times
                for(let i = 0; i < 3; i++) {
                    setTimeout(() => {
                        const img = new Image();
                        img.crossOrigin = 'anonymous';
                        img.onload = function() {
                            // Image is cached, ensure it's displayed
                            container.style.backgroundImage = `url('${logoSrc}')`;
                        };
                        img.src = logoSrc;
                    }, i * 10);
                }
                
                // Store in localStorage for persistence
                try {
                    localStorage.setItem('logoImage', logoSrc);
                } catch(e) {
                    // Ignore localStorage errors
                }
            }
        });
        
        // Profile images are now rendered as <img> tags directly (no JS needed)
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        initializeTabIndicator();
        
        // Re-run after a short delay for any dynamic elements
        setTimeout(initializeTabIndicator, 50);
    });

    // Chat Badge functionality is now handled by student-chat-badge.js

    // Mark notification as read and navigate
    function markAsReadAndNavigate(event, notificationId, url) {
        console.log('=== MARK AS READ AND NAVIGATE ===');
        console.log('Notification ID:', notificationId);
        console.log('URL:', url);
        
        event.preventDefault();
        
        const notifDiv = document.getElementById('notif-' + notificationId);
        console.log('Notification div:', notifDiv);
        
        // Immediately change background to white (mark as read visually)
        if (notifDiv) {
            notifDiv.classList.remove('notif-unread');
            notifDiv.classList.add('bg-white');
            notifDiv.dataset.notificationUnread = 'false';
            console.log('Background changed to white');
        }
        
        // Mark all visible notifications as read automatically
        fetch('/notifications/mark-all-read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => {
            console.log('Mark as read response:', response);
            return response.json();
        })
        .then(data => {
            console.log('Mark as read data:', data);
            // Navigate to URL
            console.log('Navigating to:', url);
            window.location.href = url;
        })
        .catch(error => {
            console.error('Error marking as read:', error);
            // Navigate anyway
            console.log('Navigating anyway to:', url);
            window.location.href = url;
        });
    }
    
    // Mark notification as read (for backward compatibility)
    function markAsRead(notificationId) {
        fetch(`/notifications/${notificationId}/read`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
    }

    function hideNotificationBadge() {
        const bell = document.querySelector('[data-notification-bell]');
        if (!bell) return;

        bell.dataset.notificationPanelOpen = 'true';
        bell.dataset.notificationUnreadCount = '0';
        bell.querySelector('[data-notification-badge]')?.remove();
    }

    function markAllReadOnClose() {
        const bell = document.querySelector('[data-notification-bell]');
        if (!bell) return;

        bell.dataset.notificationPanelOpen = 'false';
        bell.dataset.notificationUnreadCount = '0';

        fetch('/notifications/mark-all-read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) return;
            document.querySelectorAll('[data-notification-item][data-notification-unread="true"]').forEach(item => {
                item.classList.remove('notif-unread');
                item.classList.add('bg-white');
                item.dataset.notificationUnread = 'false';
            });
        })
        .catch(error => console.error('Error marking all notifications as read:', error));
    }

    function markAllAsRead(event) {
        event.preventDefault();

        document.querySelectorAll('[data-notification-item][data-notification-unread="true"]').forEach(item => {
            item.classList.remove('notif-unread');
            item.classList.add('bg-white');
            item.dataset.notificationUnread = 'false';
        });

        const bell = document.querySelector('[data-notification-bell]');
        if (bell) {
            bell.dataset.notificationUnreadCount = '0';
            bell.querySelector('[data-notification-badge]')?.remove();
        }

        document.querySelector('[data-mark-all-read-btn]')?.remove();

        fetch('/notifications/mark-all-read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .catch(error => console.error('Error marking all notifications as read:', error));
    }

    function updateNotificationBadge(wasUnread) {
        const bell = document.querySelector('[data-notification-bell]');
        if (!bell) return;

        let unreadCount = Number(bell.dataset.notificationUnreadCount || 0);
        if (wasUnread) unreadCount = Math.max(0, unreadCount - 1);
        bell.dataset.notificationUnreadCount = String(unreadCount);

        if (unreadCount <= 0 || bell.dataset.notificationPanelOpen === 'true') {
            bell.querySelector('[data-notification-badge]')?.remove();
            return;
        }

        let badge = bell.querySelector('[data-notification-badge]');
        if (!badge) {
            badge = document.createElement('span');
            badge.dataset.notificationBadge = '';
            badge.className = 'absolute top-0.5 right-0 inline-flex items-center justify-center text-xs font-bold leading-none text-white rounded-full shadow-md';
            badge.style.cssText = 'min-width: 18px; height: 18px; font-size: 10px; padding: 2px 4px; background: linear-gradient(to right, var(--complementary-color), var(--complementary-color));';
            bell.appendChild(badge);
        }

        badge.textContent = unreadCount > 9 ? '9+' : String(unreadCount);
    }

    function updateNotificationDropdownState() {
        const list = document.querySelector('[data-notification-list]');
        if (!list || list.querySelector('[data-notification-item]')) return;

        list.innerHTML = '<div class="p-8 text-center text-gray-500"><i class="ph ph-bell-slash text-4xl mb-2"></i><p>No notifications</p></div>';
    }

    // Delete notification without navigating away from the current page.
    function deleteNotification(event, notificationId) {
        console.log('=== DELETE NOTIFICATION CALLED ===');
        console.log('Event:', event);
        console.log('Notification ID:', notificationId);
        
        event.preventDefault();
        event.stopPropagation();
        
        const button = event.currentTarget;
        console.log('Button:', button);
        
        const notificationDiv = button.closest('[data-notification-item]');
        console.log('Notification Div:', notificationDiv);
        
        if (!notificationDiv) {
            console.error('Could not find notification div');
            alert('Error: Could not find notification div');
            return;
        }
        
        // Immediately remove from DOM with fade effect
        notificationDiv.style.opacity = '0';
        notificationDiv.style.transition = 'opacity 0.3s';
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        console.log('CSRF Token element:', csrfToken);
        console.log('CSRF Token value:', csrfToken ? csrfToken.content : 'NOT FOUND');
        
        // Delete from database
        const url = `/notifications/${notificationId}/delete`;
        console.log('Fetch URL:', url);
        
        fetch(url, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken ? csrfToken.content : ''
            }
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response:', response);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                console.log('Delete successful, removing notification in place...');
                setTimeout(() => {
                    const wasUnread = notificationDiv.dataset.notificationUnread === 'true';
                    notificationDiv.remove();
                    updateNotificationBadge(wasUnread);
                    updateNotificationDropdownState();
                }, 300);
            } else {
                console.error('Delete failed:', data);
                notificationDiv.style.opacity = '1';
                alert('Failed to delete notification');
            }
        })
        .catch(error => {
            console.error('=== ERROR DELETING ===');
            console.error('Error:', error);
            // Restore if failed
            notificationDiv.style.opacity = '1';
            alert('Error deleting notification: ' + error.message);
        });
    }

    // ── Realtime notification polling for the student bell ──────────────────
    // The bell dropdown is rendered server-side, so new notifications (e.g.
    // position assignments, complaint responses) never appeared on an already
    // open page. Poll the notifications feed and refresh the badge + dropdown.
    function studentNotificationTimeAgo(dateStr) {
        const diff = Date.now() - new Date(dateStr).getTime();
        const mins = Math.floor(diff / 60000);
        if (mins < 1) return 'Just now';
        if (mins < 60) return mins + 'm ago';
        const hours = Math.floor(mins / 60);
        if (hours < 24) return hours + 'h ago';
        const days = Math.floor(hours / 24);
        if (days < 7) return days + 'd ago';
        return new Date(dateStr).toLocaleDateString();
    }

    function studentNotificationItemHtml(notif) {
        const data = notif.data || {};
        const url = String(data.url || '/dashboard');
        const icon = data.icon || 'ph-info';
        const colorClass = data.color === 'green' ? 'text-green-500'
            : data.color === 'red' ? 'text-red-500'
            : data.color === 'blue' ? 'text-blue-500'
            : data.color === 'purple' ? 'text-purple-500' : '';
        const title = String(data.title || 'Notification');
        const message = String(data.message || '');
        const unread = notif.read_at === null ? 'true' : 'false';
        return '<div data-notification-item data-notification-unread="' + unread + '" class="relative group ' + (unread === 'true' ? 'notif-unread' : 'bg-white') + ' hover:bg-gray-50 transition" id="notif-' + notif.id + '">'
            + '<a href="' + url + '" onclick="markAsReadAndNavigate(event, \'' + notif.id + '\', \'' + url + '\')" class="block p-4 pr-12">'
            + '<div class="flex items-start"><div class="flex-shrink-0"><i class="ph ' + icon + ' text-2xl ' + colorClass + '"></i></div>'
            + '<div class="ml-3 flex-1"><p class="text-sm font-semibold text-gray-900">' + title + '</p>'
            + '<p class="text-sm text-gray-600 mt-1">' + message + '</p>'
            + '<p class="text-xs text-gray-400 mt-1">' + studentNotificationTimeAgo(notif.created_at) + '</p></div></div></a>'
            + '<button onclick="deleteNotification(event, \'' + notif.id + '\')" class="absolute top-2 right-2 p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-full transition opacity-0 group-hover:opacity-100 z-10" style="pointer-events: auto;" title="Delete notification"><i class="ph ph-x text-sm pointer-events-none"></i></button>'
            + '</div>';
    }

    function refreshStudentNotifications() {
        const bell = document.querySelector('[data-notification-bell]');
        if (!bell) return;

        fetch('/notifications')
            .then(r => r.json())
            .then(data => {
                const items = data.notifications || [];
                const unread = Number(data.unread_count || 0);
                const current = Number(bell.dataset.notificationUnreadCount || 0);

                // Update the badge when the server-side unread count changed.
                if (unread !== current) {
                    bell.dataset.notificationUnreadCount = String(unread);
                    if (unread > 0 && bell.dataset.notificationPanelOpen !== 'true') {
                        let badge = bell.querySelector('[data-notification-badge]');
                        if (!badge) {
                            badge = document.createElement('span');
                            badge.dataset.notificationBadge = '';
                            badge.className = 'absolute top-0.5 right-0 inline-flex items-center justify-center text-xs font-bold leading-none text-white rounded-full shadow-md';
                            badge.style.cssText = 'min-width: 18px; height: 18px; font-size: 10px; padding: 2px 4px; background: linear-gradient(to right, var(--complementary-color), var(--complementary-color));';
                            bell.appendChild(badge);
                        }
                        badge.textContent = unread > 9 ? '9+' : String(unread);
                    } else {
                        bell.querySelector('[data-notification-badge]')?.remove();
                    }
                }

                // Refresh the open dropdown so new notifications appear live.
                if (bell.dataset.notificationPanelOpen === 'true') {
                    const list = document.querySelector('[data-notification-list]');
                    if (list) {
                        list.innerHTML = items.length === 0
                            ? '<div class="p-8 text-center text-gray-500"><i class="ph ph-bell-slash text-4xl mb-2"></i><p>No notifications</p></div>'
                            : items.map(studentNotificationItemHtml).join('');
                    }
                    const header = document.querySelector('[data-notification-header]');
                    const markAllBtn = document.querySelector('[data-mark-all-read-btn]');
                    if (header) {
                        if (unread > 0 && !markAllBtn) {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.onclick = markAllAsRead;
                            btn.setAttribute('data-mark-all-read-btn', '');
                            btn.className = 'text-xs font-medium transition hover:opacity-80';
                            btn.style.color = 'var(--primary-color)';
                            btn.textContent = 'Mark all as read';
                            header.appendChild(btn);
                        } else if (unread <= 0 && markAllBtn) {
                            markAllBtn.remove();
                        }
                    }
                }
            })
            .catch(() => {});
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (document.querySelector('[data-notification-bell]')) {
            setInterval(refreshStudentNotifications, 15000);
        }
    });
</script>

<style>
.modal-backdrop {
    -webkit-backdrop-filter: blur(4px);
    backdrop-filter: blur(4px);
}

.notif-unread {
    background-color: #fffbeb;
    background-color: color-mix(in srgb, var(--complementary-color) 10%, white);
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.animate-slide-in {
    animation: slideIn 0.3s ease-out;
}

/* Tab indicator styles */
.nav-tabs-container {
    position: relative;
}

.tab-indicator {
    position: absolute;
    bottom: 0;
    height: 2px;
    background-color: var(--primary-color);
    transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
    pointer-events: none;
}

/* Active tab styling - admin navigation (purple background) */
nav:not(.bg-white) .nav-link.active {
    color: white !important;
}

/* Active tab styling - student navigation (white background) */
nav.bg-white .nav-link.active {
    color: var(--primary-color) !important;
}

/* Inactive tab styling - student navigation (default gray color) */
nav.bg-white .nav-link:not(.active) {
    color: #6b7280 !important; /* gray-500 */
}

/* Icon colors for active tabs in student navigation */
nav.bg-white .nav-link.active i.ph {
    color: var(--primary-color) !important;
}

/* Icon colors for inactive tabs in student navigation */
nav.bg-white .nav-link:not(.active) i.ph {
    color: var(--complementary-color) !important;
}

/* Hover effect for navigation links using CSS variables */
.nav-link-hover:hover {
    color: var(--primary-color) !important;
}

/* CRITICAL: Hover effect for student navigation links - HIGHEST PRIORITY */
nav.bg-white .nav-link:not(.active):hover,
nav.bg-white .nav-link:not(.active):hover span,
nav.bg-white .nav-link:not(.active):hover .tab-text {
    color: var(--primary-color) !important;
}

/* Hover effect for student navigation links - icon color */
nav.bg-white .nav-link:not(.active):hover i.ph,
nav.bg-white .nav-link:not(.active):hover i {
    color: var(--primary-color) !important;
}

/* Ensure all child elements change color on hover */
nav.bg-white .nav-link:not(.active):hover * {
    color: var(--primary-color) !important;
}

/* Hover effect for navigation links (both admin and student) */
nav .nav-link:not(.active):hover {
    color: var(--primary-color) !important;
}

/* Hover effect for admin logout button */
nav button:hover i.ph {
    color: var(--primary-color) !important;
}

/* Keep switch-view-icon complementary even on hover */
nav button:hover i.ph.switch-view-icon {
    color: var(--complementary-color) !important;
}

/* Override: keep logout modal confirm icon white on hover */
nav #logoutForm button[type="submit"]:hover i.ph {
    color: white !important;
}

/* Hover effect for guest navigation links */
nav a.nav-link-hover:hover {
    opacity: 0.8;
}

.nav-link.active .gold-icon {
    color: var(--complementary-color) !important;
}

.ph-spin {
    display: inline-block;
    animation: ph-spin 1s linear infinite;
}
@keyframes ph-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
