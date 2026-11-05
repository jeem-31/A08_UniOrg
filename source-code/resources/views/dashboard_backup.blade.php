@extends('layouts.app')

@section('title', 'Homepage')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Welcome Section -->
    <div class="mb-12 text-center">
        <h1 class="text-4xl font-bold text-gray-900 mb-3">Welcome back, {{ auth()->user()->first_last_name }}!</h1>
        <p class="text-xl text-gray-600">Here's what's happening in {{ $orgAcronym ?? 'CCS' }} today</p>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-12">
        <a href="{{ route('student.announcements.index') }}" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl transition-all duration-300 text-center group">
            <div class="bg-purple-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:bg-purple-200 transition-colors">
                <i class="ph ph-megaphone text-purple-600 text-2xl"></i>
            </div>
            <p class="text-gray-700 font-semibold">Announcements</p>
        </a>

        <a href="{{ route('events.calendar') }}" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl transition-all duration-300 text-center group">
            <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:bg-blue-200 transition-colors">
                <i class="ph ph-calendar-blank text-blue-600 text-2xl"></i>
            </div>
            <p class="text-gray-700 font-semibold">Events</p>
        </a>

        <a href="{{ route('payments.index') }}" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl transition-all duration-300 text-center group">
            <div class="bg-green-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:bg-green-200 transition-colors">
                <i class="ph ph-credit-card text-green-600 text-2xl"></i>
            </div>
            <p class="text-gray-700 font-semibold">Payments</p>
        </a>

        <a href="{{ route('library.index') }}" class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl transition-all duration-300 text-center group">
            <div class="bg-yellow-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:bg-yellow-200 transition-colors">
                <i class="ph ph-book text-yellow-600 text-2xl"></i>
            </div>
            <p class="text-gray-700 font-semibold">e-Library</p>
        </a>
    </div>

    <!-- Announcements and Events Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
        <!-- Latest Announcements -->
        <section>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Latest Announcements</h2>
                    <p class="text-gray-600 mt-1">Stay updated with the latest news from CCS</p>
                </div>
                <a href="{{ route('student.announcements.index') }}" 
                   class="text-purple-600 hover:text-purple-800 font-semibold transition-colors text-sm">
                    View All <i class="ph ph-arrow-right ml-1" style="color: #D7AC28;"></i>
                </a>
            </div>
            
            <div class="space-y-4">
                @forelse($announcements as $announcement)
                    <a href="{{ route('student.announcements.show', $announcement) }}" class="group block">
                        <article class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition-all duration-300">
                            <div class="flex h-32">
                                @if($announcement->image)
                                    <div class="w-32 h-32 flex-shrink-0 overflow-hidden">
                                        <img src="{{ storage_url($announcement->image) }}" 
                                             alt="{{ $announcement->title }}" 
                                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                                    </div>
                                @else
                                    <div class="w-32 h-32 flex-shrink-0 bg-gradient-to-br from-[#8238B3] to-[#3D0066] flex items-center justify-center">
                                        <i class="ph ph-megaphone text-white text-3xl opacity-50" style="color: #D7AC28;"></i>
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
                                                <i class="ph ph-clock mr-1" style="color: #D7AC28;"></i>
                                                {{ $announcement->created_at->diffForHumans() }}
                                            </span>
                                        </div>
                                        <h3 class="text-lg font-bold text-gray-900 mb-2 group-hover:text-purple-700 transition-colors">
                                            {{ $announcement->title }}
                                        </h3>
                                        <p class="text-sm text-gray-600">
                                            {{ $announcement->content ? Str::limit($announcement->content, 100) . '...' : 'No content available' }}
                                        </p>
                                    </div>
                                    <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                                        <span class="text-xs text-gray-500">
                                            <i class="ph ph-user mr-1"></i>
                                            {{ $announcement->user->name ?? 'Admin' }}
                                        </span>
                                        <span class="text-xs font-semibold text-purple-600 group-hover:text-purple-800 transition-colors">
                                            Read More <i class="ph ph-caret-right ml-1" style="color: #D7AC28;"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </a>
                @empty
                    <div class="bg-white rounded-xl shadow-md p-8 text-center">
                        <i class="ph ph-megaphone text-4xl text-gray-300 mb-4" style="color: #D7AC28;"></i>
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
                <a href="{{ route('events.calendar') }}" 
                   class="text-purple-600 hover:text-purple-800 font-semibold transition-colors text-sm">
                    View Calendar <i class="ph ph-arrow-right ml-1" style="color: #D7AC28;"></i>
                </a>
            </div>
            
            <div class="space-y-4">
                @forelse($upcomingEvents as $event)
                    <a href="{{ route('events.show', $event) }}" class="group block">
                        <article class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition-all duration-300">
                            <div class="flex h-32">
                                @if($event->image)
                                    <div class="w-32 h-32 flex-shrink-0 overflow-hidden">
                                        <img src="{{ storage_url($event->image) }}" 
                                             alt="{{ $event->title }}" 
                                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                                    </div>
                                @else
                                    <div class="w-32 h-32 flex-shrink-0 bg-gradient-to-br from-[#8238B3] to-[#3D0066] flex items-center justify-center">
                                        <i class="ph ph-calendar-blank text-white text-3xl opacity-50" style="color: #D7AC28;"></i>
                                    </div>
                                @endif
                                
                                <div class="flex-1 p-4 flex flex-col">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <div class="bg-purple-100 px-2 py-1 rounded text-xs font-bold text-purple-600">
                                                {{ $event->start_date->format('M d') }}
                                            </div>
                                            <span class="text-xs text-gray-500">
                                                <i class="ph ph-clock mr-1" style="color: #D7AC28;"></i>
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
                                        <p class="text-sm text-gray-600 mb-2">
                                            {{ $event->description ? Str::limit($event->description, 80) . '...' : 'No description available' }}
                                        </p>
                                        <div class="flex items-center gap-3 text-xs text-gray-600">
                                            <span>
                                                <i class="ph ph-clock mr-1" style="color: #D7AC28;"></i>
                                                {{ $event->start_date->format('h:i A') }}
                                            </span>
                                            <span>
                                                <i class="ph ph-map-pin mr-1" style="color: #D7AC28;"></i>
                                                {{ $event->venue }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                                        <span class="text-xs text-gray-500">
                                            <i class="ph ph-user mr-1"></i>
                                            {{ $event->user->name ?? 'Admin' }}
                                        </span>
                                        <span class="text-xs font-semibold text-purple-600 group-hover:text-purple-800 transition-colors">
                                            Learn More <i class="ph ph-caret-right ml-1" style="color: #D7AC28;"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </a>
                @empty
                    <div class="bg-white rounded-xl shadow-md p-8 text-center">
                        <i class="ph ph-calendar-blank text-4xl text-gray-300 mb-4" style="color: #D7AC28;"></i>
                        <p class="text-gray-500 text-lg">No upcoming events scheduled</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
