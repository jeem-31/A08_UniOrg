<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddCloudflareImageCacheHeaders
{
    /**
     * Add aggressive caching headers for Cloudflare R2 images
     * This tells the browser to cache images for 1 year
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Only add cache headers for image requests
        $contentType = $response->headers->get('Content-Type');
        
        if ($contentType && str_starts_with($contentType, 'image/')) {
            // Aggressive browser caching - 1 year
            $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');
            
            // Cloudflare-specific headers
            $response->headers->set('CF-Cache-Status', 'HIT');
            
            // Add CORS headers for cross-origin image loading
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
            
            // Add ETag for better caching
            if (!$response->headers->has('ETag')) {
                $etag = md5($response->getContent());
                $response->headers->set('ETag', '"' . $etag . '"');
            }
        }
        
        return $response;
    }
}
