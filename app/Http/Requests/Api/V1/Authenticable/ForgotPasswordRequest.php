<?php

namespace App\Http\Requests\Api\V1\Authenticable;

use App\Rules\TurnstileToken;
use App\Services\TurnstileService;
use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'email' => ['required', 'email'],
        ];

        if (app(TurnstileService::class)->isEnabled()) {
            $rules['captcha_token'] = ['required', 'string', new TurnstileToken];
        }

        return $rules;
    }
}
