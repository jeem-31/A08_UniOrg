@extends('layouts.osas')

@section('title', 'Submissions - Reports Compliance')

@section('content')
@php
    $stSubmitted = 0;
    $stSignature = 0;
    $stRejected = 0;
    $stNone = 0;
    $stPending = 0;
    foreach ($rows as $sRow) {
        if ($sRow['pending_application']) {
            $stPending++;
            continue;
        }
        match ($sRow['latest']?->status) {
            'approved_for_signature' => $stSignature++,
            'rejected' => $stRejected++,
            'submitted' => $stSubmitted++,
            default => $stNone++,
        };
    }
@endphp
<div class="w-full max-w-[1400px] mx-auto space-y-4">
    <header class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <nav class="mb-1.5 flex items-center gap-2 text-[11px] font-semibold text-slate-400" aria-label="Breadcrumb">
                <a href="{{ route('osas.dashboard') }}" data-osas-navigation class="text-[#26488C] hover:text-[#1a3461]">OSAS</a>
                <i class="ph ph-caret-right text-[10px] text-slate-300"></i>
                <a href="{{ route('osas.required-documents.index') }}" class="text-[#26488C] hover:text-[#1a3461]">Reports Compliance</a>
                <i class="ph ph-caret-right text-[10px] text-slate-300"></i>
                <span>Submissions</span>
            </nav>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-slate-950 sm:text-2xl">{{ $requirement->displayTitle() }}</h1>
                @if($requirement->deadline_at)<span class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[10px] font-semibold text-slate-500"><i class="ph ph-calendar-dot text-[#C4973B]"></i>{{ $requirement->deadline_at->format('M d, Y') }}</span>@endif
            </div>
            <p class="mt-1 text-xs font-medium text-slate-500">
                {{ $requirement->organization?->name ?? ('All orgs in '.($requirement->department?->department_name ?? 'department')) }}
                @if($requirement->description) • {{ $requirement->description }} @endif
            </p>
        </div>
    </header>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-xs font-semibold text-green-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-800">
            <ul class="list-disc pl-4 space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @include('components.ai-progress-card')

    <section aria-label="Submission summary" class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
        <div class="flex items-center gap-3 rounded-lg border border-blue-200 bg-blue-50/50 px-4 py-3">
            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-md bg-blue-100 text-[#26488C]"><i class="ph ph-hourglass-medium text-base"></i></span>
            <span class="min-w-0">
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-blue-700">Needs review</span>
                <span class="block text-xl font-bold text-blue-800">{{ $stSubmitted }}</span>
            </span>
        </div>
        <div class="flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 px-4 py-3">
            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-md bg-emerald-100 text-emerald-700"><i class="ph ph-seal-check text-base"></i></span>
            <span class="min-w-0">
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-emerald-700">For signature</span>
                <span class="block text-xl font-bold text-emerald-800">{{ $stSignature }}</span>
            </span>
        </div>
        <div class="flex items-center gap-3 rounded-lg border border-red-200 bg-red-50/50 px-4 py-3">
            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-md bg-red-100 text-red-600"><i class="ph ph-arrow-counter-clockwise text-base"></i></span>
            <span class="min-w-0">
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-red-700">Rejected</span>
                <span class="block text-xl font-bold text-red-800">{{ $stRejected }}</span>
            </span>
        </div>
        <div class="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50/50 px-4 py-3">
            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-md bg-amber-100 text-amber-700"><i class="ph ph-file-dashed text-base"></i></span>
            <span class="min-w-0">
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-amber-700">No submission</span>
                <span class="block text-xl font-bold text-amber-800">{{ $stNone }}</span>
            </span>
        </div>
        <div class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-md bg-slate-200 text-slate-500"><i class="ph ph-clock text-base"></i></span>
            <span class="min-w-0">
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Pending apps</span>
                <span class="block text-xl font-bold text-slate-700">{{ $stPending }}</span>
            </span>
        </div>
    </section>

    <div class="flex flex-wrap items-center gap-1.5">
        <div class="relative min-w-52 flex-1 sm:max-w-xs">
            <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i>
            <input type="search" id="submissionSearch" oninput="applySubmissionFilters()" placeholder="Search org or application…" class="w-full rounded-full border border-slate-200 bg-white py-1.5 pl-9 pr-3 text-[11px] font-semibold text-slate-600 placeholder:text-slate-400 focus:border-[#26488C] focus:outline-none">
        </div>
        <button type="button" data-submission-filter="all" onclick="setSubmissionStatus('all', this)" class="rounded-full bg-[#26488C] px-3 py-1.5 text-[11px] font-bold text-white transition">All</button>
        <button type="button" data-submission-filter="submitted" onclick="setSubmissionStatus('submitted', this)" class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-500 transition hover:border-[#26488C] hover:text-[#26488C]">Needs review ({{ $stSubmitted }})</button>
        <button type="button" data-submission-filter="approved_for_signature" onclick="setSubmissionStatus('approved_for_signature', this)" class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-500 transition hover:border-[#26488C] hover:text-[#26488C]">For signature ({{ $stSignature }})</button>
        <button type="button" data-submission-filter="rejected" onclick="setSubmissionStatus('rejected', this)" class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-500 transition hover:border-[#26488C] hover:text-[#26488C]">Rejected ({{ $stRejected }})</button>
        <button type="button" data-submission-filter="none" onclick="setSubmissionStatus('none', this)" class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-500 transition hover:border-[#26488C] hover:text-[#26488C]">No submission ({{ $stNone }})</button>
        <button type="button" data-submission-filter="pending" onclick="setSubmissionStatus('pending', this)" class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-500 transition hover:border-[#26488C] hover:text-[#26488C]">Pending apps ({{ $stPending }})</button>
        <span id="submissionCount" class="ml-auto text-[11px] font-medium text-slate-400"></span>
    </div>

    <div class="space-y-3" id="submissionList">
        @forelse($rows as $row)
            @if($row['pending_application'])
                @php $app = $row['pending_application']; @endphp
                <article data-status="pending" data-name="{{ strtolower($app->organization_name.' '.($app->organization_acronym ?? '')) }}" class="rounded-lg border border-dashed border-slate-300 bg-slate-50/60 p-4">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 shrink-0 rounded-lg bg-slate-100 text-slate-400 flex items-center justify-center"><i class="ph ph-hourglass-medium"></i></div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-bold text-slate-700">{{ $app->organization_name }}@if($app->organization_acronym) ({{ $app->organization_acronym }})@endif</h3>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md border border-amber-200 bg-amber-50 text-[10px] font-semibold text-amber-700">Applied — pending approval</span>
                            </div>
                            <p class="mt-1 text-[11px] text-slate-400">
                                {{ $app->application_type === 'renew' ? 'Renewal' : 'Recognition' }} application • {{ $app->status }} • applied {{ $app->created_at?->format('M d, Y') ?? '' }} • cannot submit until approved — this requirement will appear automatically once approved.
                            </p>
                        </div>
                    </div>
                </article>
            @else
            @php
                $latest = $row['latest'];
                $analysis = $row['analysis'];
                $badge = match ($latest?->status) {
                    'approved_for_signature' => ['For Signature', 'bg-green-50 text-green-700 border-green-100'],
                    'rejected' => ['Rejected', 'bg-red-50 text-red-700 border-red-100'],
                    'submitted' => ['Submitted — waiting', 'bg-blue-50 text-blue-700 border-blue-100'],
                    default => ['No submission yet', 'bg-amber-50 text-amber-700 border-amber-100'],
                };
                $aiBadge = $analysis ? match ($analysis['status']) {
                    'completed' => ['Summary ready', 'bg-green-50 text-green-700 border-green-100'],
                    'failed' => ['Processing failed', 'bg-red-50 text-red-700 border-red-100'],
                    'processing' => ['Processing', 'bg-blue-50 text-blue-700 border-blue-100'],
                    default => ['Pending processing', 'bg-amber-50 text-amber-700 border-amber-100'],
                } : null;
            @endphp
            <article data-status="{{ $latest?->status ?? 'none' }}" data-name="{{ strtolower($row['organization']->name) }}" class="rounded-lg border border-slate-200 bg-white p-4" @if($analysis) id="analysis-card-{{ $analysis['analysis_id'] }}" data-analysis-id="{{ $analysis['analysis_id'] }}" @endif>
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start">
                    <div class="flex min-w-0 flex-1 items-start gap-3">
                        <div class="w-9 h-9 shrink-0 rounded-lg bg-blue-50 text-[#26488C] flex items-center justify-center"><i class="ph ph-file-text"></i></div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-bold text-slate-800">{{ $row['organization']->name }}</h3>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md border text-[10px] font-semibold {{ $badge[1] }}">{{ $badge[0] }}</span>
                                @if($aiBadge)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md border text-[10px] font-semibold {{ $aiBadge[1] }}" data-role="status">{{ $aiBadge[0] }}</span>
                                @endif
                            </div>
                            @if($latest)
                                <p class="text-xs font-medium text-slate-500 truncate mt-1" title="{{ $latest->original_filename }}">{{ $latest->original_filename }} • v{{ $latest->version }} • {{ $latest->created_at->format('M d, Y • h:i A') }}</p>
                                @if($latest->remarks)
                                    <p class="mt-1.5 text-[11px] text-slate-500"><span class="font-bold">OSAS note:</span> {{ $latest->remarks }}</p>
                                @endif
                                @if($row['history']->count() > 1)
                                    <details class="mt-1.5">
                                        <summary class="cursor-pointer text-[11px] font-bold text-[#26488C]">Version history ({{ $row['history']->count() }})</summary>
                                        <ul class="mt-1 space-y-1 text-[11px] text-slate-500">
                                            @foreach($row['history'] as $h)
                                                <li>v{{ $h->version }} — {{ $h->original_filename }} — {{ $h->statusLabel() }} — {{ $h->created_at->format('M d, Y • h:i A') }}</li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                            @else
                                <p class="mt-1 text-[11px] text-slate-400">This organization has not submitted anything yet.</p>
                            @endif
                            <p class="hidden mt-2 text-xs" data-role="action-message" aria-live="polite"></p>
                        </div>
                    </div>
                    <div class="w-full lg:w-[300px] lg:shrink-0 space-y-2">
                        @if($analysis)
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" onclick="openFilePreview({{ $analysis['analysis_id'] }})" class="px-2 py-2 bg-white border border-gray-200 text-gray-600 hover:text-[#26488C] hover:border-[#26488C] rounded-md text-[11px] font-bold text-center transition-all">
                                    <i class="ph ph-eye mr-1"></i> View Document
                                </button>
                                @if($analysis['allowed_actions']['view_summary'])
                                    <button type="button" onclick="openAnalysisSurface({{ $analysis['analysis_id'] }}, 'summary')" class="px-2 py-2 bg-blue-50 border border-blue-100 text-[#26488C] rounded-md text-[11px] font-bold text-center hover:bg-blue-100 transition-all">
                                        View Summary
                                    </button>
                                @elseif($analysis['allowed_actions']['retry'])
                                    <button type="button" onclick="retryDocument({{ $analysis['analysis_id'] }}, this)" class="px-2 py-2 bg-red-600 text-white rounded-md text-[11px] font-bold text-center hover:bg-red-700 transition-all">
                                        <i class="ph ph-arrow-clockwise mr-1"></i> Retry Processing
                                    </button>
                                @endif
                            </div>
                            @if($analysis['output_available'])
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" onclick="resummarizeDocument({{ $analysis['analysis_id'] }}, this)" @disabled($analysis['summary_refresh']['in_progress']) class="px-2 py-2 bg-[#26488C] text-white rounded-md text-[11px] font-bold text-center hover:bg-[#1a3461] disabled:opacity-50 disabled:cursor-not-allowed transition-all">
                                        @if($analysis['summary_refresh']['in_progress'])<i class="ph ph-spinner ph-spin mr-1"></i> Refreshing @else<i class="ph ph-magic-wand mr-1"></i> Re-summarize @endif
                                    </button>
                                    <button type="button" onclick="retagDocument({{ $analysis['analysis_id'] }}, this)" class="px-2 py-2 bg-[#26488C] text-white rounded-md text-[11px] font-bold text-center hover:bg-[#1a3461] transition-all">
                                        <i class="ph ph-tag mr-1"></i> Re-tag
                                    </button>
                                </div>
                            @endif
                        @endif
                        @if($latest && $latest->status === 'submitted')
                            <div class="grid grid-cols-2 gap-2">
                                <form method="POST" action="{{ route('osas.required-documents.review', $latest) }}">
                                    @csrf
                                    <button type="submit" name="action" value="approve" class="w-full px-2 py-2 bg-[#C4973B] text-white rounded-md text-[11px] font-bold hover:bg-[#a67d2d] transition-all">For Signature</button>
                                </form>
                                <button type="button" onclick="document.getElementById('reject-box-{{ $latest->id }}').classList.toggle('hidden')" class="px-2 py-2 bg-white border border-red-200 text-red-600 rounded-md text-[11px] font-bold hover:bg-red-50 transition-all">Reject</button>
                            </div>
                            <form method="POST" action="{{ route('osas.required-documents.review', $latest) }}" id="reject-box-{{ $latest->id }}" class="hidden space-y-2 rounded-md border border-red-100 bg-red-50/50 p-2.5">
                                @csrf
                                <input type="hidden" name="action" value="reject">
                                <textarea name="remarks" required rows="2" placeholder="Reason (required)…" class="w-full rounded-md border border-red-200 px-2 py-2 text-[11px]"></textarea>
                                <button type="submit" class="w-full px-2 py-2 bg-red-600 text-white rounded-md text-[11px] font-bold hover:bg-red-700 transition-all">Confirm reject</button>
                            </form>
                        @endif
                    </div>
                </div>
            </article>
            @endif
        @empty
            <div class="rounded-lg border border-slate-200 bg-white p-10 text-center text-sm text-slate-400">No organizations or applications in scope.</div>
        @endforelse
    </div>
</div>

<!-- File preview modal with Document / Tags tabs -->
<div id="filePreviewModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-4xl scale-95 opacity-0 transition-all rounded-lg bg-white shadow-xl overflow-hidden">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
            <div class="flex min-w-0 items-center gap-2.5">
                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-blue-50 text-[#26488C]"><i class="ph ph-file-text"></i></span>
                <span id="previewFileName" class="truncate text-xs font-bold text-slate-700"></span>
            </div>
            <button type="button" onclick="closeFilePreview()" class="shrink-0 rounded-md bg-slate-100 px-2 py-1 text-slate-500 hover:bg-slate-200"><i class="ph ph-x"></i></button>
        </div>
        <div class="flex items-center gap-1 border-b border-slate-100 px-4 pt-2">
            <button type="button" id="previewTabDoc" onclick="switchPreviewTab('doc')" class="rounded-t-md border-b-2 border-[#26488C] px-3 py-1.5 text-[11px] font-bold text-[#26488C]">Document</button>
            <button type="button" id="previewTabTags" onclick="switchPreviewTab('tags')" class="rounded-t-md border-b-2 border-transparent px-3 py-1.5 text-[11px] font-bold text-slate-400 hover:text-slate-600">Tags <span id="previewTagCount" class="ml-0.5 rounded-full bg-slate-100 px-1.5 text-[10px]">0</span></button>
        </div>
        <div id="previewDocPane">
            <div id="previewLoader" class="hidden p-6 text-center text-xs text-slate-400">Loading…</div>
            <iframe id="previewFrame" class="h-[65vh] w-full" title="Document preview"></iframe>
        </div>
        <div id="previewTagsPane" class="hidden max-h-[65vh] overflow-auto p-4">
            <div id="previewTagsContent"></div>
        </div>
    </div>
</div>

<!-- Summary modal (same shape as accreditation workspace) -->
<div id="aiAnalysisModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-2xl scale-95 opacity-0 transition-all rounded-lg bg-white shadow-xl overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <span id="aiModalTitle" class="text-xs font-bold text-slate-700"></span>
            <button type="button" onclick="closeAIModal()" class="rounded-md bg-slate-100 px-2 py-1 text-slate-500 hover:bg-slate-200"><i class="ph ph-x"></i></button>
        </div>
        <div id="aiModalContent" class="max-h-[70vh] overflow-auto p-4"></div>
    </div>
</div>

<script>
const documentAnalyses = new Map(
    @json(collect($rows)->map(fn($r) => $r['analysis'])->filter()->values()->map(fn($a) => [(int) $a['analysis_id'], $a])->values())
);
const csrfToken = @js(csrf_token());

function escapeHtml(value) {
    return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}
function formatTimestamp(value) {
    if (!value) return 'Not available';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
}
let activePreviewAnalysisId = null;
function openFilePreview(analysisId) {
    const analysis = documentAnalyses.get(Number(analysisId));
    if (!analysis) return;
    activePreviewAnalysisId = Number(analysisId);
    const modal = document.getElementById('filePreviewModal');
    document.getElementById('previewFileName').innerText = analysis.original_filename;
    document.getElementById('previewFileName').title = analysis.original_filename;
    renderPreviewTags(analysis);
    switchPreviewTab('doc');
    document.getElementById('previewLoader').classList.remove('hidden');
    document.getElementById('previewFrame').src = analysis.urls.source;
    modal.classList.remove('hidden');
    setTimeout(() => modal.querySelector(':scope > div').classList.remove('scale-95', 'opacity-0'), 10);
}

function renderPreviewTags(analysis) {
    const tags = (analysis.details && Array.isArray(analysis.details.tags)) ? analysis.details.tags : [];
    document.getElementById('previewTagCount').textContent = tags.length;
    const content = document.getElementById('previewTagsContent');
    if (!analysis.output_available || tags.length === 0) {
        content.innerHTML = '<div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-8 text-center"><i class="ph ph-tag text-2xl text-slate-300"></i><p class="mt-2 text-xs font-semibold text-slate-500">No tags yet.</p><p class="mt-0.5 text-[11px] text-slate-400">Tags will appear once automatic processing finishes.</p></div>';
        return;
    }
    const detected = analysis.details && analysis.details.detected_document_type
        ? `<p class="mb-3 text-[11px] text-slate-500">Detected type: <span class="font-bold text-slate-700">${escapeHtml(analysis.details.detected_document_type)}</span></p>`
        : '';
    content.innerHTML = detected + '<div class="flex flex-wrap gap-1.5">' + tags.map(tag =>
        `<span class="inline-flex items-center gap-1 rounded-md border border-blue-100 bg-blue-50 px-2 py-1 text-[11px] font-semibold text-[#26488C]"><i class="ph ph-tag text-[10px]"></i>${escapeHtml(tag)}</span>`
    ).join('') + '</div>';
}

async function refreshAnalysisTags(analysisId) {
    const analysis = documentAnalyses.get(Number(analysisId));
    if (!analysis || !analysis.urls.details) return;
    try {
        const response = await fetch(analysis.urls.details, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) return;
        const details = await response.json().catch(() => null);
        if (!details || !Array.isArray(details.tags)) return;
        analysis.details = { ...(analysis.details || {}), tags: details.tags, detected_document_type: details.detected_document_type ?? (analysis.details || {}).detected_document_type };
        if (activePreviewAnalysisId === Number(analysisId)) renderPreviewTags(analysis);
    } catch (e) { /* old tags stay; the card message already confirms the refresh */ }
}

function switchPreviewTab(which) {
    const docBtn = document.getElementById('previewTabDoc');
    const tagsBtn = document.getElementById('previewTabTags');
    const active = 'rounded-t-md border-b-2 border-[#26488C] px-3 py-1.5 text-[11px] font-bold text-[#26488C]';
    const idle = 'rounded-t-md border-b-2 border-transparent px-3 py-1.5 text-[11px] font-bold text-slate-400 hover:text-slate-600';
    docBtn.className = which === 'doc' ? active : idle;
    tagsBtn.className = which === 'tags' ? active : idle;
    document.getElementById('previewDocPane').classList.toggle('hidden', which !== 'doc');
    document.getElementById('previewTagsPane').classList.toggle('hidden', which !== 'tags');
}
function closeFilePreview() {
    const modal = document.getElementById('filePreviewModal');
    modal.querySelector(':scope > div').classList.add('scale-95', 'opacity-0');
    setTimeout(() => { modal.classList.add('hidden'); document.getElementById('previewFrame').src = ''; }, 200);
}
function openAnalysisSurface(analysisId) {
    const analysis = documentAnalyses.get(Number(analysisId));
    if (!analysis || !analysis.output_available) return;
    renderAnalysisSurface(analysis);
    const modal = document.getElementById('aiAnalysisModal');
    modal.classList.remove('hidden');
    setTimeout(() => modal.querySelector(':scope > div').classList.remove('scale-95', 'opacity-0'), 10);
}
function renderAnalysisSurface(analysis) {
    document.getElementById('aiModalTitle').textContent = `${analysis.requirement_label} — Summary`;
    const output = analysis.advisory_output || {};
    const refresh = analysis.summary_refresh || {};
    let refreshHtml = '';
    if (refresh.in_progress) {
        refreshHtml = '<div class="px-3.5 py-2.5 mb-4 rounded-lg bg-blue-50 border border-blue-100 text-blue-800 text-xs font-medium"><i class="ph ph-spinner ph-spin mr-2"></i>Re-summarization is in progress. The current summary remains available.</div>';
    } else if (refresh.summary_processed_at) {
        refreshHtml = `<div class="px-3.5 py-2.5 mb-4 rounded-lg bg-gray-50 border border-gray-100 text-gray-600 text-xs font-medium"><i class="ph ph-clock mr-1.5"></i>Summary updated on ${escapeHtml(formatTimestamp(refresh.summary_processed_at))}.</div>`;
    }
    document.getElementById('aiModalContent').innerHTML = `
        ${refreshHtml}
        <section class="p-5 bg-blue-50 rounded-lg border border-blue-100">
            <h4 class="text-[11px] font-semibold text-blue-900 mb-3"><i class="ph ph-quotes text-blue-300 mr-2"></i>AI-generated Summary</h4>
            <p class="text-sm text-blue-900 leading-relaxed whitespace-pre-wrap">${escapeHtml(output.summary || 'No summary is available.')}</p>
        </section>`;
}
function closeAIModal() {
    const modal = document.getElementById('aiAnalysisModal');
    modal.querySelector(':scope > div').classList.add('scale-95', 'opacity-0');
    setTimeout(() => modal.classList.add('hidden'), 200);
}
function setActionMessage(analysisId, message, isError = false) {
    const element = document.querySelector(`#analysis-card-${analysisId} [data-role="action-message"]`);
    if (!element) return;
    element.textContent = message;
    element.classList.remove('hidden', 'text-red-700', 'text-blue-700');
    element.classList.add(isError ? 'text-red-700' : 'text-blue-700');
}
async function postAnalysisAction(analysisId, action, button) {
    const analysis = documentAnalyses.get(Number(analysisId));
    if (!analysis) return null;
    const url = analysis.urls[action];
    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i> Requesting...';
    try {
        const response = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: '{}' });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) { setActionMessage(analysisId, data.message || `The request failed (${response.status}).`, true); return null; }
        setActionMessage(analysisId, data.message || 'Queued. Refresh the page shortly to see updates.');
        return data;
    } catch (e) { setActionMessage(analysisId, 'Network error. Please try again.', true); return null; }
    finally { button.disabled = false; button.innerHTML = originalHtml; }
}
function retryDocument(analysisId, button) { postAnalysisAction(analysisId, 'retry', button); }
const aiPollers = {};
async function resummarizeDocument(analysisId, button) {
    const analysis = documentAnalyses.get(Number(analysisId));
    startAiProgress('Re-summarizing document', analysis ? analysis.requirement_label : '');
    const data = await postAnalysisAction(analysisId, 'resummarize', button);
    if (!data) { failAiProgress(); return; }
    pollAiProgress(analysisId, 'resummarize', null);
}
async function retagDocument(analysisId, button) {
    const analysis = documentAnalyses.get(Number(analysisId));
    startAiProgress('Refreshing tags', analysis ? analysis.requirement_label : '');
    const data = await postAnalysisAction(analysisId, 'retag', button);
    if (!data) { failAiProgress(); return; }
    pollAiProgress(analysisId, 'retag', data.tags_updated_at || null);
}
function pollAiProgress(analysisId, kind, baseline) {
    const key = kind + ':' + Number(analysisId);
    if (aiPollers[key]) clearInterval(aiPollers[key]);
    let tries = 0;
    aiPollers[key] = setInterval(async () => {
        tries++;
        try {
            const analysis = documentAnalyses.get(Number(analysisId));
            if (!analysis) throw new Error('missing');
            const response = await fetch(analysis.urls.status, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const status = await response.json().catch(() => ({}));
            let done = false;
            if (kind === 'resummarize') {
                done = status.output_available === true && !(status.resummarization && status.resummarization.in_progress);
            } else {
                done = !!status.updated_at && !!baseline && status.updated_at !== baseline;
            }
            if (done) {
                clearInterval(aiPollers[key]);
                if (kind === 'retag') { await refreshAnalysisTags(analysisId); }
                finishAiProgress(kind === 'resummarize' ? 'Summary refreshed.' : 'Tags refreshed.');
                setActionMessage(analysisId, kind === 'resummarize' ? 'Summary refreshed. Reload the page to see the latest state.' : 'Tags refreshed and updated below.');
                return;
            }
        } catch (e) { /* keep polling until the timeout below */ }
        if (tries >= 60) {
            clearInterval(aiPollers[key]);
            waitAiProgress('Taking longer than usual — it will finish automatically in the background.');
        }
    }, 3000);
}
let activeSubmissionStatus = 'all';
function setSubmissionStatus(status, btn) {
    activeSubmissionStatus = status;
    document.querySelectorAll('[data-submission-filter]').forEach(b => {
        b.className = 'rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-500 transition hover:border-[#26488C] hover:text-[#26488C]';
    });
    btn.className = 'rounded-full bg-[#26488C] px-3 py-1.5 text-[11px] font-bold text-white transition';
    applySubmissionFilters();
}
function applySubmissionFilters() {
    const term = (document.getElementById('submissionSearch').value || '').trim().toLowerCase();
    const rows = document.querySelectorAll('#submissionList [data-status]');
    let shown = 0;
    rows.forEach(row => {
        const statusOk = activeSubmissionStatus === 'all' || row.dataset.status === activeSubmissionStatus;
        const nameOk = term === '' || (row.dataset.name || '').includes(term);
        const show = statusOk && nameOk;
        row.classList.toggle('hidden', !show);
        if (show) shown++;
    });
    document.getElementById('submissionCount').textContent = `Showing ${shown} of ${rows.length}`;
}
document.addEventListener('DOMContentLoaded', () => {
    const rows = document.querySelectorAll('#submissionList [data-status]').length;
    document.getElementById('submissionCount').textContent = `Showing ${rows} of ${rows}`;
});
</script>
@endsection
