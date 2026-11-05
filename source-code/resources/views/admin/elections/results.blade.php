@extends(request()->boolean('fragment') ? 'layouts.fragment' : 'layouts.admin')

@section('title', 'Election Results - Admin')

@section('content')
@php
    $turnoutRate = $registeredVoters > 0 ? ($totalVoters / $registeredVoters) * 100 : 0;
    $candidateCount = $election->candidates->count();
    $isFinal = $election->end_date->isPast();
    $isPosted = (bool) $election->is_posted;
    $canManageElections = app(\App\Services\PermissionHelper::class)->hasFullAccess('elections');
@endphp

<div class="px-6 py-5">
    <header class="mb-4 border-b border-gray-200 pb-4">
        <a href="{{ route('admin.elections.index') }}" data-admin-navigation-loading aria-label="Election Operations" class="mb-3 inline-flex items-center text-xs font-semibold transition hover:opacity-75" style="color: var(--primary-color);">
            <i class="ph ph-arrow-left mr-1.5"></i>Election Operations
        </a>

        <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="truncate text-xl font-bold tracking-tight text-gray-900">{{ $election->title }}</h1>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ !$isPosted || $isFinal ? 'bg-gray-100 text-gray-700' : 'bg-green-50 text-green-700' }}">
                        <i class="ph {{ !$isPosted ? 'ph-file-text' : ($isFinal ? 'ph-flag-checkered' : 'ph-circle animate-pulse') }} mr-1"></i>{{ !$isPosted ? 'Not posted' : ($isFinal ? 'Final count' : 'Live count') }}
                    </span>
                    @if($election->results_published)
                        <span class="rounded-full bg-green-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-green-700"><i class="ph ph-check mr-1"></i>Published</span>
                    @endif
                </div>
                <p class="mt-1 max-w-4xl text-xs text-gray-500">{{ $election->description }}</p>
            </div>
            <p class="text-[11px] text-gray-500"><i class="ph ph-calendar-blank mr-1 text-gray-400"></i>{{ $election->start_date->format('M d, Y h:i A') }} <span class="mx-1.5 text-gray-300">—</span> {{ $election->end_date->format('M d, Y h:i A') }}</p>
        </div>
    </header>

    <section aria-label="Election summary" class="mb-4 grid grid-cols-2 divide-x divide-y divide-gray-200 overflow-hidden rounded-md border border-gray-200 bg-white md:grid-cols-4 md:divide-y-0">
        <div class="px-3 py-2.5"><p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Registered</p><p class="mt-0.5 text-lg font-bold leading-none text-gray-900">{{ number_format($registeredVoters) }}</p></div>
        <div class="px-3 py-2.5"><p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Voters</p><p class="mt-0.5 text-lg font-bold leading-none text-gray-900">{{ number_format($totalVoters) }}</p></div>
        <div class="px-3 py-2.5"><p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Turnout</p><p class="mt-0.5 text-lg font-bold leading-none text-gray-900">{{ number_format($turnoutRate, 1) }}<span class="text-xs">%</span></p></div>
        <div class="px-3 py-2.5"><p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Candidates</p><p class="mt-0.5 text-lg font-bold leading-none text-gray-900">{{ number_format($candidateCount) }}</p></div>
    </section>

    @if($canManageElections && $isFinal && !$election->results_published)
        <section class="mb-4 flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-2.5">
                <i class="ph ph-megaphone mt-0.5 text-amber-600"></i>
                <div>
                    <h2 class="text-xs font-bold text-gray-900">Ready for publication</h2>
                    <p class="mt-0.5 text-xs text-gray-600">Review the final rankings below before notifying students.</p>
                </div>
            </div>
            <button type="button" onclick="openPublishModal()" class="shrink-0 rounded-md bg-green-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-green-700"><i class="ph ph-check-circle mr-1.5"></i>Publish results</button>
        </section>
    @elseif($election->results_published)
        <section class="mb-4 flex items-center gap-2.5 rounded-md border border-green-200 bg-green-50 px-4 py-3">
            <i class="ph ph-check-circle text-green-600"></i>
            <p class="text-xs text-gray-700"><strong class="text-gray-900">Results are published.</strong> Students can now view the verified final count.</p>
        </section>
    @endif

    <div class="grid grid-cols-1 gap-4 2xl:grid-cols-[minmax(0,1fr)_17rem]">
        <section aria-labelledby="rankings-heading" class="min-w-0">
            <div class="mb-2 flex items-center justify-between">
                <h2 id="rankings-heading" class="text-sm font-bold text-gray-900">Position rankings</h2>
                <p class="text-[11px] text-gray-400">{{ count($results) }} positions · ranked by vote total</p>
            </div>

            <div class="grid grid-cols-1 gap-3 xl:grid-cols-2">
                @foreach($results as $posKey => $posData)
                    @php
                        $totalPositionVotes = $posData['candidates']->sum('vote_count');
                        $leader = $posData['candidates']->first();
                    @endphp
                    <article class="overflow-hidden rounded-md border border-gray-200 bg-white">
                        <header class="flex items-center justify-between border-b border-gray-100 px-3 py-2">
                            <div class="min-w-0">
                                <h3 class="truncate text-xs font-bold text-gray-900">{{ $posData['name'] }}</h3>
                                <p class="mt-0.5 text-[10px] text-gray-400">{{ $posData['candidates']->count() }} candidate{{ $posData['candidates']->count() === 1 ? '' : 's' }}</p>
                            </div>
                            <span class="ml-3 shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold text-gray-600">{{ number_format($totalPositionVotes) }} votes</span>
                        </header>

                        <div class="divide-y divide-gray-100">
                            @forelse($posData['candidates'] as $index => $candidate)
                                @php
                                    $percentage = $totalPositionVotes > 0 ? ($candidate->vote_count / $totalPositionVotes) * 100 : 0;
                                    $isLeader = $leader && $candidate->id === $leader->id;
                                @endphp
                                <div class="relative overflow-hidden px-3 py-2">
                                    <div class="absolute inset-y-0 left-0 bg-gray-50" style="width: {{ $percentage }}%;"></div>
                                    <div class="relative flex items-center gap-2.5">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded text-[10px] font-bold {{ $isLeader ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500' }}">
                                            @if($isLeader)<i class="ph ph-crown text-[9px]"></i>@else{{ $index + 1 }}@endif
                                        </span>
                                        @if($candidate->photo)
                                            <img src="{{ storage_url($candidate->photo) }}" alt="" class="h-7 w-7 shrink-0 rounded object-cover ring-1 ring-gray-200">
                                        @else
                                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded bg-gray-100 text-gray-400"><i class="ph ph-user text-[10px]"></i></span>
                                        @endif
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-xs font-semibold text-gray-800">{{ $candidate->name }}</p>
                                            @if($isLeader)
                                                <p class="text-[10px] font-semibold {{ $isFinal ? 'text-amber-700' : 'text-green-700' }}">{{ $isFinal ? 'Current winner' : 'Current leader' }}</p>
                                            @endif
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <p class="text-xs font-bold text-gray-900">{{ number_format($candidate->vote_count) }}</p>
                                            <p class="text-[10px] text-gray-500">{{ number_format($percentage, 1) }}%</p>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="px-3 py-5 text-center text-xs text-gray-400">No candidates were added for this position.</div>
                            @endforelse
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <aside class="space-y-3 2xl:sticky 2xl:top-20 2xl:self-start">
            <section class="rounded-md border border-gray-200 bg-white p-3">
                <h2 class="text-xs font-bold text-gray-900">Count status</h2>
                <dl class="mt-3 space-y-2.5 text-xs">
                    <div class="flex items-center justify-between gap-3"><dt class="text-gray-500">Election phase</dt><dd class="font-semibold text-gray-900">{{ $isFinal ? 'Completed' : 'In progress' }}</dd></div>
                    <div class="flex items-center justify-between gap-3"><dt class="text-gray-500">Publication</dt><dd class="font-semibold {{ $election->results_published ? 'text-green-700' : 'text-amber-700' }}">{{ $election->results_published ? 'Released' : 'Internal only' }}</dd></div>
                    <div class="flex items-center justify-between gap-3"><dt class="text-gray-500">Reporting units</dt><dd class="font-semibold text-gray-900">{{ count($results) }} positions</dd></div>
                </dl>
            </section>

            <section class="rounded-md border border-gray-200 bg-white p-3">
                <h2 class="text-xs font-bold text-gray-900">Turnout</h2>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full" style="width: {{ min($turnoutRate, 100) }}%; background-color: var(--primary-color);"></div>
                </div>
                <p class="mt-2 text-xs text-gray-600"><strong class="text-gray-900">{{ number_format($totalVoters) }}</strong> of {{ number_format($registeredVoters) }} registered voters participated.</p>
            </section>

            @if($election->secretary)
                <section class="rounded-md border border-green-200 bg-green-50 p-3">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-green-700">Assigned secretary</p>
                    <p class="mt-1 text-xs font-bold text-gray-900">{{ $election->secretary->name }}</p>
                </section>
            @endif
        </aside>
    </div>
</div>

<div id="publishModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 modal-backdrop p-4" role="dialog" aria-modal="true" aria-labelledby="publishModalTitle">
    <div class="w-full max-w-sm rounded-md bg-white shadow-xl">
        <div class="p-5">
            <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-md bg-amber-100 text-amber-600"><i class="ph ph-megaphone"></i></div>
            <h2 id="publishModalTitle" class="text-sm font-bold text-gray-900">Publish final results?</h2>
            <p class="mt-1.5 text-xs leading-relaxed text-gray-600">This will make the final results visible to students and send notification emails.</p>
            <div id="publishProgress" class="mt-3 hidden rounded-md border border-blue-100 bg-blue-50 px-3 py-2.5" role="status" aria-live="polite">
                <div class="flex items-start gap-2">
                    <i class="publish-progress-icon ph ph-spinner ph-spin mt-0.5 text-blue-600"></i>
                    <p class="publish-progress-message text-xs leading-relaxed text-blue-800">Publishing the results and sending email notifications. Please keep this window open.</p>
                </div>
            </div>
            <form action="{{ route('admin.elections.publish-results', $election) }}" method="POST" class="mt-4 flex gap-2" onsubmit="handlePublishSubmit(this)">
                @csrf
                <button type="button" onclick="closePublishModal()" class="publish-cancel flex-1 rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-50">Cancel</button>
                <button type="submit" class="publish-submit flex-1 rounded-md bg-green-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-green-700"><i class="ph ph-check mr-1"></i><span>Publish</span></button>
            </form>
        </div>
    </div>
</div>

<script>
function openPublishModal() {
    document.getElementById('publishModal')?.classList.remove('hidden');
}

function closePublishModal() {
    const form = document.querySelector('#publishModal form');
    if (form?.dataset.submitting === 'true') return;

    document.getElementById('publishModal')?.classList.add('hidden');
}

function handlePublishSubmit(form) {
    if (form.dataset.submitting === 'true') return false;

    form.dataset.submitting = 'true';
    const modal = document.getElementById('publishModal');
    const submitButton = form.querySelector('.publish-submit');
    const cancelButton = form.querySelector('.publish-cancel');
    const progress = document.getElementById('publishProgress');
    const progressIcon = progress?.querySelector('.publish-progress-icon');
    const progressMessage = progress?.querySelector('.publish-progress-message');

    submitButton.disabled = true;
    cancelButton.disabled = true;
    submitButton.classList.add('cursor-not-allowed', 'opacity-75');
    cancelButton.classList.add('cursor-not-allowed', 'opacity-50');
    submitButton.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i><span>Publishing...</span>';
    modal?.setAttribute('aria-busy', 'true');
    progress?.classList.remove('hidden');

    fetch(form.action, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value, 'Accept': 'application/json' },
        body: new FormData(form),
    }).then(async response => {
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Unable to publish the results.');

        progress?.classList.remove('border-blue-100', 'bg-blue-50');
        progress?.classList.add('border-green-100', 'bg-green-50');
        if (progressIcon) progressIcon.className = 'publish-progress-icon ph ph-check-circle mt-0.5 text-green-600';
        if (progressMessage) {
            progressMessage.className = 'publish-progress-message text-xs leading-relaxed text-green-800';
            progressMessage.textContent = data.message || 'The results were published and email notifications were sent.';
        }
        submitButton.classList.remove('bg-green-600', 'hover:bg-green-700');
        submitButton.classList.add('bg-green-700', 'cursor-not-allowed', 'opacity-75');
        submitButton.innerHTML = '<i class="ph ph-check mr-1"></i><span>Published</span>';
        modal?.setAttribute('aria-busy', 'false');

        window.clearAdminPageCache && window.clearAdminPageCache();
        setTimeout(() => window.refreshCurrentAdminPage && window.refreshCurrentAdminPage(), 1000);
    }).catch(error => {
        progress?.classList.remove('border-blue-100', 'bg-blue-50');
        progress?.classList.add('border-red-100', 'bg-red-50');
        if (progressIcon) progressIcon.className = 'publish-progress-icon ph ph-warning-circle mt-0.5 text-red-600';
        if (progressMessage) {
            progressMessage.className = 'publish-progress-message text-xs leading-relaxed text-red-800';
            progressMessage.textContent = error.message;
        }
        submitButton.disabled = false;
        cancelButton.disabled = false;
        submitButton.classList.remove('cursor-not-allowed', 'opacity-75');
        cancelButton.classList.remove('cursor-not-allowed', 'opacity-50');
        submitButton.innerHTML = '<i class="ph ph-check mr-1"></i><span>Try again</span>';
        form.dataset.submitting = 'false';
        modal?.setAttribute('aria-busy', 'false');
    });

    return false;
}

document.getElementById('publishModal')?.addEventListener('click', function (event) {
    if (event.target === this && document.querySelector('#publishModal form')?.dataset.submitting !== 'true') {
        closePublishModal();
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && document.querySelector('#publishModal form')?.dataset.submitting === 'true') {
        event.preventDefault();
    }
});

// Live Results realtime: refresh when any vote is recorded (election-updated broadcast from vote() or CheckElectionEnd)
(function () {
    if (!window.Echo) return;
    try {
        window.Echo.channel('elections')
            .listen('.election-updated', function () {
                if (window.clearAdminPageCache) window.clearAdminPageCache();
                setTimeout(function () { window.location.reload(); }, 500);
            })
            .listen('.election-created', function () {
                if (window.clearAdminPageCache) window.clearAdminPageCache();
                setTimeout(function () { window.location.reload(); }, 500);
            });
    } catch (e) {
        console.warn('Election realtime subscription failed:', e);
    }
})();
</script>
@endsection
