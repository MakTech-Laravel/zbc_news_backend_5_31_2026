<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Models\NavigationLink;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class NavigationController extends Controller
{
    public function quickLinks()
    {
        $links = NavigationLink::query()
            ->where('location', 'home_quick_links')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'label', 'url', 'icon', 'sort_order']);

        return sendResponse(
            true,
            'Quick links retrieved successfully',
            $links,
            HttpStatus::HTTP_OK,
        );
    }
}

