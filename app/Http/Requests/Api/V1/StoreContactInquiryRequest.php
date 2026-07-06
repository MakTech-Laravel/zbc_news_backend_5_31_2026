<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('fullName') && ! $this->has('name')) {
            $this->merge(['name' => $this->input('fullName')]);
        }

        if ($this->has('subscribeNewsletter') && ! $this->has('subscribe_newsletter')) {
            $this->merge(['subscribe_newsletter' => $this->boolean('subscribeNewsletter')]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:190'],
            'message' => ['required', 'string', 'min:5', 'max:10000'],
            'subscribe_newsletter' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'full name',
            'subscribe_newsletter' => 'newsletter subscription',
        ];
    }
}
