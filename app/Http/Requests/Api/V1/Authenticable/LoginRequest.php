<?php

namespace App\Http\Requests\Api\V1\Authenticable;

use App\Rules\TurnstileToken;
use App\Services\TurnstileService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'email' => 'required|email',
            'password' => 'required',
        ];

        if (app(TurnstileService::class)->isEnabled()) {
            $rules['captcha_token'] = ['required', 'string', new TurnstileToken];
        }

        return $rules;
    }
}
