@extends('layouts.osas')

@section('title', 'Account Details - OSAS')

@section('content')
<div class="container-fluid px-6 py-5">
    
    @if(session('success'))
    <div class="mb-4 px-4 py-3 bg-green-50 border border-green-100 rounded-lg flex items-center gap-3">
        <i class="ph ph-check-circle text-green-600"></i>
        <span class="text-sm font-bold text-green-800">{{ session('success') }}</span>
    </div>
    @endif

    @if(session('error'))
    <div class="mb-4 px-4 py-3 bg-red-50 border border-red-100 rounded-lg flex items-center gap-3">
        <i class="ph ph-warning-circle text-red-600"></i>
        <span class="text-sm font-bold text-red-800">{{ session('error') }}</span>
    </div>
    @endif

    <!-- Header / Title -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-5 gap-4">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-xl bg-blue-50 text-[#26488C] flex items-center justify-center font-black text-2xl flex-shrink-0">
                {{ substr($details->profileValues['first_name'] ?? '', 0, 1) }}{{ substr($details->profileValues['last_name'] ?? '', 0, 1) }}
            </div>
            <div>
                <h1 class="text-2xl font-black text-gray-900 tracking-tight leading-none mb-1">
                    {{ $details->fullName }}
                </h1>
                <div class="flex items-center gap-3 text-xs font-bold text-gray-400">
                    <span class="uppercase tracking-widest">{{ $details->profileValues['student_id'] ?? 'No ID' }}</span>
                    <span>&bull;</span>
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest {{ $details->active() ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600' }}">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span> {{ $details->stateLabel() }}
                    </span>
                    <span>&bull;</span>
                    <span class="uppercase tracking-widest">{{ \App\Services\AccountManagement\SystemAccessRole::tryFrom($details->role)?->label() ?? str_replace('_', ' ', $details->role) }}</span>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('osas.accounts.index') }}" class="px-4 py-2 bg-white border border-gray-200 text-gray-700 text-xs font-bold rounded-lg hover:bg-gray-50 transition-colors shadow-sm">
                <i class="ph ph-arrow-left mr-1.5"></i> Directory
            </a>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        
        <!-- Left Column: Details & Lifecycle -->
        <div class="lg:col-span-1 flex flex-col gap-5">
            <!-- Basic Info Card -->
            <div class="bg-white rounded-lg border border-gray-100 p-5">
                <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest mb-4 flex items-center gap-2 border-b border-gray-50 pb-3">
                    <i class="ph ph-identification-card text-[#26488C]"></i> Profile Details
                </h3>
                <div class="flex flex-col gap-3">
                    <div>
                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Email Address</p>
                        <p class="text-sm font-bold text-gray-800">{{ $details->profileValues['email'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Department</p>
                        <p class="text-sm font-bold text-gray-800">{{ $departmentName ?? 'N/A' }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Course</p>
                            <p class="text-sm font-bold text-gray-800">{{ $details->profileValues['course'] ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Year & Sec</p>
                            <p class="text-sm font-bold text-gray-800">{{ $details->profileValues['year_level'] ?? '-' }}{{ $details->profileValues['section'] ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Danger Zone / Lifecycle -->
            <div class="bg-white rounded-lg border border-red-100 p-5">
                <h3 class="text-xs font-black text-red-700 uppercase tracking-widest mb-4 flex items-center gap-2 border-b border-red-50 pb-3">
                    <i class="ph ph-warning"></i> Account Lifecycle
                </h3>

                <!-- Account Validity -->
                @if(isset($accountExpiry['expiresAt']) && $accountExpiry['expiresAt'])
                    <div class="mb-4 rounded-lg border p-3 {{ $accountExpiry['state'] === 'expired' ? 'border-red-200 bg-red-50' : ($accountExpiry['state'] === 'expiring' ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-gray-50') }}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-black uppercase tracking-widest {{ $accountExpiry['state'] === 'expired' ? 'text-red-700' : ($accountExpiry['state'] === 'expiring' ? 'text-amber-700' : 'text-gray-600') }}">
                                <i class="fas {{ $accountExpiry['state'] === 'expired' ? 'fa-user-slash' : ($accountExpiry['state'] === 'expiring' ? 'fa-hourglass-half' : 'fa-check-circle') }} mr-1"></i>
                                @if($accountExpiry['state'] === 'expired')
                                    Account expired — validity window closed
                                @elseif($accountExpiry['state'] === 'expiring')
                                    This account will be inactive in {{ $accountExpiry['daysLeft'] }} day{{ $accountExpiry['daysLeft'] === 1 ? '' : 's' }}
                                @else
                                    Account valid
                                @endif
                            </span>
                            <span class="text-[10px] font-bold text-gray-500">Expires {{ $accountExpiry['expiresAt']->format('M d, Y') }}</span>
                        </div>
                    </div>
                @endif
                
                <!-- Lifecycle Toggle -->
                <form method="POST" action="{{ route('osas.accounts.lifecycle', $details->accountId) }}" class="mb-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="action" value="{{ $details->active() ? 'deactivate' : 'activate' }}">
                    <input type="hidden" name="freshness" value="{{ $details->managementVersion }}">
                    @if(($accountExpiry['state'] ?? 'active') === 'expired' && ! $details->active())
                        <button type="button" disabled class="w-full flex items-center justify-between px-4 py-2 border border-gray-200 bg-gray-50 text-gray-400 rounded-lg text-xs font-bold cursor-not-allowed">
                            <span>Reactivate Account</span>
                            <i class="ph ph-lock"></i>
                        </button>
                        <p class="mt-2 text-[9px] font-bold text-red-600 uppercase tracking-widest">Expired &mdash; update its validity to reactivate this account.</p>
                    @else
                        <button type="submit" class="w-full flex items-center justify-between px-4 py-2 border {{ $details->active() ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100' : 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100' }} rounded-lg text-xs font-bold transition-colors">
                            <span>{{ $details->active() ? 'Deactivate Account' : 'Reactivate Account' }}</span>
                            <i class="fas {{ $details->active() ? 'fa-ban' : 'fa-check' }}"></i>
                        </button>
                    @endif
                </form>

                <p class="text-[9px] text-gray-500 font-bold uppercase tracking-wider mt-2">Deactivate only — OSAS no longer permanently deletes accounts from this view. Use the lifecycle toggle above.</p>
            </div>
        </div>

        <!-- Right Column: Assignments and Audits -->
        <div class="lg:col-span-2 flex flex-col gap-5">
            
            <!-- Special Appointments Card -->
            <div class="bg-white rounded-lg border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-50 flex items-center justify-between">
                    <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest flex items-center gap-2">
                        <i class="ph ph-star text-amber-500"></i> Special Appointments
                    </h3>
                </div>
                <div class="p-5 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-gray-900">Dean{{ isset($departmentName) && $departmentName ? ' of '.$departmentName : '' }}</p>
                        <p class="text-[10px] text-gray-500 mt-1">
                            @if($details->deanStatus->holdsAppointment())
                                This account is currently the appointed Dean{{ isset($departmentName) && $departmentName ? ' of '.$departmentName : '' }}. Apply/Renew forms autofill this Dean for the college.
                            @else
                                Appoint this account as the Dean{{ isset($departmentName) && $departmentName ? ' of '.$departmentName : '' }}. This will replace the current Dean of this college only. Apply/Renew forms autofill the assigned Dean, so no Dean input is needed there.
                            @endif
                        </p>
                    </div>
                    @if(!$details->deanStatus->holdsAppointment())
                        <x-accessible-confirmation-dialog id="appointDean" title="Appoint Dean" danger="false">
                            <x-slot name="trigger">
                                <button type="button" class="px-4 py-2 bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 rounded-lg text-xs font-bold transition-colors whitespace-nowrap">
                                    Appoint Dean
                                </button>
                            </x-slot>
                            <p>Are you sure you want to appoint <strong>{{ $details->fullName }}</strong> as the new Dean of OSAS? The current Dean will be relieved of their appointment and demoted to OSAS Admin.</p>
                            <x-slot name="action">
                                <form method="POST" action="{{ route('osas.accounts.appointments.dean', $details->accountId) }}">
                                    @csrf
                                    <input type="hidden" name="candidate_id" value="{{ $details->accountId }}">
                                    <input type="hidden" name="freshness" value="{{ $deanAppointment['candidateVersion'] }}">
                                    @if($deanAppointment['replacesDean'])
                                        <input type="hidden" name="outcome" value="reassign">
                                        <input type="hidden" name="former_dean_role" value="osas_admin">
                                        <input type="hidden" name="confirmation" value="{{ $deanAppointment['confirmation'] }}">
                                    @endif
                                    <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-amber-600 border border-transparent text-white text-sm font-bold rounded-lg hover:bg-amber-700 transition-colors focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                        Confirm Appointment
                                    </button>
                                </form>
                            </x-slot>
                        </x-accessible-confirmation-dialog>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded bg-amber-100 text-amber-800 text-[10px] font-black uppercase tracking-widest">
                            <i class="ph ph-check-circle"></i> Active
                        </span>
                    @endif
                </div>
            </div>

            <!-- Assignments Card -->
            <div class="bg-white rounded-lg border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-50 flex items-center justify-between">
                    <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest flex items-center gap-2">
                        <i class="ph ph-network text-[#C4973B]"></i> Organization Assignments
                    </h3>
                </div>
                <div class="p-0">
                    @if(count($details->assignments) > 0)
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50/50">
                                <tr>
                                    <th class="px-5 py-2 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">Organization</th>
                                    <th class="px-5 py-2 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">Position</th>
                                    <th class="px-5 py-2 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($details->assignments as $assignment)
                                <tr>
                                    <td class="px-5 py-3 font-bold text-gray-900 text-xs">{{ $assignment->organizationName }}</td>
                                    <td class="px-5 py-3 text-xs text-gray-600">{{ $assignment->positionLabel() }}</td>
                                    <td class="px-5 py-3 text-[10px] font-black uppercase text-green-600 tracking-widest">Active</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="p-6 text-center text-gray-400">
                            <i class="ph ph-user-tag text-2xl mb-2 text-gray-300"></i>
                            <p class="text-[10px] font-black uppercase tracking-widest">No active assignments</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Audits Card -->
            <div class="bg-white rounded-lg border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-50 flex items-center justify-between">
                    <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest flex items-center gap-2">
                        <i class="ph ph-clock-counter-clockwise text-indigo-600"></i> Recent Activity
                    </h3>
                    @if($details->auditAvailable)
                        <a href="{{ route('osas.audits.index', ['account' => $details->accountId]) }}" class="text-[9px] font-black uppercase text-indigo-600 hover:underline tracking-widest">View All</a>
                    @endif
                </div>
                <div class="p-0">
                    @if(!$details->auditAvailable)
                        <div class="p-6 text-center text-gray-400">
                            <p class="text-[10px] font-black uppercase tracking-widest">Activity log unavailable</p>
                        </div>
                    @elseif(count($details->auditEntries) > 0)
                        <div class="divide-y divide-gray-50">
                            @foreach($details->auditEntries as $entry)
                            <div class="px-5 py-3 flex gap-3 hover:bg-gray-50/50 transition-colors">
                                <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0 text-gray-500">
                                    <i class="ph ph-pencil-simple text-xs"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs text-gray-900">
                                        <span class="font-bold">{{ $entry->actorName }}</span> performed <span class="font-bold font-mono text-[10px] bg-gray-100 px-1 py-0.5 rounded">{{ $entry->actionLabel }}</span>
                                    </p>
                                    <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-1">{{ $entry->occurredAt->diffForHumans() }}</p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-6 text-center text-gray-400">
                            <p class="text-[10px] font-black uppercase tracking-widest">No recent activity</p>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    
    .container-fluid {
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
</style>
@endsection
