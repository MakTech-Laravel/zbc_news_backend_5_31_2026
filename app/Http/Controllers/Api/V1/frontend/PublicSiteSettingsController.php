<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PublicSiteSettingsResource;
use App\Services\SiteSettingsService;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class PublicSiteSettingsController extends Controller
{
    public function __construct(
        private readonly SiteSettingsService $siteSettingsService
    ) {}

    public function index()
    {
        $settings = $this->siteSettingsService->getOrDefault();

        return sendResponse(
            true,
            'Site settings retrieved successfully',
            new PublicSiteSettingsResource($settings),
            HttpStatus::HTTP_OK,
        );
    }
}
