<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTermsOfServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hero_meta' => ['required', 'string', 'max:500'],
            'quick_summary' => ['required', 'string', 'max:100000'],
            'account_terms' => ['required', 'string', 'max:100000'],
            'content_ip' => ['required', 'string', 'max:100000'],
            'subscriptions' => ['required', 'string', 'max:100000'],
            'prohibited' => ['required', 'string', 'max:100000'],
            'disputes' => ['required', 'string', 'max:100000'],
            'contact' => ['required', 'string', 'max:100000'],
        ];
    }
}
