<?php

namespace App\Http\Controllers\Api\V1\frontend;

use App\Http\Controllers\Controller;
use App\Mail\NewsletterVerificationMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'preferences' => ['nullable', 'array'],
        ]);

        $email = strtolower(trim((string) $validated['email']));
        $verificationToken = Str::random(64);
        $unsubscribeToken = Str::random(64);

        $subscriber = NewsletterSubscriber::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $validated['name'] ?? null,
                'status' => 'pending',
                'preferences' => $validated['preferences'] ?? null,
                'verification_token' => $verificationToken,
                'unsubscribe_token' => $unsubscribeToken,
                'verified_at' => null,
                'unsubscribed_at' => null,
            ]
        );

        $verifyUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/') .
            '/newsletter/verify?token=' . $verificationToken;

        try {
            Mail::to($subscriber->email)->send(new NewsletterVerificationMail($verifyUrl));
        } catch (\Throwable) {
            // Keep subscription and token even if local mail transport is unavailable.
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
        $token = trim((string) $request->query('token'));

        $subscriber = NewsletterSubscriber::query()
            ->where('verification_token', $token)
            ->first();

        if (!$subscriber) {
            return sendResponse(false, 'Invalid verification token', null, HttpStatus::HTTP_NOT_FOUND);
        }

        $subscriber->update([
            'status' => 'verified',
            'verified_at' => now(),
            'verification_token' => null,
        ]);

        return sendResponse(true, 'Newsletter subscription verified', null, HttpStatus::HTTP_OK);
    }

    public function unsubscribe(Request $request)
    {
        $token = trim((string) $request->query('token'));

        $subscriber = NewsletterSubscriber::query()
            ->where('unsubscribe_token', $token)
            ->first();

        if (!$subscriber) {
            return sendResponse(false, 'Invalid unsubscribe token', null, HttpStatus::HTTP_NOT_FOUND);
        }

        $subscriber->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);

        return sendResponse(true, 'You have been unsubscribed', null, HttpStatus::HTTP_OK);
    }
}

