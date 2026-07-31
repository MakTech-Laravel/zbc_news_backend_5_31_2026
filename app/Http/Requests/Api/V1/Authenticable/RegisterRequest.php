<?php

namespace App\Http\Requests\Api\V1\Authenticable;

use App\Rules\TurnstileToken;
use App\Services\TurnstileService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['sometimes', 'string', 'max:255'],
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'string', 'max:120'],
            // Unique check is handled in the controller with a generic response
            // so we do not reveal whether the email is already registered.
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
            'accepted_terms' => ['required', 'accepted'],
        ];

        if (app(TurnstileService::class)->isEnabled()) {
            $rules['captcha_token'] = ['required', 'string', new TurnstileToken];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'accepted_terms.required' => 'You must accept the Terms of Service and Privacy Policy.',
            'accepted_terms.accepted' => 'You must accept the Terms of Service and Privacy Policy.',
        ];
    }

    public function resolvedName(): string
    {
        if ($this->filled('name')) {
            return trim((string) $this->input('name'));
        }

        $first = trim((string) $this->input('first_name', ''));
        $last = trim((string) $this->input('last_name', ''));

        return trim("{$first} {$last}") ?: 'User';
    }
}
