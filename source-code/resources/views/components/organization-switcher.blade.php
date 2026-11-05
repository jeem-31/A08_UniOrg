@props(['organizations', 'currentOrganization'])

@if($organizations->count() > 1)
<div class="relative" x-data="{ open: false }">
    <button @click="open = !open" 
            class="flex items-center space-x-2 px-3 py-2 text-sm font-medium text-gray-700 hover:text-purple-600 transition rounded-lg hover:bg-gray-100">
        <i class="ph ph-buildings text-purple-600"></i>
        <span class="max-w-[150px] truncate">{{ $currentOrganization->name ?? 'Select Organization' }}</span>
        <i class="ph ph-caret-down text-xs"></i>
    </button>
    
    <div x-show="open" 
         @click.away="open = false" 
         x-cloak
         x-transition
         class="absolute right-0 mt-2 w-64 rounded-lg shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"
         style="display: none;">
        <div class="py-1">
            <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase border-b">
                Switch Organization
            </div>
            @foreach($organizations as $org)
                <form method="POST" action="{{ route('organization.switch') }}" class="block">
                    @csrf
                    <input type="hidden" name="organization_id" value="{{ $org->id }}">
                    <button type="submit" 
                            class="w-full text-left px-4 py-3 text-sm hover:bg-gray-100 transition flex items-center justify-between {{ $currentOrganization && $currentOrganization->id === $org->id ? 'bg-purple-50' : '' }}">
                        <div class="flex items-center space-x-3">
                            <i class="ph ph-buildings {{ $currentOrganization && $currentOrganization->id === $org->id ? 'text-purple-600' : 'text-gray-400' }}"></i>
                            <div>
                                <div class="font-medium text-gray-900">{{ $org->name }}</div>
                                <div class="text-xs text-gray-500">{{ $org->pivot->role }}</div>
                            </div>
                        </div>
                        @if($currentOrganization && $currentOrganization->id === $org->id)
                            <i class="ph ph-check text-purple-600"></i>
                        @endif
                    </button>
                </form>
            @endforeach
        </div>
    </div>
</div>
@endif
