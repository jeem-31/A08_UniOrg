@extends('layouts.osas')

@section('title', 'Reports Compliance - UniOrg')

@section('content')
@php
    $aggActive = 0;
    $aggSignature = 0;
    $aggWaiting = 0;
    $aggRejected = 0;
    foreach ($requirements as $statReq) {
        if ($statReq->is_active) {
            $aggActive++;
        }
        $aggSignature += $statReq->stats['for_signature'] ?? 0;
        $aggWaiting += $statReq->stats['submitted'] ?? 0;
        $aggRejected += $statReq->stats['rejected'] ?? 0;
    }
@endphp
<div class="w-full max-w-[1400px] mx-auto space-y-4">
    <header class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <nav class="mb-1.5 flex items-center gap-2 text-[11px] font-semibold text-slate-400" aria-label="Breadcrumb">
                <a href="{{ route('osas.dashboard') }}" data-osas-navigation class="text-[#26488C] hover:text-[#1a3461]">OSAS</a>
                <i class="ph ph-caret-right text-[10px] text-slate-300"></i>
                <span>Reports Compliance</span>
            </nav>
            <h1 class="text-xl font-bold tracking-tight text-slate-950 sm:text-2xl">Reports <span class="text-[#26488C]">Compliance</span></h1>
            <p class="mt-1 text-xs font-medium leading-relaxed text-slate-500">Set the reports each organization must submit — they will appear in the admin report submissions page.</p>
        </div>
        <button type="button" onclick="openRequirementModal()" class="inline-flex items-center justify-center gap-2 rounded-md bg-[#26488C] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#1a3461]">
            <i class="ph ph-plus text-xs"></i>
            New requirement
        </button>
    </header>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-xs font-semibold text-green-800">{{ session('success') }}</div>
    @endif

    <section aria-label="Compliance summary" class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3">
            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-md bg-blue-50 text-[#26488C]"><i class="ph ph-clipboard-text text-base"></i></span>
            <span class="min-w-0">
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Active requirements</span>
                <span class="block text-xl font-bold text-slate-900">{{ $aggActive }}</span>
            </span>
        </div>
        <div class="flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 px-4 py-3">
            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-md bg-emerald-100 text-emerald-700"><i class="ph ph-seal-check text-base"></i></span>
            <span class="min-w-0">
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-emerald-700">For signature</span>
                <span class="block text-xl font-bold text-emerald-800">{{ $aggSignature }}</span>
            </span>
        </div>
        <div class="flex items-center gap-3 rounded-lg border border-blue-200 bg-blue-50/50 px-4 py-3">
            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-md bg-blue-100 text-[#26488C]"><i class="ph ph-hourglass-medium text-base"></i></span>
            <span class="min-w-0">
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-blue-700">Waiting for review</span>
                <span class="block text-xl font-bold text-blue-800">{{ $aggWaiting }}</span>
            </span>
        </div>
        <div class="flex items-center gap-3 rounded-lg border border-red-200 bg-red-50/50 px-4 py-3">
            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-md bg-red-100 text-red-600"><i class="ph ph-arrow-counter-clockwise text-base"></i></span>
            <span class="min-w-0">
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-red-700">Rejected</span>
                <span class="block text-xl font-bold text-red-800">{{ $aggRejected }}</span>
            </span>
        </div>
    </section>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Requirement registry</h2>
                <p class="mt-0.5 text-xs text-slate-500">Track targets, deadlines, and submission progress per report.</p>
            </div>
        </div>
        <form method="GET" action="{{ route('osas.required-documents.index') }}" class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-slate-50/50 px-5 py-3">
            <div class="relative min-w-52 flex-1">
                <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search title, notes, or org…" class="w-full rounded-md border border-slate-200 bg-white py-2 pl-9 pr-3 text-xs text-slate-700 placeholder:text-slate-400 focus:border-[#26488C] focus:outline-none">
            </div>
            <select name="department_id" onchange="this.form.submit()" class="rounded-md border border-slate-200 bg-white px-2.5 py-2 text-xs font-semibold text-slate-600 focus:border-[#26488C] focus:outline-none">
                <option value="">All departments</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" {{ $filters['department_id'] === (string) $dept->id ? 'selected' : '' }}>{{ $dept->department_acronym }} — {{ $dept->department_name }}</option>
                @endforeach
            </select>
            <select name="document_key" onchange="this.form.submit()" class="rounded-md border border-slate-200 bg-white px-2.5 py-2 text-xs font-semibold text-slate-600 focus:border-[#26488C] focus:outline-none">
                <option value="">All documents</option>
                @foreach($typeLabels as $key => $label)
                    <option value="{{ $key }}" {{ $filters['document_key'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" onchange="this.form.submit()" class="rounded-md border border-slate-200 bg-white px-2.5 py-2 text-xs font-semibold text-slate-600 focus:border-[#26488C] focus:outline-none">
                <option value="">All statuses</option>
                <option value="active" {{ $filters['status'] === 'active' ? 'selected' : '' }}>Active</option>
                <option value="archived" {{ $filters['status'] === 'archived' ? 'selected' : '' }}>Archived</option>
            </select>
            <button type="submit" class="rounded-md bg-[#26488C] px-3 py-2 text-xs font-bold text-white hover:bg-[#1a3461]">Search</button>
            @if(array_filter($filters))
                <a href="{{ route('osas.required-documents.index') }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-500 hover:text-slate-800 hover:border-slate-400">Clear</a>
            @endif
        </form>
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-2.5 text-[11px] font-medium text-slate-400">
            <span>Showing {{ $requirements->firstItem() ?? 0 }}–{{ $requirements->lastItem() ?? 0 }} of {{ number_format($requirements->total()) }} requirements</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[880px] text-left">
                <thead class="border-b border-slate-100 bg-white">
                    <tr>
                        <th class="px-5 py-2.5 text-[11px] font-semibold text-slate-500">Document</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold text-slate-500">Target</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold text-slate-500">Deadline</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold text-slate-500">Progress</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold text-slate-500">Status</th>
                        <th class="px-5 py-2.5 text-right text-[11px] font-semibold text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($requirements as $req)
                        <tr class="group transition hover:bg-slate-50/70 {{ $req->is_active ? '' : 'opacity-60' }}">
                            <td class="px-5 py-2.5">
                                <p class="max-w-[220px] truncate text-[13px] font-semibold text-slate-900 group-hover:text-[#26488C]" title="{{ $req->displayTitle() }}">{{ $req->displayTitle() }}</p>
                                @if($req->description)<p class="mt-0.5 max-w-[220px] truncate text-[11px] font-medium text-slate-400" title="{{ $req->description }}">{{ $req->description }}</p>@endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-2.5">
                                <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-700"><span class="h-1.5 w-1.5 rounded-full bg-[#26488C]"></span>{{ $req->target_label }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-2.5 text-xs @if($req->deadline_at && $req->deadline_at->isPast()) font-bold text-red-600 @else text-slate-500 @endif">{{ $req->deadline_at?->format('M d, Y') ?? '—' }}</td>
                            <td class="px-5 py-2.5">
                                <p class="text-[11px] font-semibold text-slate-600">{{ $req->stats['for_signature'] }}/{{ $req->stats['orgs'] }} for signature</p>
                                <p class="mt-0.5 text-[10px] font-medium text-slate-400">{{ $req->stats['submitted'] }} waiting • {{ $req->stats['rejected'] }} rejected @if($req->stats['pending']) • {{ $req->stats['pending'] }} applied @endif</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-2.5">
                                @if($req->is_active)
                                    <span class="inline-flex items-center gap-1.5 rounded-md border border-green-200 bg-green-50 px-2 py-1 text-[10px] font-semibold text-green-700"><span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>Active</span>
                                @else
                                    <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-500">Archived</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-2.5 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('osas.required-documents.submissions', $req) }}" title="Submissions" class="inline-flex items-center rounded-md bg-blue-50 border border-blue-100 px-2.5 py-1.5 text-[11px] font-bold text-[#26488C] hover:bg-blue-100">
                                        <i class="ph ph-eye"></i>
                                    </a>
                                    <form method="POST" action="{{ route('osas.required-documents.toggle', $req) }}" class="inline">
                                        @csrf
                                        <button type="submit" title="{{ $req->is_active ? 'Archive' : 'Re-activate' }}" class="inline-flex items-center rounded-md bg-white border border-slate-200 px-2.5 py-1.5 text-[11px] font-bold text-slate-500 hover:text-slate-800 hover:border-slate-400">
                                            <i class="ph @if($req->is_active) ph-archive @else ph-arrow-counter-clockwise @endif"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-slate-400">No requirements yet. Click “New requirement” above to set one.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($requirements->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $requirements->links() }}</div>
        @endif
    </section>
</div>

<div id="requirementModal" class="hidden fixed inset-0 z-50 overflow-y-auto p-4">
    <div class="fixed inset-0 bg-black/50" onclick="closeRequirementModal()"></div>
    <div class="relative mx-auto w-full max-w-2xl rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-base font-bold text-slate-900">Set a report requirement</h2>
                <p class="mt-0.5 text-xs text-slate-500">Organizations will see it in their report submissions page.</p>
            </div>
            <button type="button" onclick="closeRequirementModal()" class="rounded-md bg-slate-100 px-2 py-1 text-slate-500 hover:bg-slate-200"><i class="ph ph-x"></i></button>
        </div>
        <form method="POST" action="{{ route('osas.required-documents.store') }}" class="grid grid-cols-1 gap-3 p-5 md:grid-cols-2">
            @csrf
            @if($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-800 md:col-span-2">
                    <ul class="list-disc pl-4 space-y-1">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            <div>
                <label class="text-[11px] font-bold text-slate-600">Department</label>
                <select name="department_id" id="req-department" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="" disabled selected>Select department…</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->department_name }} ({{ $dept->department_acronym }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-[11px] font-bold text-slate-600">Organization <span class="font-medium text-slate-400">(blank = all orgs)</span></label>
                <select name="organization_id" id="req-organization" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All organizations in department</option>
                </select>
                <p id="req-org-hint" class="hidden mt-1 text-[11px] font-medium text-amber-700">No approved orgs in this department yet — this will cover the whole department, including applied orgs once approved.</p>
            </div>
            <div>
                <label class="text-[11px] font-bold text-slate-600">Document</label>
                <select name="document_key" id="req-doctype" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="" disabled selected>Select document…</option>
                    @foreach($typeLabels as $key => $label)
                        <option value="{{ $key }}" {{ old('document_key') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div id="custom-title-wrap" class="hidden">
                <label class="text-[11px] font-bold text-slate-600">Custom document title</label>
                <input type="text" name="custom_title" value="{{ old('custom_title') }}" placeholder="e.g. Mid-Year Narrative Report" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-[11px] font-bold text-slate-600">Deadline <span class="font-medium text-slate-400">(optional)</span></label>
                <input type="datetime-local" name="deadline_at" value="{{ old('deadline_at') }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-[11px] font-bold text-slate-600">Notes for orgs <span class="font-medium text-slate-400">(optional)</span></label>
                <input type="text" name="description" value="{{ old('description') }}" placeholder="Short instruction…" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="flex justify-end gap-2 md:col-span-2">
                <button type="button" onclick="closeRequirementModal()" class="rounded-md border border-slate-300 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-[#26488C] px-4 py-2 text-xs font-bold text-white hover:bg-[#1a3461]">
                    <i class="ph ph-paper-plane-tilt"></i> Publish requirement
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openRequirementModal() {
    document.getElementById('requirementModal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}
function closeRequirementModal() {
    document.getElementById('requirementModal').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}
(function () {
    const deptSelect = document.getElementById('req-department');
    const orgSelect = document.getElementById('req-organization');
    const typeSelect = document.getElementById('req-doctype');
    const customWrap = document.getElementById('custom-title-wrap');
    const oldOrg = @json(old('organization_id'));

    function toggleCustom() {
        customWrap.classList.toggle('hidden', typeSelect.value !== 'other');
    }
    typeSelect.addEventListener('change', toggleCustom);
    toggleCustom();

    async function loadOrgs() {
        orgSelect.innerHTML = '<option value="">All organizations in department</option>';
        document.getElementById('req-org-hint').classList.add('hidden');
        const deptId = deptSelect.value;
        if (!deptId) return;
        try {
            const res = await fetch(`/api/organizations?department_id=${encodeURIComponent(deptId)}`, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            const orgs = data.organizations || [];
            orgs.forEach(org => {
                const opt = document.createElement('option');
                opt.value = org.id;
                opt.textContent = `${org.name}${org.org_acronym ? ' (' + org.org_acronym + ')' : ''}`;
                if (String(oldOrg) === String(org.id)) opt.selected = true;
                orgSelect.appendChild(opt);
            });
            if (orgs.length === 0) {
                document.getElementById('req-org-hint').classList.remove('hidden');
            }
        } catch (e) { /* leave default option */ }
    }
    deptSelect.addEventListener('change', loadOrgs);
    if (deptSelect.value) loadOrgs();

    @if($errors->any())
        openRequirementModal();
    @endif
})();
</script>
@endsection
