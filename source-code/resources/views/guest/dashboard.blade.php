@extends('layouts.guest')

@section('title', 'Home')

@section('content')
<style>
    .card-hover {
        transition: all 0.3s ease;
    }
    .card-hover:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(130, 56, 179, 0.3);
    }
    .text-truncate-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .text-truncate-3 {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .gold-icon {
        color: #D7AC28;
    }
</style>

<!-- Main Content -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">

    <!-- Announcements and Events Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
        <!-- Latest Announcements -->
        <section>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Latest Announcements</h2>
                    <p class="text-gray-600 mt-1">Stay updated with the latest news from CCS</p>
                </div>
                <a href="{{ route('guest.announcements.index') }}" 
                   class="text-purple-600 hover:text-purple-800 font-semibold transition-colors text-sm">
                    View All <i class="ph ph-arrow-right ml-1 gold-icon"></i>
                </a>
            </div>
            
            <div class="space-y-4 mb-6">
            @forelse($announcements as $announcement)
                <a href="{{ route('guest.announcements.show', $announcement) }}?from=dashboard" class="group block">
                    <article class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition-all duration-300">
                        <div class="flex h-32">
                            @if($announcement->images && count($announcement->images) > 0)
                                <div class="w-32 h-32 flex-shrink-0 overflow-hidden relative">
                                    <img src="{{ storage_url($announcement->images[0]) }}" 
                                         alt="{{ $announcement->title }}" 
                                         class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                                    @if(count($announcement->images) > 1)
                                        <div class="absolute top-1 right-1 bg-purple-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                            {{ count($announcement->images) }}
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="w-32 h-32 flex-shrink-0 bg-gradient-to-br from-[#8238B3] to-[#3D0066] flex items-center justify-center">
                                    <i class="ph ph-megaphone text-white text-3xl opacity-50 gold-icon"></i>
                                </div>
                            @endif
                            
                            <div class="flex-1 p-4 flex flex-col">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full shadow
                                            {{ $announcement->type === 'urgent' ? 'bg-red-500 text-white' : '' }}
                                            {{ $announcement->type === 'event' ? 'bg-blue-500 text-white' : '' }}
                                            {{ $announcement->type === 'academic' ? 'bg-green-500 text-white' : '' }}
                                            {{ $announcement->type === 'general' ? 'bg-gray-500 text-white' : '' }}">
                                            {{ ucfirst($announcement->type) }}
                                        </span>
                                        <span class="text-xs text-gray-500">
                                            <i class="ph ph-clock mr-1 gold-icon"></i>
                                            {{ $announcement->created_at->diffForHumans() }}
                                        </span>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-900 mb-2 group-hover:text-purple-700 transition-colors">
                                        {{ $announcement->title }}
                                    </h3>
                                    <p class="text-sm text-gray-600 mb-4">
                                        {{ $announcement->content ? Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($announcement->content)))), 100) : 'No content available' }}
                                    </p>
                                </div>
                                <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                                    <span class="text-xs text-gray-500">
                                        <i class="ph ph-user mr-1"></i>
                                        {{ $announcement->user->name ?? 'Admin' }}
                                    </span>
                                    <span class="text-sm font-bold text-purple-600 group-hover:text-purple-800 transition-colors bg-purple-50 px-3 py-1 rounded-full">
                                        Read More <i class="ph ph-caret-right ml-1 text-xs gold-icon"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </article>
                </a>
            @empty
                <div class="bg-white rounded-xl shadow-md p-8 text-center">
                    <i class="ph ph-megaphone text-4xl text-gray-300 mb-4 gold-icon"></i>
                    <p class="text-gray-500 text-lg">No announcements available at the moment</p>
                </div>
            @endforelse
        </div>
        </section>

        <!-- Upcoming Events -->
        <section>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Upcoming Events</h2>
                    <p class="text-gray-600 mt-1">Don't miss out on exciting CCS events</p>
                </div>
                <a href="{{ route('guest.events.index') }}" 
                   class="text-purple-600 hover:text-purple-800 font-semibold transition-colors text-sm">
                    View All <i class="ph ph-arrow-right ml-1 gold-icon"></i>
                </a>
            </div>
            
            <div class="space-y-4 mb-6">
            @forelse($upcomingEvents as $event)
                <a href="{{ route('guest.events.show', $event) }}?from=dashboard" class="group block">
                    <article class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition-all duration-300">
                        <div class="flex h-32">
                            @if($event->images && count($event->images) > 0)
                                <div class="w-32 h-32 flex-shrink-0 overflow-hidden relative">
                                    <img src="{{ storage_url($event->images[0]) }}" 
                                         alt="{{ $event->title }}" 
                                         class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                                    @if(count($event->images) > 1)
                                        <div class="absolute top-1 right-1 bg-blue-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                            {{ count($event->images) }}
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="w-32 h-32 flex-shrink-0 bg-gradient-to-br from-[#8238B3] to-[#3D0066] flex items-center justify-center">
                                    <i class="ph ph-calendar-blank text-white text-3xl opacity-50 gold-icon"></i>
                                </div>
                            @endif
                            
                            <div class="flex-1 p-4 flex flex-col">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="bg-purple-100 px-2 py-1 rounded text-xs font-bold text-purple-600">
                                            {{ $event->start_date->format('M d') }}
                                        </div>
                                        <span class="text-xs text-gray-500">
                                            <i class="ph ph-clock mr-1 gold-icon"></i>
                                            @if($event->start_date->isToday())
                                                Today
                                            @elseif($event->start_date->isTomorrow())
                                                Tomorrow
                                            @else
                                                {{ $event->start_date->diffForHumans() }}
                                            @endif
                                        </span>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-900 mb-2 group-hover:text-purple-700 transition-colors">
                                        {{ $event->title }}
                                    </h3>
                                    <p class="text-sm text-gray-600 mb-4">
                                        {{ $event->description ? Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($event->description)))), 80) : 'No description available' }}
                                    </p>
                                    <div class="flex items-center gap-3 text-xs text-gray-600 mb-3">
                                        <span>
                                            <i class="ph ph-clock mr-1 gold-icon"></i>
                                            {{ $event->start_date->format('h:i A') }}
                                        </span>
                                        <span>
                                            <i class="ph ph-map-pin mr-1 gold-icon"></i>
                                            {{ $event->venue }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                                    <span class="text-xs text-gray-500">
                                        <i class="ph ph-user mr-1"></i>
                                        {{ $event->user->name ?? 'Admin' }}
                                    </span>
                                    <span class="text-sm font-bold text-purple-600 group-hover:text-purple-800 transition-colors bg-purple-50 px-3 py-1 rounded-full">
                                        Learn More <i class="ph ph-caret-right ml-1 text-xs gold-icon"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </article>
                </a>
            @empty
                <div class="bg-white rounded-xl shadow-md p-8 text-center">
                    <i class="ph ph-calendar-blank text-4xl text-gray-300 mb-4 gold-icon"></i>
                    <p class="text-gray-500 text-lg">No upcoming events scheduled</p>
                </div>
            @endforelse
        </div>
        </section>
    </div>
</div>
@endsection
