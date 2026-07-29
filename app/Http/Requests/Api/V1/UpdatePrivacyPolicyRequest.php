<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrivacyPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hero_meta' => ['required', 'string', 'max:500'],
            'plain_summary' => ['required', 'string', 'max:100000'],
            'overview' => ['required', 'string', 'max:100000'],
            'data_we_collect' => ['required', 'string', 'max:100000'],
            'how_we_use' => ['required', 'string', 'max:100000'],
            'your_rights' => ['required', 'string', 'max:100000'],
            'data_security' => ['required', 'string', 'max:100000'],
            'third_parties' => ['required', 'string', 'max:100000'],
            'contact' => ['required', 'string', 'max:100000'],
        ];
    }
}
