@extends((request()->boolean('fragment') && request()->ajax()) ? 'layouts.fragment' : 'layouts.osas')

@section('title', 'Analytics - UniOrg')

@section('content')
<div class="container-fluid px-6 py-5">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-5 gap-2">
        <div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight leading-none">
                Analytics <span class="text-[#26488C]">&amp; Reports</span>
            </h1>
            <p class="text-gray-500 mt-1 text-xs font-medium">Event activity and engagement analytics.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('osas.analytics') }}">
                <label for="sy-filter" class="sr-only">School year</label>
                <select id="sy-filter" name="sy" onchange="this.form.submit()"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-black uppercase tracking-widest text-gray-700 cursor-pointer focus:outline-none focus:ring-2 focus:ring-[#26488C]/40">
                    <option value="" @selected($selectedSy === null)>All Time</option>
                    @foreach($schoolYears as $sy)
                        <option value="{{ $sy['key'] }}" @selected($selectedSy === $sy['key'])>{{ $sy['label'] }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('osas.reports.organizations.pdf') }}"
               class="inline-flex items-center gap-2 px-4 py-2 text-xs font-black uppercase tracking-widest text-white rounded-lg bg-[#26488C] hover:bg-[#1a3461] transition-colors shadow-sm">
                <i class="ph ph-file-pdf"></i> Export Report
            </a>
        </div>
    </div>

    <!-- Events & Engagement -->
    <div class="mt-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-black text-gray-900 uppercase tracking-widest flex items-center gap-2">
                <i class="ph ph-calendar-dot text-[#26488C]"></i> Events &amp; Engagement
            </h2>
            <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ $periodLabel }}</span>
        </div>

        <!-- Event KPIs -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <div class="bg-white rounded-lg border border-gray-100 p-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-blue-50 text-[#26488C] flex items-center justify-center flex-shrink-0">
                    <i class="ph ph-calendar-dot text-sm"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-black text-gray-900 leading-none">{{ number_format($eventTotals['total']) }}</p>
                    <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-0.5 truncate">Total Events</p>
                </div>
            </div>
            <div class="bg-white rounded-lg border border-gray-100 p-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-green-50 text-green-600 flex items-center justify-center flex-shrink-0">
                    <i class="ph ph-check-circle text-sm"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-black text-gray-900 leading-none">{{ number_format($eventTotals['approved']) }}</p>
                    <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-0.5 truncate">Approved</p>
                </div>
            </div>
            <div class="bg-white rounded-lg border border-gray-100 p-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-amber-50 text-[#C4973B] flex items-center justify-center flex-shrink-0">
                    <i class="ph ph-clock text-sm"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-black text-gray-900 leading-none">{{ number_format($eventTotals['pending']) }}</p>
                    <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-0.5 truncate">Pending Approval</p>
                </div>
            </div>
            <div class="bg-white rounded-lg border border-gray-100 p-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0">
                    <i class="ph ph-timer text-sm"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-black text-gray-900 leading-none">{{ number_format($avgApprovalDays, 1) }}<span class="text-[10px] font-black text-gray-400 ml-0.5">d</span></p>
                    <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-0.5 truncate">Avg Approval Time</p>
                </div>
            </div>
        </div>

        <!-- Monthly pipeline + Venue utilization -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-4">
            <div class="lg:col-span-8 bg-white rounded-lg border border-gray-100 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest flex items-center gap-2">
                        <i class="ph ph-chart-bar text-[#26488C]"></i> Events per Month
                    </h3>
                    <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">By Status</span>
                </div>
                <div class="h-[240px]">
                    <canvas id="eventsTrendChart"></canvas>
                </div>
            </div>

            <div class="lg:col-span-4 bg-white rounded-lg border border-gray-100 p-5">
                <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest flex items-center gap-2 mb-3">
                    <i class="ph ph-map-pin text-[#C4973B]"></i> Venue Utilization
                </h3>
                <div class="h-[240px]">
                    <canvas id="venueChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Top orgs + Approval turnaround -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white rounded-lg border border-gray-100 p-5">
                <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest flex items-center gap-2 mb-3">
                    <i class="ph ph-trophy text-[#26488C]"></i> Top Organizations by Events
                </h3>
                <div class="h-[260px]">
                    <canvas id="topEventOrgsChart"></canvas>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-100 p-5">
                <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest flex items-center gap-2 mb-3">
                    <i class="ph ph-gauge text-[#C4973B]"></i> Approval Turnaround
                </h3>
                <p class="text-[10px] font-medium text-gray-400 mb-3">Average days from submission to OSAS approval, by month.</p>
                <div class="h-[220px]">
                    <canvas id="turnaroundChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Events per Month (stacked bar by status)
        const eventTrend = @json($eventTrend);
        const trendCtx = document.getElementById('eventsTrendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: eventTrend.map(d => d.month),
                datasets: [
                    { label: 'Approved', data: eventTrend.map(d => d.approved), backgroundColor: '#22C55E', borderRadius: 2, maxBarThickness: 22 },
                    { label: 'Pending', data: eventTrend.map(d => d.pending), backgroundColor: '#C4973B', borderRadius: 2, maxBarThickness: 22 },
                    { label: 'Rejected', data: eventTrend.map(d => d.rejected), backgroundColor: '#EF4444', borderRadius: 2, maxBarThickness: 22 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, padding: 12, color: '#64748B', font: { size: 10, weight: 'bold' } }
                    },
                    tooltip: {
                        backgroundColor: '#1f2937',
                        titleFont: { size: 12, weight: 'bold' },
                        bodyFont: { size: 11 },
                        padding: 10,
                        cornerRadius: 8
                    }
                },
                scales: {
                    x: { stacked: true, grid: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: '#94a3b8', maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } },
                    y: { stacked: true, beginAtZero: true, grid: { color: '#F1F5F9' }, ticks: { font: { size: 10 }, color: '#94a3b8', precision: 0 } }
                }
            }
        });

        // Venue Utilization (horizontal bar)
        const venueUsage = @json($venueUsage);
        const venueCtx = document.getElementById('venueChart').getContext('2d');
        new Chart(venueCtx, {
            type: 'bar',
            data: {
                labels: venueUsage.map(d => d.label),
                datasets: [{
                    label: 'Events',
                    data: venueUsage.map(d => d.total),
                    backgroundColor: '#C4973B',
                    borderRadius: 4,
                    maxBarThickness: 18
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1f2937',
                        titleFont: { size: 12, weight: 'bold' },
                        bodyFont: { size: 11 },
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: false
                    }
                },
                scales: {
                    x: { beginAtZero: true, grid: { color: '#F1F5F9' }, ticks: { font: { size: 10 }, color: '#94a3b8', precision: 0 } },
                    y: { grid: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: '#475569' } }
                }
            }
        });

        // Top Organizations by Events (horizontal bar, biggest on top)
        const topEventOrgs = @json($topEventOrgs);
        const orgCtx = document.getElementById('topEventOrgsChart').getContext('2d');
        new Chart(orgCtx, {
            type: 'bar',
            data: {
                labels: topEventOrgs.map(d => d.label).reverse(),
                datasets: [{
                    label: 'Events',
                    data: topEventOrgs.map(d => d.total).reverse(),
                    backgroundColor: '#26488C',
                    borderRadius: 4,
                    maxBarThickness: 18
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1f2937',
                        titleFont: { size: 12, weight: 'bold' },
                        bodyFont: { size: 11 },
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: false
                    }
                },
                scales: {
                    x: { beginAtZero: true, grid: { color: '#F1F5F9' }, ticks: { font: { size: 10 }, color: '#94a3b8', precision: 0 } },
                    y: { grid: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: '#475569' } }
                }
            }
        });

        // Approval Turnaround (line)
        const turnaroundTrend = @json($turnaroundTrend);
        const turnaroundCtx = document.getElementById('turnaroundChart').getContext('2d');
        new Chart(turnaroundCtx, {
            type: 'line',
            data: {
                labels: turnaroundTrend.map(d => d.month),
                datasets: [{
                    label: 'Avg days to approve',
                    data: turnaroundTrend.map(d => d.days),
                    borderColor: '#C4973B',
                    backgroundColor: 'rgba(196, 151, 59, 0.10)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#C4973B',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1f2937',
                        titleFont: { size: 12, weight: 'bold' },
                        bodyFont: { size: 11 },
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function (context) {
                                return context.parsed.y === null ? 'No approvals' : context.parsed.y + ' days';
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: '#94a3b8', maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } },
                    y: { beginAtZero: true, grid: { color: '#F1F5F9' }, ticks: { font: { size: 10 }, color: '#94a3b8' } }
                }
            }
        });
    });
</script>
@endpush

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    .container-fluid {
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
</style>
@endsection
