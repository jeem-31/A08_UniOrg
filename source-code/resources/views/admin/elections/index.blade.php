@extends(request()->boolean('fragment') ? 'layouts.fragment' : 'layouts.admin')

@section('title', 'Manage Elections - Admin')

@section('content')
<div class="px-6 py-6" x-data="{
    showCreateModal: false,
    showCandidateModal: false,
    showSecretaryModal: false,
    selectedElection: null,
    selectedPosition: ''
}">
    @php
        $canManageElections = app(\App\Services\PermissionHelper::class)->hasFullAccess('elections');
        $hasActiveElection = $elections->contains(fn ($election) => !$election->end_date->isPast());
        $ongoingCount = $elections->filter(fn ($election) => now()->between($election->start_date, $election->end_date) && $election->is_posted)->count();
        $completedCount = $elections->filter(fn ($election) => $election->end_date->isPast())->count();
        $candidateCount = $elections->sum('candidates_count');
    @endphp

    <section class="mb-4 border-b border-gray-200 pb-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-bold tracking-tight text-gray-900">Election Operations</h1>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-gray-500">{{ $elections->total() }} total</span>
                </div>
                <p class="mt-1 text-xs text-gray-500">Configure ballots, monitor participation, and release verified results.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center divide-x divide-gray-200 rounded-md border border-gray-200 bg-white text-xs">
                    <div class="px-3 py-2"><span class="font-bold text-gray-900">{{ $ongoingCount }}</span><span class="ml-1 text-gray-500">live</span></div>
                    <div class="px-3 py-2"><span class="font-bold text-gray-900">{{ $completedCount }}</span><span class="ml-1 text-gray-500">completed</span></div>
                    <div class="px-3 py-2"><span class="font-bold text-gray-900">{{ $candidateCount }}</span><span class="ml-1 text-gray-500">candidates</span></div>
                </div>
                @if($canManageElections && $hasActiveElection)
                    <div class="relative group">
                        <button disabled class="rounded-md border border-gray-200 bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-400 cursor-not-allowed">
                            <i class="ph ph-plus mr-1.5"></i>New Election
                        </button>
                        <div class="pointer-events-none absolute right-0 top-full z-10 mt-2 w-64 rounded-md bg-gray-900 px-3 py-2 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">
                            Only one current or upcoming election can be scheduled at a time.
                        </div>
                    </div>
                @elseif($canManageElections)
                    <button @click="showCreateModal = true" style="background-color: var(--primary-color);" class="rounded-md px-3 py-2 text-xs font-semibold text-white transition hover:opacity-90">
                        <i class="ph ph-plus mr-1.5"></i>New Election
                    </button>
                @endif
            </div>
        </div>
    </section>

    <!-- Election workspaces -->
    <div class="space-y-3">
        @forelse($elections as $election)
            <div data-election-workspace class="bg-white overflow-hidden rounded-lg border border-gray-200">
                <!-- Election summary and controls -->
                <div class="border-b border-gray-200 bg-white px-4 py-3">
                    <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-sm font-bold text-gray-900">{{ $election->title }}</h2>
                                @if(!$election->is_posted)
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-gray-500"><i class="ph ph-file-text mr-1"></i>Not posted</span>
                                @elseif($election->end_date->isPast())
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-gray-600"><i class="ph ph-flag-checkered mr-1"></i>Completed</span>
                                @elseif($election->start_date->isFuture())
                                    <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-blue-700"><i class="ph ph-clock mr-1"></i>Scheduled</span>
                                @else
                                    <span class="rounded-full bg-green-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-green-700"><i class="ph ph-circle mr-1 animate-pulse"></i>Live</span>
                                @endif
                                @if($election->is_posted)
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-500">Posted</span>
                                @endif
                            </div>
                            <p class="mt-1 max-w-3xl truncate text-xs text-gray-500">{{ $election->description }}</p>
                        </div>

                        <div class="grid grid-cols-3 divide-x divide-gray-200 rounded-md border border-gray-200 bg-gray-50 text-center text-[11px] shrink-0">
                            <div class="px-3 py-1.5"><span class="block font-bold text-gray-900">{{ $election->candidates_count }}</span><span class="text-gray-500">Candidates</span></div>
                            <div class="px-3 py-1.5"><span class="block font-bold text-gray-900">{{ $election->total_votes }}</span><span class="text-gray-500">Votes</span></div>
                            <div class="px-3 py-1.5"><span class="block font-bold text-gray-900">{{ $election->candidates->pluck('position')->unique()->count() }}/11</span><span class="text-gray-500">Filled</span></div>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-col gap-2 border-t border-gray-100 pt-2.5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-[11px] text-gray-500"><i class="ph ph-calendar-blank mr-1 text-gray-400"></i>{{ $election->start_date->format('M d, Y h:i A') }}<span class="mx-1.5 text-gray-300">—</span>{{ $election->end_date->format('M d, Y h:i A') }}</p>
                        <div class="flex flex-wrap items-center gap-2">
                            @php
                                $isOngoing = now()->between($election->start_date, $election->end_date) && $election->is_posted;
                                $isCompleted = $election->end_date->isPast();
                            @endphp
                            @if($canManageElections && !$isCompleted && !$isOngoing)
                                <button onclick="openEditModal({{ $election->id }}, '{{ addslashes($election->title) }}', '{{ addslashes($election->description) }}', '{{ $election->start_date->format('Y-m-d\TH:i') }}', '{{ $election->end_date->format('Y-m-d\TH:i') }}')" class="rounded-md border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50"><i class="ph ph-pen mr-1"></i>Edit</button>
                            @endif
                            @if($canManageElections && $election->is_posted)
                                <button onclick="showUnpostModal({{ $election->id }}, '{{ addslashes($election->title) }}')" class="rounded-md border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-100"><i class="ph ph-prohibit mr-1"></i>Unpost</button>
                            @elseif($canManageElections)
                                <form action="{{ route('admin.elections.post', $election) }}" method="POST" onsubmit="return validateElectionBeforePost({{ $election->id }}, event)">
                                    @csrf
                                    <button type="submit" style="background-color: var(--primary-color);" class="post-election-submit rounded-md px-2.5 py-1.5 text-xs font-semibold text-white transition hover:opacity-90"><i class="ph ph-paper-plane-tilt mr-1"></i>Post election</button>
                                </form>
                            @endif
                            <a href="{{ route('admin.elections.results', $election) }}" data-admin-navigation-loading aria-label="{{ !$election->is_posted ? 'Results' : ($election->end_date->isPast() ? 'Final Results' : 'Live Results') }}" class="rounded-md bg-blue-600 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700"><i class="ph ph-chart-bar mr-1"></i>{{ !$election->is_posted ? 'Results' : ($election->end_date->isPast() ? 'Final Results' : 'Live Results') }}</a>
                            @if($canManageElections)<button onclick="showDeleteElectionModal({{ $election->id }}, '{{ addslashes($election->title) }}')" class="rounded-md px-2 py-1.5 text-xs font-semibold text-gray-400 transition hover:bg-red-50 hover:text-red-600" title="Delete election"><i class="ph ph-trash"></i></button>@endif
                        </div>
                    </div>
                </div>

                <!-- Ballot configuration -->
                <div class="p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Ballot configuration</h3>
                        <span class="text-[11px] text-gray-400">Add candidates before posting</span>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                        @php
                            $positions = [
                                'president' => ['name' => 'President', 'icon' => 'ph-crown'],
                                'vice_president' => ['name' => 'Vice President', 'icon' => 'ph-user'],
                                'treasurer' => ['name' => 'Treasurer', 'icon' => 'ph-coins'],
                                'auditor' => ['name' => 'Auditor', 'icon' => 'ph-clipboard-text'],
                                'public_information_officer' => ['name' => 'Public Info. Officer', 'icon' => 'ph-megaphone'],
                                'business_manager' => ['name' => 'Business Manager', 'icon' => 'ph-briefcase'],
                                'event_coordinator' => ['name' => 'Event Coordinator', 'icon' => 'ph-calendar-blank'],
                                '1st_year_rep' => ['name' => '1st Year Rep.', 'icon' => 'ph-student'],
                                '2nd_year_rep' => ['name' => '2nd Year Rep.', 'icon' => 'ph-student'],
                                '3rd_year_rep' => ['name' => '3rd Year Rep.', 'icon' => 'ph-student'],
                                '4th_year_rep' => ['name' => '4th Year Rep.', 'icon' => 'ph-student'],
                            ];
                        @endphp

                        @foreach($positions as $posKey => $posData)
                            @php
                                $candidates = $election->candidates->where('position', $posKey);
                            @endphp
                            <div class="border border-gray-200 rounded-md p-3 hover:border-gray-300 transition bg-white flex flex-col" data-position="{{ $posKey }}" data-candidate-count="{{ $candidates->count() }}">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="w-7 h-7 rounded-md flex items-center justify-center shrink-0" style="background-color: rgba(var(--primary-color-rgb), 0.1);">
                                            <i style="color: var(--primary-color);" class="ph {{ $posData['icon'] }} text-xs"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <h3 class="font-semibold text-gray-900 text-xs truncate leading-tight">{{ $posData['name'] }}</h3>
                                            <p class="text-[11px] text-gray-400">{{ $candidates->count() }} candidate(s)</p>
                                        </div>
                                    </div>
                                    @php
                                        $isOngoing = now()->between($election->start_date, $election->end_date) && $election->is_posted;
                                        $isCompleted = $election->end_date->isPast();
                                    @endphp
                                    @if(!$isOngoing && !$isCompleted)
                                        <button onclick="openCandidateModal({{ $election->id }}, '{{ $posKey }}', '{{ $posData['name'] }}')"
                                                style="color: var(--primary-color);" class="shrink-0 hover:opacity-70">
                                            <i class="ph ph-plus-circle text-sm"></i>
                                        </button>
                                    @endif
                                </div>

                                <!-- Candidates List -->
                                <div class="space-y-1.5">
                                    @forelse($candidates as $candidate)
                                        <div class="flex items-center justify-between bg-gray-50 px-2 py-1.5 rounded-md hover:bg-gray-100 transition">
                                            <div class="flex items-center gap-2 min-w-0">
                                                @if($candidate->photo)
                                                    <img src="{{ storage_url($candidate->photo) }}" class="w-6 h-6 rounded-full object-cover border shrink-0" style="border-color: rgba(var(--primary-color-rgb), 0.2);">
                                                @else
                                                    <div class="w-6 h-6 rounded-full flex items-center justify-center shrink-0" style="background-color: rgba(var(--primary-color-rgb), 0.1);">
                                                        <i style="color: var(--primary-color);" class="ph ph-user text-[10px]"></i>
                                                    </div>
                                                @endif
                                                <p class="text-xs font-medium text-gray-800 truncate">{{ $candidate->name }}</p>
                                            </div>
                                            @php
                                                $isOngoing = now()->between($election->start_date, $election->end_date) && $election->is_posted;
                                                $isCompleted = $election->end_date->isPast();
                                            @endphp
                                            @if($isCompleted || $isOngoing)
                                                <button disabled class="text-gray-300 text-[10px] font-medium cursor-not-allowed shrink-0" title="Cannot remove candidates from {{ $isCompleted ? 'completed' : 'ongoing' }} election">
                                                    <i class="ph ph-lock"></i>
                                                </button>
                                            @else
                                                <button onclick="showRemoveCandidateModal({{ $candidate->id }}, '{{ addslashes($candidate->name) }}')"
                                                        class="text-red-500 hover:text-red-700 text-[10px] font-medium shrink-0">
                                                    Remove
                                                </button>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="text-[11px] text-gray-300 text-center py-1.5">No candidates yet</p>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Secretary Info (Display only) -->
                    @if($election->secretary)
                        <div class="mt-4 bg-green-50 border border-green-200 rounded-md px-4 py-2.5">
                            <h3 class="font-semibold text-gray-900 text-xs">
                                <i class="ph ph-check-circle text-green-600 mr-1.5"></i>Secretary Assigned
                            </h3>
                            <p class="text-xs text-gray-700 mt-0.5">{{ $election->secretary->name }}</p>
                            <p class="text-[11px] text-gray-400 mt-0.5">Assigned by the winning President and Vice President</p>
                        </div>
                    @elseif($election->end_date->isPast())
                        <div class="mt-4 bg-blue-50 border border-blue-200 rounded-md px-4 py-2.5">
                            <h3 class="font-semibold text-gray-900 text-xs">
                                <i class="ph ph-info text-blue-600 mr-1.5"></i>Secretary Assignment
                            </h3>
                            <p class="text-xs text-gray-600 mt-0.5">The winning President and Vice President will assign the Secretary.</p>
                        </div>
                    @endif

                    <!-- Action buttons are kept in the election summary header for faster access. -->
                </div>
            </div>
        @empty
            <div class="bg-white rounded-lg border border-gray-200 p-10 text-center">
                <i class="ph ph-check-square text-gray-200 text-4xl mb-3"></i>
                <p class="text-sm text-gray-400 font-medium">No elections yet. Create your first election!</p>
            </div>
        @endforelse
    </div>

    <!-- Create Election Modal -->
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showCreateModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full p-5 z-10">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-bold text-gray-900">Create New Election</h3>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">
                        <i class="ph ph-x"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.elections.store') }}" data-admin-inplace-form x-data="{ isSubmitting: false }" @submit="isSubmitting = true">
                    @csrf
                    <div class="mb-3">
                        <label class="block text-gray-600 text-xs font-bold mb-1.5">Election Title</label>
                        <input type="text" name="title" required
                            class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-sm focus:outline-none focus:ring-2"
                            placeholder="e.g., Student Council Election 2024">
                    </div>

                    <div class="mb-3">
                        <label class="block text-gray-600 text-xs font-bold mb-1.5">Description</label>
                        <textarea name="description" rows="3" required
                            class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-sm focus:outline-none focus:ring-2"
                            placeholder="Brief description of the election"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div>
                            <label class="block text-gray-600 text-xs font-bold mb-1.5">Start Date & Time</label>
                            <input type="datetime-local" name="start_date" id="createStartDate" required
                                min="{{ now()->format('Y-m-d\TH:i') }}"
                                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-sm focus:outline-none focus:ring-2"
                                onchange="document.getElementById('createEndDate').min = this.value">
                        </div>
                        <div>
                            <label class="block text-gray-600 text-xs font-bold mb-1.5">End Date & Time</label>
                            <input type="datetime-local" name="end_date" id="createEndDate" required
                                min="{{ now()->format('Y-m-d\TH:i') }}"
                                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-sm focus:outline-none focus:ring-2">
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button type="button" @click="showCreateModal = false" :disabled="isSubmitting"
                            class="flex-1 border border-gray-200 text-gray-600 font-semibold text-xs py-2 rounded-md hover:bg-gray-50 transition" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }">
                            <i class="ph ph-x mr-1.5"></i>Cancel
                        </button>
                        <button type="submit" :disabled="isSubmitting" style="background-color: var(--primary-color);" class="flex-1 text-white text-xs font-semibold py-2 rounded-md transition" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }">
                            <i class="ph mr-1.5" :class="isSubmitting ? 'ph-spinner ph-spin' : 'ph-plus'"></i>
                            <span x-show="!isSubmitting">Create Election</span>
                            <span x-show="isSubmitting">Creating...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Election Modal -->
    <div id="editElectionModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div onclick="closeEditModal()" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full p-5 z-10">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-bold text-gray-900">Edit Election</h3>
                    <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="ph ph-x"></i>
                    </button>
                </div>

                <form id="editElectionForm" method="POST" data-admin-inplace-form x-data="{ isUpdating: false }" @submit="isUpdating = true">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="block text-gray-600 text-xs font-bold mb-1.5">Election Title</label>
                        <input type="text" name="title" id="editTitle" required
                            class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-sm focus:outline-none focus:ring-2"
                            placeholder="e.g., Student Council Election 2024">
                    </div>

                    <div class="mb-3">
                        <label class="block text-gray-600 text-xs font-bold mb-1.5">Description</label>
                        <textarea name="description" id="editDescription" rows="3" required
                            class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-sm focus:outline-none focus:ring-2"
                            placeholder="Brief description of the election"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div>
                            <label class="block text-gray-600 text-xs font-bold mb-1.5">Start Date & Time</label>
                            <input type="datetime-local" name="start_date" id="editStartDate"
                                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label class="block text-gray-600 text-xs font-bold mb-1.5">End Date & Time</label>
                            <input type="datetime-local" name="end_date" id="editEndDate"
                                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-sm focus:outline-none focus:ring-2">
                        </div>
                    </div>
                    <p class="mb-4 text-[11px] text-gray-400">Leave the dates unchanged if you are only editing the title or description.</p>

                    <div class="flex gap-2">
                        <button type="button" onclick="closeEditModal()" :disabled="isUpdating"
                            class="flex-1 border border-gray-200 text-gray-600 font-semibold text-xs py-2 rounded-md hover:bg-gray-50 transition" :class="{ 'opacity-50 cursor-not-allowed': isUpdating }">
                            <i class="ph ph-x mr-1.5"></i>Cancel
                        </button>
                        <button type="submit" :disabled="isUpdating" class="flex-1 bg-blue-600 text-white text-xs font-semibold py-2 rounded-md hover:bg-blue-700 transition" :class="{ 'opacity-50 cursor-not-allowed': isUpdating }">
                            <i class="ph mr-1.5" :class="isUpdating ? 'ph-spinner ph-spin' : 'ph-floppy-disk'"></i>
                            <span x-show="!isUpdating">Update Election</span>
                            <span x-show="isUpdating">Updating...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Candidate Modal -->
    <div id="candidateModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div onclick="closeCandidateModal()" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full z-10">
                <form id="candidateForm" method="POST" enctype="multipart/form-data" data-admin-inplace-form x-data="{ isAdding: false }" @submit="isAdding = true">
                    @csrf
                    <input type="hidden" name="position" id="candidatePosition">

                    <div class="p-5">
                        <!-- Position Display -->
                        <div style="border-color: var(--primary-color); background-color: rgba(var(--primary-color-rgb), 0.05);" class="mb-4 px-3 py-2.5 border-l-4 rounded-md">
                            <p style="color: var(--primary-color);" class="text-xs font-semibold">Position:</p>
                            <p class="text-sm font-bold text-gray-900" id="positionDisplay"></p>
                        </div>

                        <!-- Centered Photo Upload (Square) -->
                        <div class="flex flex-col items-center mb-4">
                            <label class="block text-gray-600 text-xs font-bold mb-2">Photo</label>
                            <div style="border-color: var(--primary-color);" class="relative w-40 h-40 border-2 border-dashed border-gray-200 rounded-lg transition cursor-pointer group">
                                <input type="file" name="photo" id="photoInput" accept="image/*" class="hidden" onchange="previewPhoto(event)">
                                <label for="photoInput" class="absolute inset-0 flex flex-col items-center justify-center cursor-pointer">
                                    <div id="photoPreview" class="hidden absolute inset-0">
                                        <img id="previewImage" src="" class="w-full h-full object-cover rounded-lg">
                                        <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center rounded-lg">
                                            <span class="text-white font-semibold text-center px-4 text-xs">
                                                <i class="ph ph-camera text-lg mb-1 block"></i>
                                                Change Photo
                                            </span>
                                        </div>
                                    </div>
                                    <div id="photoPlaceholder" class="flex flex-col items-center justify-center text-gray-400 p-4">
                                        <i style="color: var(--primary-color);" class="ph ph-cloud-arrow-up text-2xl mb-2"></i>
                                        <span class="text-xs font-medium text-center">Upload Photo</span>
                                        <span class="text-[11px] text-gray-400 mt-1 text-center">Click to browse</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Student ID Field (required, must be org member) -->
                        <div>
                            <label class="block text-gray-600 text-xs font-bold mb-1.5">Student ID <span class="text-red-500">*</span></label>
                            <div class="flex gap-2">
                                <input type="text" name="student_id" id="candidateStudentId" required autocomplete="off"
                                    class="flex-1 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-md text-sm focus:outline-none focus:ring-2"
                                    placeholder="e.g. 20-1234-0001">
                                <button type="button" onclick="verifyCandidateStudent()" id="verifyStudentBtn" class="hidden px-3 py-2.5 rounded-md text-xs font-semibold text-white whitespace-nowrap" style="background-color: var(--primary-color);">
                                    <i class="ph ph-magnifying-glass mr-1"></i>Verify
                                </button>
                            </div>
                            <p class="mt-1 text-[11px] text-gray-400">Only active members of this organization can be candidates.</p>
                            <div id="candidateVerifyStatus" class="mt-2 hidden rounded-md border px-3 py-2 text-xs"></div>
                            <div id="candidateVerifiedPreview" class="mt-2 hidden flex items-center gap-3 rounded-md border border-green-200 bg-green-50 px-3 py-2">
                                <img id="candidateVerifiedPhoto" src="" alt="" class="w-8 h-8 rounded-full object-cover hidden">
                                <div id="candidateVerifiedFallback" class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold hidden" style="background-color: var(--primary-color);"></div>
                                <div>
                                    <p id="candidateVerifiedName" class="text-xs font-semibold text-gray-900"></p>
                                    <p id="candidateVerifiedYear" class="text-[11px] text-gray-500"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="bg-gray-50 px-5 py-3 rounded-b-lg flex gap-2">
                        <button type="button" onclick="closeCandidateModal()" :disabled="isAdding"
                            class="flex-1 border border-gray-200 text-gray-600 font-semibold text-xs py-2.5 rounded-md hover:bg-gray-100 transition" :class="{ 'opacity-50 cursor-not-allowed': isAdding }">
                            <i class="ph ph-x mr-1.5"></i>Cancel
                        </button>
                        <button type="submit" :disabled="isAdding" class="flex-1 text-white text-xs font-semibold py-2.5 rounded-md transition" style="background-color: var(--primary-color);" :class="{ 'opacity-50 cursor-not-allowed': isAdding }">
                            <i class="ph mr-1.5" :class="isAdding ? 'ph-spinner ph-spin' : 'ph-user-plus'"></i>
                            <span x-show="!isAdding">Add Candidate</span>
                            <span x-show="isAdding">Adding...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Unpost Election Modal -->
    <div id="unpostElectionModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div onclick="closeUnpostModal()" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-sm w-full mx-4 z-10">
                <div class="p-5">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 mb-3">
                            <i class="ph ph-warning-circle text-xl text-yellow-600"></i>
                        </div>
                        <h3 class="text-sm font-bold text-gray-900 mb-1.5">Unpost Election</h3>
                        <p class="text-xs text-gray-500 mb-4">
                            Are you sure you want to unpost "<span id="unpostElectionTitle" class="font-semibold text-gray-700"></span>"? Students will no longer be able to vote.
                        </p>
                        <div class="flex gap-2" x-data="{ isSubmitting: false }">
                            <button @click="closeUnpostModal()" :disabled="isSubmitting" class="flex-1 px-3 py-2 border border-gray-200 rounded-md text-gray-600 font-semibold text-xs hover:bg-gray-50 transition" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }">
                                <i class="ph ph-x mr-1.5"></i>Cancel
                            </button>
                            <form id="unpostElectionForm" method="POST" data-admin-inplace-form class="flex-1" @submit="isSubmitting = true">
                                @csrf
                                <button type="submit" class="w-full px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md font-semibold text-xs transition" :disabled="isSubmitting" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }">
                                    <i class="ph mr-1.5" :class="isSubmitting ? 'ph-spinner ph-spin' : 'ph-x-circle'"></i>
                                    <span x-show="!isSubmitting">Unpost</span>
                                    <span x-show="isSubmitting">Unposting...</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Post Election Confirmation Modal -->
    <div id="postElectionModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div onclick="closePostElectionModal()" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-sm w-full mx-4 z-10">
                <div class="p-5">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-emerald-100 mb-3">
                            <i class="ph ph-paper-plane-tilt text-xl text-emerald-600"></i>
                        </div>
                        <h3 class="text-sm font-bold text-gray-900 mb-1.5">Post Election</h3>
                        <p class="text-xs text-gray-500 mb-4">
                            Are you sure you want to post "<span id="postElectionTitle" class="font-semibold text-gray-700"></span>"? It will be visible to all students and email notifications will be sent to every eligible student immediately.
                        </p>
                        <div class="flex gap-2">
                            <button @click="closePostElectionModal()" class="flex-1 px-3 py-2 border border-gray-200 rounded-md text-gray-600 font-semibold text-xs hover:bg-gray-50 transition">
                                <i class="ph ph-x mr-1.5"></i>Cancel
                            </button>
                            <button @click="confirmPostElection()" style="background-color: var(--primary-color);" class="flex-1 px-3 py-2 text-white rounded-md font-semibold text-xs transition hover:opacity-90">
                                <i class="ph ph-paper-plane-tilt mr-1.5"></i>Post Election
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Election Modal -->
    <div id="deleteElectionModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div onclick="closeDeleteElectionModal()" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-sm w-full mx-4 z-10">
                <div class="p-5">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-3">
                            <i class="ph ph-warning text-xl text-red-600"></i>
                        </div>
                        <h3 class="text-sm font-bold text-gray-900 mb-1.5">Delete Election</h3>
                        <p class="text-xs text-gray-500 mb-4">
                            Are you sure you want to delete "<span id="deleteElectionTitle" class="font-semibold text-gray-700"></span>"? This action cannot be undone.
                        </p>
                        <div class="flex gap-2" x-data="{ isDeleting: false }">
                            <button @click="closeDeleteElectionModal()" :disabled="isDeleting" class="flex-1 px-3 py-2 border border-gray-200 rounded-md text-gray-600 font-semibold text-xs hover:bg-gray-50 transition" :class="{ 'opacity-50 cursor-not-allowed': isDeleting }">
                                <i class="ph ph-x mr-1.5"></i>Cancel
                            </button>
                            <form id="deleteElectionForm" method="POST" data-admin-inplace-form class="flex-1" @submit="isDeleting = true">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md font-semibold text-xs transition" :disabled="isDeleting" :class="{ 'opacity-50 cursor-not-allowed': isDeleting }">
                                    <i class="ph mr-1.5" :class="isDeleting ? 'ph-spinner ph-spin' : 'ph-trash'"></i>
                                    <span x-show="!isDeleting">Delete</span>
                                    <span x-show="isDeleting">Deleting...</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Remove Candidate Modal -->
    <div id="removeCandidateModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div onclick="closeRemoveCandidateModal()" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-sm w-full mx-4 z-10">
                <div class="p-5">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-3">
                            <i class="ph ph-user-minus text-xl text-red-600"></i>
                        </div>
                        <h3 class="text-sm font-bold text-gray-900 mb-1.5">Remove Candidate</h3>
                        <p class="text-xs text-gray-500 mb-4">
                            Are you sure you want to remove "<span id="removeCandidateName" class="font-semibold text-gray-700"></span>" from this election? This action cannot be undone.
                        </p>
                        <div class="flex gap-2" x-data="{ isRemoving: false }">
                            <button @click="closeRemoveCandidateModal()" :disabled="isRemoving" class="flex-1 px-3 py-2 border border-gray-200 rounded-md text-gray-600 font-semibold text-xs hover:bg-gray-50 transition" :class="{ 'opacity-50 cursor-not-allowed': isRemoving }">
                                <i class="ph ph-x mr-1.5"></i>Cancel
                            </button>
                            <form id="removeCandidateForm" method="POST" data-admin-inplace-form class="flex-1" @submit="isRemoving = true">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md font-semibold text-xs transition" :disabled="isRemoving" :class="{ 'opacity-50 cursor-not-allowed': isRemoving }">
                                    <i class="ph mr-1.5" :class="isRemoving ? 'ph-spinner ph-spin' : 'ph-user-minus'"></i>
                                    <span x-show="!isRemoving">Remove</span>
                                    <span x-show="isRemoving">Removing...</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validation function for posting election
function validateElectionBeforePost(electionId, event) {
    const positions = [
        'president', 'vice_president', 'treasurer', 'auditor',
        'public_information_officer', 'business_manager', 'event_coordinator',
        '1st_year_rep', '2nd_year_rep', '3rd_year_rep', '4th_year_rep'
    ];
    
    const positionNames = {
        'president': 'President',
        'vice_president': 'Vice President',
        'treasurer': 'Treasurer',
        'auditor': 'Auditor',
        'public_information_officer': 'Public Information Officer',
        'business_manager': 'Business Manager',
        'event_coordinator': 'Event Coordinator',
        '1st_year_rep': '1st Year Representative',
        '2nd_year_rep': '2nd Year Representative',
        '3rd_year_rep': '3rd Year Representative',
        '4th_year_rep': '4th Year Representative'
    };
    
    const missingPositions = [];
    
    // Check each position for candidates
    positions.forEach(position => {
        const electionCard = event.target.closest('[data-election-workspace]');
        const positionCard = electionCard.querySelector(`[data-position="${position}"]`);
        
        if (positionCard) {
            const candidateCount = parseInt(positionCard.getAttribute('data-candidate-count') || '0');
            if (candidateCount === 0) {
                missingPositions.push(positionNames[position]);
            }
        }
    });
    
    if (missingPositions.length > 0) {
        event.preventDefault();
        showErrorNotification('Cannot post election! All positions need at least one candidate.');
        return false;
    }

    // Open the styled confirmation modal instead of the default browser dialog.
    pendingPostForm = event.target.closest('form');
    const electionWorkspace = pendingPostForm.closest('[data-election-workspace]');
    const electionTitle = electionWorkspace ? electionWorkspace.querySelector('h2').textContent.trim() : 'this election';
    document.getElementById('postElectionTitle').textContent = electionTitle || 'this election';
    document.getElementById('postElectionModal').classList.remove('hidden');
    event.preventDefault();
    return false;
}

let pendingPostForm = null;

function confirmPostElection() {
    const form = pendingPostForm;
    closePostElectionModal();
    if (form) {
        submitElectionPost(form);
    }
}

function closePostElectionModal() {
    const modal = document.getElementById('postElectionModal');
    if (modal) modal.classList.add('hidden');
    pendingPostForm = null;
}

function submitElectionPost(form) {
    if (form.dataset.submitting === 'true') return;
    form.dataset.submitting = 'true';
    const button = form.querySelector('.post-election-submit');
    button.disabled = true;
    button.classList.add('cursor-not-allowed', 'opacity-75');
    button.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i>Sending...';
    showElectionSendingToast();

    fetch(form.action, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value, 'Accept': 'application/json' },
        body: new FormData(form),
    }).then(async response => {
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Unable to post the election.');
        showElectionSendingToast(data.message, true);
        // The post request is intentionally AJAX so notification dispatch is
        // immediate. Refresh only the existing Admin content so the status
        // and action buttons match the updated election record.
        window.clearAdminPageCache && window.clearAdminPageCache();
        setTimeout(() => window.refreshCurrentAdminPage && window.refreshCurrentAdminPage(), 800);
    }).catch(error => {
        showElectionSendingToast(error.message, false);
        form.dataset.submitting = 'false';
        button.disabled = false;
        button.classList.remove('cursor-not-allowed', 'opacity-75');
        button.innerHTML = '<i class="ph ph-paper-plane-tilt mr-1"></i>Post election';
    });

    return false;
}

let electionToastTimer = null;

function showElectionSendingToast(message = 'Please wait while the election email notifications are being sent.', success = null) {
    let toast = document.getElementById('electionSendingToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'electionSendingToast';
        toast.className = 'fixed top-4 right-4 z-50 max-w-sm rounded-lg border bg-white px-4 py-3 shadow-lg';
        document.body.appendChild(toast);
    }
    const color = success === true ? 'text-green-600' : success === false ? 'text-red-600' : 'text-blue-600';
    const title = success === true ? 'Sending completed' : success === false ? 'Sending failed' : 'Please wait';
    toast.innerHTML = `<div class="flex items-start gap-3"><i class="ph ${success === true ? 'ph-check-circle' : success === false ? 'ph-warning-circle' : 'ph-spinner ph-spin'} mt-0.5 ${color}"></i><div><p class="text-xs font-bold text-gray-900">${title}</p><p class="mt-0.5 text-xs text-gray-600">${message}</p></div></div>`;

    // Auto-dismiss completed/failed toasts so they do not linger on screen.
    // The "Please wait" spinner stays until the request resolves.
    clearTimeout(electionToastTimer);
    if (success !== null) {
        electionToastTimer = setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 5000);
    }
}

// Show error notification
function showErrorNotification(message) {
    // Remove existing error notification if any
    const existingError = document.getElementById('dynamicErrorNotification');
    if (existingError) {
        existingError.remove();
    }
    
    // Create new error notification
    const notification = document.createElement('div');
    notification.id = 'dynamicErrorNotification';
    notification.className = 'fixed top-6 right-6 z-50 bg-white border-l-4 border-red-500 rounded-lg shadow-xl px-6 py-4 flex items-center space-x-3 max-w-md';
    notification.innerHTML = `
        <div class="flex-shrink-0">
            <div class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center">
                <i class="ph ph-warning-circle text-xl text-red-600"></i>
            </div>
        </div>
        <div class="flex-1">
            <p class="text-sm font-semibold text-gray-900">Error!</p>
            <p class="text-sm text-gray-600">${message}</p>
        </div>
        <button onclick="this.parentElement.remove()" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition">
            <i class="ph ph-x text-lg"></i>
        </button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 6 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 6000);
}

// Edit Election Modal Functions
function openEditModal(electionId, title, description, startDate, endDate) {
    // Get current date and time in local format
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
    
    // Set form action and values
    document.getElementById('editElectionForm').action = `/admin/elections/${electionId}`;
    document.getElementById('editTitle').value = title;
    document.getElementById('editDescription').value = description;
    
    // Set date values and minimum dates
    const startDateInput = document.getElementById('editStartDate');
    const endDateInput = document.getElementById('editEndDate');
    
    startDateInput.value = startDate;
    endDateInput.value = endDate;
    startDateInput.min = minDateTime;
    endDateInput.min = minDateTime;
    
    // Update end date minimum when start date changes
    startDateInput.addEventListener('change', function() {
        endDateInput.min = this.value;
    });
    
    document.getElementById('editElectionModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editElectionModal').classList.add('hidden');
    document.getElementById('editElectionForm').reset();
}

let candidateVerified = false;
let candidateVerifiedData = null;

function openCandidateModal(electionId, position, positionName) {
    document.getElementById('candidateForm').action = `/admin/elections/${electionId}/candidates`;
    document.getElementById('candidatePosition').value = position;
    document.getElementById('positionDisplay').textContent = positionName;
    candidateVerified = false;
    candidateVerifiedData = null;
    const statusEl = document.getElementById('candidateVerifyStatus');
    const previewEl = document.getElementById('candidateVerifiedPreview');
    if (statusEl) { statusEl.classList.add('hidden'); statusEl.textContent = ''; }
    if (previewEl) previewEl.classList.add('hidden');
    document.getElementById('candidateModal').classList.remove('hidden');
}

function closeCandidateModal() {
    document.getElementById('candidateModal').classList.add('hidden');
    document.getElementById('candidateForm').reset();
    candidateVerified = false;
    candidateVerifiedData = null;
    const statusEl = document.getElementById('candidateVerifyStatus');
    const previewEl = document.getElementById('candidateVerifiedPreview');
    if (statusEl) { statusEl.classList.add('hidden'); statusEl.textContent = ''; }
    if (previewEl) previewEl.classList.add('hidden');
    // Reset photo preview
    document.getElementById('photoPreview').classList.add('hidden');
    document.getElementById('photoPlaceholder').classList.remove('hidden');
}

// Auto-format Student ID like login (YYYY-XXXX or 00-0000-0000)
function formatCandidateStudentId(input) {
    let value = input.value.replace(/\D/g, '');
    let formatted = '';
    if (value.length <= 8) {
        if (value.length >= 4) {
            formatted = value.substring(0, 4) + '-' + value.substring(4, 8);
        } else {
            formatted = value;
        }
    } else {
        formatted = value.substring(0, 2);
        if (value.length > 2) {
            formatted += '-' + value.substring(2, 6);
        }
        if (value.length > 6) {
            formatted += '-' + value.substring(6, 10);
        }
    }
    input.value = formatted;
}

function verifyCandidateStudent() {
    const input = document.getElementById('candidateStudentId');
    const studentId = (input.value || '').trim();
    const statusEl = document.getElementById('candidateVerifyStatus');
    const previewEl = document.getElementById('candidateVerifiedPreview');
    const verifyBtn = document.getElementById('verifyStudentBtn');
    const isBtnVisible = verifyBtn && !verifyBtn.classList.contains('hidden');
    if (!studentId) {
        statusEl.textContent = 'Enter a student ID first.';
        statusEl.className = 'mt-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700';
        statusEl.classList.remove('hidden');
        candidateVerified = false;
        return;
    }
    if (isBtnVisible) {
        verifyBtn.disabled = true;
        verifyBtn.innerHTML = '<i class="ph ph-spinner ph-spin mr-1"></i>Verifying...';
    } else {
        statusEl.textContent = 'Verifying...';
        statusEl.className = 'mt-2 rounded-md border border-blue-100 bg-blue-50 px-3 py-2 text-xs text-blue-700';
        statusEl.classList.remove('hidden');
    }
    previewEl.classList.add('hidden');

    fetch(`/admin/members/lookup/${encodeURIComponent(studentId)}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
    })
    .then(async res => {
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.message || 'Verification failed.');
        return data;
    })
    .then(data => {
        const s = data.student;
        candidateVerified = true;
        candidateVerifiedData = s;
        statusEl.textContent = 'Verified: ' + s.full_name + ' (' + s.student_id + ')';
        statusEl.className = 'mt-2 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-xs text-green-700';
        statusEl.classList.remove('hidden');
        document.getElementById('candidateVerifiedName').textContent = s.full_name;
        document.getElementById('candidateVerifiedYear').textContent = s.year_level ? 'Year: ' + s.year_level + ' • ' + s.student_id : s.student_id;
        const photoEl = document.getElementById('candidateVerifiedPhoto');
        const fallbackEl = document.getElementById('candidateVerifiedFallback');
        if (s.photo_url) {
            photoEl.src = s.photo_url;
            photoEl.classList.remove('hidden');
            fallbackEl.classList.add('hidden');
        } else {
            photoEl.classList.add('hidden');
            fallbackEl.textContent = s.full_name.charAt(0).toUpperCase();
            fallbackEl.classList.remove('hidden');
        }
        previewEl.classList.remove('hidden');
    })
    .catch(err => {
        candidateVerified = false;
        candidateVerifiedData = null;
        statusEl.textContent = err.message;
        statusEl.className = 'mt-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700';
        statusEl.classList.remove('hidden');
        previewEl.classList.add('hidden');
    })
    .finally(() => {
        if (isBtnVisible) {
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = '<i class="ph ph-magnifying-glass mr-1"></i>Verify';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const sidInput = document.getElementById('candidateStudentId');
    if (sidInput) {
        sidInput.removeAttribute('maxlength');
        let previousValue = sidInput.value;
        let verifyTimeout = null;
        sidInput.addEventListener('input', function() {
            if (this.value.length >= previousValue.length) {
                formatCandidateStudentId(this);
            }
            previousValue = this.value;
            if (this.value.length > 12) {
                this.value = this.value.substring(0, 12);
            }
            candidateVerified = false;
            const statusEl = document.getElementById('candidateVerifyStatus');
            const previewEl = document.getElementById('candidateVerifiedPreview');
            if (statusEl) statusEl.classList.add('hidden');
            if (previewEl) previewEl.classList.add('hidden');
            clearTimeout(verifyTimeout);
            const digits = this.value.replace(/\D/g, '');
            if (digits.length === 8 || digits.length === 10) {
                verifyTimeout = setTimeout(() => verifyCandidateStudent(), 500);
            }
        });
        sidInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(verifyTimeout);
                verifyCandidateStudent();
            }
        });
        sidInput.addEventListener('blur', function() {
            const digits = this.value.replace(/\D/g, '');
            if ((digits.length === 8 || digits.length === 10) && !candidateVerified) {
                clearTimeout(verifyTimeout);
                verifyCandidateStudent();
            }
        });
    }
    const form = document.getElementById('candidateForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!candidateVerified) {
                e.preventDefault();
                showErrorNotification('Please verify the student ID before adding the candidate.');
                verifyCandidateStudent();
            }
        });
    }
});

function previewPhoto(event) {
    const file = event.target.files[0];
    if (!file) return;
    // Client validation: must be image and <=2MB (match server max:2048)
    if (!file.type.startsWith('image/')) {
        event.target.value = '';
        document.getElementById('photoPreview').classList.add('hidden');
        document.getElementById('photoPlaceholder').classList.remove('hidden');
        showErrorNotification('Invalid file type. Please upload an image file.');
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        event.target.value = '';
        document.getElementById('photoPreview').classList.add('hidden');
        document.getElementById('photoPlaceholder').classList.remove('hidden');
        showErrorNotification('File too large. Maximum size is 2MB.');
        return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('previewImage').src = e.target.result;
        document.getElementById('photoPreview').classList.remove('hidden');
        document.getElementById('photoPlaceholder').classList.add('hidden');
    };
    reader.readAsDataURL(file);
}

function showUnpostModal(electionId, electionTitle) {
    document.getElementById('unpostElectionTitle').textContent = electionTitle;
    document.getElementById('unpostElectionForm').action = `/admin/elections/${electionId}/unpost`;
    document.getElementById('unpostElectionModal').classList.remove('hidden');
}

// Drag-drop support for candidate photo (bypass of accept attribute)
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.querySelector('#candidateModal .relative.w-40');
    const photoInput = document.getElementById('photoInput');
    if (!dropZone || !photoInput) return;
    ['dragenter','dragover'].forEach(function(evt){ dropZone.addEventListener(evt, function(e){ e.preventDefault(); e.stopPropagation(); dropZone.classList.add('border-[var(--primary-color)]'); }); });
    ['dragleave','drop'].forEach(function(evt){ dropZone.addEventListener(evt, function(e){ e.preventDefault(); e.stopPropagation(); dropZone.classList.remove('border-[var(--primary-color)]'); }); });
    dropZone.addEventListener('drop', function(e){
        const files = e.dataTransfer && e.dataTransfer.files;
        if (!files || !files.length) return;
        const file = files[0];
        if (!file.type.startsWith('image/')) {
            showErrorNotification('Invalid file type. Please upload an image file.');
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            showErrorNotification('File too large. Maximum size is 2MB.');
            return;
        }
        // Assign to input and trigger preview
        const dt = new DataTransfer();
        dt.items.add(file);
        photoInput.files = dt.files;
        previewPhoto({target: photoInput});
    });
});

function closeUnpostModal() {
    document.getElementById('unpostElectionModal').classList.add('hidden');
}

function showDeleteElectionModal(electionId, electionTitle) {
    document.getElementById('deleteElectionTitle').textContent = electionTitle;
    document.getElementById('deleteElectionForm').action = `/admin/elections/${electionId}`;
    document.getElementById('deleteElectionModal').classList.remove('hidden');
}

function closeDeleteElectionModal() {
    document.getElementById('deleteElectionModal').classList.add('hidden');
}

function showRemoveCandidateModal(candidateId, candidateName) {
    document.getElementById('removeCandidateName').textContent = candidateName;
    document.getElementById('removeCandidateForm').action = `/admin/candidates/${candidateId}`;
    document.getElementById('removeCandidateModal').classList.remove('hidden');
}

function closeRemoveCandidateModal() {
    document.getElementById('removeCandidateModal').classList.add('hidden');
}
</script>

<style>
    [x-cloak] { display: none !important; }
</style>

@endsection
