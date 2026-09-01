<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SiteSettingsRequest;
use App\Http\Resources\Api\V1\SiteSettingsResource;
use App\Services\SiteSettingsService;
use App\Support\SiteSocialContactSettings;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class SiteSettingsController extends Controller
{
    public function __construct(
        private readonly SiteSettingsService $siteSettingsService
    ) {}

    public function index()
    {
        $siteSettings = $this->siteSettingsService->getOrDefault();

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
        $existing = $this->siteSettingsService->getAll();

        $data = SiteSocialContactSettings::packIntoPayload(
            $data,
            is_array($existing?->social_contact_settings) ? $existing->social_contact_settings : null,
        );

        if (array_key_exists('site_logo', $data) && is_string($data['site_logo'])) {
            $data['site_logo'] = trim($data['site_logo']) ?: null;
        }

        if (array_key_exists('favicon', $data) && is_string($data['favicon'])) {
            $data['favicon'] = trim($data['favicon']) ?: null;
        }

        foreach (
            [
                'google_adsense_client',
                'google_adsense_banner_slot',
                'google_adsense_sidebar_slot',
                'google_adsense_square_slot',
                'social_facebook_url',
                'social_x_url',
                'social_linkedin_url',
                'social_tiktok_url',
                'social_instagram_url',
                'contact_general_email',
                'contact_press_email',
                'contact_advertising_email',
                'contact_corrections_email',
            ] as $field
        ) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]) ?: null;
            }
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
