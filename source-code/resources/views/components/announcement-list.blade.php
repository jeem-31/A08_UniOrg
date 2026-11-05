{{-- Announcement List Component --}}
<div data-student-announcement-list class="announcement-list">
    <div class="announcement-list__grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($announcements as $announcement)
            <a href="{{ route('student.announcements.show', $announcement) }}" data-student-navigation class="announcement-card-link group" style="--announcement-index: {{ $loop->index }};">
                <div class="announcement-card bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 h-full flex flex-col"
                     onmouseover="this.querySelector('.announcement-title').style.color='var(--primary-color)'" 
                     onmouseout="this.querySelector('.announcement-title').style.color='#111827'">
                    @if($announcement->images && count($announcement->images) > 0)
                        <div class="announcement-card__media relative overflow-hidden h-48">
                            <img src="{{ storage_url($announcement->images[0]) }}" alt="{{ $announcement->title }}" class="announcement-card__image w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                            @if(count($announcement->images) > 1)
                                <div class="absolute top-2 right-2 bg-black bg-opacity-70 text-white px-2 py-1 rounded-full text-xs font-semibold">
                                    <i class="ph ph-images mr-1"></i>{{ count($announcement->images) }}
                                </div>
                            @endif
                            <div class="absolute top-2 left-2">
                                <span class="announcement-card__badge px-2 py-1 text-xs font-semibold rounded-full shadow-lg
                                    {{ $announcement->type === 'urgent' ? 'bg-red-500 text-white' : '' }}
                                    {{ $announcement->type === 'event' ? 'bg-blue-500 text-white' : '' }}
                                    {{ $announcement->type === 'academic' ? 'bg-green-500 text-white' : '' }}
                                    {{ $announcement->type === 'general' ? 'bg-gray-500 text-white' : '' }}">
                                    {{ ucfirst($announcement->type) }}
                                </span>
                                @if($announcement->organization_id != $currentOrgId && $announcement->organization)
                                    <span class="announcement-card__org-badge ml-1 px-2 py-1 text-xs font-semibold rounded-full shadow-lg bg-purple-500 text-white">
                                        {{ $announcement->organization->org_acronym }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="announcement-card__placeholder relative h-48 flex items-center justify-center" style="background: linear-gradient(to bottom right, var(--primary-color), rgba(var(--primary-color-rgb), 0.8));">
                            <i class="ph ph-megaphone text-white text-5xl opacity-50"></i>
                            <div class="absolute top-2 left-2">
                                <span class="announcement-card__badge px-2 py-1 text-xs font-semibold rounded-full shadow-lg
                                    {{ $announcement->type === 'urgent' ? 'bg-red-500 text-white' : '' }}
                                    {{ $announcement->type === 'event' ? 'bg-blue-500 text-white' : '' }}
                                    {{ $announcement->type === 'academic' ? 'bg-green-500 text-white' : '' }}
                                    {{ $announcement->type === 'general' ? 'bg-gray-500 text-white' : '' }}">
                                    {{ ucfirst($announcement->type) }}
                                </span>
                                @if($announcement->organization_id != $currentOrgId && $announcement->organization)
                                    <span class="announcement-card__org-badge ml-1 px-2 py-1 text-xs font-semibold rounded-full shadow-lg bg-purple-500 text-white">
                                        {{ $announcement->organization->org_acronym }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endif
                    
                    <div class="announcement-card__body p-4 flex-1 flex flex-col">
                        <h3 class="announcement-title text-lg font-black tracking-tight text-gray-900 mb-2 transition-colors line-clamp-2" style="transition: color 0.3s;">
                            {{ $announcement->title }}
                        </h3>
                        
                        <p class="announcement-card__excerpt text-gray-600 text-sm mb-3 flex-1 line-clamp-2">
                            {{ trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($announcement->content)))) }}
                        </p>
                        
                        <div class="announcement-card__meta flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-gray-100">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                                <span class="inline-flex items-center">
                                    @if($announcement->user?->photo)
                                        <img src="{{ storage_url($announcement->user->photo) }}" alt="{{ $announcement->user->announcement_display_name }}" class="mr-1.5 h-5 w-5 rounded-full object-cover ring-1 ring-gray-200">
                                    @else
                                        <span class="mr-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-gray-100 text-[10px] font-semibold text-gray-500">
                                            {{ strtoupper(substr($announcement->user?->first_name ?? 'U', 0, 1)) }}
                                        </span>
                                    @endif
                                    <span>{{ $announcement->user?->announcement_display_name ?? 'Unknown user' }}</span>
                                </span>
                                <span class="inline-flex items-center">
                                    <i class="ph ph-clock mr-1"></i>
                                    <span>{{ $announcement->created_at->diffForHumans() }}</span>
                                </span>
                            </div>
                            <span class="announcement-card__read-more font-bold text-sm transition-colors" style="color: var(--primary-color);" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">
                                Read more <i class="ph ph-arrow-right ml-1"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </a>
        @empty
            <div class="announcement-list__empty col-span-full bg-white rounded-lg shadow p-12 text-center">
                <i class="ph ph-megaphone text-gray-300 text-6xl mb-4"></i>
                <p class="text-gray-500 text-lg">No announcements available at the moment</p>
            </div>
        @endforelse
    </div>

    <div class="announcement-pagination mt-8" data-student-navigation-pagination>
        {{ $announcements->links() }}
    </div>
</div>
