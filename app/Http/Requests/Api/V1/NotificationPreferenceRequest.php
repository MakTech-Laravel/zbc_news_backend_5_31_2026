<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class NotificationPreferenceRequest extends FormRequest
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
        return [
            'breaking_news'                => ['required', 'boolean'],
            'daily_newsletter'             => ['required', 'boolean'],
            'personalized_recommendations' => ['required', 'boolean'],
            'comment_replies'              => ['required', 'boolean'],
            'saved_article_updates'        => ['required', 'boolean'],
        ];
    }
}
