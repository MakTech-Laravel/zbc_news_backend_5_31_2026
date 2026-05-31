<?php

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Storage;

function sendResponse($status, $message, $data = null, $statusCode = 200, $additional = null)
{

    // Initialize the base response data
    $responseData = [
        'success' => $status,
        'message' => $message,
    ];

    // Check if the data is a Laravel ResourceCollection
    if ($data instanceof ResourceCollection) {
        $resourcePayload = $data->response()->getData(true);

        $responseData['data'] = $resourcePayload['data'] ?? $data->resolve();

        if (isset($resourcePayload['links'], $resourcePayload['meta'])) {
            $responseData['links'] = $resourcePayload['links'];
            $responseData['meta'] = $resourcePayload['meta'];
        }
    } else {
        // If it's not a collection, just add the data directly
        $responseData['data'] = $data;
    }

    // Merge any additional data if provided
    if (! empty($additional) && is_array($additional)) {
        $responseData = array_merge($responseData, $additional);
    }

    return response()->json($responseData, $statusCode);
}

if (! function_exists('storage_url')) {
    /**
     * Absolute URL for a path on the `public` disk (storage/app/public → /storage/...).
     * Matches manufacturer catalog file URLs. Pass through already-absolute http(s) URLs.
     */
    function storage_url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $trimmed = ltrim($path, '/');

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($trimmed);
    }
}
