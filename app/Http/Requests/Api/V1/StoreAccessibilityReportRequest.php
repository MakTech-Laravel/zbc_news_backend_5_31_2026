<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccessibilityReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'issue' => ['required', 'string', 'min:5', 'max:10000'],
            'page_url' => ['nullable', 'url', 'max:2048'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
