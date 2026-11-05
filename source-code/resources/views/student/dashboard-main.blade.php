@extends('layouts.app')

@section('title', 'Homepage')

@section('content')
<div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8 md:py-12">
    <!-- Welcome Section -->
    <div class="mb-8 md:mb-12 text-center">
        <h1 class="text-2xl sm:text-3xl md:text-4xl font-black tracking-tight text-gray-900 mb-2 md:mb-3">Welcome back, {{ auth()->user()->first_last_name }}!</h1>
        <p class="text-base sm:text-lg md:text-xl text-gray-600">Here's what's happening in {{ $orgAcronym ?? 'CCS' }} today</p>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 mb-8 md:mb-12">
        <a href="{{ route('student.announcements.index') }}" class="bg-white p-4 sm:p-6 rounded-xl shadow-md hover:shadow-xl transition-all duration-300 text-center group">
            <div class="bg-purple-100 w-12 h-12 sm:w-16 sm:h-16 rounded-full flex items-center justify-center mx-auto mb-2 sm:mb-3 group-hover:bg-purple-200 transition-colors">
                <i style="color: var(--primary-color);" class="ph ph-megaphone text-xl sm:text-2xl"></i>
            </div>
            <p class="text-gray-700 font-semibold text-sm sm:text-base">Announcements</p>
        </a>

        <a href="{{ route('events.calendar') }}" class="bg-white p-4 sm:p-6 rounded-xl shadow-md hover:shadow-xl transition-all duration-300 text-center group">
            <div class="bg-blue-100 w-12 h-12 sm:w-16 sm:h-16 rounded-full flex items-center justify-center mx-auto mb-2 sm:mb-3 group-hover:bg-blue-200 transition-colors">
                <i class="ph ph-calendar-blank text-blue-600 text-xl sm:text-2xl"></i>
            </div>
            <p class="text-gray-700 font-semibold text-sm sm:text-base">Events</p>
        </a>

        <a href="{{ route('payments.index') }}" class="bg-white p-4 sm:p-6 rounded-xl shadow-md hover:shadow-xl transition-all duration-300 text-center group">
            <div class="bg-green-100 w-12 h-12 sm:w-16 sm:h-16 rounded-full flex items-center justify-center mx-auto mb-2 sm:mb-3 group-hover:bg-green-200 transition-colors">
                <i class="ph ph-credit-card text-green-600 text-xl sm:text-2xl"></i>
            </div>
            <p class="text-gray-700 font-semibold text-sm sm:text-base">Payments</p>
        </a>

        <a href="{{ route('library.index') }}" class="bg-white p-4 sm:p-6 rounded-xl shadow-md hover:shadow-xl transition-all duration-300 text-center group">
            <div class="w-12 h-12 sm:w-16 sm:h-16 rounded-full flex items-center justify-center mx-auto mb-2 sm:mb-3 transition-colors" style="background-color: rgba(var(--complementary-color-rgb, 218, 165, 32), 0.2);">
                <i class="ph ph-book text-xl sm:text-2xl" style="color: var(--complementary-color);"></i>
            </div>
            <p class="text-gray-700 font-semibold text-sm sm:text-base">e-Library</p>
        </a>
    </div>

    <!-- Announcements and Events Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 mb-8 md:mb-12">
        <!-- Latest Announcements -->
        <section>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4 sm:mb-6">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Latest Announcements</h2>
                    <p class="text-sm sm:text-base text-gray-600 mt-1">Stay updated with the latest news from CCS</p>
                </div>
                <a href="{{ route('student.announcements.index') }}" 
                   style="color: var(--primary-color);" class="hover:opacity-80 font-semibold transition-colors text-sm self-start sm:self-auto">
                    View All <i class="ph ph-arrow-right ml-1" style="color: var(--complementary-color);"></i>
                </a>
            </div>
            
            <div class="space-y-3 sm:space-y-4 mb-4 sm:mb-6">
                @forelse($announcements as $announcement)
                    <a href="{{ route('student.announcements.show', $announcement) }}" class="group block">
                        <article class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition-all duration-300">
                            <div class="flex h-28 sm:h-32">
                                @if($announcement->images && count($announcement->images) > 0)
                                    <div class="w-24 h-28 sm:w-32 sm:h-32 flex-shrink-0 overflow-hidden relative">
                                        <img src="{{ storage_url($announcement->images[0]) }}" 
                                             alt="{{ $announcement->title }}" 
                                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                                        @if(count($announcement->images) > 1)
                                            <div style="background-color: var(--complementary-color);" class="absolute top-1 right-1 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                                {{ count($announcement->images) }}
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div class="w-24 h-28 sm:w-32 sm:h-32 flex-shrink-0 bg-gradient-to-br from-[var(--primary-color)] to-[rgba(var(--primary-color-rgb, 130, 56, 179), 0.8)] flex items-center justify-center">
                                        <i class="ph ph-megaphone text-white text-2xl sm:text-3xl opacity-50" style="color: var(--complementary-color);"></i>
                                    </div>
                                @endif
                                
                                <div class="flex-1 p-3 sm:p-4 flex flex-col min-w-0">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1 sm:mb-2 flex-wrap">
                                            <span class="px-2 py-0.5 sm:py-1 text-xs font-semibold rounded-full shadow
                                                {{ $announcement->type === 'urgent' ? 'bg-red-500 text-white' : '' }}
                                                {{ $announcement->type === 'event' ? 'bg-blue-500 text-white' : '' }}
                                                {{ $announcement->type === 'academic' ? 'bg-green-500 text-white' : '' }}
                                                {{ $announcement->type === 'general' ? 'bg-gray-500 text-white' : '' }}">
                                                {{ ucfirst($announcement->type) }}
                                            </span>
                                            <span class="text-xs text-gray-500 hidden sm:inline">
                                                <i class="ph ph-clock mr-1" style="color: var(--complementary-color);"></i>
                                                {{ $announcement->created_at->diffForHumans() }}
                                            </span>
                                        </div>
                                        <h3 style="color: var(--primary-color);" class="text-sm sm:text-lg font-bold text-gray-900 mb-1 sm:mb-2 group-hover:opacity-80 transition-colors line-clamp-1 sm:line-clamp-1">
                                            {{ $announcement->title }}
                                        </h3>
                                        <p class="text-xs sm:text-sm text-gray-600 line-clamp-2">
                                            {{ $announcement->content ? Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($announcement->content)))), 80) : 'No content available' }}
                                        </p>
                                    </div>
                                    <div class="hidden sm:flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                                        <span class="text-xs text-gray-500">
                                            <i class="ph ph-user mr-1" style="color: var(--complementary-color);"></i>
                                            {{ $announcement->user->name ?? 'Admin' }}
                                        </span>
                                        <span style="color: var(--primary-color);" class="text-sm font-bold group-hover:opacity-80 transition-colors bg-purple-50 px-3 py-1 rounded-full">
                                            Read More <i class="ph ph-caret-right ml-1" style="color: var(--complementary-color);"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </a>
                @empty
                    <div class="bg-white rounded-xl shadow-md p-6 sm:p-8 text-center">
                        <i class="ph ph-megaphone text-3xl sm:text-4xl text-gray-300 mb-3 sm:mb-4" style="color: var(--complementary-color);"></i>
                        <p class="text-gray-500 text-sm sm:text-base">No announcements available at the moment</p>
                    </div>
                @endforelse
            </div>
        </section>

        <!-- Upcoming Events -->
        <section>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4 sm:mb-6">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Upcoming Events</h2>
                    <p class="text-sm sm:text-base text-gray-600 mt-1">Don't miss out on exciting CCS events</p>
                </div>
                <a href="{{ route('events.calendar') }}" 
                   style="color: var(--primary-color);" class="hover:opacity-80 font-semibold transition-colors text-sm self-start sm:self-auto">
                    View Calendar <i class="ph ph-arrow-right ml-1" style="color: var(--complementary-color);"></i>
                </a>
            </div>
            
            <div class="space-y-3 sm:space-y-4 mb-4 sm:mb-6">
                @forelse($upcomingEvents as $event)
                    <a href="{{ route('events.show', $event) }}" class="group block">
                        <article class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition-all duration-300">
                            <div class="flex h-28 sm:h-32">
                                @if($event->images && count($event->images) > 0)
                                    <div class="w-24 h-28 sm:w-32 sm:h-32 flex-shrink-0 overflow-hidden relative">
                                        <img src="{{ storage_url($event->images[0]) }}" 
                                             alt="{{ $event->title }}" 
                                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                                        @if(count($event->images) > 1)
                                            <div style="background-color: var(--complementary-color);" class="absolute top-1 right-1 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                                {{ count($event->images) }}
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div class="w-24 h-28 sm:w-32 sm:h-32 flex-shrink-0 bg-gradient-to-br from-[var(--primary-color)] to-[rgba(var(--primary-color-rgb, 130, 56, 179), 0.8)] flex items-center justify-center">
                                        <i class="ph ph-calendar-blank text-white text-2xl sm:text-3xl opacity-50" style="color: var(--complementary-color);"></i>
                                    </div>
                                @endif
                                
                                <div class="flex-1 p-3 sm:p-4 flex flex-col min-w-0">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1 sm:mb-2 flex-wrap">
                                            <div style="color: var(--primary-color);" class="bg-purple-100 px-2 py-0.5 sm:py-1 rounded text-xs font-bold">
                                                {{ $event->start_date->format('M d') }}
                                            </div>
                                            <span class="text-xs text-gray-500 hidden sm:inline">
                                                <i class="ph ph-clock mr-1" style="color: var(--complementary-color);"></i>
                                                @if($event->start_date->isToday())
                                                    Today
                                                @elseif($event->start_date->isTomorrow())
                                                    Tomorrow
                                                @else
                                                    {{ $event->start_date->diffForHumans() }}
                                                @endif
                                            </span>
                                        </div>
                                        <h3 style="color: var(--primary-color);" class="text-sm sm:text-lg font-bold text-gray-900 mb-1 sm:mb-2 group-hover:opacity-80 transition-colors line-clamp-1">
                                            {{ $event->title }}
                                        </h3>
                                        <p class="text-xs sm:text-sm text-gray-600 line-clamp-2 mb-1 sm:mb-2">
                                            {{ $event->description ? Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($event->description)))), 60) : 'No description available' }}
                                        </p>
                                        <div class="hidden sm:flex items-center gap-3 text-xs text-gray-600 mb-3">
                                            <span>
                                                <i class="ph ph-clock mr-1" style="color: var(--complementary-color);"></i>
                                                {{ $event->start_date->format('h:i A') }}
                                            </span>
                                            <span class="truncate">
                                                <i class="ph ph-map-pin mr-1" style="color: var(--complementary-color);"></i>
                                                {{ $event->venue }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="hidden sm:flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                                        <span class="text-xs text-gray-500">
                                            <i class="ph ph-user mr-1" style="color: var(--complementary-color);"></i>
                                            {{ $event->user->name ?? 'Admin' }}
                                        </span>
                                        <span style="color: var(--primary-color);" class="text-sm font-bold group-hover:opacity-80 transition-colors bg-purple-50 px-3 py-1 rounded-full">
                                            Learn More <i class="ph ph-caret-right ml-1" style="color: var(--complementary-color);"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </a>
                @empty
                    <div class="bg-white rounded-xl shadow-md p-6 sm:p-8 text-center">
                        <i class="ph ph-calendar-blank text-3xl sm:text-4xl text-gray-300 mb-3 sm:mb-4" style="color: var(--complementary-color);"></i>
                        <p class="text-gray-500 text-sm sm:text-base">No upcoming events scheduled</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
