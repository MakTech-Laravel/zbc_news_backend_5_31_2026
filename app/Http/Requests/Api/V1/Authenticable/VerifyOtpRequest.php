<?php

namespace App\Http\Requests\Api\V1\Authenticable;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'size:6'],
            'code' => ['sometimes', 'string', 'size:6'],
            'verification_code' => ['sometimes', 'string', 'size:6'],
        ];
    }

    public function otpCode(): string
    {
        return (string) ($this->input('otp')
            ?: $this->input('code')
            ?: $this->input('verification_code'));
    }
}
