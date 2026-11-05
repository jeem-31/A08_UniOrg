<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EdushareFolder;
use App\Models\EdushareItem;
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
        
        $folders = EdushareFolder::where('user_id', auth()->id())
            ->where('parent_id', $folderId)
            ->orderBy('name')
            ->get();
        
        $items = EdushareItem::where('user_id', auth()->id())
            ->where('folder_id', $folderId)
            ->orderBy('created_at', 'desc')
            ->get();
        
        $currentFolder = $folderId ? EdushareFolder::find($folderId) : null;
        $breadcrumbs = $this->getBreadcrumbs($currentFolder);
        
        return view('admin.edushare.index', compact('folders', 'items', 'currentFolder', 'breadcrumbs'));
    }

    public function createFolder(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:edushare_folders,id',
        ]);

        $validated['user_id'] = auth()->id();

        $folder = EdushareFolder::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Folder created successfully',
            'folder_id' => $folder->id
        ]);
    }

    public function uploadFile(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|max:'.config('edushare.max_file_kb'),
            'folder_id' => 'nullable|exists:edushare_folders,id',
        ], [
            'file.max' => 'The file must be 20 MB or smaller.',
        ]);

        $validated['user_id'] = auth()->id();
        $file = $request->file('file');
        $validated['title'] = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $validated['file_path'] = $file->store('edushare/admin', storage_disk());
        $validated['file_type'] = $file->getClientOriginalExtension();
        $validated['is_public'] = \Illuminate\Support\Facades\DB::raw('FALSE');

        $item = EdushareItem::create($validated);

        // Return the upload response without waiting for a realtime broadcast.
        return response()->json([
            'success' => true,
            'message' => 'File uploaded successfully',
            'item_id' => $item->id
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
            return response()->json([
                'success' => false,
                'message' => 'Please provide at least one email address'
            ], 422);
        }

        if (!isset($validated['item_id']) && !isset($validated['folder_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Please select an item or folder to share'
            ], 422);
        }

        // Get item or folder for notification
        $item = isset($validated['item_id']) ? EdushareItem::find($validated['item_id']) : null;
        $folder = isset($validated['folder_id']) ? EdushareFolder::find($validated['folder_id']) : null;

        // Check if sharing specific
        if ($validated['share_type'] === 'specific') {
            $invalidEmails = [];
            $alreadySharedEmails = [];
            $currentUser = auth()->user();
            $sharerName = $currentUser ? trim($currentUser->first_name . ' ' . $currentUser->last_name) : 'Admin';
            
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

                $user = User::where('email', $email)->where('role', 'student')->first();
                
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
                $user->notify(new EdushareNotification(
                    $item,
                    $folder,
                    $sharerName,  // Use the name string
                    isset($validated['folder_id']) ? 'folder' : 'item'
                ));
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
                    'message' => 'Some emails are not registered students: ' . implode(', ', $invalidEmails),
                    'invalid_emails' => $invalidEmails
                ], 422);
            }
        } else {
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
            $sharerName = $currentUser ? trim($currentUser->first_name . ' ' . $currentUser->last_name) : 'Admin';
            
            \Log::info('[ADMIN] Sending notifications', [
                'item' => $item ? $item->id : 'null',
                'folder' => $folder ? $folder->id : 'null',
                'sharerName' => $sharerName,
                'type' => isset($validated['folder_id']) ? 'folder' : 'item'
            ]);
            
            foreach ($students as $student) {
                $student->notify(new EdushareNotification(
                    $item,
                    $folder,
                    $sharerName,  // Pass the name string instead of user object
                    isset($validated['folder_id']) ? 'folder' : 'item'
                ));
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Shared successfully'
        ]);
    }

    public function deleteItem(EdushareItem $item)
    {
        if ($item->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $itemId = $item->id;
        Storage::disk(storage_disk())->delete($item->file_path);
        $item->delete();

        // Return the delete response without waiting for a realtime broadcast.
        return response()->json([
            'success' => true,
            'message' => 'File deleted successfully'
        ]);
    }

    public function deleteFolder(EdushareFolder $folder)
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

    private function getBreadcrumbs($folder)
    {
        $breadcrumbs = [];
        
        while ($folder) {
            array_unshift($breadcrumbs, $folder);
            $folder = $folder->parent;
        }
        
        return $breadcrumbs;
    }

    public function download(EdushareItem $item)
    {
        return Storage::disk(storage_disk())->download($item->file_path, $item->title . '.' . $item->file_type);
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

        // Broadcast real-time update
        broadcast(new \App\Events\EdushareItemUpdated($item));

        return response()->json([
            'success' => true,
            'message' => 'File renamed successfully'
        ]);
    }
}
