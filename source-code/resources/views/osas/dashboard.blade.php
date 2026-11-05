@extends((request()->boolean('fragment') && request()->ajax()) ? 'layouts.fragment' : 'layouts.osas')

@section('title', 'OSAS Operations - UniOrg')

@section('content')
@php
    $approvalRate = $stats['total_requests'] > 0
        ? round(($stats['approved_requests'] / $stats['total_requests']) * 100)
        : 0;
    $adoptionRate = $stats['total_student_population'] > 0
        ? ($stats['registered_students'] / $stats['total_student_population']) * 100
        : 0;
    $adoptionRate = $adoptionRate < 1
        ? number_format(min(100, $adoptionRate), 1)
        : (string) round(min(100, $adoptionRate));
    $organizationTotal = max(1, (int) $stats['total_organizations']);
    $organizationMix = [
        ['label' => 'Mother organizations', 'value' => $stats['mother_orgs'], 'color' => '#26488C'],
        ['label' => 'Sub organizations', 'value' => $stats['sub_orgs'], 'color' => '#5B7FC4'],
        ['label' => 'Non-academic', 'value' => $stats['non_academic_orgs'], 'color' => '#C4973B'],
    ];
@endphp

<div class="osas-dashboard w-full max-w-[1700px] mx-auto space-y-5">
    <header class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2 mb-2">
                <span class="text-xs font-semibold uppercase tracking-[0.14em] text-[#26488C]">University organization services</span>
            </div>
            <h1 class="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">
                OSAS <span class="text-[#26488C]">Operations</span>
            </h1>
            <p class="mt-1.5 max-w-2xl text-sm font-normal leading-relaxed text-slate-500">
                A focused view of organization health, accreditation activity, and the work that needs attention today.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-right">
                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">Today</p>
                <p class="mt-0.5 text-xs font-bold text-slate-800">{{ now()->format('D, M d, Y') }}</p>
            </div>
            <a href="{{ route('osas.apply-org.index') }}" data-osas-navigation class="inline-flex items-center gap-2 rounded-lg bg-[#26488C] px-4 py-3 text-xs font-bold uppercase tracking-wider text-white shadow-sm transition hover:bg-[#1a3461]">
                <i class="ph ph-tray text-[11px] text-[#E7C875]"></i>
                Review queue
                @if($stats['pending_requests'] > 0)
                    <span class="rounded-full bg-white/15 px-1.5 py-0.5 text-[10px]">{{ $stats['pending_requests'] }}</span>
                @endif
            </a>
        </div>
    </header>

    @if($expiringOrganizations->isNotEmpty())
    <section aria-label="Accreditation expiring soon" class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
        <div class="flex items-start gap-3">
            <i class="ph ph-warning mt-0.5 text-amber-500"></i>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-bold uppercase tracking-wider text-amber-800">Accreditation attention needed</p>
                <p class="mt-1 text-xs font-normal text-amber-700">
                    {{ $expiringOrganizations->count() }} approved organization(s) with accreditation expired or expiring within 30 days.
                </p>
                <div class="mt-2.5 flex flex-wrap gap-2">
                    @foreach($expiringOrganizations as $expOrg)
                        <a href="{{ route('osas.organizations.show', $expOrg) }}" data-osas-navigation
                           class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-amber-200 px-3 py-1.5 text-[11px] font-semibold text-slate-700 transition hover:border-amber-400">
                            {{ $expOrg->name }}
                            @if($expOrg->accreditationStatus() === 'expired')
                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold uppercase text-red-600">Expired</span>
                            @else
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-700">{{ $expOrg->daysUntilExpiration() }}d</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
    @endif

    <section aria-label="Organization overview" class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <a href="{{ route('osas.organizations.index') }}" data-osas-navigation class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-[#26488C]/30 hover:shadow-md">
            <div class="flex items-start justify-between gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-[#26488C]"><i class="ph ph-buildings text-base"></i></div>
                <i class="ph ph-arrow-square-out text-[10px] text-slate-300 transition group-hover:text-[#26488C]"></i>
            </div>
            <p class="mt-4 text-3xl font-black leading-none text-slate-950">{{ number_format($stats['total_organizations']) }}</p>
            <div class="mt-1.5 flex items-center justify-between gap-2">
                <span class="text-xs font-semibold tracking-wide text-slate-400">Organizations</span>
                <span class="text-xs font-bold text-emerald-600">{{ number_format($stats['active_organizations']) }} active</span>
            </div>
        </a>

        <a href="{{ route('osas.organizations.index', ['status' => 'Approved']) }}" data-osas-navigation class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
            <div class="flex items-start justify-between gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><i class="ph ph-check-circle text-base"></i></div>
                <span class="text-xs font-bold text-emerald-600">{{ $stats['total_organizations'] > 0 ? round(($stats['active_organizations'] / $stats['total_organizations']) * 100) : 0 }}%</span>
            </div>
            <p class="mt-4 text-3xl font-black leading-none text-slate-950">{{ number_format($stats['active_organizations']) }}</p>
            <div class="mt-1.5 flex items-center justify-between gap-2">
                <span class="text-xs font-semibold tracking-wide text-slate-400">Active organizations</span>
                <span class="text-xs font-medium text-slate-400">of total</span>
            </div>
        </a>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"><i class="ph ph-users text-base"></i></div>
                <span class="text-xs font-semibold tracking-wide text-slate-400">Network</span>
            </div>
            <p class="mt-4 text-3xl font-black leading-none text-slate-950">{{ number_format($stats['total_members']) }}</p>
            <div class="mt-1.5 flex items-center justify-between gap-2">
                <span class="text-xs font-semibold tracking-wide text-slate-400">Registered members</span>
                <span class="text-xs font-medium text-slate-400">{{ number_format($stats['registered_students']) }} students</span>
            </div>
        </div>

        <a href="{{ route('osas.apply-org.index') }}" data-osas-navigation class="group rounded-xl border border-amber-200 bg-amber-50/50 p-5 shadow-sm transition hover:-translate-y-0.5 hover:bg-amber-50 hover:shadow-md">
            <div class="flex items-start justify-between gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-[#C4973B] shadow-sm"><i class="ph ph-clock text-base"></i></div>
                <span class="inline-flex items-center gap-1 text-xs font-bold tracking-wide text-[#9B7427]"><span class="h-1.5 w-1.5 rounded-full bg-[#C4973B]"></span>Action</span>
            </div>
            <p class="mt-4 text-3xl font-black leading-none text-slate-950">{{ number_format($stats['pending_requests']) }}</p>
            <div class="mt-1.5 flex items-center justify-between gap-2">
                <span class="text-xs font-semibold tracking-wide text-[#9B7427]">Pending accreditation</span>
                <i class="ph ph-arrow-right text-[10px] text-[#C4973B] transition group-hover:translate-x-0.5"></i>
            </div>
        </a>
    </section>

    <div class="grid grid-cols-1 items-stretch gap-5 xl:grid-cols-12">
            <section class="dashboard-panel order-1 min-w-0 overflow-hidden xl:col-span-8">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-6 py-5">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600"><i class="ph ph-arrow-counter-clockwise text-sm"></i></span>
                            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-900">Recent submissions</h2>
                        </div>
                        <p class="mt-1.5 pl-10.5 text-xs font-normal text-slate-400">The latest recognition and renewal activity.</p>
                    </div>
                    <a href="{{ route('osas.apply-org.index') }}" data-osas-navigation class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#26488C] hover:text-[#1a3461]">View queue <i class="ph ph-arrow-right text-[10px]"></i></a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[620px] text-left">
                        <thead class="border-b border-slate-100 bg-slate-50/60">
                            <tr>
                                <th class="px-6 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Organization</th>
                                <th class="px-6 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Request type</th>
                                <th class="px-6 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Status</th>
                                <th class="px-6 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-slate-400">Submitted</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($recentRequests as $request)
                                @php $isRecognition = $request->request_type === 'apply'; @endphp
                                <tr class="group transition hover:bg-slate-50/70">
                                    <td class="px-6 py-3.5">
                                        <a href="{{ route('osas.apply-org.show', $request) }}" data-osas-navigation class="flex min-w-0 items-center gap-3">
                                            @if($request->organization?->settings && $request->organization->settings->getRawOriginal('logo_url'))
                                                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-100 bg-white"><img src="{{ $request->organization->settings->logo_url }}" alt="{{ $request->organization_name }} logo" class="h-full w-full object-cover"></span>
                                            @else
                                                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg {{ $isRecognition ? 'bg-blue-50 text-[#26488C]' : 'bg-amber-50 text-[#9B7427]' }} text-xs font-bold">{{ strtoupper(substr($request->organization_name, 0, 1)) }}</span>
                                            @endif
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-semibold text-slate-900 group-hover:text-[#26488C]">{{ $request->organization_name }}</span>
                                                <span class="mt-0.5 block truncate text-xs font-normal text-slate-400">{{ $request->organization_acronym ?: 'No acronym provided' }}</span>
                                            </span>
                                        </a>
                                    </td>
                                    <td class="px-6 py-3.5 whitespace-nowrap">
                                        <span class="text-xs font-semibold tracking-wide {{ $isRecognition ? 'text-[#26488C]' : 'text-[#9B7427]' }}">{{ $isRecognition ? 'Recognition' : 'Renewal' }}</span>
                                    </td>
                                    <td class="px-6 py-3.5 whitespace-nowrap">
                                        @if($request->status === 'Pending')
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-700"><span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Pending</span>
                                        @elseif($request->status === 'Approved')
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Approved</span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-rose-700"><span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>{{ $request->status }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3.5 text-right whitespace-nowrap text-xs font-medium text-slate-400">{{ $request->created_at->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-6 py-12 text-center text-xs font-semibold uppercase tracking-wider text-slate-400">No submissions recorded yet</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="dashboard-panel order-2 min-w-0 overflow-hidden xl:order-3 xl:col-span-8">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-6 py-5">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-[#C4973B]"><i class="ph ph-calendar-dot text-sm"></i></span>
                            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-900">Events this week</h2>
                        </div>
                        <p class="mt-1.5 pl-10.5 text-xs font-normal text-slate-400">Approved activities across the university calendar.</p>
                    </div>
                    <a href="{{ route('osas.calendar') }}" data-osas-navigation class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#26488C] hover:text-[#1a3461]">Master calendar <i class="ph ph-arrow-right text-[10px]"></i></a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left">
                        <thead class="border-b border-slate-100 bg-slate-50/60">
                            <tr>
                                <th class="px-6 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Organization</th>
                                <th class="px-6 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Event</th>
                                <th class="px-6 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Schedule</th>
                                <th class="px-6 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Venue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($eventsThisWeek as $event)
                                <tr class="transition hover:bg-slate-50/70">
                                    <td class="px-6 py-3.5 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            @if($event->organization->settings && $event->organization->settings->getRawOriginal('logo_url'))
                                                <span class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-lg border border-slate-100 bg-white"><img src="{{ $event->organization->settings->logo_url }}" alt="{{ $event->organization->name }} logo" class="h-full w-full object-cover"></span>
                                            @else
                                                <span class="flex h-8 w-8 items-center justify-center rounded-lg text-[10px] font-bold text-white" style="background-color: {{ $event->organization->settings->primary_color ?? '#26488C' }}">{{ strtoupper(substr($event->organization->org_acronym ?? $event->organization->name, 0, 1)) }}</span>
                                            @endif
                                            <span class="max-w-[150px] truncate text-sm font-semibold text-slate-800">{{ $event->organization->org_acronym ?? $event->organization->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3.5 whitespace-nowrap"><span class="block max-w-[190px] truncate text-sm font-semibold text-slate-800">{{ $event->title }}</span></td>
                                    <td class="px-6 py-3.5 whitespace-nowrap text-xs font-medium text-slate-500">{{ $event->start_date->format('M d, h:i A') }}</td>
                                    <td class="px-6 py-3.5 whitespace-nowrap"><span class="block max-w-[130px] truncate text-xs font-normal text-slate-500">{{ $event->venue ?: 'Venue to be confirmed' }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-6 py-12 text-center text-xs font-semibold uppercase tracking-wider text-slate-400">No events scheduled this week</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($eventsThisWeek->hasPages())
                    <div class="border-t border-slate-100 bg-slate-50/40 px-4 py-3 premium-pagination">{{ $eventsThisWeek->links() }}</div>
                @endif
            </section>

            <section class="dashboard-panel order-3 flex min-w-0 flex-col xl:order-2 xl:col-span-4">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-6 py-5">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600"><i class="ph ph-student text-sm"></i></span>
                        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-900">Student registration</h2>
                    </div>
                    <span class="text-xs font-semibold text-indigo-600">{{ $adoptionRate }}% registered</span>
                </div>
                <div class="flex flex-1 items-center gap-5 px-6 py-5">
                    <div class="h-28 w-28 flex-shrink-0"><canvas id="populationChart" aria-label="Registered student participation" role="img"></canvas></div>
                    <div class="min-w-0 flex-1 space-y-3">
                        <div class="flex items-center justify-between gap-3"><span class="flex items-center gap-2 text-xs font-semibold tracking-wide text-slate-400"><span class="h-2 w-2 rounded-full bg-[#26488C]"></span>Student accounts</span><span class="text-sm font-bold text-slate-900">{{ number_format($stats['registered_students']) }}</span></div>
                        <div class="flex items-center justify-between gap-3"><span class="flex items-center gap-2 text-xs font-semibold tracking-wide text-slate-400"><span class="h-2 w-2 rounded-full bg-slate-200"></span>Population</span><span class="text-sm font-bold text-slate-900">{{ number_format($stats['total_student_population']) }}</span></div>
                        <div class="border-t border-slate-100 pt-2.5 text-[11px] font-normal leading-relaxed text-slate-400">Student accounts registered in UniOrg compared with the enrolled population.</div>
                    </div>
                </div>
            </section>

            <section class="dashboard-panel order-4 flex min-w-0 flex-col xl:col-span-4">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-6 py-5">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-[#26488C]"><i class="ph ph-tree-structure text-sm"></i></span>
                        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-900">Organization overview</h2>
                    </div>
                    <a href="{{ route('osas.organizations.index') }}" data-osas-navigation class="text-xs font-semibold text-[#26488C] hover:underline">Directory</a>
                </div>
                <div class="flex flex-1 flex-col justify-center space-y-2.5 px-5 py-4">
                    @foreach($organizationMix as $mix)
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-3">
                                <span class="text-xs font-medium text-slate-600">{{ $mix['label'] }}</span>
                                <span class="text-xs font-bold text-slate-900">{{ number_format($mix['value']) }}</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full" style="width: {{ min(100, ($mix['value'] / $organizationTotal) * 100) }}%; background-color: {{ $mix['color'] }}"></div></div>
                        </div>
                    @endforeach
                </div>
            </section>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    (function () {
        function renderOsasDashboardCharts() {
            const populationCanvas = document.getElementById('populationChart');
            if (!populationCanvas || typeof Chart === 'undefined') return;

            const registered = {{ (int) $stats['registered_students'] }};
            const population = {{ (int) $stats['total_student_population'] }};
            const remaining = Math.max(0, population - registered);

            new Chart(populationCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Registered', 'Not connected'],
                    datasets: [{ data: [registered, remaining], backgroundColor: ['#26488C', '#E8EEF7'], borderWidth: 0, borderRadius: 5, hoverOffset: 4 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '76%',
                    plugins: { legend: { display: false }, tooltip: { displayColors: false, backgroundColor: '#17233B', padding: 9, cornerRadius: 6, bodyFont: { size: 10, weight: '600' } } }
                }
            });
        }

        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', renderOsasDashboardCharts, { once: true });
        else renderOsasDashboardCharts();
    }());
</script>
@endpush

<style>
    .osas-dashboard { font-family: 'Plus Jakarta Sans', sans-serif; }
    .osas-dashboard .text-slate-400 { color: #64748B !important; }
    .dashboard-panel { background: #fff; border: 1px solid #E2E8F0; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 1px 2px rgba(15, 23, 42, 0.02); }
    .premium-pagination nav > div:first-child { display: none !important; }
    .premium-pagination nav > div:last-child { justify-content: center !important; gap: 0.35rem !important; }
    .premium-pagination a, .premium-pagination span > span, .premium-pagination span > a { border-radius: 0.5rem !important; border: 1px solid #E2E8F0 !important; padding: 0.35rem 0.75rem !important; font-size: 0.7rem !important; font-weight: 700 !important; }
    .premium-pagination span[aria-current="page"] > span { background: #26488C !important; color: #fff !important; border-color: #26488C !important; }
    .premium-pagination a:hover { border-color: #26488C !important; color: #26488C !important; background: #F8FAFC !important; }
</style>
@endsection
