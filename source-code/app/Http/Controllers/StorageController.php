<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageController extends Controller
{
    public function serve($path)
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk(storage_disk());

        if (!$disk->exists($path)) {
            abort(404);
        }

        $file = $disk->get($path);
        
        $extension = pathinfo($path, PATHINFO_EXTENSION);
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
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            'txt' => 'text/plain',
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'svg' => 'image/svg+xml',
        ];

        try {
            $type = $disk->mimeType($path);
        } catch (\Exception $e) {
            $type = $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
        }

        $response = Response::make($file, 200);
        $response->header("Content-Type", $type);

        return $response;
    }

    /**
     * Proxy R2 storage files through Laravel (BACKUP METHOD)
     * Use this if direct public URLs don't work
     * Provides fast, secure access without signed URLs (~400ms vs 1200ms)
     */
    public function proxy(string $path)
    {
        // Security: Validate path
        if (str_contains($path, '..') || str_starts_with($path, '/')) {
            abort(403, 'Invalid path');
        }

        // Check if file exists
        if (!Storage::disk('s3')->exists($path)) {
            abort(404, 'File not found');
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
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
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            'txt' => 'text/plain',
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'svg' => 'image/svg+xml',
        ];

        // Get file metadata
        try {
            $mimeType = Storage::disk('s3')->mimeType($path);
        } catch (\Exception $e) {
            $mimeType = $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
        }
        
        try {
            $size = Storage::disk('s3')->size($path);
        } catch (\Exception $e) {
            $size = 0;
        }

        try {
            $lastModified = Storage::disk('s3')->lastModified($path);
        } catch (\Exception $e) {
            $lastModified = time();
        }

        // Stream file from R2 to browser
        return new StreamedResponse(function () use ($path) {
            $stream = Storage::disk('s3')->readStream($path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => $size,
            'Cache-Control' => 'public, max-age=31536000', // Cache for 1 year
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified) . ' GMT',
            'Expires' => gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT',
        ]);
    }
}
