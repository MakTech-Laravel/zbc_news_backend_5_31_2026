<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Models\AdSlot;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AdSlotController extends Controller
{
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
                ->keyBy('slot_key'),
        );

        return sendResponse(
            true,
            'Ad slots retrieved successfully',
            $slots,
            HttpStatus::HTTP_OK,
        );
    }
}
