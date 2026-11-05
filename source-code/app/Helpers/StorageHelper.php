<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class StorageHelper
{
    /**
     * Get the storage disk to use (s3 for production, public for local)
     */
    public static function disk(): string
    {
        return config('filesystems.default');
    }

    /**
     * Get the public URL for a file
     */
    public static function url(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        // Remove 'storage/' prefix if it exists (for backward compatibility)
        $path = preg_replace('#^storage/#', '', $path);
        
        // Normalize the path - ensure it's properly formatted
        $path = trim($path);

        // Serve private directories through Laravel secure serve route instead of public R2 CDN
        $privateDirs = ['student_documents', 'library', 'edushare'];
        $isPrivate = false;
        foreach ($privateDirs as $dir) {
            if (str_starts_with($path, $dir . '/')) {
                $isPrivate = true;
                break;
            }
        }

        if ($isPrivate) {
            return url('storage/' . $path);
        }

        // If using S3/R2
        if (self::disk() === 's3') {
            try {
                // Use direct public URL from R2 CDN - much faster than signed URLs
                $publicUrl = config('filesystems.disks.s3.url');
                
                if ($publicUrl) {
                    // Ensure no double slashes
                    $publicUrl = rtrim($publicUrl, '/');
                    $path = ltrim($path, '/');
                    
                    // URL encode the path segments (to handle spaces and special chars, but preserve slashes)
                    $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
                    
                    // Return direct CDN URL (no signing needed for public buckets)
                    return $publicUrl . '/' . $encodedPath;
                }
                
                // Fallback to signed URL if AWS_URL not configured
                /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                $disk = Storage::disk('s3');
                return method_exists($disk, 'temporaryUrl') 
                    ? $disk->temporaryUrl($path, now()->addHours(24))
                    : asset('storage/' . $path);
            } catch (\Exception $e) {
                \Log::error('Failed to generate URL', [
                    'path' => $path,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        }

        // If using local/public disk, return asset URL
        return asset('storage/' . $path);
    }

    /**
     * Store a file and return the path
     */
    public static function store($file, string $directory): string
    {
        return $file->store($directory, self::disk());
    }

    /**
     * Delete a file
     */
    public static function delete(?string $path): bool
    {
        if (!$path) {
            return false;
        }

        return Storage::disk(self::disk())->delete($path);
    }

    /**
     * Check if a file exists
     */
    public static function exists(?string $path): bool
    {
        if (!$path) {
            return false;
        }

        return Storage::disk(self::disk())->exists($path);
    }

    /**
     * Transform images array to include full URLs
     */
    public static function transformImages(?array $images): array
    {
        if (!$images) {
            return [];
        }

        $transformed = [];
        foreach ($images as $image) {
            if (empty($image)) {
                continue; // Skip empty values
            }
            
            try {
                $url = self::url($image);
                if ($url) {
                    $transformed[] = $url;
                }
            } catch (\Exception $e) {
                \Log::error('Failed to generate signed URL for image: ' . $image, [
                    'error' => $e->getMessage()
                ]);
                // Continue with other images instead of failing completely
            }
        }
        
        return $transformed;
    }
}
