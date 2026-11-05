@extends('layouts.app')

@section('title', 'Elections')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@endpush

@section('content')
@php
    $organizationName = organization_name();
    $featured = $featuredElection;
    $featuredIsOpen = $featured && $featured->is_active && now()->between($featured->start_date, $featured->end_date);
    $featuredIsUpcoming = $featured && now()->lt($featured->start_date);
    $featuredIsEnded = $featured && now()->gt($featured->end_date);

    $statusFor = function ($election) {
        if ($election->is_active && now()->between($election->start_date, $election->end_date)) {
            return ['label' => 'Voting open', 'class' => 'bg-emerald-50 text-emerald-700', 'icon' => 'ph-circle'];
        }

        if (now()->lt($election->start_date)) {
            return ['label' => 'Upcoming', 'class' => 'bg-amber-50 text-amber-700', 'icon' => 'ph-clock'];
        }

        if ($election->results_published) {
            return ['label' => 'Results available', 'class' => 'bg-slate-100 text-slate-700', 'icon' => 'ph-checks'];
        }

        return ['label' => 'Election ended', 'class' => 'bg-slate-100 text-slate-600', 'icon' => 'ph-flag-checkered'];
    };
@endphp

<div class="student-election-page -mt-8 bg-slate-50">
    <div class="mx-auto max-w-7xl px-3 py-6 sm:px-6 sm:py-8 lg:px-8">
        <header class="election-hero">
            <div class="election-hero__copy">
                <p class="election-eyebrow">Student voting</p>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-gray-900">Elections</h1>
            </div>
        </header>

        @if(session('success'))
            <div class="mb-5 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                <i class="ph ph-check-circle mt-0.5 text-emerald-600" aria-hidden="true"></i>
                <p class="font-medium">{{ session('success') }}</p>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-5 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
                <i class="ph ph-warning-circle mt-0.5 text-red-600" aria-hidden="true"></i>
                <p class="font-medium">{{ session('error') }}</p>
            </div>
        @endif

        @if($featured)
            @php($featuredStatus = $statusFor($featured))
            <section class="student-election-featured overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" style="--election-card-index: 0; border-top: 1px solid rgba(var(--primary-color-rgb), 0.38);">
                <div class="border-b border-slate-100 px-5 py-5 sm:px-7 sm:py-6">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--primary-color);">Featured election</span>
                                <span class="student-election-status inline-flex items-center gap-1.5 px-0.5 text-xs font-semibold {{ $featuredStatus['class'] }}">
                                    <i class="ph {{ $featuredStatus['icon'] }} text-[7px]" aria-hidden="true"></i>
                                    {{ $featuredStatus['label'] }}
                                </span>
                                @if($featuredHasVoted)
                                    <span class="student-election-status inline-flex items-center gap-1.5 px-0.5 text-xs font-semibold text-slate-600">
                                        <i class="ph ph-check text-[9px]" aria-hidden="true"></i> Vote recorded
                                    </span>
                                @endif
                            </div>
                            <h2 class="max-w-3xl text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">{{ $featured->title }}</h2>
                            <p class="mt-1 text-sm font-medium text-slate-500">{{ $organizationName }}</p>
                            @if($featured->description)
                                <p class="mt-4 max-w-3xl text-sm leading-6 text-slate-600">{{ $featured->description }}</p>
                            @endif
                        </div>

                        <div class="flex shrink-0 flex-col gap-3 lg:items-end">
                            @if($featuredIsOpen || $featuredHasVoted)
                                <a href="{{ route('elections.show', $featured) }}" data-student-navigation class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-bold text-white transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-[var(--primary-color)] focus:ring-offset-2" style="background-color: var(--primary-color);">
                                    <i class="ph {{ $featuredHasVoted ? 'ph-receipt' : 'ph-arrow-right' }} text-xs" aria-hidden="true"></i>
                                    {{ $featuredHasVoted ? 'View ballot receipt' : 'View candidates and vote' }}
                                </a>
                            @elseif($featuredIsUpcoming)
                                <div class="student-election-upcoming-callout w-full max-w-xs rounded-xl border px-4 py-3.5 lg:w-60">
                                    <div class="flex items-start gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" style="background-color: rgba(var(--primary-color-rgb), 0.12); color: var(--primary-color);">
                                            <i class="ph ph-clock text-sm" aria-hidden="true"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold" style="color: var(--primary-color);">Voting starts soon</p>
                                            <p class="mt-1 text-lg font-bold tracking-tight text-slate-950">{{ $featured->start_date->diffForHumans() }}</p>
                                            <p class="mt-0.5 text-xs text-slate-500">Opens {{ $featured->start_date->format('M j, Y · g:i A') }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <dl class="grid gap-4 bg-slate-50/70 px-5 py-4 text-sm sm:grid-cols-2 sm:px-7 lg:grid-cols-4">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Voting opens</dt>
                        <dd class="mt-1 font-semibold text-slate-800">{{ $featured->start_date->format('M j, Y') }}</dd>
                        <dd class="text-xs text-slate-500">{{ $featured->start_date->format('g:i A') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Voting closes</dt>
                        <dd class="mt-1 font-semibold text-slate-800">{{ $featured->end_date->format('M j, Y') }}</dd>
                        <dd class="text-xs text-slate-500">{{ $featured->end_date->format('g:i A') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Candidates</dt>
                        <dd class="mt-1 font-semibold text-slate-800">{{ $featured->candidates->count() }}</dd>
                        <dd class="text-xs text-slate-500">Across available positions</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Next step</dt>
                        <dd class="mt-1 font-semibold text-slate-800">
                            @if($featuredIsOpen && !$featuredHasVoted) Select one candidate per position
                            @elseif($featuredHasVoted) Review your recorded ballot
                            @elseif($featuredIsUpcoming) Voting opens {{ $featured->start_date->format('M j') }}
                            @else Check the election status
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>
        @else
            <section class="election-section rounded-xl border border-slate-200 bg-white px-6 py-12 text-center shadow-sm sm:px-10" style="--election-card-index: 0;">
                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-lg text-[var(--primary-color)]" style="background-color: rgba(var(--primary-color-rgb), 0.10);"><i class="ph ph-check-square" aria-hidden="true"></i></span>
                <h2 class="mt-4 text-xl font-bold text-slate-950">No elections available</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-600">There are no posted elections for your organization at the moment. Please check again later.</p>
            </section>
        @endif

        <section class="election-section mt-6 px-5 py-6 sm:px-7" style="--election-card-index: 1;" aria-label="How to vote">
            <p class="text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--primary-color);">How to vote</p>
            <h2 class="mt-1 text-lg font-bold text-slate-950">Cast your ballot in 3 steps</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                <div class="election-howto-card rounded-xl p-5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg text-sm" style="background-color: rgba(var(--primary-color-rgb), 0.10); color: var(--primary-color);"><i class="ph ph-list-checks" aria-hidden="true"></i></span>
                    <p class="mt-3 text-xs font-bold uppercase tracking-[0.12em] text-slate-400">Step 1</p>
                    <h3 class="mt-1 text-sm font-bold text-slate-900">Review candidates</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Browse each position and tap a photo to view it larger.</p>
                </div>
                <div class="election-howto-card rounded-xl p-5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg text-sm" style="background-color: rgba(var(--primary-color-rgb), 0.10); color: var(--primary-color);"><i class="ph ph-check-square" aria-hidden="true"></i></span>
                    <p class="mt-3 text-xs font-bold uppercase tracking-[0.12em] text-slate-400">Step 2</p>
                    <h3 class="mt-1 text-sm font-bold text-slate-900">Select one per position</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Your pick is saved as you move through the ballot.</p>
                </div>
                <div class="election-howto-card rounded-xl p-5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg text-sm" style="background-color: rgba(var(--primary-color-rgb), 0.10); color: var(--primary-color);"><i class="ph ph-paper-plane-tilt" aria-hidden="true"></i></span>
                    <p class="mt-3 text-xs font-bold uppercase tracking-[0.12em] text-slate-400">Step 3</p>
                    <h3 class="mt-1 text-sm font-bold text-slate-900">Review &amp; submit</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Check every position on the final step, then submit. Votes are final.</p>
                </div>
            </div>
        </section>

        @if($featured)
            <section class="election-section mt-6 px-5 py-6 sm:px-7" style="--election-card-index: 2;" aria-label="Election timeline">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--primary-color);">Election timeline</p>
                <h2 class="mt-1 text-lg font-bold text-slate-950">Important dates</h2>
                <ol class="election-timeline mt-5 space-y-5">
                    <li class="election-timeline-item">
                        <span class="election-timeline-dot" aria-hidden="true"><i class="ph ph-play"></i></span>
                        <div>
                            <p class="text-sm font-bold text-slate-900">Voting opens</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $featured->start_date->format('F j, Y · g:i A') }}</p>
                        </div>
                    </li>
                    <li class="election-timeline-item">
                        <span class="election-timeline-dot" aria-hidden="true"><i class="ph ph-flag-checkered"></i></span>
                        <div>
                            <p class="text-sm font-bold text-slate-900">Voting closes</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $featured->end_date->format('F j, Y · g:i A') }}</p>
                        </div>
                    </li>
                    <li class="election-timeline-item">
                        <span class="election-timeline-dot" aria-hidden="true"><i class="ph ph-chart-bar"></i></span>
                        <div>
                            <p class="text-sm font-bold text-slate-900">Results published</p>
                            <p class="mt-0.5 text-xs text-slate-500">Official results appear here after COMELEC verification.</p>
                        </div>
                    </li>
                </ol>
            </section>
        @endif

        @if($resultsElection)
            <section class="election-section mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" style="--election-card-index: 3;">
                <div class="flex flex-col gap-1 border-b border-slate-100 px-5 py-4 sm:px-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--primary-color);">Published results</p>
                    <h2 class="text-lg font-bold text-slate-950">{{ $resultsElection->title }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500" style="box-shadow: inset 3px 0 0 var(--primary-color);">
                            <tr>
                                <th class="px-5 py-3 sm:px-6">Position</th>
                                <th class="px-5 py-3 sm:px-6">Winner</th>
                                <th class="px-5 py-3 text-right sm:px-6">Votes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($positions as $positionKey => $position)
                                @php($winner = $resultsElection->candidates->where('position', $positionKey)->sortByDesc('vote_count')->first())
                                @if($winner)
                                    <tr class="text-slate-700">
                                        <td class="whitespace-nowrap px-5 py-3 font-semibold text-slate-900 sm:px-6">{{ $position['name'] }}</td>
                                        <td class="px-5 py-3 sm:px-6">
                                            <div class="flex items-center gap-3">
                                                @if($winner->photo)
                                                    <img src="{{ storage_url($winner->photo) }}" alt="{{ $winner->name }}" class="h-8 w-8 rounded-md border border-slate-200 object-cover">
                                                @else
                                                    <span class="flex h-8 w-8 items-center justify-center rounded-md bg-slate-100 text-xs text-slate-500"><i class="ph ph-user" aria-hidden="true"></i></span>
                                                @endif
                                                <span class="font-medium text-slate-800">{{ $winner->name }}</span>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-slate-800 sm:px-6">{{ number_format($winner->vote_count) }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if($historyElections->isNotEmpty())
            <section class="mt-5">
                <div class="mb-3 flex items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--primary-color);">Election history</p>
                        <h2 class="mt-1 text-lg font-bold text-slate-950">Previous elections</h2>
                    </div>
                    <span class="text-xs font-medium text-slate-500">{{ $historyElections->count() }} posted</span>
                </div>

                <div class="election-section overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" style="--election-card-index: 4;">
                    @foreach($historyElections as $historyElection)
                        @php($historyStatus = $statusFor($historyElection))
                        <a href="{{ route('elections.show', $historyElection) }}" data-student-navigation class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 transition last:border-b-0 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="truncate font-semibold text-slate-900">{{ $historyElection->title }}</h3>
                                    <span class="student-election-status inline-flex items-center gap-1.5 px-0.5 text-[11px] font-semibold {{ $historyStatus['class'] }}">
                                        <i class="ph {{ $historyStatus['icon'] }} text-[7px]" aria-hidden="true"></i>{{ $historyStatus['label'] }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">{{ $historyElection->start_date->format('M j, Y') }} – {{ $historyElection->end_date->format('M j, Y') }} · {{ $historyElection->candidates->count() }} candidates</p>
                            </div>
                            <span class="inline-flex shrink-0 items-center gap-2 text-sm font-semibold" style="color: var(--primary-color);">
                                View details <i class="ph ph-arrow-right text-xs" aria-hidden="true"></i>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
<style>
    .student-election-page {
        --neu-bg: #E9EDF3;
        --neu-text: #2E3A4C;
        --neu-muted: #5A6B80;
        --neu-faint: #7C8A9E;
        --neu-divider: rgba(163, 177, 198, .45);
        --neu-dark-shadow: rgba(163, 177, 198, .6);
        --neu-light-shadow: rgba(255, 255, 255, .85);
        background: var(--neu-bg) !important;
        min-height: auto !important;
        margin-top: -2rem;
        margin-bottom: -2rem;
        padding-top: 0;
        color: var(--neu-text);
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Segoe UI", sans-serif;
        -webkit-font-smoothing: antialiased;
    }

    html:has(.student-election-page),
    body:has(.student-election-page),
    main:has(.student-election-page) {
        background: var(--neu-bg) !important;
    }

    .student-election-page h1,
    .student-election-page h2,
    .student-election-page h3 {
        color: var(--neu-text);
        letter-spacing: -0.025em;
    }

    .student-election-page .rounded-xl.border.border-slate-200.bg-white,
    .student-election-page .rounded-lg.border.border-slate-200.bg-white {
        border: 0 !important;
        background: var(--neu-bg) !important;
    }

    .student-election-page .student-election-featured,
    .student-election-page .election-section {
        border: 0 !important;
        border-radius: 1.5rem !important;
        background: var(--neu-bg) !important;
        box-shadow: 10px 10px 20px var(--neu-dark-shadow), -10px -10px 20px var(--neu-light-shadow) !important;
        animation: election-card-in .6s both cubic-bezier(.2, .8, .2, 1);
        animation-delay: calc(var(--election-card-index, 0) * 70ms);
    }
    .student-election-page .student-election-featured {
        box-shadow: 10px 10px 20px var(--neu-dark-shadow), -10px -10px 20px var(--neu-light-shadow), inset 0 2px 0 rgba(var(--primary-color-rgb, 124, 58, 237), .45) !important;
    }
    .student-election-page .student-election-featured > div,
    .student-election-page .election-section > div { border-color: var(--neu-divider) !important; }
    .student-election-page .border-slate-100,
    .student-election-page .border-slate-200 { border-color: var(--neu-divider) !important; }
    .student-election-page .divide-slate-100 > :not([hidden]) ~ :not([hidden]) { border-color: var(--neu-divider) !important; }

    .student-election-page .bg-slate-50 { background-color: #E3E9F1 !important; }
    .student-election-page .bg-slate-50\/70 { background-color: transparent !important; }
    .student-election-page .bg-slate-100 { background-color: #DDE4EE !important; }

    .student-election-page .text-slate-950,
    .student-election-page .text-slate-900,
    .student-election-page .text-slate-800,
    .student-election-page .text-slate-700 { color: var(--neu-text) !important; }
    .student-election-page .text-slate-600,
    .student-election-page .text-slate-500 { color: var(--neu-muted) !important; }
    .student-election-page .text-slate-400 { color: var(--neu-faint) !important; }

    .student-election-page .student-election-status {
        border: 0 !important;
        border-radius: 999px !important;
        padding: .35rem .75rem !important;
        box-shadow: 3px 3px 6px rgba(163, 177, 198, .5), -3px -3px 6px rgba(255, 255, 255, .9) !important;
    }
    .student-election-page .bg-emerald-50 { background-color: rgba(16, 185, 129, .13) !important; }
    .student-election-page .bg-amber-50 { background-color: rgba(245, 158, 11, .13) !important; }

    .student-election-page a[style*="background-color: var(--primary-color)"],
    .student-election-page button[style*="background-color: var(--primary-color)"] {
        border: 0 !important;
        border-radius: 1rem !important;
        box-shadow: 5px 5px 12px rgba(var(--primary-color-rgb, 124, 58, 237), .35), -5px -5px 12px rgba(255, 255, 255, .85) !important;
        transition: transform .28s cubic-bezier(.2, .8, .2, 1), box-shadow .28s cubic-bezier(.2, .8, .2, 1), filter .28s ease;
    }
    .student-election-page a[style*="background-color: var(--primary-color)"]:hover,
    .student-election-page button[style*="background-color: var(--primary-color)"]:hover {
        transform: translateY(-2px);
        filter: brightness(1.04);
    }
    .student-election-page a:hover { text-decoration: none; }

    .student-election-page [role="status"] {
        border: 0 !important;
        border-radius: 1rem !important;
        background-color: rgba(16, 185, 129, .12) !important;
        color: #0B5A44 !important;
        box-shadow: inset 3px 3px 7px rgba(16, 185, 129, .15), inset -3px -3px 7px rgba(255, 255, 255, .8) !important;
    }
    .student-election-page [role="alert"] {
        border: 0 !important;
        border-radius: 1rem !important;
        background-color: rgba(220, 38, 38, .10) !important;
        color: #7F1D1D !important;
        box-shadow: inset 3px 3px 7px rgba(220, 38, 38, .14), inset -3px -3px 7px rgba(255, 255, 255, .8) !important;
    }
    .student-election-page [role="status"] i, .student-election-page [role="status"] .ph { color: #0B8A5E !important; }

    .student-election-page .student-election-upcoming-callout {
        border: 0 !important;
        border-radius: 1.25rem !important;
        background: rgba(var(--primary-color-rgb, 124, 58, 237), .07) !important;
        box-shadow: inset 3px 3px 7px rgba(var(--primary-color-rgb, 124, 58, 237), .15), inset -3px -3px 7px rgba(255, 255, 255, .8) !important;
    }

    .student-election-page table thead { background: #DDE4EE !important; }
    .student-election-page table thead th { color: var(--neu-muted) !important; letter-spacing: .05em; }
    .student-election-page table tbody tr { transition: background-color .2s ease; }
    .student-election-page table tbody tr:hover { background: rgba(var(--primary-color-rgb, 124, 58, 237), .045); }
    .student-election-page table tbody td { border-color: var(--neu-divider) !important; }
    .student-election-page .border.border-slate-200.bg-white {
        border: 0 !important;
        box-shadow: 3px 3px 6px rgba(163, 177, 198, .45), -3px -3px 6px rgba(255, 255, 255, .9) !important;
    }
    .student-election-page table img.rounded-md {
        border: 0 !important;
        box-shadow: 3px 3px 6px rgba(163, 177, 198, .45), -3px -3px 6px rgba(255, 255, 255, .9) !important;
    }

    .student-election-page .election-section a[href*="/elections/"]:not([style]) { border-color: var(--neu-divider) !important; }
    .student-election-page .election-section a[href*="/elections/"]:not([style]):hover { background: rgba(var(--primary-color-rgb, 124, 58, 237), .04) !important; }

    /* Apple feel */
    .student-election-page > .mx-auto { padding-top: 1.5rem !important; padding-bottom: 2rem !important; }

    .election-hero { padding: 0 0 clamp(1rem, 2vw, 1.5rem); }
    .election-eyebrow {
        margin: 0 0 .45rem;
        color: var(--primary-color, #7c3aed);
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .08em;
        line-height: 1.25;
        text-transform: uppercase;
    }
    .election-hero h1 { margin: 0; color: var(--neu-text); letter-spacing: -.025em; line-height: 1.25; }
    .election-hero__copy > p:last-child {
        max-width: 38rem;
        margin: .8rem 0 0;
        color: var(--neu-muted);
        font-size: clamp(.95rem, 2vw, 1.05rem);
        line-height: 1.55;
    }

    .student-election-page .election-howto-card {
        border: 0 !important;
        border-radius: 1.25rem !important;
        background: var(--neu-bg) !important;
        box-shadow: 6px 6px 12px var(--neu-dark-shadow), -6px -6px 12px var(--neu-light-shadow) !important;
        transition: transform .3s cubic-bezier(.2, .8, .2, 1), box-shadow .3s cubic-bezier(.2, .8, .2, 1) !important;
    }
    .student-election-page .election-howto-card:hover {
        transform: translateY(-3px);
        box-shadow: 9px 9px 18px var(--neu-dark-shadow), -9px -9px 18px var(--neu-light-shadow) !important;
    }

    .election-timeline { position: relative; margin: 0; padding: 0; list-style: none; }
    .election-timeline::before {
        content: "";
        position: absolute;
        left: 1rem;
        top: .6rem;
        bottom: .6rem;
        width: 2px;
        border-radius: 999px;
        background: rgba(var(--primary-color-rgb, 124, 58, 237), .25);
        box-shadow: inset 1px 1px 2px rgba(163, 177, 198, .5);
    }
    .election-timeline-item { position: relative; display: flex; align-items: flex-start; gap: .9rem; }
    .election-timeline-dot {
        position: static;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.1rem;
        height: 2.1rem;
        border-radius: 999px;
        background: var(--neu-bg);
        color: var(--primary-color, #7c3aed);
        font-size: .8rem;
        box-shadow: 3px 3px 7px var(--neu-dark-shadow), -3px -3px 7px var(--neu-light-shadow);
    }

    @keyframes election-card-in { from { opacity: 0; transform: translateY(14px) scale(.99); } to { opacity: 1; transform: translateY(0) scale(1); } }

    @media (max-width: 640px) {
        .student-election-page { min-height: auto !important; margin-top: -1rem; margin-bottom: -1rem; }
        .student-election-page > .mx-auto { padding-top: 1.25rem !important; padding-bottom: 1.75rem !important; }
    }

    @media (prefers-reduced-motion: reduce) {
        .student-election-featured, .election-section { animation: none !important; }
    }
</style>
@endsection

@push('scripts')
<script>
(function () {
    let reloadPending = false;

    const reloadElections = () => {
        if (reloadPending) return;
        reloadPending = true;
        setTimeout(() => window.location.reload(), 350);
    };

    const subscribeToElectionUpdates = () => {
        if (!window.Echo) return;
        window.Echo.channel('elections')
            .listen('.election-created', reloadElections)
            .listen('.election-updated', reloadElections)
            .listen('.election-deleted', reloadElections);
    };

    subscribeToElectionUpdates();
})();
</script>
@endpush
