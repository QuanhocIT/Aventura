<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompressResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Check if compression is enabled (defaults to true in production, false in local)
        $enabled = config('app.compress_responses', ! app()->isLocal());
        if (! $enabled || ! extension_loaded('zlib')) {
            return $response;
        }

        // Avoid double compression or compressing already encoded responses
        if ($response->headers->has('Content-Encoding')) {
            return $response;
        }

        // Check if client supports Gzip
        $acceptEncoding = $request->header('Accept-Encoding', '');
        if (! str_contains($acceptEncoding, 'gzip')) {
            return $response;
        }

        // We only compress text, HTML, and JSON responses
        $contentType = $response->headers->get('Content-Type', '');
        if (str_contains($contentType, 'text/') || str_contains($contentType, 'application/json')) {
            $content = $response->getContent();

            // Only compress payloads larger than 1KB to avoid overhead
            if ($content !== false && strlen($content) > 1024) {
                $compressed = gzencode($content, 5); // Level 5 compression offers a good balance of CPU speed and size reduction

                if ($compressed !== false) {
                    $response->setContent($compressed);
                    $response->headers->set('Content-Encoding', 'gzip');
                    $response->headers->set('Vary', 'Accept-Encoding');
                    $response->headers->set('Content-Length', strlen($compressed));
                }
            }
        }

        return $response;
    }
}
