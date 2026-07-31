<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileService
{
    public function isEnabled(): bool
    {
        return filled(config('services.turnstile.secret'));
    }

    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        if (! is_string($token) || trim($token) === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(8)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => config('services.turnstile.secret'),
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]);

            if (! $response->ok()) {
                Log::warning('Turnstile verification HTTP failure', [
                    'status' => $response->status(),
                ]);

                return false;
            }

            return (bool) $response->json('success');
        } catch (\Throwable $exception) {
            Log::error('Turnstile verification failed', [
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
