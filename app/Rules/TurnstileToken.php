<?php

namespace App\Rules;

use App\Services\TurnstileService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TurnstileToken implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $service = app(TurnstileService::class);

        if (! $service->isEnabled()) {
            return;
        }

        if (! $service->verify(is_string($value) ? $value : null, request()->ip())) {
            $fail('Bot verification failed. Please try again.');
        }
    }
}
