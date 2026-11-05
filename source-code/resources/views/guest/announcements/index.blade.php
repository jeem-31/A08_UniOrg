@extends('layouts.guest')

@section('title', 'Announcements')

@section('content')
<style>
.gold-icon {
    color: #D7AC28;
}
</style>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Back Button -->
    <div class="mb-6">
        <a href="{{ route('guest.dashboard') }}" class="inline-flex items-center text-purple-600 hover:text-purple-800 font-semibold transition-colors">
            <i class="ph ph-arrow-left mr-2 gold-icon"></i>
            Back to Home
        </a>
    </div>
    
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Announcements</h1>
        <p class="text-gray-600 mt-2">Stay updated with the latest news from SOCCS</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($announcements as $announcement)
            <a href="{{ route('guest.announcements.show', $announcement) }}" class="group">
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 h-full flex flex-col">
                    @if($announcement->images && count($announcement->images) > 0)
                        <div class="relative overflow-hidden h-48">
                            <img src="{{ storage_url($announcement->images[0]) }}" alt="{{ $announcement->title }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                            @if(count($announcement->images) > 1)
                                <div class="absolute top-3 right-3 bg-black bg-opacity-60 text-white px-2 py-1 rounded-full text-xs">
                                    <i class="ph ph-images mr-1"></i> {{ count($announcement->images) }}
                                </div>
                            @endif
                            <div class="absolute top-3 left-3">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full shadow-lg
                                    {{ $announcement->type === 'urgent' ? 'bg-red-500 text-white' : '' }}
                                    {{ $announcement->type === 'event' ? 'bg-blue-500 text-white' : '' }}
                                    {{ $announcement->type === 'academic' ? 'bg-green-500 text-white' : '' }}
                                    {{ $announcement->type === 'general' ? 'bg-gray-500 text-white' : '' }}">
                                    {{ ucfirst($announcement->type) }}
                                </span>
                            </div>
                        </div>
                    @else
                        <div class="relative h-48 flex items-center justify-center" style="background: linear-gradient(to bottom right, #8238B3, #3D0066);">
                            <i class="ph ph-megaphone text-white text-6xl opacity-50 gold-icon"></i>
                            <div class="absolute top-3 left-3">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full shadow-lg
                                    {{ $announcement->type === 'urgent' ? 'bg-red-500 text-white' : '' }}
                                    {{ $announcement->type === 'event' ? 'bg-blue-500 text-white' : '' }}
                                    {{ $announcement->type === 'academic' ? 'bg-green-500 text-white' : '' }}
                                    {{ $announcement->type === 'general' ? 'bg-gray-500 text-white' : '' }}">
                                    {{ ucfirst($announcement->type) }}
                                </span>
                            </div>
                        </div>
                    @endif
                    
                    <div class="p-5 flex-1 flex flex-col">
                        <h3 class="text-xl font-bold text-gray-900 mb-2 transition-colors line-clamp-2" style="group-hover:color: #8238B3;">
                            {{ $announcement->title }}
                        </h3>
                        
                        <p class="text-gray-600 text-sm mb-4 flex-1 line-clamp-3">
                            {{ trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($announcement->content)))) }}
                        </p>
                        
                        <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                            <div class="flex items-center text-xs text-gray-500">
                                <i class="ph ph-clock mr-1 gold-icon"></i>
                                <span>{{ $announcement->created_at->diffForHumans() }}</span>
                            </div>
                            <span class="font-semibold text-sm" style="color: #8238B3;">
                                Read more <i class="ph ph-arrow-right ml-1 gold-icon"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </a>
        @empty
            <div class="col-span-3 bg-white rounded-lg shadow p-12 text-center">
                <i class="ph ph-megaphone text-gray-300 text-6xl mb-4 gold-icon"></i>
                <p class="text-gray-500 text-lg">No announcements available at the moment</p>
            </div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $announcements->links() }}
    </div>
</div>
@endsection
