<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Models\NavigationLink;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class NavigationController extends Controller
{
    /**
     * The cached value is a plain array, never the Eloquent collection: cache stores serialize
     * their payloads and models do not survive that round trip here, returning as
     * __PHP_Incomplete_Class. The JSON response is identical either way.
     */
    public function quickLinks()
    {
        $links = Cache::remember(
            NavigationLink::CACHE_QUICK_LINKS,
            NavigationLink::TTL_QUICK_LINKS,
            fn () => NavigationLink::query()
                ->where('location', NavigationLink::LOCATION_QUICK_LINKS)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'label', 'url', 'icon', 'sort_order'])
                ->toArray(),
        );

        return sendResponse(
            true,
            'Quick links retrieved successfully',
            $links,
            HttpStatus::HTTP_OK,
        );
    }
}
