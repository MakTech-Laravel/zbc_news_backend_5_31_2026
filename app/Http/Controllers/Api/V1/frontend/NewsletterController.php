<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Services\Newsletter\NewsletterService;
use App\Services\Newsletter\NewsletterTrackingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class NewsletterController extends Controller
{
    public function __construct(
        private readonly NewsletterService $newsletterService,
        private readonly NewsletterTrackingService $trackingService,
    ) {}

    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'preferences' => ['nullable'],
            'source' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $subscriber = $this->newsletterService->subscribe(
                array_merge($validated, ['source' => $validated['source'] ?? 'website']),
                $request->user(),
            );
        } catch (QueryException $exception) {
            report($exception);

            return sendResponse(
                false,
                'Newsletter storage is not ready. Please run database migrations on the server.',
                null,
                HttpStatus::HTTP_INTERNAL_SERVER_ERROR,
            );
        } catch (\Throwable $exception) {
            report($exception);

            return sendResponse(
                false,
                'Unable to process subscription right now.',
                null,
                HttpStatus::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return sendResponse(
            true,
            'Subscription received. Please verify your email.',
            ['email' => $subscriber->email],
            HttpStatus::HTTP_CREATED,
        );
    }

    public function verify(Request $request)
    {
        $subscriber = $this->newsletterService->verify((string) $request->query('token'));

        if (!$subscriber) {
            return sendResponse(false, 'Invalid verification token', null, HttpStatus::HTTP_NOT_FOUND);
        }

        return sendResponse(true, 'Newsletter subscription verified', [
            'email' => $subscriber->email,
        ], HttpStatus::HTTP_OK);
    }

    public function unsubscribe(Request $request)
    {
        $subscriber = $this->newsletterService->unsubscribe((string) $request->query('token'));

        if (!$subscriber) {
            return sendResponse(false, 'Invalid unsubscribe token', null, HttpStatus::HTTP_NOT_FOUND);
        }

        return sendResponse(true, 'You have been unsubscribed', [
            'email' => $subscriber->email,
        ], HttpStatus::HTTP_OK);
    }

    public function preferences(Request $request)
    {
        $token = trim((string) $request->query('token'));
        $subscriber = $this->newsletterService->getPreferencesByToken($token);

        if (!$subscriber) {
            return sendResponse(false, 'Invalid or expired preferences link', null, HttpStatus::HTTP_NOT_FOUND);
        }

        return sendResponse(true, 'Newsletter preferences retrieved', [
            'email' => $subscriber->email,
            'name' => $subscriber->name,
            'preferences' => $subscriber->preferences,
            'categories' => $this->newsletterService->preferenceCategories(),
        ], HttpStatus::HTTP_OK);
    }

    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'preferences' => ['required'],
        ]);

        $subscriber = $this->newsletterService->updatePreferences(
            $validated['token'],
            is_array($validated['preferences']) ? $validated['preferences'] : ['categories' => $validated['preferences']],
        );

        if (!$subscriber) {
            return sendResponse(false, 'Invalid or expired preferences link', null, HttpStatus::HTTP_NOT_FOUND);
        }

        return sendResponse(true, 'Preferences updated successfully', [
            'preferences' => $subscriber->preferences,
        ], HttpStatus::HTTP_OK);
    }

    public function categories()
    {
        return sendResponse(
            true,
            'Newsletter categories retrieved',
            $this->newsletterService->preferenceCategories(),
            HttpStatus::HTTP_OK,
        );
    }

    public function trackOpen(int $campaignId, int $subscriberId, string $signature)
    {
        if (!$this->trackingService->verify($campaignId, $subscriberId, $signature)) {
            return response('Invalid signature', HttpStatus::HTTP_FORBIDDEN);
        }

        $campaign = NewsletterCampaign::query()->find($campaignId);
        $subscriber = NewsletterSubscriber::query()->find($subscriberId);

        if ($campaign && $subscriber) {
            $this->trackingService->recordOpen($campaign, $subscriber);
        }

        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel, HttpStatus::HTTP_OK, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function trackClick(Request $request, int $campaignId, int $subscriberId, string $signature)
    {
        $url = (string) $request->query('url', '/');

        if (!$this->trackingService->verify($campaignId, $subscriberId, $signature)) {
            return redirect()->away($url);
        }

        $campaign = NewsletterCampaign::query()->find($campaignId);
        $subscriber = NewsletterSubscriber::query()->find($subscriberId);

        if ($campaign && $subscriber) {
            $this->trackingService->recordClick($campaign, $subscriber, $url);
        }

        return redirect()->away($url);
    }
}
