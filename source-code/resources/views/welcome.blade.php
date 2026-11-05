<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ organization_name() }}</title>
    <link rel="icon" type="image/png" href="{{ organization_logo() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
    <style>
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-20px);
            }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.8s ease-out;
        }
        

        
        .btn-primary {
            background: linear-gradient(135deg, #8238B3 0%, #3D0066 100%);
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(130, 56, 179, 0.4);
        }
        
        .btn-secondary {
            border: 2px solid #8238B3;
            color: #8238B3;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: #8238B3;
            color: white;
            transform: translateY(-2px);
        }
        
        .feature-card {
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .feature-card:hover .icon-wrapper {
            transform: scale(1.1) rotate(5deg);
        }
        
        .icon-wrapper {
            transition: all 0.3s ease;
        }
        
        .hero-gradient {
            background: linear-gradient(135deg, #8238B3 0%, #3D0066 100%);
        }
        
        .gold-icon {
            color: #D7AC28;
        }
        
        .nav-hidden {
            transform: translateY(-100%);
            transition: transform 0.3s ease-in-out;
        }
        
        .nav-visible {
            transform: translateY(0);
            transition: transform 0.3s ease-in-out;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav id="navbar" class="bg-white shadow-md sticky top-0 z-50 nav-visible">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="{{ route('home') }}" class="flex items-center">
                            <img src="{{ organization_logo() }}" alt="{{ organization_name() }} Logo" class="h-10 w-10 object-contain mr-2">
                            <span class="text-2xl font-bold" style="color: #8238B3;">{{ organization_name() }}</span>
                        </a>
                    </div>
                </div>
                <div class="flex items-center">
                    @auth
                        <a href="{{ route('dashboard') }}" class="px-6 py-2.5 btn-primary text-white text-sm font-semibold">
                            <i class="ph ph-gauge mr-2 gold-icon"></i>Dashboard
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-gradient text-white py-24 md:py-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                <div class="animate-fade-in-up">
                    <h1 class="text-4xl md:text-6xl font-bold mb-6 leading-tight">
                        Welcome to <span class="text-yellow-300">SOCCSync</span>
                    </h1>
                    <p class="text-xl md:text-2xl text-purple-100 mb-8">
                        Your Integrated Student Organization Management System for the College of Computer Studies
                    </p>
                    <div class="flex flex-wrap gap-4">
                        @guest
                            <a href="{{ route('guest.dashboard') }}" class="inline-flex items-center px-8 py-4 bg-white text-purple-700 text-lg font-semibold rounded-lg hover:bg-purple-50 transition-all transform hover:scale-105 shadow-xl">
                                <i class="ph ph-rocket mr-2 gold-icon"></i>Get Started
                            </a>
                            <a href="{{ route('login') }}" class="inline-flex items-center px-8 py-4 border-2 border-white text-white text-lg font-semibold rounded-lg hover:bg-white hover:text-purple-700 transition-all transform hover:scale-105">
                                <i class="ph ph-sign-in mr-2 gold-icon"></i>Login/Register
                            </a>
                        @endguest
                    </div>
                </div>
                <div class="hidden md:block">
                    <div class="flex justify-center">
                        <div class="bg-white rounded-full p-2 shadow-2xl inline-block">
                            <img src="{{ organization_logo() }}" alt="{{ organization_name() }}" class="w-96 h-96 object-contain">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">
                    Everything You Need in <span style="color: #8238B3;">One Platform</span>
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    SOCCSync brings together all essential tools for student organization management, making campus life easier and more connected.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="feature-card bg-white p-8 rounded-2xl shadow-lg border border-gray-100">
                    <div class="icon-wrapper w-16 h-16 rounded-full bg-gradient-to-br from-yellow-100 to-yellow-200 flex items-center justify-center mb-6">
                        <i class="ph ph-megaphone text-3xl gold-icon"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900">Announcements</h3>
                    <p class="text-gray-600 leading-relaxed">Stay updated with the latest news, updates, and important announcements from SOCCS in real-time.</p>
                </div>

                <!-- Feature 2 -->
                <div class="feature-card bg-white p-8 rounded-2xl shadow-lg border border-gray-100">
                    <div class="icon-wrapper w-16 h-16 rounded-full bg-gradient-to-br from-yellow-100 to-yellow-200 flex items-center justify-center mb-6">
                        <i class="ph ph-calendar-blank text-3xl gold-icon"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900">Events</h3>
                    <p class="text-gray-600 leading-relaxed">Discover and participate in exciting CCS events, workshops, seminars, and activities throughout the year.</p>
                </div>

                <!-- Feature 3 -->
                <div class="feature-card bg-white p-8 rounded-2xl shadow-lg border border-gray-100">
                    <div class="icon-wrapper w-16 h-16 rounded-full bg-gradient-to-br from-yellow-100 to-yellow-200 flex items-center justify-center mb-6">
                        <i class="ph ph-credit-card text-3xl gold-icon"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900">Payments</h3>
                    <p class="text-gray-600 leading-relaxed">Easily manage and track your payments for fees, events, memberships, and other transactions.</p>
                </div>

                <!-- Feature 4 -->
                <div class="feature-card bg-white p-8 rounded-2xl shadow-lg border border-gray-100">
                    <div class="icon-wrapper w-16 h-16 rounded-full bg-gradient-to-br from-yellow-100 to-yellow-200 flex items-center justify-center mb-6">
                        <i class="ph ph-check-square text-3xl gold-icon"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900">Elections</h3>
                    <p class="text-gray-600 leading-relaxed">Participate in secure, transparent online voting for SOCCS officers and important decisions.</p>
                </div>

                <!-- Feature 5 -->
                <div class="feature-card bg-white p-8 rounded-2xl shadow-lg border border-gray-100">
                    <div class="icon-wrapper w-16 h-16 rounded-full bg-gradient-to-br from-yellow-100 to-yellow-200 flex items-center justify-center mb-6">
                        <i class="ph ph-book text-3xl gold-icon"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900">e-Library</h3>
                    <p class="text-gray-600 leading-relaxed">Access a comprehensive collection of study materials, PDFs, and learning resources anytime, anywhere.</p>
                </div>

                <!-- Feature 6 -->
                <div class="feature-card bg-white p-8 rounded-2xl shadow-lg border border-gray-100">
                    <div class="icon-wrapper w-16 h-16 rounded-full bg-gradient-to-br from-yellow-100 to-yellow-200 flex items-center justify-center mb-6">
                        <i class="ph ph-share-network text-3xl gold-icon"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-3 text-gray-900">EduShare</h3>
                    <p class="text-gray-600 leading-relaxed">Share and download student projects, code repositories, and collaborate with fellow students.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-white text-gray-800 py-12 border-t border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                <div>
                    <div class="flex items-center mb-4">
                        <img src="{{ organization_logo() }}" alt="{{ organization_name() }} Logo" class="h-10 w-10 object-contain mr-3">
                        <span class="text-2xl font-bold" style="color: #8238B3;">{{ organization_name() }}</span>
                    </div>
                    <p class="text-gray-600">
                        Student Organization Management System for the College of Computer Studies, LSPU.
                    </p>
                </div>
                <div>
                    <h4 class="text-lg font-semibold mb-4" style="color: #8238B3;">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="{{ route('guest.dashboard') }}" class="text-gray-600 hover:text-purple-700 transition">Home</a></li>
                        <li><a href="{{ route('guest.announcements.index') }}" class="text-gray-600 hover:text-purple-700 transition">Announcements</a></li>
                        <li><a href="{{ route('guest.events.index') }}" class="text-gray-600 hover:text-purple-700 transition">Events</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-lg font-semibold mb-4" style="color: #8238B3;">Contact</h4>
                    <p class="text-gray-600">
                        <i class="ph ph-map-pin mr-2 gold-icon"></i>
                        LSPU Sta. Cruz Campus<br>
                        <i class="ph ph-envelope mr-2 gold-icon mt-2 inline-block"></i>
                        demo.contact@example.invalid<br>
                        <i class="ph ph-facebook-logo mr-2 gold-icon mt-2 inline-block"></i>
                        <a href="https://www.facebook.com/profile.php?id=100083475335462" target="_blank" class="text-gray-600 hover:text-purple-700 transition">SOCCS Facebook Page</a>
                    </p>
                </div>
            </div>
            <div class="border-t border-gray-200 pt-8 text-center">
                <p class="text-gray-500">
                    &copy; {{ date('Y') }} SOCCSync - College of Computer Studies, LSPU. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <script>
        let lastScrollTop = 0;
        const navbar = document.getElementById('navbar');
        
        window.addEventListener('scroll', function() {
            let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                // Scrolling down
                navbar.classList.remove('nav-visible');
                navbar.classList.add('nav-hidden');
            } else {
                // Scrolling up
                navbar.classList.remove('nav-hidden');
                navbar.classList.add('nav-visible');
            }
            
            lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
        });
    </script>
</body>
</html>
