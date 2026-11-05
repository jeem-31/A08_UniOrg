@extends('layouts.osas')

@section('title', 'Audit Logs - OSAS')

@section('content')
<div class="container-fluid px-6 py-5">
    
    @if($errors->any())
    <div class="mb-4 px-4 py-3 bg-red-50 border border-red-100 rounded-lg flex items-center gap-3">
        <i class="ph ph-warning-circle text-red-600"></i>
        <div class="text-sm font-bold text-red-800">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Header -->
    <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight leading-none mb-1">
                Audit <span class="text-indigo-600">Logs</span>
            </h1>
            <p class="text-gray-500 text-xs font-medium">Review administrative actions and account lifecycle events.</p>
        </div>
        <a href="{{ route('osas.accounts.index') }}" class="px-4 py-2 bg-white border border-gray-200 text-gray-700 text-xs font-bold rounded-lg hover:bg-gray-50 transition-colors shadow-sm whitespace-nowrap">
            <i class="ph ph-arrow-left mr-1.5"></i> Back to Directory
        </a>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white rounded-t-lg border border-gray-100 p-4 border-b-0 flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('osas.audits.index') }}" class="flex-1 flex flex-wrap items-center gap-3">
            
            <div class="flex items-center gap-2">
                <label class="text-[10px] font-black uppercase text-gray-400 tracking-widest">Account</label>
                <input type="number" name="account" value="{{ request('account') }}" placeholder="e.g. 123" class="w-24 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-200 focus:border-indigo-600 transition-all">
            </div>
            
            <div class="flex items-center gap-2">
                <label class="text-[10px] font-black uppercase text-gray-400 tracking-widest">Actor</label>
                <input type="number" name="actor" value="{{ request('actor') }}" placeholder="e.g. 456" class="w-24 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-200 focus:border-indigo-600 transition-all">
            </div>

            <div class="flex items-center gap-2">
                <label class="text-[10px] font-black uppercase text-gray-400 tracking-widest">From</label>
                <input type="date" name="from" value="{{ request('from') }}" class="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-200 focus:border-indigo-600 transition-all">
            </div>

            <div class="flex items-center gap-2">
                <label class="text-[10px] font-black uppercase text-gray-400 tracking-widest">To</label>
                <input type="date" name="to" value="{{ request('to') }}" class="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-200 focus:border-indigo-600 transition-all">
            </div>

            <button type="submit" class="px-4 py-1.5 bg-indigo-50 text-indigo-700 border border-indigo-100 text-xs font-bold rounded-lg hover:bg-indigo-100 transition-colors">
                Apply Filters
            </button>

            @if(request()->hasAny(['account', 'actor', 'from', 'to']))
                <a href="{{ route('osas.audits.index') }}" class="text-[10px] font-black uppercase text-gray-400 hover:text-red-500 tracking-widest transition-colors">Clear</a>
            @endif
        </form>
    </div>

    <!-- Log Table -->
    <div class="bg-white rounded-b-lg border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-50">
                        <th class="px-5 py-3 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">Date &amp; Time</th>
                        <th class="px-5 py-3 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">Actor</th>
                        <th class="px-5 py-3 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">Action</th>
                        <th class="px-5 py-3 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">Target Account</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($audits->entries as $entry)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-5 py-3 whitespace-nowrap">
                                <div class="text-xs font-bold text-gray-900">{{ $entry->occurredAt->format('M d, Y') }}</div>
                                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ $entry->occurredAt->format('h:i:s A') }}</div>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-[9px] font-black flex-shrink-0">
                                        <i class="ph ph-user-shield"></i>
                                    </div>
                                    <span class="text-xs font-bold text-gray-800">{{ $entry->actorName }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-block px-2 py-1 bg-gray-100 text-gray-700 text-[10px] font-mono font-bold rounded">
                                    {{ $entry->actionLabel }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-[#26488C]">{{ $entry->affectedAccountName }}</span>
                                    <a href="{{ route('osas.accounts.show', $entry->affectedAccountId) }}" class="text-gray-300 hover:text-[#26488C] transition-colors">
                                        <i class="ph ph-arrow-square-out text-[10px]"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-12 text-center text-gray-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="ph ph-clipboard-text text-3xl mb-3 text-gray-300"></i>
                                    <p class="text-[11px] font-black uppercase tracking-widest">No audit records found</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($audits->lastPage() > 1)
            <div class="px-5 py-3 border-t border-gray-50 bg-gray-50/30 flex items-center justify-between">
                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Page {{ $audits->page() }} of {{ $audits->lastPage() }}</span>
                <div class="flex gap-2">
                    @if($audits->hasPreviousPage())
                        <a href="?{{ http_build_query($audits->queryParametersForPage($audits->page() - 1)) }}" class="px-3 py-1 border border-gray-200 rounded text-[10px] font-black uppercase tracking-widest text-gray-600 hover:bg-white hover:text-indigo-600 transition-colors">Prev</a>
                    @endif
                    @if($audits->hasNextPage())
                        <a href="?{{ http_build_query($audits->queryParametersForPage($audits->page() + 1)) }}" class="px-3 py-1 border border-gray-200 rounded text-[10px] font-black uppercase tracking-widest text-gray-600 hover:bg-white hover:text-indigo-600 transition-colors">Next</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    
    .container-fluid {
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
</style>
@endsection
