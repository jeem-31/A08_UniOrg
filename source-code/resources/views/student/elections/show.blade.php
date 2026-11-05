@extends('layouts.app')

@section('title', $election->title . ' - Vote')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@endpush

@section('content')
@php
    $organization = $election->organization;
    $organizationName = $organization?->name ?? organization_name();
    $organizationLogo = $organization?->settings?->logo_url ?? organization_logo();
    $isVotingOpen = !$hasVoted && $election->is_active && now()->between($election->start_date, $election->end_date);
    $isUpcoming = now()->lt($election->start_date);
    $isEnded = now()->gt($election->end_date);

    if ($isVotingOpen) {
        $status = ['label' => 'Voting open', 'class' => 'bg-emerald-50 text-emerald-700', 'icon' => 'ph-circle'];
    } elseif ($hasVoted) {
        $status = ['label' => 'Vote recorded', 'class' => 'bg-slate-100 text-slate-700', 'icon' => 'ph-check'];
    } elseif ($isUpcoming) {
        $status = ['label' => 'Upcoming', 'class' => 'bg-amber-50 text-amber-700', 'icon' => 'ph-clock'];
    } elseif ($election->results_published) {
        $status = ['label' => 'Results available', 'class' => 'bg-slate-100 text-slate-700', 'icon' => 'ph-checks'];
    } else {
        $status = ['label' => $isEnded ? 'Election ended' : 'Unavailable', 'class' => 'bg-slate-100 text-slate-600', 'icon' => $isEnded ? 'ph-flag-checkered' : 'ph-info'];
    }

    $positionIcon = static fn (string $key): string => match ($key) {
        'president' => 'ph-crown',
        'vice_president' => 'ph-user',
        'treasurer' => 'ph-wallet',
        'auditor' => 'ph-clipboard-text',
        'public_information_officer' => 'ph-megaphone',
        'business_manager' => 'ph-briefcase',
        'event_coordinator' => 'ph-calendar',
        default => 'ph-graduation-cap',
    };
@endphp
@php
    $positionsMeta = $positionsWithCandidates->map(fn ($panel) => ['name' => $panel['data']['name'], 'icon' => $positionIcon($panel['key'])])->values();
@endphp

<div class="student-election-page -mt-8 bg-slate-50" x-data="candidateImagePreview()" @keydown.escape.window="closeImagePreview()">
    <div class="mx-auto max-w-7xl px-3 py-5 sm:px-6 sm:py-8 lg:px-8">
        <a href="{{ route('elections.index') }}" data-student-navigation class="election-back-btn mb-4 inline-flex items-center gap-2 text-sm font-semibold transition">
            <i class="ph ph-arrow-left text-xs" style="color: var(--primary-color);" aria-hidden="true"></i>
            Back to Elections
        </a>

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

        <section class="student-election-featured overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" style="--election-card-index: 0; border-top: 1px solid rgba(var(--primary-color-rgb), 0.38);">
            <div class="border-b border-slate-100 px-5 py-5 sm:px-7 sm:py-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex min-w-0 items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white p-1.5 sm:h-14 sm:w-14">
                            <img src="{{ $organizationLogo }}" alt="{{ $organizationName }} logo" class="h-full w-full object-contain">
                        </div>
                        <div class="min-w-0">
                            <div class="mb-1 flex flex-wrap items-center gap-2">
                                <span class="text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--primary-color);">Official election</span>
                                <span class="student-election-status inline-flex items-center gap-1.5 px-0.5 text-xs font-semibold {{ $status['class'] }}">
                                    <i class="ph {{ $status['icon'] }} text-[7px]" aria-hidden="true"></i>{{ $status['label'] }}
                                </span>
                            </div>
                            <h1 class="max-w-3xl text-2xl sm:text-3xl font-black tracking-tight text-gray-900">{{ $election->title }}</h1>
                            <p class="mt-1 text-sm font-medium text-slate-500">{{ $organizationName }}</p>
                        </div>
                    </div>

                    <dl class="grid shrink-0 grid-cols-2 gap-x-6 gap-y-2 border-t border-slate-100 pt-4 text-sm sm:flex sm:gap-7 lg:border-l lg:border-t-0 lg:pl-7 lg:pt-0">
                        <div>
                            <dt class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Opens</dt>
                            <dd class="font-semibold text-slate-800">{{ $election->start_date->format('M j, Y') }}</dd>
                            <dd class="text-xs text-slate-500">{{ $election->start_date->format('g:i A') }}</dd>
                        </div>
                        <div>
                            <dt class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Closes</dt>
                            <dd class="font-semibold text-slate-800">{{ $election->end_date->format('M j, Y') }}</dd>
                            <dd class="text-xs text-slate-500">{{ $election->end_date->format('g:i A') }}</dd>
                        </div>
                    </dl>
                </div>

                @if($election->description)
                    <p class="mt-5 max-w-4xl border-l-2 pl-4 text-sm leading-6 text-slate-600" style="border-color: var(--primary-color);">{{ $election->description }}</p>
                @endif
            </div>
        </section>

        @if($isVotingOpen && $positionsWithCandidates->isNotEmpty())
            <form method="POST" action="{{ route('elections.vote', $election) }}" x-data="votingWizard()" x-on:resize.window="syncLayout()" x-on:load.window="syncLayout()" class="mt-4 pb-4">
                @csrf

                <div class="election-section mb-5 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm sm:px-5" style="--election-card-index: 0;">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-sm font-bold text-slate-900" x-text="`${selectedCount()} of {{ $positionsWithCandidates->count() }} positions selected`"></p>
                            <p class="mt-0.5 text-xs text-slate-500">Choose one candidate for each position, then review before submitting.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-500 sm:gap-3">
                            <button type="button" @click="previousStep()" :disabled="currentStep === 0" aria-label="Previous position" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-bold text-slate-600 transition hover:bg-white hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-40 sm:text-sm">
                                <i class="ph ph-arrow-left text-xs" aria-hidden="true"></i><span class="hidden sm:inline">Previous</span>
                            </button>
                            <span class="whitespace-nowrap" x-text="currentStep < {{ $positionsWithCandidates->count() }} ? `Step ${currentStep + 1} of {{ $positionsWithCandidates->count() }}` : 'Final review'"></span>
                            <div class="h-1.5 w-20 overflow-hidden rounded-full bg-slate-100 sm:w-28" role="progressbar" aria-label="Election progress" aria-valuemin="0" aria-valuemax="{{ $positionsWithCandidates->count() }}" x-bind:aria-valuenow="progressPositionCount()">
                                <div class="h-full rounded-full transition-[width] duration-300 ease-out" x-bind:style="{ width: progressPercentage() + '%', backgroundColor: 'var(--primary-color)' }"></div>
                            </div>
                            <button x-bind:type="currentStep < {{ $positionsWithCandidates->count() }} ? 'button' : 'submit'" @click="if (currentStep < {{ $positionsWithCandidates->count() }}) nextStep()" :disabled="currentStep < {{ $positionsWithCandidates->count() }} ? !selectedPositions[currentStep] : !allPositionsSelected()" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-bold text-white transition enabled:hover:opacity-90 disabled:cursor-not-allowed disabled:bg-slate-300 sm:px-4 sm:text-sm" style="background-color: var(--primary-color);">
                                <span class="hidden sm:inline" x-text="currentStep === {{ $positionsWithCandidates->count() }} ? 'Submit final vote' : (currentStep === {{ $positionsWithCandidates->count() - 1 }} ? 'Review selections' : 'Next position')"></span>
                                <span class="sm:hidden" x-text="currentStep === {{ $positionsWithCandidates->count() }} ? 'Submit' : (currentStep === {{ $positionsWithCandidates->count() - 1 }} ? 'Review' : 'Next')"></span>
                                <i class="ph ph-arrow-right text-xs" x-show="currentStep < {{ $positionsWithCandidates->count() }}" aria-hidden="true"></i>
                                <i class="ph ph-check-circle text-xs" x-show="currentStep === {{ $positionsWithCandidates->count() }}" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div x-ref="wizardStage" class="student-election-wizard-stage grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
                    <main class="student-election-stage-main min-w-0">
                        <section class="flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" x-bind:style="stageMinHeight ? { minHeight: stageMinHeight } : {}">
                            <header x-ref="voteCardHeader" class="flex flex-col gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-lg text-sm" style="background-color: rgba(var(--primary-color-rgb), 0.10); color: var(--primary-color);">
                                        <i class="ph student-election-symbol" x-bind:class="currentStep < {{ $positionsWithCandidates->count() }} ? positionsMeta[currentStep].icon : 'ph-list-checks'" aria-hidden="true"></i>
                                    </span>
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.12em]" style="color: var(--primary-color);" x-text="currentStep < {{ $positionsWithCandidates->count() }} ? 'Position ' + (currentStep + 1) : 'Final step'"></p>
                                        <h2 class="text-xl font-bold text-slate-950" x-text="currentStep < {{ $positionsWithCandidates->count() }} ? positionsMeta[currentStep].name : 'Review your selections'"></h2>
                                    </div>
                                </div>
                                <p class="text-sm text-slate-500" x-text="currentStep < {{ $positionsWithCandidates->count() }} ? 'Select one candidate' : selectedCount() + ' of {{ $positionsWithCandidates->count() }} selected'"></p>
                            </header>

                            <div class="election-panels-viewport flex-1" x-bind:style="viewportHeight ? { height: viewportHeight } : {}">
                                <div class="election-panels-track" x-ref="voteTrack" x-bind:style="{ transform: 'translateY(-' + trackOffset + 'px)' }">
                        @foreach($positionsWithCandidates as $index => $position)
                            <div data-panel="{{ $index }}" x-bind:class="panelClass({{ $index }})" x-bind:aria-hidden="currentStep !== {{ $index }} ? 'true' : 'false'" class="election-panel px-1 py-4 sm:px-2">
                                <p x-show="currentStep !== {{ $index }}" class="mb-3 flex items-center gap-2 px-4 text-xs font-bold uppercase tracking-[0.12em] text-slate-400 sm:px-6">
                                    <i class="ph {{ $positionIcon($position['key']) }}" aria-hidden="true"></i> Position {{ $index + 1 }} · {{ $position['data']['name'] }}
                                </p>

                                @php
                                    $candidateCount = $position['candidates']->count();
                                    $candidateGridClass = match (true) {
                                        $candidateCount <= 1 => 'grid-cols-1 max-w-md mx-auto',
                                        $candidateCount === 2 => 'grid-cols-1 sm:grid-cols-2 max-w-3xl mx-auto',
                                        $candidateCount === 3 => 'grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 max-w-5xl mx-auto',
                                        default => 'grid-cols-1 sm:grid-cols-2 xl:grid-cols-3',
                                    };
                                @endphp
                                <div class="grid w-full content-start items-stretch justify-center gap-3 p-4 sm:gap-4 sm:p-6 {{ $candidateGridClass }}">
                                    @foreach($position['candidates'] as $candidate)
                                        <label class="candidate-choice group relative block cursor-pointer">
                                            <input type="radio"
                                                   name="votes[{{ $position['key'] }}]"
                                                   value="{{ $candidate->id }}"
                                                   class="peer sr-only"
                                                   required
                                                   x-bind:disabled="currentStep !== {{ $index }} && currentStep < {{ $positionsWithCandidates->count() }}"
                                                   @change="selectCandidate({{ $index }}, @js($candidate->name), {{ $candidate->id }})">
                                            <span class="block h-full rounded-lg border border-slate-200 bg-white p-4 transition duration-150 group-hover:border-slate-300 group-hover:bg-slate-50 peer-focus-visible:ring-2 peer-focus-visible:ring-slate-400" :class="selectedIds[{{ $index }}] === {{ $candidate->id }} ? 'border-[var(--primary-color)] bg-slate-50 ring-1 ring-[var(--primary-color)]' : ''">
                                                <span class="flex items-start gap-3">
                                                    @if($candidate->photo)
                                                        <button type="button" @click.stop.prevent="openImagePreview(@js(storage_url($candidate->photo)), @js($candidate->name))" class="candidate-photo-trigger shrink-0 cursor-zoom-in rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary-color)] focus-visible:ring-offset-2" aria-label="View {{ $candidate->name }}'s photo larger">
                                                            <img src="{{ storage_url($candidate->photo) }}" alt="{{ $candidate->name }}" class="h-12 w-12 rounded-md border border-slate-200 object-cover transition duration-150 hover:opacity-85">
                                                        </button>
                                                    @else
                                                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-500"><i class="ph ph-user" aria-hidden="true"></i></span>
                                                    @endif
                                                    <span class="min-w-0 flex-1 pt-0.5">
                                                        <span class="block break-words text-sm font-bold text-slate-900">{{ $candidate->name }}</span>
                                                        @if($candidate->year_level)
                                                            <span class="mt-1 block text-xs text-slate-500">{{ $candidate->year_level }}</span>
                                                        @endif
                                                    </span>
                                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-slate-300 text-[10px] text-transparent transition" :class="selectedIds[{{ $index }}] === {{ $candidate->id }} ? 'border-[var(--primary-color)] bg-[var(--primary-color)] text-white' : ''">
                                                        <i class="ph ph-check" aria-hidden="true"></i>
                                                    </span>
                                                </span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                            <div data-panel="{{ $positionsWithCandidates->count() }}" x-bind:class="panelClass({{ $positionsWithCandidates->count() }})" x-bind:aria-hidden="currentStep !== {{ $positionsWithCandidates->count() }} ? 'true' : 'false'" class="election-panel px-1 py-4 sm:px-2">
                                <div class="grid gap-3 px-4 sm:grid-cols-2 sm:px-6">
                                    @foreach($positionsWithCandidates as $index => $position)
                                        <button type="button" @click="goToStep({{ $index }})" class="flex items-center gap-3 rounded-lg border border-slate-200 px-4 py-3 text-left transition hover:border-slate-300 hover:bg-slate-50">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-slate-100 text-xs" style="color: var(--primary-color);"><i class="ph {{ $positionIcon($position['key']) }} student-election-symbol student-election-symbol-sm" aria-hidden="true"></i></span>
                                            <span class="min-w-0">
                                                <span class="block text-xs font-semibold text-slate-500">{{ $position['data']['name'] }}</span>
                                                <span class="block truncate text-sm font-bold text-slate-900" x-text="selectedCandidates[{{ $index }}]"></span>
                                            </span>
                                            <i class="ph ph-pencil-simple ml-auto text-xs" style="color: var(--primary-color);" aria-label="Edit selection"></i>
                                        </button>
                                    @endforeach
                                </div>
                                <div class="mx-4 mb-2 mt-4 flex gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 sm:mx-6">
                                    <i class="ph ph-shield-check mt-0.5 text-slate-500" aria-hidden="true"></i>
                                    <p><strong class="text-slate-900">Your vote is final.</strong> Check each selection before submitting. You will not be able to edit your vote afterward.</p>
                                </div>
                            </div>
                                </div>
                            </div>
                        </section>

                    </main>

                    <aside x-ref="ballotAside" class="hidden lg:sticky lg:top-5 lg:block lg:self-start">
                        <div class="election-section overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" style="--election-card-index: 1;">
                            <div class="border-b border-slate-100 px-5 py-4">
                                <h2 class="font-bold text-slate-900">Your ballot</h2>
                                <p class="mt-1 text-xs leading-5 text-slate-500">Completed positions are marked as you move through the ballot.</p>
                            </div>
                            <div class="p-2">
                                @foreach($positionsWithCandidates as $index => $position)
                                    <button type="button" @click="goToStep({{ $index }})" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition hover:bg-slate-50" :class="currentStep === {{ $index }} ? 'bg-slate-50' : ''">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-xs transition" :class="selectedPositions[{{ $index }}] ? 'border-[var(--primary-color)] bg-slate-100 text-slate-700' : 'border-slate-200 bg-white text-slate-400'">
                                            <i :class="selectedPositions[{{ $index }}] ? 'ph ph-check' : 'ph ph-minus'" aria-hidden="true"></i>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-xs font-semibold text-slate-700">{{ $position['data']['name'] }}</span>
                                            <span x-show="selectedPositions[{{ $index }}]" x-text="selectedCandidates[{{ $index }}]" class="block truncate text-xs text-slate-500"></span>
                                        </span>
                                        <i x-show="currentStep === {{ $index }}" class="ph ph-caret-right text-[10px]" style="color: var(--primary-color);" aria-hidden="true"></i>
                                    </button>
                                @endforeach
                            </div>
                            <div class="border-t border-slate-100 bg-slate-50 p-4">
                                <button type="button" @click="goToReview()" :disabled="!allPositionsSelected()" class="flex w-full items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-bold transition" :class="allPositionsSelected() ? 'bg-slate-900 text-white hover:bg-slate-800' : 'cursor-not-allowed bg-slate-200 text-slate-400'">Review ballot <i class="ph ph-arrow-right text-xs" aria-hidden="true"></i></button>
                            </div>
                        </div>
                    </aside>
                </div>
            </form>
        @elseif($hasVoted)
            <section class="election-section mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" style="--election-card-index: 1;">
                <header class="flex flex-col gap-3 border-b border-slate-100 px-5 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg" style="background-color: rgba(var(--primary-color-rgb), 0.10); color: var(--primary-color);"><i class="ph ph-check-circle" aria-hidden="true"></i></span>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.12em]" style="color: var(--primary-color);">Ballot receipt</p>
                            <h2 class="text-xl font-bold text-slate-950">Your vote has been recorded</h2>
                        </div>
                    </div>
                    <p class="text-sm text-slate-500">Thank you for participating.</p>
                </header>
                <div class="grid gap-3 p-4 sm:grid-cols-2 sm:p-6 lg:grid-cols-3">
                    @foreach($positions as $positionKey => $position)
                        @php($vote = $userVotes->firstWhere('candidate.position', $positionKey))
                        @if($vote?->candidate)
                            <div class="flex items-center gap-3 rounded-lg border border-slate-200 p-4">
                                @if($vote->candidate->photo)
                                    <button type="button" @click="openImagePreview(@js(storage_url($vote->candidate->photo)), @js($vote->candidate->name))" class="candidate-photo-trigger shrink-0 cursor-zoom-in rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary-color)] focus-visible:ring-offset-2" aria-label="View {{ $vote->candidate->name }}'s photo larger">
                                        <img src="{{ storage_url($vote->candidate->photo) }}" alt="{{ $vote->candidate->name }}" class="h-10 w-10 rounded-md border border-slate-200 object-cover transition duration-150 hover:opacity-85">
                                    </button>
                                @else
                                    <span class="flex h-10 w-10 items-center justify-center rounded-md bg-slate-100 text-slate-500"><i class="ph ph-user" aria-hidden="true"></i></span>
                                @endif
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-slate-500">{{ $position['name'] }}</p>
                                    <p class="truncate text-sm font-bold text-slate-900">{{ $vote->candidate->name }}</p>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
                <footer class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                    <p class="text-sm text-slate-600">Results will be available after the election closes on <strong class="text-slate-800">{{ $election->end_date->format('F j, Y \a\t g:i A') }}</strong>.</p>
                    <a href="{{ route('elections.index') }}" data-student-navigation class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-bold text-white transition hover:opacity-90" style="background-color: var(--primary-color);"><i class="ph ph-arrow-left text-xs" aria-hidden="true"></i> Back to Elections</a>
                </footer>
            </section>
        @else
            <section class="election-section mt-5 grid overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm lg:grid-cols-[minmax(0,1fr)_19rem]" style="--election-card-index: 1;">
                <div class="p-6 sm:p-8">
                    <span class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg" style="background-color: rgba(var(--primary-color-rgb), 0.10); color: var(--primary-color);"><i class="ph {{ $isUpcoming ? 'ph-clock' : ($isEnded ? 'ph-flag-checkered' : 'ph-info') }}" aria-hidden="true"></i></span>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-950">
                        @if($isUpcoming) Voting has not opened yet
                        @elseif($isEnded) This election has ended
                        @else Voting is currently unavailable
                        @endif
                    </h2>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-slate-600">
                        @if($isUpcoming) Come back when the voting period begins. You will be able to review candidates and submit one ballot for every available position.
                        @elseif($isEnded && $election->results_published) The election period has finished and official results are available from the Elections page.
                        @elseif($isEnded) The election period has finished. Results will appear once they are published by the organization.
                        @else This election is not active at the moment. Please check the election schedule or contact your organization for assistance.
                        @endif
                    </p>
                    <a href="{{ route('elections.index') }}" data-student-navigation class="mt-6 inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-bold text-white transition hover:opacity-90" style="background-color: var(--primary-color);"><i class="ph ph-arrow-left text-xs" aria-hidden="true"></i> View all elections</a>
                </div>
                <div class="border-t border-slate-100 bg-slate-50 p-6 lg:border-l lg:border-t-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em]" style="color: var(--primary-color);">Election schedule</p>
                    <dl class="mt-5 space-y-5 text-sm">
                        <div><dt class="text-slate-500">Voting opens</dt><dd class="mt-1 font-bold text-slate-800">{{ $election->start_date->format('F j, Y · g:i A') }}</dd></div>
                        <div><dt class="text-slate-500">Voting closes</dt><dd class="mt-1 font-bold text-slate-800">{{ $election->end_date->format('F j, Y · g:i A') }}</dd></div>
                    </dl>
                </div>
            </section>
        @endif
        <div x-show="previewImageUrl" x-cloak x-transition.opacity class="election-preview-backdrop fixed inset-0 z-[1000] flex items-center justify-center p-4 sm:p-8" role="dialog" aria-modal="true" aria-label="Candidate photo preview" @click.self="closeImagePreview()">
            <div x-transition.scale.95 class="relative flex max-h-full max-w-full flex-col items-center gap-3">
                <button type="button" @click="closeImagePreview()" class="absolute -right-2 -top-2 z-10 flex h-9 w-9 items-center justify-center rounded-full border border-white/20 bg-white text-slate-700 shadow-lg transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-white" aria-label="Close photo preview">
                    <i class="ph ph-x" aria-hidden="true"></i>
                </button>
                <img :src="previewImageUrl" :alt="previewImageAlt" class="max-h-[82vh] max-w-[min(92vw,48rem)] rounded-xl bg-white object-contain p-1 shadow-2xl">
                <p x-text="previewImageAlt" class="max-w-full truncate text-center text-sm font-semibold text-white"></p>
            </div>
        </div>
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

    .student-election-page .border.border-slate-200.bg-white {
        border: 0 !important;
        box-shadow: 3px 3px 6px rgba(163, 177, 198, .45), -3px -3px 6px rgba(255, 255, 255, .9) !important;
    }

    .student-election-page .student-election-wizard-stage { scroll-margin-top: 82px; }
    .student-election-symbol { display: inline-flex; align-items: center; justify-content: center; font-size: 20px; line-height: 1; }
    .student-election-symbol-sm { font-size: 16px; }

    [x-cloak] { display: none !important; }
    .candidate-photo-trigger {
        position: relative;
        z-index: 0;
    }
    .candidate-photo-trigger img {
        transform: scale(1);
        transform-origin: center;
        transition: transform 220ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 220ms cubic-bezier(0.22, 1, 0.36, 1), opacity 150ms ease;
    }
    .election-panels-viewport { overflow: hidden; position: relative; min-height: 24rem; }
    .election-panels-track {
        position: relative;
        transition: transform 480ms cubic-bezier(0.22, 1, 0.36, 1);
        will-change: transform;
    }
    .election-panel { transition: opacity 400ms ease, filter 400ms ease; }
    .election-panel.is-current { opacity: 1; filter: blur(0); }
    .election-panel.is-next { opacity: .55; filter: blur(6px); pointer-events: none; user-select: none; }
    .election-panel.is-done { opacity: .5; filter: blur(4px); pointer-events: none; user-select: none; }
    @media (hover: hover) and (pointer: fine) {
        .candidate-photo-trigger:hover { z-index: 2; }
        .candidate-photo-trigger:hover img {
            transform: scale(1.18);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.16);
        }
    }
    .candidate-choice input:focus-visible + span { outline: 2px solid rgba(var(--primary-color-rgb), 0.45); outline-offset: 3px; }

    /* Neumorphism surfaces */
    .student-election-page .candidate-choice > span.block {
        border: 0 !important;
        border-radius: 1.25rem !important;
        background: var(--neu-bg) !important;
        box-shadow: 6px 6px 12px var(--neu-dark-shadow), -6px -6px 12px var(--neu-light-shadow) !important;
        transition: transform .3s cubic-bezier(.2, .8, .2, 1), box-shadow .3s cubic-bezier(.2, .8, .2, 1), border-color .3s ease, background-color .3s ease !important;
    }
    .student-election-page .candidate-choice:hover > span.block {
        transform: translateY(-3px);
        box-shadow: 9px 9px 18px var(--neu-dark-shadow), -9px -9px 18px var(--neu-light-shadow) !important;
    }
    .student-election-page .candidate-choice input:checked + span.block {
        background: rgba(var(--primary-color-rgb, 124, 58, 237), .09) !important;
        box-shadow: inset 4px 4px 9px rgba(var(--primary-color-rgb, 124, 58, 237), .18), inset -4px -4px 9px rgba(255, 255, 255, .9) !important;
    }
    .student-election-page .candidate-choice input:checked + span.block > span > span:last-child {
        border-color: var(--primary-color) !important;
        background-color: var(--primary-color) !important;
    }
    .student-election-page .candidate-photo-trigger img { border: 0 !important; border-radius: .85rem !important; box-shadow: 3px 3px 7px rgba(163, 177, 198, .5), -3px -3px 7px rgba(255, 255, 255, .9) !important; }

    .student-election-page .student-election-wizard-stage section.rounded-xl {
        border: 0 !important;
        border-radius: 1.5rem !important;
        background: var(--neu-bg) !important;
        box-shadow: 10px 10px 20px var(--neu-dark-shadow), -10px -10px 20px var(--neu-light-shadow) !important;
    }
    .student-election-page .student-election-wizard-stage footer button[type="button"] {
        border-radius: .9rem !important;
        background: transparent !important;
        color: var(--neu-muted) !important;
    }
    .student-election-page .student-election-wizard-stage footer button[type="button"]:hover {
        background: rgba(var(--primary-color-rgb, 124, 58, 237), .07) !important;
        color: var(--neu-text) !important;
    }

    .student-election-page .election-section button.rounded-lg.border,
    .student-election-page .election-section div.rounded-lg.border,
    .student-election-page .election-section label.rounded-lg.border {
        border: 0 !important;
        border-radius: 1rem !important;
        background: var(--neu-bg) !important;
        box-shadow: 5px 5px 11px var(--neu-dark-shadow), -5px -5px 11px var(--neu-light-shadow) !important;
    }
    .student-election-page .election-section button.rounded-lg.border:hover {
        background: rgba(var(--primary-color-rgb, 124, 58, 237), .06) !important;
    }

    .student-election-page .h-1\.5.overflow-hidden.rounded-full.bg-slate-100 {
        background: rgba(163, 177, 198, .4) !important;
        box-shadow: inset 2px 2px 4px rgba(163, 177, 198, .45), inset -2px -2px 4px rgba(255, 255, 255, .8) !important;
    }

    .student-election-page .election-back-btn {
        gap: .1rem;
        padding: .55rem .95rem;
        border: 0;
        border-radius: 999px;
        background: var(--neu-bg);
        box-shadow: 4px 4px 10px var(--neu-dark-shadow), -4px -4px 10px var(--neu-light-shadow);
        color: var(--neu-text) !important;
        font-weight: 600;
        transition: transform .25s cubic-bezier(.2, .8, .2, 1), box-shadow .25s cubic-bezier(.2, .8, .2, 1), background-color .25s ease !important;
    }
    .election-back-btn:hover, .election-back-btn:focus-visible {
        transform: translateY(-2px);
        background: var(--neu-bg);
        box-shadow: 6px 6px 14px var(--neu-dark-shadow), -6px -6px 14px var(--neu-light-shadow);
        color: var(--neu-text) !important;
    }
    .election-back-btn:focus-visible { outline: 3px solid rgba(var(--primary-color-rgb), .35); outline-offset: 3px; }

    .election-preview-backdrop {
        background: rgba(17, 17, 19, .46) !important;
        -webkit-backdrop-filter: blur(4px);
        backdrop-filter: blur(4px);
    }

    .student-election-page footer button[type="button"] { border-radius: .9rem !important; }

    .student-election-page > .mx-auto { padding-top: 1.5rem !important; padding-bottom: 2rem !important; }

    @media (prefers-reduced-motion: reduce) {
        .student-election-featured, .election-section { animation: none !important; }
        .candidate-choice > span.block, .election-back-btn { transition-duration: .01ms !important; }
        .candidate-choice span, .candidate-photo-trigger img, .election-panels-track, .election-panel, [x-transition] { transition: none !important; }
        .election-panel.is-next, .election-panel.is-done { filter: none !important; }
    }

    @keyframes election-card-in { from { opacity: 0; transform: translateY(14px) scale(.99); } to { opacity: 1; transform: translateY(0) scale(1); } }

    @media (max-width: 640px) {
        .student-election-page { min-height: auto !important; margin-top: -1rem; margin-bottom: -1rem; }
        .student-election-page > .mx-auto { padding-top: 1.25rem !important; padding-bottom: 1.75rem !important; }
    }
</style>

<script>
    function candidateImagePreview() {
        return {
            previewImageUrl: '',
            previewImageAlt: '',

            openImagePreview(url, alt) {
                this.previewImageUrl = url;
                this.previewImageAlt = alt;
            },

            closeImagePreview() {
                this.previewImageUrl = '';
                this.previewImageAlt = '';
            },
        };
    }

    function votingWizard() {
        return {
            direction: 'forward',
            currentStep: 0,
            selectedPositions: Array({{ $positionsWithCandidates->count() }}).fill(false),
            selectedCandidates: Array({{ $positionsWithCandidates->count() }}).fill(''),
            selectedIds: Array({{ $positionsWithCandidates->count() }}).fill(null),
            stageMinHeight: null,
            trackOffset: 0,
            viewportHeight: null,
            positionsMeta: @js($positionsMeta),

            init() {
                this.syncLayout();
            },

            syncLayout() {
                this.syncStageHeight();
                this.syncPanels();
            },

            panelClass(index) {
                if (index === this.currentStep) return 'election-panel is-current';
                return 'election-panel' + (index < this.currentStep ? ' is-done' : ' is-next');
            },

            syncPanels() {
                this.$nextTick(() => {
                    const track = this.$refs.voteTrack;
                    const panels = track ? [...track.querySelectorAll('[data-panel]')] : [];
                    const current = panels[this.currentStep];
                    if (!current) {
                        this.trackOffset = 0;
                        return;
                    }
                    this.trackOffset = current.offsetTop;
                    const header = this.$refs.voteCardHeader;
                    const aside = this.$refs.ballotAside;
                    if (aside && header && window.innerWidth >= 1024) {
                        this.viewportHeight = Math.max(320, aside.offsetHeight - header.offsetHeight) + 'px';
                    } else {
                        const peek = this.currentStep < this.positionsMeta.length ? 160 : 0;
                        this.viewportHeight = Math.max(384, current.offsetHeight + peek) + 'px';
                    }
                });
            },

            syncStageHeight() {
                this.$nextTick(() => {
                    const aside = this.$refs.ballotAside;
                    if (aside && window.innerWidth >= 1024) {
                        this.stageMinHeight = aside.offsetHeight + 'px';
                    } else {
                        this.stageMinHeight = null;
                    }
                });
            },

            selectCandidate(index, candidateName, candidateId) {
                this.selectedPositions[index] = true;
                this.selectedCandidates[index] = candidateName;
                this.selectedIds[index] = candidateId;
                this.syncLayout();
            },

            selectedCount() { return this.selectedPositions.filter(Boolean).length; },
            allPositionsSelected() { return this.selectedPositions.every(Boolean); },
            progressPositionCount() { return this.selectedCount(); },
            progressPercentage() { return (this.progressPositionCount() / {{ max($positionsWithCandidates->count(), 1) }}) * 100; },

            nextStep() {
                if (!this.selectedPositions[this.currentStep]) return;
                if (this.currentStep < {{ $positionsWithCandidates->count() - 1 }}) this.goToStep(this.currentStep + 1);
                else this.goToReview();
            },

            previousStep() {
                if (this.currentStep > 0) this.goToStep(this.currentStep - 1);
            },

            goToStep(step) {
                this.direction = step < this.currentStep ? 'backward' : 'forward';
                this.currentStep = step;
                this.$nextTick(() => {
                    window.requestAnimationFrame(() => this.scrollToWizardStage());
                });
                this.syncLayout();
            },

            goToReview() {
                if (this.allPositionsSelected()) this.goToStep({{ $positionsWithCandidates->count() }});
            },

            scrollToWizardStage() {
                if (window.innerWidth >= 1024 || !this.$refs.wizardStage) return;

                const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                const headerOffset = 82;
                const top = this.$refs.wizardStage.getBoundingClientRect().top + window.scrollY - headerOffset;

                window.scrollTo({
                    top: Math.max(top, 0),
                    behavior: reducedMotion ? 'auto' : 'smooth',
                });
            }
        };
    }
</script>
@endsection
