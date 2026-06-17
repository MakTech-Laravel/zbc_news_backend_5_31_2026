<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Models\NewsletterCampaign;
use App\Services\Newsletter\NewsletterService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class NewsletterController extends Controller
{
    public function __construct(
        private readonly NewsletterService $newsletterService,
    ) {}

    public function analytics()
    {
        try {
            $data = $this->newsletterService->analytics();
        } catch (QueryException $exception) {
            report($exception);

            return sendResponse(
                false,
                'Newsletter analytics unavailable. Please run database migrations on the server.',
                null,
                HttpStatus::HTTP_INTERNAL_SERVER_ERROR,
            );
        } catch (\Throwable $exception) {
            report($exception);

            return sendResponse(
                false,
                'Unable to load newsletter analytics.',
                null,
                HttpStatus::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return sendResponse(
            true,
            'Newsletter analytics retrieved successfully',
            $data,
            HttpStatus::HTTP_OK,
        );
    }

    public function subscribers(Request $request)
    {
        return sendResponse(
            true,
            'Newsletter subscribers retrieved successfully',
            $this->newsletterService->listSubscribers($request->query('status')),
            HttpStatus::HTTP_OK,
        );
    }

    public function campaigns()
    {
        return sendResponse(
            true,
            'Newsletter campaigns retrieved successfully',
            $this->newsletterService->listCampaigns(),
            HttpStatus::HTTP_OK,
        );
    }

    public function showCampaign(int $id)
    {
        $campaign = $this->newsletterService->getCampaign($id);

        if (!$campaign) {
            return sendResponse(false, 'Campaign not found', null, HttpStatus::HTTP_NOT_FOUND);
        }

        return sendResponse(true, 'Newsletter campaign retrieved successfully', $campaign, HttpStatus::HTTP_OK);
    }

    public function storeCampaign(Request $request)
    {
        $validated = $this->validateCampaign($request);

        try {
            $campaign = $this->newsletterService->createCampaign($validated);
        } catch (QueryException $exception) {
            report($exception);

            return sendResponse(
                false,
                'Newsletter campaigns table is not ready. Please run database migrations on the server.',
                null,
                HttpStatus::HTTP_INTERNAL_SERVER_ERROR,
            );
        } catch (\Throwable $exception) {
            report($exception);

            return sendResponse(
                false,
                'Unable to create newsletter campaign.',
                null,
                HttpStatus::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return sendResponse(true, 'Newsletter campaign created successfully', $campaign, HttpStatus::HTTP_CREATED);
    }

    public function updateCampaign(Request $request, int $id)
    {
        $campaign = $this->newsletterService->getCampaign($id);

        if (!$campaign) {
            return sendResponse(false, 'Campaign not found', null, HttpStatus::HTTP_NOT_FOUND);
        }

        try {
            $updated = $this->newsletterService->updateCampaign($campaign, $this->validateCampaign($request, false));
        } catch (\RuntimeException $e) {
            return sendResponse(false, $e->getMessage(), null, HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
        }

        return sendResponse(true, 'Newsletter campaign updated successfully', $updated, HttpStatus::HTTP_OK);
    }

    public function scheduleCampaign(Request $request, int $id)
    {
        $validated = $request->validate([
            'scheduled_at' => ['required', 'date'],
        ]);

        $campaign = $this->newsletterService->getCampaign($id);

        if (!$campaign) {
            return sendResponse(false, 'Campaign not found', null, HttpStatus::HTTP_NOT_FOUND);
        }

        try {
            $scheduled = $this->newsletterService->scheduleCampaign(
                $campaign,
                Carbon::parse($validated['scheduled_at']),
            );
        } catch (\RuntimeException $e) {
            return sendResponse(false, $e->getMessage(), null, HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
        }

        return sendResponse(true, 'Campaign scheduled successfully', $scheduled, HttpStatus::HTTP_OK);
    }

    public function sendCampaign(int $id)
    {
        $campaign = $this->newsletterService->getCampaign($id);

        if (!$campaign) {
            return sendResponse(false, 'Campaign not found', null, HttpStatus::HTTP_NOT_FOUND);
        }

        try {
            $dispatched = $this->newsletterService->dispatchCampaign($campaign);
        } catch (\RuntimeException $e) {
            return sendResponse(false, $e->getMessage(), null, HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
        }

        return sendResponse(true, 'Campaign dispatch started', $dispatched, HttpStatus::HTTP_OK);
    }

    public function deleteSubscriber(int $id)
    {
        if (!$this->newsletterService->deleteSubscriber($id)) {
            return sendResponse(false, 'Subscriber not found', null, HttpStatus::HTTP_NOT_FOUND);
        }

        return sendResponse(true, 'Subscriber removed successfully', null, HttpStatus::HTTP_OK);
    }

    public function categories()
    {
        return sendResponse(
            true,
            'Newsletter categories retrieved successfully',
            $this->newsletterService->preferenceCategories(),
            HttpStatus::HTTP_OK,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCampaign(Request $request, bool $requireAll = true): array
    {
        $rules = [
            'title' => [$requireAll ? 'required' : 'sometimes', 'string', 'max:255'],
            'subject' => [$requireAll ? 'required' : 'sometimes', 'string', 'max:255'],
            'preview_text' => ['nullable', 'string', 'max:255'],
            'content_html' => [$requireAll ? 'required' : 'sometimes', 'string'],
            'status' => ['nullable', 'in:draft,scheduled,sent,sending'],
            'scheduled_at' => ['nullable', 'date'],
            'segments' => ['nullable', 'array'],
            'category_slugs' => ['nullable', 'array'],
            'audience_type' => ['nullable', 'string', 'max:30'],
            'premium_only' => ['nullable', 'boolean'],
        ];

        return $request->validate($rules);
    }
}
