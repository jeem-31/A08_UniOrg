<footer class="neu-footer bg-white text-gray-800 py-12 border-t border-gray-200">
    <style>
        .gold-icon {
            color: #D7AC28;
        }
    </style>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
            <div>
                <div class="flex items-center mb-4">
                    <img src="{{ organization_logo() }}" alt="{{ organization_acronym() }} Logo" class="h-10 w-10 object-contain mr-3">
                    <span class="text-2xl font-bold" style="color: var(--primary-color);">{{ organization_acronym() }}</span>
                </div>
                <p class="text-gray-600 text-sm">
                    Student Organization Management System for {{ organization_department() }}, LSPU.
                </p>
            </div>
            <div>
                <h4 class="text-lg font-semibold mb-4" style="color: var(--primary-color);">Quick Links</h4>
                @if(auth()->check() && (auth()->user()->role === 'admin' || auth()->user()->role === 'super_admin'))
                    <!-- Admin Links - 3 columns x 4 rows -->
                    <div class="grid grid-cols-3 gap-x-4 gap-y-2">
                        <a href="{{ route('admin.dashboard') }}" class="text-gray-600 transition text-sm neu-footer-link" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">Dashboard</a>
                        <a href="{{ route('admin.announcements.index') }}" class="text-gray-600 transition text-sm neu-footer-link" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">Announcements</a>
                        <a href="{{ route('admin.events.index') }}" class="text-gray-600 transition text-sm neu-footer-link" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">Events</a>
                        <a href="{{ route('admin.payments.index') }}" class="text-gray-600 transition text-sm neu-footer-link" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">Payments</a>
                        @if(module_enabled('elections'))
                        <a href="{{ route('admin.elections.index') }}" class="text-gray-600 transition text-sm neu-footer-link" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">Elections</a>
                        @endif
                        @if(module_enabled('documents'))
                        <a href="{{ route('admin.documents.student-documents') }}" class="text-gray-600 transition text-sm neu-footer-link" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">Documents</a>
                        @endif
                        @if(module_enabled('elibrary'))
                        <a href="{{ route('admin.library.index') }}" class="text-gray-600 transition text-sm neu-footer-link" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">e-Library</a>
                        @endif
                        @if(module_enabled('edushare'))
                        <a href="{{ route('admin.edushare.index') }}" class="text-gray-600 transition text-sm neu-footer-link" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">EduShare</a>
                        @endif
                        @if(module_enabled('complaints'))
                        <a href="{{ route('admin.complaints.index') }}" class="text-gray-600 transition text-sm neu-footer-link" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">Complaints</a>
                        @endif
                        <a href="{{ route('admin.chatbot.faqs') }}" class="text-gray-600 transition text-sm neu-footer-link" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">FAQs</a>
                    </div>
                @else
                    <!-- Student Links - 3 columns x 4 rows -->
                    <div class="grid grid-cols-3 gap-x-4 gap-y-2">
                        <a href="{{ route('dashboard') }}" class="text-gray-600 transition text-sm neu-footer-link" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">Dashboard</a>
                        <a href="{{ route('student.announcements.index') }}" class="text-gray-600 transition text-sm neu-footer-link" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">Announcements</a>
                        <a href="{{ route('events.index') }}" class="text-gray-600 transition text-sm neu-footer-link" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">Events</a>
                        <a href="{{ route('payments.index') }}" class="text-gray-600 transition text-sm neu-footer-link" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">Payments</a>
                        @if(module_enabled('elections'))
                        <a href="{{ route('elections.index') }}" class="text-gray-600 transition text-sm neu-footer-link" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">Elections</a>
                        @endif
                        @if(module_enabled('documents'))
                        <a href="{{ route('documents.my-documents') }}" class="text-gray-600 transition text-sm neu-footer-link" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">Documents</a>
                        @endif
                        @if(module_enabled('elibrary'))
                        <a href="{{ route('library.index') }}" class="text-gray-600 transition text-sm neu-footer-link" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">e-Library</a>
                        @endif
                        @if(module_enabled('edushare'))
                        <a href="{{ route('edushare.index') }}" class="text-gray-600 transition text-sm neu-footer-link" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">EduShare</a>
                        @endif
                        @if(module_enabled('complaints'))
                        <a href="{{ route('complaints.index') }}" class="text-gray-600 transition text-sm neu-footer-link" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">Complaints</a>
                        @endif
                        <a href="{{ route('chatbot.index') }}" class="text-gray-600 transition text-sm neu-footer-link" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">Chat / FAQ</a>
                    </div>
                @endif
            </div>
            <div>
                <h4 class="text-lg font-semibold mb-4" style="color: var(--primary-color);">
                    @if(auth()->check() && (auth()->user()->role === 'admin' || auth()->user()->role === 'super_admin'))
                        System Info
                    @else
                        Contact
                    @endif
                </h4>
                <p class="text-gray-600 text-sm">
                    <i class="ph ph-map-pin mr-2 gold-icon neu-footer-icon"></i>
                    LSPU Sta. Cruz Campus<br>
                    <i class="ph ph-envelope mr-2 gold-icon mt-2 inline-block neu-footer-icon"></i>
                    <a href="https://mail.google.com/mail/?view=cm&fs=1&to=osas_scc@lspu.edu.ph" target="_blank" class="text-gray-600 transition" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">osas_scc@lspu.edu.ph</a><br>
                    <i class="ph ph-facebook-logo mr-2 gold-icon mt-2 inline-block neu-footer-icon"></i>
                    <a href="https://web.facebook.com/osaslspuscc" target="_blank" class="text-gray-600 hover:opacity-75 transition" onmouseover="this.style.color='var(--primary-color)'" onmouseout="this.style.color=''">OSAS FB Page</a>
                </p>
            </div>
        </div>
        <div class="neu-footer-bottom border-t border-gray-200 pt-8 text-center">
            <p class="text-gray-500">
                &copy; {{ date('Y') }} {{ organization_acronym() }} - LSPU. All rights reserved.
            </p>
        </div>
    </div>
</footer>
