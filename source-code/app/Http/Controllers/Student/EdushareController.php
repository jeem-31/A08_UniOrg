<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\EdushareItem;
use App\Models\EdushareFolder;
use App\Models\EdushareShare;
use App\Models\User;
use App\Notifications\EdushareNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EdushareController extends Controller
{
    public function index(Request $request)
    {
        $folderId = $request->get('folder');
        $search = $request->get('search');
        $currentFolder = $folderId ? EdushareFolder::find($folderId) : null;
        
        // Check if current folder is shared (not owned by user)
        $isSharedFolder = $currentFolder && $currentFolder->user_id !== auth()->id();
        
        // All Files - everything (my uploads + shared with me) - ROOT LEVEL ONLY
        $allItemsQuery = EdushareItem::whereNull('folder_id') // Only root-level files
            ->where(function($q) {
                $q->where('user_id', auth()->id())
                  ->orWhereHas('shares', function($query) {
                      $query->where('share_type', 'everyone')
                            ->orWhere(function($subQuery) {
                                $subQuery->where('share_type', 'specific')
                                        ->where('shared_with_email', auth()->user()->email);
                            });
                  });
            })->with('user');
        
        $searchLike = '%' . mb_strtolower($search) . '%';

        if ($search) {
            $allItemsQuery->where(function($q) use ($searchLike) {
                $q->whereRaw('LOWER(title) LIKE ?', [$searchLike]);
            });
        }
        
        $allItems = $allItemsQuery->latest()->get();
        
        $allFoldersQuery = EdushareFolder::whereNull('parent_id') // Only root-level folders
            ->where(function($q) {
                $q->where('user_id', auth()->id())
                  ->orWhereHas('shares', function($query) {
                      $query->where('share_type', 'everyone')
                            ->orWhere(function($subQuery) {
                                $subQuery->where('share_type', 'specific')
                                        ->where('shared_with_email', auth()->user()->email);
                            });
                  });
            })->with('user');
        
        if ($search) {
            $allFoldersQuery->where(function($q) use ($searchLike) {
                $q->whereRaw('LOWER(name) LIKE ?', [$searchLike]);
            });
        }
        
        $allFolders = $allFoldersQuery->latest()->get();
        
        // My Uploads - ONLY files/folders uploaded by current user
        // If viewing a shared folder, show root-level items instead
        $myFolderContext = $isSharedFolder ? null : $folderId;
        
        $myFoldersQuery = EdushareFolder::where('user_id', auth()->id())
            ->where('parent_id', $myFolderContext);
        $myItemsQuery = EdushareItem::where('user_id', auth()->id())
            ->where('folder_id', $myFolderContext);
        
        if ($search) {
            $myFoldersQuery->where(function($q) use ($searchLike) {
                $q->whereRaw('LOWER(name) LIKE ?', [$searchLike]);
            });
            $myItemsQuery->where(function($q) use ($searchLike) {
                $q->whereRaw('LOWER(title) LIKE ?', [$searchLike]);
            });
        }
        
        $myFolders = $myFoldersQuery->orderBy('name')->get();
        $myItems = $myItemsQuery->orderBy('created_at', 'desc')->get();
        
        // Shared with Me Tab - separate variables for shared folder navigation
        if ($isSharedFolder) {
            // When viewing inside a shared folder, show ONLY its contents (not user's own files)
            // Show subfolders and files that belong to the shared folder
            $sharedFolderContents = EdushareFolder::where('parent_id', $folderId)
                ->where('user_id', '!=', auth()->id()) // Exclude user's own folders
                ->orderBy('name')
                ->get();
            $sharedFileContents = EdushareItem::where('folder_id', $folderId)
                ->where('user_id', '!=', auth()->id()) // Exclude user's own files
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            // Root level - show only shared items/folders
            $sharedFolderContents = collect(); // Empty collection
            $sharedFileContents = collect(); // Empty collection
        }
        
        // Shared items - includes files in folders if they're explicitly shared
        $sharedItemsQuery = EdushareItem::where('user_id', '!=', auth()->id())
            ->whereHas('shares', function($q) {
                $q->where('share_type', 'everyone')
                  ->orWhere(function($subQuery) {
                      $subQuery->where('share_type', 'specific')
                              ->where('shared_with_email', auth()->user()->email);
                  });
            })->with('user', 'folder');
        
        if ($search) {
            $sharedItemsQuery->where(function($q) use ($searchLike) {
                $q->whereRaw('LOWER(title) LIKE ?', [$searchLike]);
            });
        }
        
        $sharedItems = $sharedItemsQuery->latest()->get();
        
        // Root-level shared folders
        $sharedFoldersQuery = EdushareFolder::where('user_id', '!=', auth()->id())
            ->whereNull('parent_id')
            ->whereHas('shares', function($q) {
                $q->where('share_type', 'everyone')
                  ->orWhere(function($subQuery) {
                      $subQuery->where('share_type', 'specific')
                              ->where('shared_with_email', auth()->user()->email);
                  });
            })->with('user');
        
        if ($search) {
            $sharedFoldersQuery->where(function($q) use ($searchLike) {
                $q->whereRaw('LOWER(name) LIKE ?', [$searchLike]);
            });
        }
        
        $sharedFolders = $sharedFoldersQuery->latest()->get();
        
        $breadcrumbs = $this->getBreadcrumbs($currentFolder);

        return view('student.edushare.index', compact(
            'allItems', 'allFolders',
            'myFolders', 'myItems',
            'sharedItems', 'sharedFolders',
            'sharedFolderContents', 'sharedFileContents',
            'currentFolder', 'breadcrumbs',
            'search', 'isSharedFolder'
        ));
    }
    
    private function getBreadcrumbs($folder)
    {
        $breadcrumbs = [];
        
        while ($folder) {
            array_unshift($breadcrumbs, $folder);
            $folder = $folder->parent;
        }
        
        return $breadcrumbs;
    }

    private function canAccessItem(EdushareItem $item): bool
    {
        if ($item->user_id === auth()->id()) {
            return true;
        }

        $isShared = EdushareShare::where('item_id', $item->id)
            ->where(function ($q) {
                $q->where('share_type', 'everyone')
                  ->orWhere(function ($sub) {
                      $sub->where('share_type', 'specific')
                          ->where('shared_with_email', auth()->user()->email);
                  });
            })
            ->exists();

        if ($isShared) {
            return true;
        }

        // Also allow access when any ancestor folder is shared with the user
        $folderId = $item->folder_id;
        $seen = [];
        while ($folderId && !isset($seen[$folderId])) {
            $seen[$folderId] = true;

            $folderShared = EdushareShare::where('folder_id', $folderId)
                ->where(function ($q) {
                    $q->where('share_type', 'everyone')
                      ->orWhere(function ($sub) {
                          $sub->where('share_type', 'specific')
                              ->where('shared_with_email', auth()->user()->email);
                      });
                })
                ->exists();

            if ($folderShared) {
                return true;
            }

            $parent = EdushareFolder::find($folderId);
            $folderId = $parent ? $parent->parent_id : null;
        }

        return false;
    }

    public function show(EdushareItem $item)
    {
        // Only the owner or users the item/folder is shared with may view it
        if (!$this->canAccessItem($item)) {
            abort(403, 'Unauthorized');
        }

        // Serve the file directly for viewing (proxy through Laravel)
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk(storage_disk());
        
        if (!$disk->exists($item->file_path)) {
            abort(404, 'File not found');
        }
        
        $extension = $item->file_type ?? pathinfo($item->file_path, PATHINFO_EXTENSION);
        $filename = $item->title . '.' . $extension;

        $mimeTypes = [
            'pdf' => 'application/pdf',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'zip' => 'application/zip',
        ];
        
        try {
            $mimeType = $disk->mimeType($item->file_path);
        } catch (\Exception $e) {
            $mimeType = $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
        }
        
        return response()->stream(function () use ($disk, $item) {
            $stream = $disk->readStream($item->file_path);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
        ]);
    }

    public function download(EdushareItem $item)
    {
        // Only the owner or users the item/folder is shared with may download
        if (!$this->canAccessItem($item)) {
            abort(403, 'Unauthorized');
        }

        $item->incrementDownloadCount();
        
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk(storage_disk());
        
        if (!$disk->exists($item->file_path)) {
            abort(404, 'File not found');
        }

        $extension = $item->file_type ?? pathinfo($item->file_path, PATHINFO_EXTENSION);
        $filename = $item->title . '.' . $extension;

        $mimeTypes = [
            'pdf' => 'application/pdf',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'zip' => 'application/zip',
        ];
        
        try {
            $mimeType = $disk->mimeType($item->file_path);
        } catch (\Exception $e) {
            $mimeType = $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
        }
        
        return response()->streamDownload(function () use ($disk, $item) {
            $stream = $disk->readStream($item->file_path);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, $filename, [
            'Content-Type' => $mimeType,
        ]);
    }

    public function getShares(Request $request)
    {
        $type = $request->get('type'); // 'item' or 'folder'
        $id = $request->get('id');
        
        if ($type === 'item') {
            $shares = EdushareShare::where('item_id', $id)->get();
        } else {
            $shares = EdushareShare::where('folder_id', $id)->get();
        }
        
        $everyoneShare = $shares->where('share_type', 'everyone')->first();
        $specificShares = $shares->where('share_type', 'specific')->pluck('shared_with_email')->toArray();
        
        return response()->json([
            'success' => true,
            'share_type' => $everyoneShare ? 'everyone' : ($specificShares ? 'specific' : null),
            'emails' => $specificShares
        ]);
    }

    public function share(Request $request)
    {
        try {
            $validated = $request->validate([
                'item_id' => 'nullable|exists:edushare_items,id',
                'folder_id' => 'nullable|exists:edushare_folders,id',
                'share_type' => 'required|in:everyone,specific',
                'emails' => 'nullable|array',
                'emails.*' => 'nullable|email',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
        
        // Additional validation for specific share type
        if ($validated['share_type'] === 'specific' && empty($validated['emails'])) {
            return response()->json(['success' => false, 'message' => 'Please provide at least one email address'], 422);
        }

        if (!isset($validated['item_id']) && !isset($validated['folder_id'])) {
            return response()->json(['success' => false, 'message' => 'Please select an item or folder to share'], 422);
        }

        // Initialize variables
        $item = null;
        $folder = null;

        // Check ownership
        if (isset($validated['item_id'])) {
            $item = EdushareItem::find($validated['item_id']);
            if (!$item || $item->user_id !== auth()->id()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }
        if (isset($validated['folder_id'])) {
            $folder = EdushareFolder::find($validated['folder_id']);
            if (!$folder || $folder->user_id !== auth()->id()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }

        if ($validated['share_type'] === 'everyone') {
            // Check if already shared with everyone
            $existingShare = EdushareShare::where(function($q) use ($validated) {
                if (isset($validated['item_id'])) {
                    $q->where('item_id', $validated['item_id']);
                }
                if (isset($validated['folder_id'])) {
                    $q->where('folder_id', $validated['folder_id']);
                }
            })
            ->where('share_type', 'everyone')
            ->first();

            if ($existingShare) {
                return response()->json([
                    'success' => false,
                    'message' => 'Already shared with everyone'
                ], 422);
            }

            // Share with everyone
            EdushareShare::create([
                'item_id' => $validated['item_id'] ?? null,
                'folder_id' => $validated['folder_id'] ?? null,
                'share_type' => 'everyone',
                'shared_by' => auth()->id(),
            ]);
            
            // Academic organizations notify their own active students only.
            // Non-Academic organizations intentionally notify every student.
            $sourceOrganizationId = $item?->organization_id ?? $folder?->organization_id;
            $students = User::eligibleStudentsForOrganizationNotifications($sourceOrganizationId)->get();
            $currentUser = auth()->user();
            $sharerName = $currentUser ? trim($currentUser->first_name . ' ' . $currentUser->last_name) : 'Someone';
            
            \Log::info('Sending notifications', [
                'item' => $item ? $item->id : 'null',
                'folder' => $folder ? $folder->id : 'null',
                'sharerName' => $sharerName,
                'type' => isset($validated['folder_id']) ? 'folder' : 'item'
            ]);
            
            foreach ($students as $student) {
                if ($student->id !== auth()->id()) {
                    $student->notify(new EdushareNotification(
                        $item,
                        $folder,
                        $sharerName,  // Pass the name string instead of user object
                        isset($validated['folder_id']) ? 'folder' : 'item'
                    ));
                }
            }
            
            return response()->json(['success' => true, 'message' => 'Shared with everyone successfully']);
        }

        // Share with specific emails
        $invalidEmails = [];
        $alreadySharedEmails = [];
        $currentUser = auth()->user();
        $sharerName = $currentUser ? trim($currentUser->first_name . ' ' . $currentUser->last_name) : 'Someone';
        
        foreach ($validated['emails'] ?? [] as $email) {
            // Check if already shared with this email
            $existingShare = EdushareShare::where(function($q) use ($validated) {
                if (isset($validated['item_id'])) {
                    $q->where('item_id', $validated['item_id']);
                }
                if (isset($validated['folder_id'])) {
                    $q->where('folder_id', $validated['folder_id']);
                }
            })
            ->where('share_type', 'specific')
            ->where('shared_with_email', $email)
            ->first();

            if ($existingShare) {
                $alreadySharedEmails[] = $email;
                continue;
            }

            $user = User::where('email', $email)->first();
            if (!$user) {
                $invalidEmails[] = $email;
                continue;
            }

            EdushareShare::create([
                'item_id' => $validated['item_id'] ?? null,
                'folder_id' => $validated['folder_id'] ?? null,
                'share_type' => 'specific',
                'shared_with_email' => $email,
                'shared_by' => auth()->id(),
            ]);
            
            // Send notification to specific user
            if ($user->id !== auth()->id()) {
                $user->notify(new EdushareNotification(
                    $item,
                    $folder,
                    $sharerName,  // Use the name string
                    isset($validated['folder_id']) ? 'folder' : 'item'
                ));
            }
        }

        if (!empty($alreadySharedEmails)) {
            return response()->json([
                'success' => false,
                'message' => 'Already shared with: ' . implode(', ', $alreadySharedEmails),
                'already_shared' => $alreadySharedEmails
            ], 422);
        }

        if (!empty($invalidEmails)) {
            return response()->json([
                'success' => false,
                'message' => 'Some emails are not registered: ' . implode(', ', $invalidEmails),
                'invalid_emails' => $invalidEmails
            ], 422);
        }

        return response()->json(['success' => true, 'message' => 'Shared successfully']);
    }

    public function myUploads()
    {
        $items = auth()->user()->edushareItems()->latest()->paginate(12);
        return view('student.edushare.my-uploads', compact('items'));
    }

    public function create()
    {
        return view('student.edushare.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'files' => 'required|array|min:1|max:'.config('edushare.max_files'),
            'files.*' => 'required|file|max:'.config('edushare.max_file_kb'),
            'folder_id' => 'nullable|exists:edushare_folders,id',
        ], [
            'files.max' => 'You can upload up to '.config('edushare.max_files').' files at a time.',
            'files.*.max' => 'Each file must be 20 MB or smaller.',
        ]);

        $totalBytes = array_sum(array_map(
            static fn ($file): int => (int) $file->getSize(),
            $request->file('files'),
        ));

        if ($totalBytes > config('edushare.max_total_kb') * 1024) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'files' => 'The combined upload size must be 20 MB or smaller.',
            ]);
        }

        $uploadedCount = 0;
        $files = $request->file('files');
        
        foreach ($files as $file) {
            // Use the original filename without extension as title
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            
            $data = [
                'user_id' => auth()->id(),
                'title' => $originalName,
                'file_path' => $file->store('edushare/students', storage_disk()),
                'file_type' => $file->getClientOriginalExtension(),
                'is_public' => \Illuminate\Support\Facades\DB::raw('FALSE'),
                'folder_id' => $validated['folder_id'] ?? null,
            ];
            
            $item = EdushareItem::create($data);
            
            // Keep the upload request focused on saving the file and database row.
            $uploadedCount++;
        }

        return response()->json([
            'success' => true,
            'message' => $uploadedCount . ' file(s) uploaded successfully'
        ]);
    }
    
    public function createFolder(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:edushare_folders,id',
        ]);

        $validated['user_id'] = auth()->id();

        EdushareFolder::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Folder created successfully'
        ]);
    }

    public function edit(EdushareItem $item)
    {
        $this->authorize('update', $item);
        return view('student.edushare.edit', compact('item'));
    }

    public function update(Request $request, EdushareItem $item)
    {
        $this->authorize('update', $item);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:project,code,system,documentation,other',
            'course' => 'nullable|string',
            'file' => 'nullable|file|max:'.config('edushare.max_file_kb'),
            'is_public' => 'boolean',
        ]);

        if ($request->hasFile('file')) {
            Storage::disk(storage_disk())->delete($item->file_path);
            $file = $request->file('file');
            $validated['file_path'] = $file->store('edushare', storage_disk());
            $validated['file_type'] = $file->getClientOriginalExtension();
        }

        if ($request->has('is_public')) {
            $validated['is_public'] = $request->is_public ? \Illuminate\Support\Facades\DB::raw('TRUE') : \Illuminate\Support\Facades\DB::raw('FALSE');
        }

        $item->update($validated);

        return redirect()->route('edushare.my-uploads')
            ->with('success', 'Item updated successfully.');
    }

    public function destroy(EdushareItem $item)
    {
        if ($item->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        Storage::disk(storage_disk())->delete($item->file_path);
        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'File deleted successfully'
        ]);
    }
    
    public function removeShare(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'nullable|exists:edushare_items,id',
            'folder_id' => 'nullable|exists:edushare_folders,id',
        ]);

        if (!isset($validated['item_id']) && !isset($validated['folder_id'])) {
            return response()->json(['success' => false, 'message' => 'Please select an item or folder'], 422);
        }

        // Delete shares for this user
        $deleted = EdushareShare::where(function($q) use ($validated) {
            if (isset($validated['item_id'])) {
                $q->where('item_id', $validated['item_id']);
            }
            if (isset($validated['folder_id'])) {
                $q->where('folder_id', $validated['folder_id']);
            }
        })
        ->where(function($q) {
            $q->where('share_type', 'everyone')
              ->orWhere(function($subQuery) {
                  $subQuery->where('share_type', 'specific')
                          ->where('shared_with_email', auth()->user()->email);
              });
        })
        ->delete();

        if ($deleted > 0) {
            return response()->json(['success' => true, 'message' => 'Removed from your shared items']);
        }

        return response()->json(['success' => false, 'message' => 'Item not found in your shared items'], 404);
    }

    public function unshareEmail(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'nullable|exists:edushare_items,id',
            'folder_id' => 'nullable|exists:edushare_folders,id',
            'email' => 'required|email',
        ]);

        if (!isset($validated['item_id']) && !isset($validated['folder_id'])) {
            return response()->json(['success' => false, 'message' => 'Please select an item or folder'], 422);
        }

        // Verify ownership
        if (isset($validated['item_id'])) {
            $item = EdushareItem::find($validated['item_id']);
            if ($item->user_id !== auth()->id()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }

        if (isset($validated['folder_id'])) {
            $folder = EdushareFolder::find($validated['folder_id']);
            if ($folder->user_id !== auth()->id()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }

        // Delete the specific share
        $deleted = EdushareShare::where(function($q) use ($validated) {
            if (isset($validated['item_id'])) {
                $q->where('item_id', $validated['item_id']);
            }
            if (isset($validated['folder_id'])) {
                $q->where('folder_id', $validated['folder_id']);
            }
        })
        ->where('share_type', 'specific')
        ->where('shared_with_email', $validated['email'])
        ->delete();

        if ($deleted > 0) {
            return response()->json(['success' => true, 'message' => 'Successfully unshared with ' . $validated['email']]);
        }

        return response()->json(['success' => false, 'message' => 'Share not found'], 404);
    }

    public function destroyFolder(EdushareFolder $folder)
    {
        if ($folder->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Delete all items in folder
        foreach ($folder->items as $item) {
            Storage::disk(storage_disk())->delete($item->file_path);
            $item->delete();
        }

        // Delete all subfolders recursively
        $this->deleteFolderRecursive($folder);

        return response()->json([
            'success' => true,
            'message' => 'Folder deleted successfully'
        ]);
    }
    
    private function deleteFolderRecursive($folder)
    {
        foreach ($folder->children as $child) {
            $this->deleteFolderRecursive($child);
        }
        
        foreach ($folder->items as $item) {
            Storage::disk(storage_disk())->delete($item->file_path);
            $item->delete();
        }
        
        $folder->delete();
    }

    public function renameFolder(Request $request, EdushareFolder $folder)
    {
        if ($folder->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $folder->update(['name' => $validated['name']]);

        return response()->json([
            'success' => true,
            'message' => 'Folder renamed successfully'
        ]);
    }

    public function renameItem(Request $request, EdushareItem $item)
    {
        if ($item->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $item->update(['title' => $validated['name']]);

        return response()->json([
            'success' => true,
            'message' => 'File renamed successfully'
        ]);
    }

    public function moveFolder(Request $request, EdushareFolder $folder)
    {
        if ($folder->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validated = $request->validate([
            'target_folder_id' => 'required|exists:edushare_folders,id',
        ]);

        // Check if target folder belongs to user
        $targetFolder = EdushareFolder::find($validated['target_folder_id']);
        if ($targetFolder->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot move to this folder'
            ], 403);
        }

        // Prevent circular reference
        if ($this->isDescendant($folder->id, $validated['target_folder_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot move folder into its own subfolder'
            ], 400);
        }

        $folder->update(['parent_id' => $validated['target_folder_id']]);

        return response()->json([
            'success' => true,
            'message' => 'Folder moved successfully'
        ]);
    }

    public function moveItem(Request $request, EdushareItem $item)
    {
        if ($item->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validated = $request->validate([
            'target_folder_id' => 'required|exists:edushare_folders,id',
        ]);

        // Check if target folder belongs to user
        $targetFolder = EdushareFolder::find($validated['target_folder_id']);
        if ($targetFolder->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot move to this folder'
            ], 403);
        }

        $item->update(['folder_id' => $validated['target_folder_id']]);

        return response()->json([
            'success' => true,
            'message' => 'File moved successfully'
        ]);
    }

    private function isDescendant($folderId, $potentialAncestorId)
    {
        $folder = EdushareFolder::find($potentialAncestorId);
        
        while ($folder && $folder->parent_id) {
            if ($folder->parent_id == $folderId) {
                return true;
            }
            $folder = $folder->parent;
        }
        
        return false;
    }
}
