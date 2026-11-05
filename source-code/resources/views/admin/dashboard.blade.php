@extends(request()->boolean('fragment') ? 'layouts.fragment' : 'layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
<div class="px-6 py-6">

    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Admin Dashboard</h1>
            <p class="text-xs text-gray-400 font-medium mt-0.5">Overview of your organization's activity</p>
        </div>
        <span class="text-xs text-gray-400 font-semibold">{{ now()->format('D, M d, Y') }}</span>
    </div>

    <!-- Accreditation Expiring/Expired Banner -->
    @if($organization && in_array($organization->accreditationStatus(), ['expiring', 'expired'], true))
        @php $accStatus = $organization->accreditationStatus(); $accDays = $organization->daysUntilExpiration(); @endphp
        <div class="mb-5 flex flex-col sm:flex-row sm:items-center gap-3 rounded-lg border px-4 py-3 {{ $accStatus === 'expired' ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50' }}">
            <i class="fas {{ $accStatus === 'expired' ? 'fa-ban' : 'fa-exclamation-triangle' }} mt-0.5 {{ $accStatus === 'expired' ? 'text-red-500' : 'text-amber-500' }}"></i>
            <div class="flex-1">
                @if($accStatus === 'expired')
                    <p class="text-sm font-semibold text-red-800">Accreditation expired on {{ $organization->accreditation_expires_at->format('M d, Y') }}</p>
                    <p class="text-xs text-red-700">Access to organization features is restricted until accreditation is renewed.</p>
                @else
                    <p class="text-sm font-semibold text-amber-800">Accreditation expires in {{ $accDays }} day{{ $accDays === 1 ? '' : 's' }}</p>
                    <p class="text-xs text-amber-700">Renew before {{ $organization->accreditation_expires_at->format('M d, Y') }} to avoid losing access.</p>
                @endif
            </div>
            @php
                $bannerBlock = method_exists(\App\Models\Organization::class, 'renewBlockInfo') ? \App\Models\Organization::renewBlockInfo($organization) : ['blocked'=>false,'title'=>'','message'=>''];
                $bannerIsBlocked = $bannerBlock['blocked'] ?? false;
                $bannerTitle = $bannerBlock['title'] ?? '';
                $bannerMsg = $bannerBlock['message'] ?? '';
            @endphp
            @if($bannerIsBlocked)
                <button type="button" onclick="if(window.Alpine){ window.dispatchEvent(new CustomEvent('open-renew-blocked-modal', { detail: { title: @js($bannerTitle), message: @js($bannerMsg) } })) } else { alert(@js($bannerTitle.': '.$bannerMsg)) }"
                        class="inline-flex items-center justify-center gap-1 rounded-md px-3 py-1.5 text-xs font-bold text-white {{ $accStatus === 'expired' ? 'bg-red-500 hover:bg-red-600' : 'bg-amber-500 hover:bg-amber-600' }}">
                    <i class="fas fa-sync-alt"></i> Renew Now
                </button>
            @else
                <a href="{{ route('admin.renew.index') }}" class="inline-flex items-center justify-center gap-1 rounded-md px-3 py-1.5 text-xs font-bold text-white {{ $accStatus === 'expired' ? 'bg-red-500 hover:bg-red-600' : 'bg-amber-500 hover:bg-amber-600' }}">
                    <i class="fas fa-sync-alt"></i> Renew Now
                </a>
            @endif
        </div>
    @endif

    <!-- Compact Stat Strip -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
        <div class="bg-white border border-gray-200 rounded-lg px-4 py-3 flex items-center gap-3">
            <div class="w-9 h-9 rounded-md bg-blue-50 flex items-center justify-center shrink-0">
                <i class="ph ph-users text-blue-600 text-sm"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-gray-400 uppercase font-bold tracking-wide">Students</p>
                <p class="text-xl font-bold text-gray-900 leading-tight">{{ $stats['total_students'] }}</p>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg px-4 py-3 flex items-center gap-3">
            <div class="w-9 h-9 rounded-md flex items-center justify-center shrink-0" style="background-color: color-mix(in srgb, var(--primary-color) 12%, white)">
                <i class="ph ph-megaphone text-sm" style="color: var(--primary-color)"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-gray-400 uppercase font-bold tracking-wide">Announcements</p>
                <p class="text-xl font-bold text-gray-900 leading-tight">{{ $stats['total_announcements'] }}</p>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg px-4 py-3 flex items-center gap-3">
            <div class="w-9 h-9 rounded-md bg-green-50 flex items-center justify-center shrink-0">
                <i class="ph ph-calendar text-green-600 text-sm"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-gray-400 uppercase font-bold tracking-wide">Events</p>
                <p class="text-xl font-bold text-gray-900 leading-tight">{{ $stats['total_events'] }}</p>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg px-4 py-3 flex items-center gap-3">
            <div class="w-9 h-9 rounded-md bg-amber-50 flex items-center justify-center shrink-0">
                <i class="ph ph-clock text-amber-600 text-sm"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-gray-400 uppercase font-bold tracking-wide">Pending Payments</p>
                <p class="text-xl font-bold text-gray-900 leading-tight">{{ $stats['pending_payments'] ?? 0 }}</p>
            </div>
        </div>

        @php
            $accStat = $organization ? $organization->accreditationStatus() : null;
            $accBadge = match ($accStat) {
                'expired' => 'bg-red-100 text-red-700',
                'expiring' => 'bg-amber-100 text-amber-700',
                'active' => 'bg-emerald-100 text-emerald-700',
                default => 'bg-gray-100 text-gray-600',
            };
            $accLabel2 = match ($accStat) {
                'expired' => 'Expired',
                'expiring' => 'Expiring Soon',
                'active' => 'Active',
                default => 'No Period Set',
            };
            $accIconBg = match ($accStat) {
                'expired' => 'bg-red-50',
                'expiring' => 'bg-amber-50',
                'active' => 'bg-emerald-50',
                default => 'bg-gray-50',
            };
            $accIconColor = match ($accStat) {
                'expired' => 'text-red-600',
                'expiring' => 'text-amber-600',
                'active' => 'text-emerald-600',
                default => 'text-gray-500',
            };
        @endphp
        <div class="bg-white border border-gray-200 rounded-lg px-4 py-3 flex items-center gap-3">
            <div class="w-9 h-9 rounded-md {{ $accIconBg }} flex items-center justify-center shrink-0">
                <i class="ph ph-seal-check {{ $accIconColor }} text-sm"></i>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-xs text-gray-400 uppercase font-bold tracking-wide">Accreditation</p>
                <span class="inline-flex items-center px-2 py-0.5 mt-1 text-xs font-bold rounded-full {{ $accBadge }}">{{ $accLabel2 }}</span>
            </div>
        </div>
    </div>

    <!-- Quick Actions Row -->
    <div class="bg-white border border-gray-200 rounded-lg mb-5">
        <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide">Quick Actions</h3>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-gray-100">
            <a href="{{ route('admin.announcements.index') }}" class="flex items-center gap-3 px-5 py-4 hover:bg-gray-50 transition-colors border-b sm:border-b-0 border-gray-100">
                <i class="ph ph-megaphone text-lg" style="color: var(--primary-color)"></i>
                <span class="text-sm font-semibold text-gray-700">Announcements</span>
            </a>
            <a href="{{ route('admin.events.index') }}" class="flex items-center gap-3 px-5 py-4 hover:bg-gray-50 transition-colors border-b sm:border-b-0 border-gray-100">
                <i class="ph ph-calendar-blank text-lg text-blue-600"></i>
                <span class="text-sm font-semibold text-gray-700">Events</span>
            </a>
            <a href="{{ route('admin.payments.index') }}" class="flex items-center gap-3 px-5 py-4 hover:bg-gray-50 transition-colors border-gray-100">
                <i class="ph ph-credit-card text-lg text-green-600"></i>
                <span class="text-sm font-semibold text-gray-700">Payments</span>
            </a>
            <a href="{{ route('admin.elections.index') }}" class="flex items-center gap-3 px-5 py-4 hover:bg-gray-50 transition-colors">
                <i class="ph ph-check-square text-lg text-red-600"></i>
                <span class="text-sm font-semibold text-gray-700">Elections</span>
            </a>
        </div>
    </div>

    <!-- Two-column: Announcements & Events -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">
        <div class="bg-white border border-gray-200 rounded-lg">
            <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide">Recent Announcements</h3>
                <a href="{{ route('admin.announcements.index') }}" class="text-xs font-bold uppercase tracking-wide" style="color: var(--primary-color)">View All</a>
            </div>
            <div class="divide-y divide-gray-100 max-h-[360px] overflow-y-auto">
                @forelse($recentAnnouncements as $announcement)
                    <div class="flex items-center gap-3 px-4 py-3">
                        @if($announcement->images && count($announcement->images) > 0)
                            <div class="relative shrink-0">
                                <img src="{{ storage_url($announcement->images[0]) }}" alt="{{ $announcement->title }}" class="w-16 h-16 object-cover rounded-lg">
                                @if(count($announcement->images) > 1)
                                    <div style="background-color: var(--primary-color);" class="absolute -top-1.5 -right-1.5 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">
                                        {{ count($announcement->images) }}
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="w-16 h-16 shrink-0 rounded-lg flex items-center justify-center" style="background-color: color-mix(in srgb, var(--primary-color) 10%, white)">
                                <i class="ph ph-megaphone text-xl" style="color: var(--primary-color)"></i>
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-semibold text-gray-800 truncate">{{ $announcement->title }}</h4>
                            <p class="text-xs text-gray-500 truncate">{{ Str::limit(strip_tags($announcement->content), 60) }}</p>
                        </div>
                        <span class="text-xs text-gray-400 shrink-0 whitespace-nowrap">{{ $announcement->created_at->diffForHumans(null, true) }}</span>
                        <a href="{{ route('admin.announcements.index') }}?edit_announcement={{ $announcement->id }}"
                           style="color: var(--primary-color);" class="shrink-0 hover:opacity-80 text-sm">
                            <i class="ph ph-pencil-simple"></i>
                        </a>
                    </div>
                @empty
                    <p class="text-gray-400 text-center py-8 text-sm font-medium">No announcements</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg">
            <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide">Upcoming Events</h3>
                <a href="{{ route('admin.events.index') }}" class="text-xs font-bold uppercase tracking-wide" style="color: var(--primary-color)">View All</a>
            </div>
            <div class="divide-y divide-gray-100 max-h-[360px] overflow-y-auto">
                @forelse($upcomingEvents as $event)
                    <div class="flex items-center gap-3 px-4 py-3">
                        @if($event->images && count($event->images) > 0)
                            <div class="relative shrink-0">
                                <img src="{{ storage_url($event->images[0]) }}" alt="{{ $event->title }}" class="w-16 h-16 object-cover rounded-lg">
                                @if(count($event->images) > 1)
                                    <div style="background-color: var(--primary-color);" class="absolute -top-1.5 -right-1.5 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">
                                        {{ count($event->images) }}
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="w-16 h-16 shrink-0 rounded-lg bg-blue-50 flex items-center justify-center">
                                <i class="ph ph-calendar-blank text-blue-600 text-xl"></i>
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-semibold text-gray-800 truncate">{{ $event->title }}</h4>
                            <p class="text-xs text-gray-500 truncate"><i class="ph ph-map-pin text-[10px] mr-1"></i>{{ $event->venue }}</p>
                        </div>
                        <span class="text-xs text-gray-400 shrink-0 whitespace-nowrap">{{ $event->start_date->format('M d') }}</span>
                        <a href="{{ route('admin.events.index') }}?edit_event={{ $event->id }}"
                           class="shrink-0 text-blue-600 hover:text-blue-800 text-sm">
                            <i class="ph ph-pencil-simple"></i>
                        </a>
                    </div>
                @empty
                    <p class="text-gray-400 text-center py-8 text-sm font-medium">No upcoming events</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Recent Payments Table -->
    <div class="bg-white border border-gray-200 rounded-lg">
        <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide">Recent Payments</h3>
            <a href="{{ route('admin.payments.index') }}" class="text-xs font-bold uppercase tracking-wide" style="color: var(--primary-color)">View All</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Transaction ID</th>
                        <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Student</th>
                        <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Category</th>
                        <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Amount</th>
                        <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($recentPayments as $payment)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-2.5 whitespace-nowrap text-sm font-medium text-gray-600">{{ $payment->transaction_id }}</td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-sm text-gray-700">{{ $payment->user->name }}</td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-sm text-gray-500">{{ ucfirst(str_replace('_', ' ', $payment->category)) }}</td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-sm font-bold text-gray-800">₱{{ number_format($payment->amount, 2) }}</td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <span class="px-2 py-0.5 text-xs font-bold rounded-full
                                    {{ $payment->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $payment->status === 'pending' ? 'bg-amber-100 text-amber-700' : '' }}
                                    {{ $payment->status === 'failed' ? 'bg-red-100 text-red-700' : '' }}">
                                    {{ ucfirst($payment->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-sm text-gray-400">{{ $payment->created_at->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm font-medium">No payments yet</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
