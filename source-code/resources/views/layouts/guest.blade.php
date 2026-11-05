<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', organization_name())</title>
    <link rel="icon" type="image/png" href="{{ organization_logo() }}">
    <!-- Preconnect to CDNs for faster loading -->
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        .btn-primary {
            background-color: #8238B3;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #3D0066;
        }
        /* Tab underline animation */
        .nav-tabs-container {
            position: relative;
        }
        .nav-link {
            transition: color 0.3s ease;
        }
        .tab-text {
            display: inline-block;
        }
        .tab-indicator {
            position: absolute;
            bottom: 0;
            height: 2px;
            background-color: #8238B3;
            transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            margin-left: 0 !important;
            pointer-events: none;
        }
        .gold-icon {
            color: #D7AC28;
        }
    </style>
    @stack('styles')
</head>
<body class="bg-gray-50">
    <!-- Guest Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="{{ route('home') }}" class="flex items-center">
                            <div class="h-10 w-10 mr-2 nav-logo logo-container" data-logo-src="{{ organization_logo() }}" style="background-image: url('{{ organization_logo() }}'); background-size: contain; background-repeat: no-repeat; background-position: center;"></div>
                            <span class="text-2xl font-bold" style="color: #8238B3;">{{ organization_name() }}</span>
                        </a>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:gap-8 nav-tabs-container" id="guestNavTabs">
                        <a href="{{ route('guest.dashboard') }}" class="nav-link inline-flex items-center px-1 pt-1 text-sm font-medium {{ request()->routeIs('guest.dashboard') ? 'active' : 'text-gray-500 hover:text-purple-600' }}" style="{{ request()->routeIs('guest.dashboard') ? 'color: #8238B3;' : '' }}" data-tab="home">
                            <i class="ph ph-house mr-1 gold-icon"></i><span class="tab-text">Home</span>
                        </a>
                        <a href="{{ route('guest.announcements.index') }}" class="nav-link inline-flex items-center px-1 pt-1 text-sm font-medium {{ request()->routeIs('guest.announcements.*') ? 'active' : 'text-gray-500 hover:text-purple-600' }}" style="{{ request()->routeIs('guest.announcements.*') ? 'color: #8238B3;' : '' }}" data-tab="announcements">
                            <i class="ph ph-megaphone mr-1 gold-icon"></i><span class="tab-text">Announcements</span>
                        </a>
                        <a href="{{ route('guest.events.index') }}" class="nav-link inline-flex items-center px-1 pt-1 text-sm font-medium {{ request()->routeIs('guest.events.*') ? 'active' : 'text-gray-500 hover:text-purple-600' }}" style="{{ request()->routeIs('guest.events.*') ? 'color: #8238B3;' : '' }}" data-tab="events">
                            <i class="ph ph-calendar-blank mr-1 gold-icon"></i><span class="tab-text">Events</span>
                        </a>
                        <div class="tab-indicator"></div>
                    </div>
                </div>

                <div class="flex items-center">
                    <a href="{{ route('login') }}" class="px-4 py-2 btn-primary text-white text-sm font-medium">
                        Login / Register
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Guest Notice Banner -->
    <div class="border-b" style="background-color: #EFD9F7; border-color: #8238B3;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="ph ph-info mr-2 gold-icon"></i>
                    <span class="text-sm" style="color: #3D0066;">You are viewing as a guest. <a href="{{ route('login') }}" class="font-semibold underline">Login / Register</a> now to access payments, elections, e-library, and more!</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="py-8">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-16">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-gray-500">
                &copy; {{ date('Y') }} {{ organization_name() }} - College of Computer Studies, LSPU. All rights reserved.
            </p>
        </div>
    </footer>
    
    <script>
        // Tab indicator animation
        document.addEventListener('DOMContentLoaded', function() {
            const navTabs = document.getElementById('guestNavTabs');
            if (!navTabs) return;
            
            const indicator = navTabs.querySelector('.tab-indicator');
            const activeTab = navTabs.querySelector('.nav-link.active');
            const navLinks = navTabs.querySelectorAll('.nav-link');
            
            function getTabPosition(tab) {
                if (!tab) return null;
                
                const textSpan = tab.querySelector('.tab-text');
                if (!textSpan) return null;
                
                const textRect = textSpan.getBoundingClientRect();
                const containerRect = navTabs.getBoundingClientRect();
                
                return {
                    left: textRect.left - containerRect.left,
                    width: textRect.width
                };
            }
            
            function updateIndicator(tab, animate = true) {
                const position = getTabPosition(tab);
                if (!position || !indicator) return;
                
                if (animate) {
                    indicator.style.transition = 'all 0.15s cubic-bezier(0.4, 0, 0.2, 1)';
                } else {
                    indicator.style.transition = 'none';
                }
                
                indicator.style.left = position.left + 'px';
                indicator.style.width = position.width + 'px';
            }
            
            // Get previous tab from sessionStorage
            const previousTabIndex = sessionStorage.getItem('guestActiveTabIndex');
            const currentTabIndex = Array.from(navLinks).indexOf(activeTab);
            
            // If there was a previous tab, start from there
            if (previousTabIndex !== null && previousTabIndex !== currentTabIndex.toString()) {
                const previousTab = navLinks[parseInt(previousTabIndex)];
                if (previousTab) {
                    // Start at previous position (no transition)
                    updateIndicator(previousTab, false);
                    
                    // Force reflow
                    indicator.offsetHeight;
                    
                    // Re-enable transition and move to current tab
                    setTimeout(() => {
                        updateIndicator(activeTab, true);
                    }, 20);
                } else {
                    updateIndicator(activeTab, false);
                }
            } else {
                // No previous tab, just show current position
                updateIndicator(activeTab, false);
            }
            
            // Track if user clicked a tab
            let clickedTab = null;
            let hoverTimeout = null;
            let isOverNav = false;

            navTabs.addEventListener('mouseenter', function() {
                isOverNav = true;
                if (hoverTimeout) {
                    clearTimeout(hoverTimeout);
                    hoverTimeout = null;
                }
            });

            navTabs.addEventListener('mouseleave', function() {
                isOverNav = false;
                hoverTimeout = setTimeout(() => {
                    if (!isOverNav) {
                        if (clickedTab) {
                            updateIndicator(clickedTab, true);
                        } else if (activeTab) {
                            updateIndicator(activeTab, true);
                        }
                    }
                }, 50);
            });

            // Save current tab index and animate on interactions
            navLinks.forEach((link, index) => {
                // Hover underline follows hovered tab
                link.addEventListener('mouseenter', function() {
                    updateIndicator(link, true);
                });

                // Return underline to clicked or active tab on mouse leave
                link.addEventListener('mouseleave', function() {
                    hoverTimeout = setTimeout(() => {
                        if (!isOverNav) return;
                        if (clickedTab) {
                            updateIndicator(clickedTab, true);
                        } else if (activeTab) {
                            updateIndicator(activeTab, true);
                        }
                    }, 50);
                });

                link.addEventListener('click', function() {
                    clickedTab = link;
                    sessionStorage.setItem('guestActiveTabIndex', index);
                });
            });

            // Update on window resize
            window.addEventListener('resize', function() {
                if (clickedTab) {
                    updateIndicator(clickedTab, false);
                } else if (activeTab) {
                    updateIndicator(activeTab, false);
                }
            });

            // Logo caching to prevent reloads
            const logoContainers = document.querySelectorAll('.logo-container');
            logoContainers.forEach(container => {
                const logoSrc = container.dataset.logoSrc;
                if (logoSrc) {
                    container.style.backgroundImage = `url('${logoSrc}')`;
                    for (let i = 0; i < 3; i++) {
                        setTimeout(() => {
                            const img = new Image();
                            img.crossOrigin = 'anonymous';
                            img.onload = function() {
                                container.style.backgroundImage = `url('${logoSrc}')`;
                            };
                            img.src = logoSrc;
                        }, i * 10);
                    }
                    try {
                        localStorage.setItem('guestLogoImage', logoSrc);
                    } catch(e) {}
                }
            });
        });
    </script>
    
    @stack('scripts')
</body>
</html>
