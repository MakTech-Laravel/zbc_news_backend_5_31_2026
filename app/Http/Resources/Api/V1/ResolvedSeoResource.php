<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the fully-resolved SEO payload produced by SeoResolverService.
 * The resource is the already-shaped array, so we return it as-is.
 */
class ResolvedSeoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return $data;
    }
}
