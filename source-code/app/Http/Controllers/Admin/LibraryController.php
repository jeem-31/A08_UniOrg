<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LibraryResource;
use App\Services\ContentCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LibraryController extends Controller
{
    public function index(Request $request, ContentCache $contentCache)
    {
        $currentOrgId = session('current_organization_id');
        // Get all published resources for current organization for client-side filtering
        $resources = $contentCache->rememberLibraryResources($currentOrgId, function () use ($currentOrgId) {
            return LibraryResource::where('organization_id', $currentOrgId)
                ->published()
                ->with('user')
                ->latest()
                ->get()
                ->map(function($r) {
                    return [
                        'id' => $r->id,
                        'title' => $r->title,
                        'description' => $r->description,
                        'description_short' => \Str::limit($r->description, 100),
                        'resource_type' => $r->resource_type,
                        'url' => $r->url,
                        'file_path' => $r->file_path,
                    ];
                });
        });

        return view('student.library.index', compact('resources'));
    }

    public function show(LibraryResource $resource)
    {
        return view('student.library.show', compact('resource'));
    }

    public function view(LibraryResource $resource)
    {
        if ($resource->resource_type === 'link') {
            return redirect($resource->url);
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk(storage_disk());

        // Check if file exists in storage
        if (!$disk->exists($resource->file_path)) {
            abort(404, 'File not found in storage');
        }

        $extension = $resource->file_type ?? pathinfo($resource->file_path, PATHINFO_EXTENSION);
        $filename = $resource->title . '.' . $extension;

        // Custom mime-type lookup dictionary to prevent S3/R2 unable to retrieve metadata errors
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
            $mimeType = $disk->mimeType($resource->file_path);
        } catch (\Exception $e) {
            $mimeType = $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
        }

        // For PPT files, force download since browsers can't display them inline
        if ($resource->resource_type === 'presentation') {
            return response()->streamDownload(function () use ($disk, $resource) {
                $stream = $disk->readStream($resource->file_path);
                if (is_resource($stream)) {
                    fpassthru($stream);
                    fclose($stream);
                }
            }, $filename, [
                'Content-Type' => $mimeType,
            ]);
        }

        // For PDF or other files to view inline, stream them inline
        return response()->stream(function () use ($disk, $resource) {
            $stream = $disk->readStream($resource->file_path);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
        ]);
    }

    public function download(LibraryResource $resource)
    {
        $resource->incrementDownloadCount();

        if ($resource->resource_type === 'link') {
            return redirect($resource->url);
        }

        // Get file extension
        $extension = $resource->file_type ?? pathinfo($resource->file_path, PATHINFO_EXTENSION);
        $filename = $resource->title . '.' . $extension;

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk(storage_disk());

        if (!$disk->exists($resource->file_path)) {
            abort(404, 'File not found in storage');
        }

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
            $mimeType = $disk->mimeType($resource->file_path);
        } catch (\Exception $e) {
            $mimeType = $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
        }

        return response()->streamDownload(function () use ($disk, $resource) {
            $stream = $disk->readStream($resource->file_path);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, $filename, [
            'Content-Type' => $mimeType,
        ]);
    }

    // Admin methods
    public function adminIndex()
    {
        $currentOrgId = session('current_organization_id');
        $resources = LibraryResource::where('organization_id', $currentOrgId)
            ->with('user')
            ->latest()
            ->paginate(15);

        return view('admin.library.index', compact('resources'));
    }

    public function create()
    {
        return view('admin.library.create');
    }

    public function store(Request $request)
    {
        // Custom validation based on resource type
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',

            'resource_type' => 'required|in:pdf,link,presentation',
            'is_published' => 'boolean',
        ];

        // Add specific file validation based on resource type
        if ($request->resource_type === 'pdf') {
            $rules['file'] = 'required|file|mimes:pdf|mimetypes:application/pdf|max:20480';
        } elseif ($request->resource_type === 'presentation') {
            $rules['file'] = 'required|file|mimes:ppt,pptx|mimetypes:application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation|max:20480';
        } elseif ($request->resource_type === 'link') {
            $rules['url'] = 'required|url';
        }

        $messages = [
            'file.mimes' => 'The file must be a valid :values file.',
            'file.mimetypes' => 'Invalid file type. Please upload the correct file format.',
        ];

        $validated = $request->validate($rules, $messages);
        $validated['user_id'] = auth()->id();
        $validated['organization_id'] = session('current_organization_id');

        if ($request->resource_type === 'link') {
            $validated['file_path'] = null;
            $validated['file_type'] = null;
        } else {
            $file = $request->file('file');
            $validated['file_path'] = $file->store('library', storage_disk());
            $validated['file_type'] = $file->getClientOriginalExtension();
            $validated['url'] = null;
        }

        $resource = LibraryResource::create($validated);

        // Broadcast real-time update
        broadcast(new \App\Events\LibraryResourceCreated($resource));



        return redirect()->route('admin.library.index')
            ->with('success', 'Resource added successfully.');
    }

    public function edit(LibraryResource $resource)
    {
        return view('admin.library.edit', compact('resource'));
    }

    public function update(Request $request, LibraryResource $resource)
    {
        // Custom validation based on resource type
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',

            'resource_type' => 'required|in:pdf,link,presentation',
            'is_published' => 'boolean',
        ];

        // Add specific file validation based on resource type
        if ($request->hasFile('file')) {
            if ($request->resource_type === 'pdf') {
                $rules['file'] = 'file|mimes:pdf|mimetypes:application/pdf|max:20480';
            } elseif ($request->resource_type === 'presentation') {
                $rules['file'] = 'file|mimes:ppt,pptx|mimetypes:application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation|max:20480';
            }
        }

        if ($request->resource_type === 'link') {
            $rules['url'] = 'required|url';
        }

        $messages = [
            'file.mimes' => 'The file must be a valid :values file.',
            'file.mimetypes' => 'Invalid file type. Please upload the correct file format.',
        ];

        $validated = $request->validate($rules, $messages);

        // Handle link type
        if ($request->resource_type === 'link') {
            $validated['url'] = $request->url;
            // Don't change file_path for links
        } else {
            // Handle file upload for PDF and Presentation
            if ($request->hasFile('file')) {
                if ($resource->file_path) {
                    Storage::disk(storage_disk())->delete($resource->file_path);
                }
                $file = $request->file('file');
                $validated['file_path'] = $file->store('library', storage_disk());
                $validated['file_type'] = $file->getClientOriginalExtension();
            }
            $validated['url'] = null;
        }

        $resource->update($validated);

        // Broadcast real-time update
        broadcast(new \App\Events\LibraryResourceUpdated($resource));



        return redirect()->route('admin.library.index')
            ->with('success', 'Resource updated successfully.');
    }

    public function destroy(LibraryResource $resource)
    {
        $resourceId = $resource->id;

        // Only delete file if it exists (links don't have file_path)
        if ($resource->file_path) {
            Storage::disk(storage_disk())->delete($resource->file_path);
        }

        $resource->delete();

        // Broadcast real-time update
        broadcast(new \App\Events\LibraryResourceDeleted($resourceId));



        return back()->with('success', 'Resource deleted successfully.');
    }
}
