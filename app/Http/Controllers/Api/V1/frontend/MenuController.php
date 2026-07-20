<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Services\MenuService;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class MenuController extends Controller
{
    public function __construct(
        private readonly MenuService $menuService,
    ) {}

    public function byLocation(string $location)
    {
        $menu = $this->menuService->getPublicByLocation($location);

        if (! $menu) {
            return sendResponse(false, 'No menu assigned to this location', null, HttpStatus::HTTP_NOT_FOUND);
        }

        return sendResponse(true, 'Menu retrieved successfully', $menu, HttpStatus::HTTP_OK);
    }

    public function bySlug(string $slug)
    {
        $menu = $this->menuService->getPublicBySlug($slug);

        if (! $menu) {
            return sendResponse(false, 'Menu not found', null, HttpStatus::HTTP_NOT_FOUND);
        }

        return sendResponse(true, 'Menu retrieved successfully', $menu, HttpStatus::HTTP_OK);
    }
}
