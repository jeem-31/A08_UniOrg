<?php

namespace App\Http\Controllers\Admin;

use App\Events\AnnouncementCreated;
use App\Events\AnnouncementDeleted;
use App\Events\AnnouncementUpdated;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\ContentCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AnnouncementController extends Controller
{
    private function isSystemWideRequest(): bool
    {
        return request()->routeIs('osas.announcements*');
    }

    private function managementRoute(string $suffix = 'index'): string
    {
        return $this->isSystemWideRequest()
            ? "osas.announcements.{$suffix}"
            : "admin.announcements.{$suffix}";
    }

    private function resolveAnnouncement($announcement): Announcement
    {
        $announcementId = $announcement instanceof Announcement
            ? $announcement->getKey()
            : $announcement;

        $query = $this->isSystemWideRequest()
            ? Announcement::allOrganizations()->whereNull('organization_id')
            : Announcement::query();

        return $query->whereKey($announcementId)->firstOrFail();
    }

    public function index(Request $request, ContentCache $contentCache)
    {
        $user = auth()->user();
        $currentOrgId = session('current_organization_id')
            ?: $user->organizations()->wherePivot('membership_status', 'Active')->value('organizations.id');
        $organizationIds = $currentOrgId ? [$currentOrgId] : [];
        $currentOrg = $currentOrgId ? \App\Models\Organization::find($currentOrgId) : null;

        // Mother Organization students can also see announcements from related Sub-Orgs.
        if ($currentOrgId) {
            if ($currentOrg && $currentOrg->type === 'Mother Org' && $currentOrg->department_id) {
                $subOrgIds = \App\Models\Organization::where('department_id', $currentOrg->department_id)
                    ->where('type', 'Sub-Org')
                    ->pluck('id')
                    ->all();

                $organizationIds = array_merge($organizationIds, $subOrgIds);
            }
        }

        $orgAcronym = $currentOrg?->org_acronym ?? 'your organization';

        $page = $request->integer('page', 1);
        $announcements = $contentCache->rememberAnnouncementIndex($currentOrgId, $page, function () use ($organizationIds, $page) {
            return Announcement::allOrganizations()
                ->with(['user', 'organization', 'targetOrganizations'])
                ->where(function ($query) use ($organizationIds) {
                    $query->where(function ($q) {
                        $q->whereNull('organization_id')
                            ->where(function ($v) {
                                $v->where('visibility', 'all')->orWhereNull('visibility');
                            });
                    });

                    $query->orWhere(function ($q) use ($organizationIds) {
                        $q->whereNull('organization_id')
                            ->where('visibility', 'selected')
                            ->whereHas('targetOrganizations', function ($t) use ($organizationIds) {
                                $t->whereIn('organizations.id', $organizationIds);
                            });
                    });

                    if (!empty($organizationIds)) {
                        $query->orWhereIn('organization_id', $organizationIds);
                    }
                })
                ->published()
                ->orderByRaw("CASE WHEN type = 'urgent' THEN 0 ELSE 1 END")
                ->recent()
                ->paginate(10, ['*'], 'page', $page);
        });

        // `fragment=1` is reserved by the shared student navigation for a
        // complete page fragment. The realtime list refresh needs only the
        // announcement cards, so it uses its own explicit request flag.
        if ($request->boolean('announcement_list')) {
            return view('components.announcement-list', compact('announcements', 'currentOrgId'));
        }

        $visibleOrganizationIds = array_values(array_unique($organizationIds));

        return view('student.announcements.index', compact(
            'announcements',
            'currentOrgId',
            'orgAcronym',
            'visibleOrganizationIds'
        ));
    }

    public function show($announcement, ContentCache $contentCache)
    {
        $user = auth()->user();
        $currentOrgId = session('current_organization_id')
            ?: $user->organizations()->wherePivot('membership_status', 'Active')->value('organizations.id');
        $organizationIds = $currentOrgId ? [$currentOrgId] : [];

        if ($currentOrgId) {
            $currentOrg = \App\Models\Organization::find($currentOrgId);

            if ($currentOrg && $currentOrg->type === 'Mother Org' && $currentOrg->department_id) {
                $organizationIds = array_merge($organizationIds, \App\Models\Organization::where('department_id', $currentOrg->department_id)
                    ->where('type', 'Sub-Org')
                    ->pluck('id')
                    ->all());
            }
        }

        $announcementId = $announcement instanceof Announcement ? $announcement->getKey() : $announcement;
        $announcement = $contentCache->rememberAnnouncement($announcementId, $currentOrgId, function () use ($organizationIds, $announcementId) {
            return Announcement::allOrganizations()
                ->with(['user', 'targetOrganizations'])
                ->where(function ($query) use ($organizationIds) {
                    $query->where(function ($q) {
                        $q->whereNull('organization_id')
                            ->where(function ($v) {
                                $v->where('visibility', 'all')->orWhereNull('visibility');
                            });
                    });

                    $query->orWhere(function ($q) use ($organizationIds) {
                        $q->whereNull('organization_id')
                            ->where('visibility', 'selected')
                            ->whereHas('targetOrganizations', function ($t) use ($organizationIds) {
                                $t->whereIn('organizations.id', $organizationIds);
                            });
                    });

                    if (!empty($organizationIds)) {
                        $query->orWhereIn('organization_id', $organizationIds);
                    }
                })
                ->whereKey($announcementId)
                ->published()
                ->firstOrFail();
        });

        return view('student.announcements.show', compact('announcement'));
    }

    public function create()
    {
        return view('admin.announcements.create');
    }

    public function store(Request $request)
    {
        // Increase execution time for image uploads
        set_time_limit(120);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:general,urgent',
            'visibility' => 'nullable|in:all,selected',
            'organization_ids' => 'nullable|array',
            'organization_ids.*' => 'exists:organizations,id',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'is_published' => 'boolean',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $validated['user_id'] = auth()->user()->id;
        $isSystemWide = $this->isSystemWideRequest();
        $targetOrgIds = [];

        if ($isSystemWide) {
            $validated['organization_id'] = null;
            $validated['visibility'] = $validated['visibility'] ?? 'all';

            if ($validated['visibility'] === 'selected') {
                $targetOrgIds = array_values(array_unique($request->input('organization_ids', [])));

                if (empty($targetOrgIds)) {
                    if ($request->expectsJson() || $request->ajax()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Please select at least one organization for a targeted announcement.',
                        ], 422);
                    }

                    return redirect()->back()->withErrors([
                        'organization_ids' => 'Please select at least one organization for a targeted announcement.',
                    ])->withInput();
                }
            }
        } elseif (session('current_organization_id')) {
            $validated['organization_id'] = session('current_organization_id');
        }

        // Determine status based on publish settings
        if ($request->is_published) {
            $validated['status'] = 'active';
            $validated['is_published'] = \DB::raw('TRUE');
            $validated['published_at'] = now();
        } elseif ($request->scheduled_at) {
            $validated['status'] = 'scheduled';
            $validated['is_published'] = \DB::raw('FALSE');
        } else {
            $validated['status'] = 'draft';
            $validated['is_published'] = \DB::raw('FALSE');
        }

        // Handle multiple image uploads with timeout handling
        if ($request->hasFile('images')) {
            $imagePaths = [];
            $totalImages = count($request->file('images'));
            \Log::info("Starting upload of {$totalImages} images to R2...");

            foreach ($request->file('images') as $index => $image) {
                try {
                    $startTime = microtime(true);
                    $path = $image->store('announcements', storage_disk());
                    $uploadTime = microtime(true) - $startTime;
                    
                    \Log::info("Image " . ($index + 1) . "/{$totalImages} uploaded in " . number_format($uploadTime, 2) . "s");
                    $imagePaths[] = $path;
                } catch (\Exception $e) {
                    \Log::error("Image " . ($index + 1) . " upload failed: " . $e->getMessage());
                    // Continue with other images
                }
            }

            if (!empty($imagePaths)) {
                $validated['images'] = $imagePaths;
                \Log::info("Total images uploaded: " . count($imagePaths) . "/{$totalImages}");
            }
        }

        unset($validated['organization_ids']);

        $announcement = Announcement::create($validated);

        if ($isSystemWide && ($announcement->visibility === 'selected') && !empty($targetOrgIds)) {
            $announcement->targetOrganizations()->sync($targetOrgIds);
        }

        // Load relationships for response
        $announcement->load(['user', 'targetOrganizations']);

        // Return response immediately for AJAX, broadcast happens synchronously
        if ($request->expectsJson() || $request->ajax()) {
            // Broadcast immediately (sync queue means instant)
            try {
                \Log::info('Broadcasting AnnouncementCreated event for ID: ' . $announcement->id);
                broadcast(new AnnouncementCreated($announcement));
                \Log::info('Broadcast successful');
            } catch (\Exception $e) {
                \Log::error('Broadcast failed: ' . $e->getMessage());
            }

            // Format announcement data for Alpine.js
            $announcementData = [
                'id' => $announcement->id,
                'organization_id' => $announcement->organization_id,
                'title' => $announcement->title,
                'content' => $announcement->content,
                'type' => $announcement->type,
                'status' => $announcement->status,
                'visibility' => $announcement->visibility ?? 'all',
                'target_organization_ids' => $announcement->targetOrganizations->pluck('id')->all(),
                'images' => $announcement->images ?? [],
                'images_urls' => $announcement->images ? array_map(function($path) {
                    return storage_url($path);
                }, $announcement->images) : [],
                'created_at' => $announcement->created_at->toISOString(),
                'user' => [
                    'id' => $announcement->user->id,
                    'name' => $announcement->user->name,
                ],
                'isNew' => true, // Flag for animation
            ];

            return response()->json([
                'success' => true,
                'message' => 'Announcement created successfully.',
                'announcement' => $announcementData
            ]);
        }

        // For regular requests, broadcast normally
        try {
            broadcast(new AnnouncementCreated($announcement));
        } catch (\Exception $e) {
            \Log::error('Broadcast failed: ' . $e->getMessage());
        }

        return redirect()->route($this->managementRoute())
            ->with('success', 'Announcement created successfully.');
    }

    public function edit($announcement)
    {
        $announcement = $this->resolveAnnouncement($announcement);

        return view('admin.announcements.edit', compact('announcement'));
    }

    public function update(Request $request, $announcement)
    {
        $announcement = $this->resolveAnnouncement($announcement);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:general,urgent',
            'visibility' => 'nullable|in:all,selected',
            'organization_ids' => 'nullable|array',
            'organization_ids.*' => 'exists:organizations,id',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'existing_images' => 'nullable|array',
            'is_published' => 'boolean',
            'scheduled_at' => 'nullable|date',
        ]);

        $isSystemWideUpdate = $this->isSystemWideRequest();
        $targetOrgIds = null;

        if ($isSystemWideUpdate) {
            $newVisibility = $validated['visibility'] ?? $announcement->visibility ?? 'all';
            $validated['visibility'] = $newVisibility;

            if ($newVisibility === 'selected') {
                $targetOrgIds = array_values(array_unique($request->input('organization_ids', [])));

                if (empty($targetOrgIds)) {
                    if ($request->expectsJson() || $request->ajax()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Please select at least one organization for a targeted announcement.',
                        ], 422);
                    }

                    return redirect()->back()->withErrors([
                        'organization_ids' => 'Please select at least one organization for a targeted announcement.',
                    ])->withInput();
                }
            } else {
                $targetOrgIds = [];
            }

            unset($validated['organization_ids']);
        } else {
            unset($validated['visibility'], $validated['organization_ids']);
        }

        // Handle hide action for active announcements
        $isPublishedRaw = null;

        if ($request->hide_announcement == '1' && $announcement->status === 'active') {
            $validated['status'] = 'hidden';
            $isPublishedRaw = 'FALSE';
        }

        // Handle unhide action for hidden announcements
        if ($request->unhide_announcement == '1' && $announcement->status === 'hidden') {
            $validated['status'] = 'active';
            $isPublishedRaw = 'TRUE';
            $validated['published_at'] = now();
        }

        // Handle scheduled announcements
        if ($request->scheduled_at && ($announcement->status === 'scheduled' || $announcement->status === 'draft')) {
            $validated['status'] = 'scheduled';
            $isPublishedRaw = 'FALSE';
        }

        // Handle images
        $existingImages = $request->input('existing_images', []);
        $newImages = [];

        // Upload new images
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('announcements', storage_disk());
                $newImages[] = $path;
            }
        }

        // Combine existing and new images
        $allImages = array_merge($existingImages, $newImages);
        $validated['images'] = $allImages;

        // Delete removed images from storage
        if ($announcement->images) {
            $removedImages = array_diff($announcement->images, $existingImages);
            foreach ($removedImages as $removedImage) {
                Storage::disk(storage_disk())->delete($removedImage);
            }
        }

        // For AJAX requests, skip the change detection and update directly
        if ($request->expectsJson()) {
            $announcement->update($validated);

            if ($isSystemWideUpdate && $targetOrgIds !== null) {
                $announcement->targetOrganizations()->sync($targetOrgIds);
            }

            // The boolean cast on is_published makes Eloquent skip raw
            // expression changes (DB::raw('FALSE') casts to true), so the
            // publish flag is persisted separately via the query builder.
            if ($isPublishedRaw !== null) {
                \DB::table('announcements')
                    ->where('id', $announcement->id)
                    ->update(['is_published' => \DB::raw($isPublishedRaw)]);
                $announcement->refresh();
            }

            $announcement->load(['user', 'targetOrganizations']);

            try {
                \Log::info('Broadcasting AnnouncementUpdated event for ID: ' . $announcement->id);
                broadcast(new AnnouncementUpdated($announcement));
                \Log::info('Update broadcast successful');
            } catch (\Exception $e) {
                \Log::error('Update broadcast failed: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Announcement updated successfully.',
                'announcement' => [
                    'id' => $announcement->id,
                    'organization_id' => $announcement->organization_id,
                    'title' => $announcement->title,
                    'content' => $announcement->content,
                    'type' => $announcement->type,
                    'status' => $announcement->status,
                    'visibility' => $announcement->visibility ?? 'all',
                    'target_organization_ids' => $announcement->targetOrganizations->pluck('id')->all(),
                    'images' => $announcement->images ?? [],
                    'images_urls' => $announcement->images ? array_map(function ($path) {
                        return storage_url($path);
                    }, $announcement->images) : [],
                    'created_at' => $announcement->created_at->toISOString(),
                    'user' => [
                        'id' => $announcement->user->id,
                        'name' => $announcement->user->name,
                    ],
                ],
            ]);
        }

        // Check if any actual changes were made (for regular form submissions)
        $hasChanges = false;

        // Check text field changes
        if ($announcement->title !== $validated['title'] || 
            $announcement->content !== $validated['content'] ||
            $announcement->type !== $validated['type']) {
            $hasChanges = true;
        }

        // Check status changes
        if (isset($validated['status']) && $announcement->status !== $validated['status']) {
            $hasChanges = true;
        }

        // Check scheduled_at changes
        if (isset($validated['scheduled_at'])) {
            $oldScheduledAt = $announcement->scheduled_at ? $announcement->scheduled_at->format('Y-m-d\TH:i') : null;
            $newScheduledAt = $validated['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($validated['scheduled_at'])) : null;
            if ($oldScheduledAt !== $newScheduledAt) {
                $hasChanges = true;
            }
        }

        // Check if images changed
        // If new images were uploaded, there's definitely a change
        if (count($newImages) > 0) {
            $hasChanges = true;
        }

        // Check if images were removed (compare counts and contents)
        $oldImages = $announcement->images ?? [];
        $newImagesArray = $validated['images'] ?? [];

        if (count($oldImages) !== count($newImagesArray)) {
            $hasChanges = true;
        }

        // Check if any image was removed or changed
        if (!$hasChanges) {
            foreach ($oldImages as $oldImage) {
                if (!in_array($oldImage, $newImagesArray)) {
                    $hasChanges = true;
                    break;
                }
            }
        }

        // Check visibility / target org changes for system-wide announcements
        if (!$hasChanges && $isSystemWideUpdate && $targetOrgIds !== null) {
            $oldVisibility = $announcement->visibility ?? 'all';
            $newVisibility = $validated['visibility'] ?? $oldVisibility;

            if ($oldVisibility !== $newVisibility) {
                $hasChanges = true;
            } else {
                $oldTargets = $announcement->targetOrganizations()->pluck('organizations.id')->all();
                sort($oldTargets);
                $newTargets = $targetOrgIds;
                sort($newTargets);

                if ($oldTargets !== $newTargets) {
                    $hasChanges = true;
                }
            }
        }

        // If no changes were made, return with info message
        if (!$hasChanges) {
            return redirect()->route($this->managementRoute())
                ->with('info', 'No changes were made to the announcement.');
        }

        $announcement->update($validated);

        if ($isSystemWideUpdate && $targetOrgIds !== null) {
            $announcement->targetOrganizations()->sync($targetOrgIds);
        }

        // See the AJAX branch above: the boolean cast would otherwise skip
        // raw is_published changes, so persist the publish flag separately.
        if ($isPublishedRaw !== null) {
            \DB::table('announcements')
                ->where('id', $announcement->id)
                ->update(['is_published' => \DB::raw($isPublishedRaw)]);
            $announcement->refresh();
        }

        // Broadcast real-time update
        try {
            \Log::info('Broadcasting AnnouncementUpdated event for ID: ' . $announcement->id);
            broadcast(new AnnouncementUpdated($announcement));
            \Log::info('Update broadcast successful');
        } catch (\Exception $e) {
            \Log::error('Update broadcast failed: ' . $e->getMessage());
        }



        return redirect()->route($this->managementRoute())
            ->with('success', 'Announcement updated successfully.');
    }

    public function destroy($announcement)
    {
        $announcement = $this->resolveAnnouncement($announcement);
        $announcementId = $announcement->id;
        $announcementOrganizationId = $announcement->organization_id;

        // Delete all images from storage
        if ($announcement->images) {
            foreach ($announcement->images as $image) {
                Storage::disk(storage_disk())->delete($image);
            }
        }

        $announcement->delete();

        // Broadcast real-time delete
        try {
            \Log::info('Broadcasting AnnouncementDeleted event for ID: ' . $announcementId);
            broadcast(new AnnouncementDeleted($announcementId, $announcementOrganizationId));
            \Log::info('Delete broadcast successful');
        } catch (\Exception $e) {
            \Log::error('Delete broadcast failed: ' . $e->getMessage());
        }



        // Return JSON for AJAX requests
        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Announcement deleted successfully.'
            ]);
        }

        return redirect()->route($this->managementRoute())
            ->with('success', 'Announcement deleted successfully.');
    }

    public function adminIndex()
    {
        $isSystemWide = $this->isSystemWideRequest();
        $user = auth()->user();
        $currentOrgId = session('current_organization_id')
            ?: $user->organizations()->wherePivot('membership_status', 'Active')->value('organizations.id');

        $announcementQuery = $isSystemWide
            ? Announcement::allOrganizations()->whereNull('organization_id')
            : Announcement::where('organization_id', $currentOrgId);

        $announcementStats = (clone $announcementQuery)
            ->selectRaw("\n                COUNT(*) as total,\n                COUNT(*) FILTER (WHERE status = 'active') as active,\n                COUNT(*) FILTER (WHERE status = 'scheduled') as scheduled,\n                COUNT(*) FILTER (WHERE status IN ('draft', 'hidden')) as drafts\n            ")
            ->first();

        $announcements = $announcementQuery
            ->with(['user', 'targetOrganizations'])
            ->latest()
            ->paginate(15);

        // Transform images to full URLs for edit modal
        $announcements->getCollection()->transform(function ($announcement) {
            if ($announcement->images) {
                $announcement->images_urls = \App\Helpers\StorageHelper::transformImages($announcement->images);
            }
            $announcement->target_organization_ids = $announcement->targetOrganizations->pluck('id')->all();
            $announcement->target_organization_count = $announcement->targetOrganizations->count();
            return $announcement;
        });

        $organizations = $isSystemWide
            ? \App\Models\Organization::orderBy('name')->get(['id', 'name', 'org_acronym', 'type'])
            : collect();

        $announcementBasePath = $isSystemWide ? 'osas/announcements' : 'admin/announcements';
        $announcementRoutePrefix = $isSystemWide ? 'osas' : 'admin';
        $canManageAnnouncements = $isSystemWide || app(\App\Services\PermissionHelper::class)->hasFullAccess('announcements');

        return view('admin.announcements.index', compact(
            'announcements',
            'announcementStats',
            'isSystemWide',
            'currentOrgId',
            'announcementBasePath',
            'announcementRoutePrefix',
            'canManageAnnouncements',
            'organizations'
        ));
    }
    
    // API endpoint to fetch single announcement
    public function getAnnouncement($announcement)
    {
        $announcement = $this->resolveAnnouncement($announcement);
        $announcement->load('targetOrganizations');

        // Transform images to full URLs
        if ($announcement->images) {
            $announcement->images_urls = \App\Helpers\StorageHelper::transformImages($announcement->images);
        }

        $announcement->target_organization_ids = $announcement->targetOrganizations->pluck('id')->all();

        return response()->json($announcement);
    }

    // Guest methods
    public function guestIndex()
    {
        $announcements = Announcement::with('user')
            ->published()
            ->orderByRaw("CASE WHEN type = 'urgent' THEN 0 ELSE 1 END")
            ->recent()
            ->paginate(10);

        return view('guest.announcements.index', compact('announcements'));
    }

    public function guestShow(Announcement $announcement)
    {
        return view('guest.announcements.show', compact('announcement'));
    }
}
