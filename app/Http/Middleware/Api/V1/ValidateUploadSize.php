<?php

namespace App\Http\Middleware\Api\V1;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateUploadSize
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $mime = $file->getMimeType();

            $maxBytes = match (true) {
                str_starts_with($mime, 'video/') => config('cloudinary.max_video_size'),
                str_starts_with($mime, 'image/') => config('cloudinary.max_image_size'),
                default => config('cloudinary.max_document_size'),
            };

            if ($file->getSize() > $maxBytes) {
                return response()->json([
                    'success' => false,
                    'message' => 'File too large.',
                    'data' => [
                        'max_allowed' => round($maxBytes / 1048576, 0) . ' MB',
                    ],
                ], 413);
            }
        }

        return $next($request);
    }
}
