<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Models\AdSlot;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AdSlotController extends Controller
{
    /**
     * The cached value is a plain array keyed by slot_key, never the Eloquent collection:
     * cache stores serialize their payloads and models do not survive that round trip here,
     * returning as __PHP_Incomplete_Class. keyBy() runs before toArray() so the response keeps
     * its keyed-object shape.
     */
    public function index()
    {
        $slots = Cache::remember(
            AdSlot::CACHE_PUBLIC,
            AdSlot::TTL_PUBLIC,
            fn () => AdSlot::query()
                ->where('is_active', true)
                ->orderBy('slot_key')
                ->get([
                    'slot_key',
                    'provider',
                    'google_ad_client',
                    'google_ad_slot',
                    'manual_image_url',
                    'manual_click_url',
                    'manual_html',
                ])
                ->keyBy('slot_key')
                ->toArray(),
        );

        return sendResponse(
            true,
            'Ad slots retrieved successfully',
            $slots,
            HttpStatus::HTTP_OK,
        );
    }
}
