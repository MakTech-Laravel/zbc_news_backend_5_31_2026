<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Support\FacebookVideoUrlResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class FacebookVideoEmbedController extends Controller
{
    public function __construct(
        private readonly FacebookVideoUrlResolver $resolver,
    ) {}

    public function resolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'url', 'max:2048'],
        ]);

        $inputUrl = trim($validated['url']);
        if (! $this->resolver->isAllowedUrl($inputUrl)) {
            return sendResponse(
                false,
                'Only Facebook video URLs are allowed.',
                null,
                HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $canonical = $this->resolver->resolve($inputUrl);

        if ($canonical === null) {
            return sendResponse(
                false,
                'Could not resolve this Facebook link. Open it in a browser and paste the watch or reel URL.',
                null,
                HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return sendResponse(
            true,
            'Facebook video URL resolved successfully.',
            [
                'canonical_url' => $canonical,
                'input_url' => $inputUrl,
            ],
            HttpStatus::HTTP_OK,
        );
    }
}
