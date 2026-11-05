@php
    $visiblePending = $requests->whereIn('status', ['Pending', 'For-Checking'])->count();
@endphp

<div data-accreditation-table data-total="{{ $requests->total() }}">
    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-5 py-2.5 text-[11px] font-medium text-slate-400">
        <span>Showing {{ $requests->firstItem() ?? 0 }}–{{ $requests->lastItem() ?? 0 }} of {{ number_format($requests->total()) }} submissions</span>
        @if($visiblePending > 0)
            <span class="inline-flex items-center gap-1.5 font-semibold text-amber-700"><span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>{{ $visiblePending }} pending in this page</span>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[880px] text-left">
            <thead class="border-b border-slate-100 bg-white">
                <tr>
                    <th class="px-5 py-2.5 text-[11px] font-semibold text-slate-500">Organization</th>
                    <th class="px-5 py-2.5 text-[11px] font-semibold text-slate-500">Requester</th>
                    <th class="px-5 py-2.5 text-[11px] font-semibold text-slate-500">Request type</th>
                    <th class="px-5 py-2.5 text-center text-[11px] font-semibold text-slate-500">Document scan</th>
                    <th class="px-5 py-2.5 text-[11px] font-semibold text-slate-500">Status</th>
                    <th class="px-5 py-2.5 text-right text-[11px] font-semibold text-slate-500">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($requests as $application)
                    @php
                        $isRecognition = $application->request_type === 'apply';
                    @endphp
                    <tr class="group transition hover:bg-slate-50/70">
                        <td class="px-5 py-2.5">
                            <a href="{{ route('osas.apply-org.show', $application) }}" data-osas-navigation class="flex min-w-0 items-center gap-3">
                                @if($application->organization?->settings?->logo_url)
                                    <img src="{{ $application->organization->settings->logo_url }}" alt="{{ $application->organization_name }}" class="h-8 w-8 flex-shrink-0 rounded-md object-cover">
                                @else
                                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md {{ $isRecognition ? 'bg-blue-50 text-[#26488C]' : 'bg-indigo-50 text-indigo-600' }} text-sm font-bold">{{ strtoupper(substr($application->organization_name, 0, 1)) }}</span>
                                @endif
                                <span class="min-w-0">
                                    <span class="block max-w-[220px] truncate text-[13px] font-semibold text-slate-900 group-hover:text-[#26488C]">{{ $application->organization_name }}</span>
                                    <span class="mt-0.5 flex items-center gap-1.5 text-[11px] font-medium text-slate-400"><i class="ph ph-calendar-dot text-[11px] text-[#C4973B]"></i>{{ ($application->updated_at ?? $application->created_at)->format('M d, Y') }}</span>
                                </span>
                            </a>
                        </td>
                        <td class="px-5 py-2.5">
                            <div class="max-w-[180px]">
                                <p class="truncate text-xs font-semibold text-slate-700">{{ $application->requester_first_name }} {{ $application->requester_last_name }}</p>
                                <p class="mt-0.5 truncate text-[11px] font-medium text-slate-400">{{ $application->requester_position ?: 'Requester' }}</p>
                            </div>
                        </td>
                        <td class="px-5 py-2.5 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold {{ $isRecognition ? 'text-[#26488C]' : 'text-indigo-600' }}"><span class="h-1.5 w-1.5 rounded-full {{ $isRecognition ? 'bg-[#26488C]' : 'bg-indigo-500' }}"></span>{{ $isRecognition ? 'Recognition' : 'Renewal' }}</span>
                            <span class="mt-0.5 block text-[11px] font-medium text-slate-400">{{ $application->organization_type ?: 'Organization' }}</span>
                        </td>
                        <td class="px-5 py-2.5 text-center whitespace-nowrap">
                            @if($application->auto_generated)
                                <span class="inline-flex items-center gap-1.5 rounded-md border border-amber-100 bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-700"><i class="ph ph-hourglass text-[11px]"></i>Awaiting org upload</span>
                            @elseif(! empty($application->analysis_state) && $application->analysis_state['total'] > 0)
                                @php
                                    $state = $application->analysis_state;
                                    $documentBadges = [];
                                    if (($state['counts']['pending'] ?? 0) > 0) {
                                        $documentBadges[] = ['Pending ('.$state['counts']['pending'].')', 'border-amber-100 bg-amber-50 text-amber-700'];
                                    }
                                    if (($state['counts']['processing'] ?? 0) > 0) {
                                        $documentBadges[] = ['Processing ('.$state['counts']['processing'].')', 'border-blue-100 bg-blue-50 text-blue-700'];
                                    }
                                    if (($state['counts']['completed'] ?? 0) > 0) {
                                        $documentBadges[] = ['Completed ('.$state['counts']['completed'].'/'.$state['output_available'].')', 'border-emerald-100 bg-emerald-50 text-emerald-700'];
                                    }
                                    if (($state['counts']['failed'] ?? 0) > 0) {
                                        $documentBadges[] = ['Failed ('.$state['counts']['failed'].')', 'border-rose-100 bg-rose-50 text-rose-700'];
                                    }
                                @endphp
                                <span class="inline-flex flex-wrap items-center justify-center gap-1">
                                    @foreach($documentBadges as [$badgeText, $badgeClass])
                                        <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-semibold {{ $badgeClass }}">{{ $badgeText }}</span>
                                    @endforeach
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-semibold text-slate-400"><i class="ph ph-minus-circle text-[11px]"></i>No Documents</span>
                            @endif
                        </td>
                        <td class="px-5 py-2.5 whitespace-nowrap">
                            @if($application->status === 'For-Checking')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-1 text-[10px] font-semibold text-blue-700"><span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>For Checking</span>
                            @elseif($application->status === 'Pending')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-semibold text-amber-700"><span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>{{ $isRecognition ? 'Pending' : 'Pending' }}</span>
                            @elseif($application->status === 'Approved')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Approved</span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 text-[10px] font-semibold text-rose-700"><span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>{{ $application->status }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-2.5 text-right whitespace-nowrap">
                            <a href="{{ route('osas.apply-org.show', $application) }}" data-osas-navigation class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:border-[#26488C] hover:bg-[#26488C] hover:text-white"><span>View</span><i class="ph ph-arrow-square-out text-[11px]"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-10 text-center">
                            <div class="mx-auto flex max-w-sm flex-col items-center">
                                <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-slate-100 text-slate-300"><i class="ph ph-tray text-lg"></i></span>
                                <h2 class="mt-3.5 text-sm font-bold text-slate-900">No matching submissions</h2>
                                <p class="mt-1 text-xs font-medium leading-relaxed text-slate-400">Adjust the search or status filter to return more accreditation records.</p>
                                <a href="{{ route('osas.apply-org.index') }}" data-osas-navigation class="mt-3 text-[11px] font-semibold text-[#26488C] hover:underline">Reset queue</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div data-accreditation-pagination class="flex flex-col items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/40 px-5 py-2.5 sm:flex-row premium-pagination">
        <p class="text-[11px] font-medium text-slate-400">Use View to inspect documents, officers, and reviewer actions.</p>
        {{ $requests->links() }}
    </div>
</div>