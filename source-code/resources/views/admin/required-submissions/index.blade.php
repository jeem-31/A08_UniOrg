@extends('layouts.admin')

@section('title', 'Report Submissions - ' . $organization->name)

@section('content')
@php
    $statTotal = count($cards);
    $statNeeds = 0;
    $statWaiting = 0;
    $statSignature = 0;
    foreach ($cards as $statCard) {
        $statStatus = $statCard['latest']?->status;
        if ($statStatus === 'approved_for_signature') {
            $statSignature++;
        } elseif ($statStatus === 'submitted') {
            $statWaiting++;
        } else {
            $statNeeds++;
        }
    }
@endphp
<div class="mx-auto w-full max-w-[1600px] px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-2 border-b border-slate-200 pb-4 mb-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider" style="color: var(--primary-color, #8238B3);">Operations / Reports</p>
            <div class="mt-1 flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Report Submissions</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                    <span class="h-1.5 w-1.5 rounded-full" style="background-color: var(--primary-color, #8238B3);"></span> {{ $organization->name }}
                </span>
            </div>
            <p class="mt-1 text-sm text-slate-500">Reports required by OSAS. Upload each document and wait for it to be marked For Signature.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-xs font-semibold text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-800">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-800">
            <ul class="list-disc pl-4 space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="mb-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total required</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $statTotal }}</p>
        </div>
        <div class="rounded-lg border border-amber-200 bg-amber-50/50 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Needs submission</p>
            <p class="mt-1 text-2xl font-bold text-amber-800">{{ $statNeeds }}</p>
        </div>
        <div class="rounded-lg border border-blue-200 bg-blue-50/50 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Waiting for OSAS</p>
            <p class="mt-1 text-2xl font-bold text-blue-800">{{ $statWaiting }}</p>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50/50 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">For signature</p>
            <p class="mt-1 text-2xl font-bold text-emerald-800">{{ $statSignature }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Submission registry</h2>
                <p class="mt-0.5 text-xs text-slate-500">Track deadlines, latest files, and OSAS feedback per report.</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Report</th>
                        <th class="whitespace-nowrap px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Deadline</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Latest file</th>
                        <th class="whitespace-nowrap px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($cards as $card)
                        @php
                            $req = $card['requirement'];
                            $latest = $card['latest'];
                            $chip = match ($latest?->status) {
                                'approved_for_signature' => ['For Signature', 'bg-green-50 text-green-700 border-green-100'],
                                'rejected' => ['Rejected — upload again', 'bg-red-50 text-red-700 border-red-100'],
                                'submitted' => ['Submitted — waiting', 'bg-blue-50 text-blue-700 border-blue-100'],
                                default => ['Needs submission', 'bg-amber-50 text-amber-700 border-amber-100'],
                            };
                            $canUpload = ! $latest || $latest->status === 'rejected';
                            $isOverdue = $req->deadline_at && $req->deadline_at->isPast() && $latest?->status !== 'approved_for_signature';
                        @endphp
                        <tr class="align-top hover:bg-slate-50/60">
                            <td class="max-w-xs px-4 py-3">
                                <p class="text-sm font-bold text-slate-800">{{ $req->displayTitle() }}</p>
                                <p class="mt-0.5 text-[11px] text-slate-400">@if($req->organization_id) Set for your org @else Set for all orgs in {{ $req->department?->department_name }} @endif</p>
                                @if($req->description)<p class="mt-0.5 text-[11px] leading-relaxed text-slate-500">{{ $req->description }}</p>@endif
                                @if($latest?->remarks)<p class="mt-1 text-[11px] leading-relaxed text-red-600"><span class="font-bold">OSAS note:</span> {{ $latest->remarks }}</p>@endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs @if($isOverdue) font-bold text-red-600 @else text-slate-500 @endif">{{ $req->deadline_at?->format('M d, Y') ?? '—' }}</td>
                            <td class="max-w-xs px-4 py-3">
                                @if($latest)
                                    <p class="truncate text-xs font-semibold text-slate-700" title="{{ $latest->original_filename }}">{{ $latest->original_filename }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-400">v{{ $latest->version }} • {{ $latest->created_at->format('M d, Y • h:i A') }}</p>
                                    @if($card['history']->count() > 1)
                                        <details class="mt-1">
                                            <summary class="cursor-pointer text-[11px] font-bold" style="color: var(--primary-color, #8238B3);">Older versions ({{ $card['history']->count() - 1 }})</summary>
                                            <ul class="mt-1 space-y-0.5 text-[11px] text-slate-500">
                                                @foreach($card['history']->skip(1) as $h)
                                                    <li>v{{ $h->version }} — {{ $h->original_filename }} — {{ $h->statusLabel() }}</li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif
                                @else
                                    <p class="text-xs text-slate-400">No file yet</p>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-semibold {{ $chip[1] }}">{{ $chip[0] }}</span>
                            </td>
                            <td class="min-w-52 px-4 py-3">
                                @if($canUpload)
                                    <form method="POST" action="{{ route('admin.required-submissions.store') }}" enctype="multipart/form-data" class="space-y-1.5">
                                        @csrf
                                        <input type="hidden" name="required_document_id" value="{{ $req->id }}">
                                        <input type="file" name="document" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="block w-full rounded-md border border-slate-200 text-[11px] text-slate-600 file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-2.5 file:py-1.5 file:text-[11px] file:font-bold file:text-slate-600 hover:file:bg-slate-200">
                                        <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:opacity-90" style="background-color: var(--primary-color, #8238B3);">
                                            <i class="ph ph-upload text-xs"></i> {{ $latest ? 'Resubmit' : 'Submit' }}
                                        </button>
                                    </form>
                                    <p class="mt-1 text-[10px] text-slate-400">PDF, DOC/DOCX, image • max 20 MB</p>
                                @elseif($latest && $latest->status === 'submitted')
                                    <p class="text-[11px] font-medium text-slate-400">With OSAS now — waiting to be marked For Signature.</p>
                                @else
                                    <p class="inline-flex items-center gap-1 text-[11px] font-bold text-green-700"><i class="ph ph-check-circle text-sm"></i> Done</p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-400">No reports required right now. OSAS will post them here when there are reports to submit.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
