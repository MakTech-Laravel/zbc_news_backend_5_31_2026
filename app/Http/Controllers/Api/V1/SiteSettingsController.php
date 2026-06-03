<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SiteSettingsRequest;
use App\Http\Resources\Api\V1\SiteSettingsResource;
use App\Services\SiteSettingsService;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class SiteSettingsController extends Controller
{
    public function __construct(
        private readonly SiteSettingsService $siteSettingsService
    ) {}

    public function index()
    {
        $siteSettings = $this->siteSettingsService->getAll();

        return sendResponse(
            true,
            'Site settings retrieved successfully',
            new SiteSettingsResource($siteSettings),
            HttpStatus::HTTP_OK,
        );
    }

    public function createOrUpdate(SiteSettingsRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('site_logo')) {
            $data['site_logo'] = $request->file('site_logo')
                ->store('site_logos', 'public');
        }

        $siteSettings = $this->siteSettingsService->createOrUpdate($data);

        $isNew = $siteSettings->wasRecentlyCreated;

        return sendResponse(
            true,
            $isNew ? 'Site settings created successfully' : 'Site settings updated successfully',
            new SiteSettingsResource($siteSettings),
            $isNew ? HttpStatus::HTTP_CREATED : HttpStatus::HTTP_OK,
        );
    }
}