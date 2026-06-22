<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TrackArticleRequest extends FormRequest
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
            'user_id'      => ['nullable', 'integer', 'exists:users,id'],
            'article_id'   => ['required', 'integer', 'exists:articles,id'],
            'session_id'   => ['required', 'string', 'max:255'],
            'time_spent'   => ['required', 'integer', 'min:5'],
            'scroll_depth' => ['required', 'integer', 'between:0,100'],
        ];
    }

    public function messages(): array
    {
        return [
            'article_id.exists'      => 'Article not found.',
            'time_spent.min'         => 'Read time must be at least 5 seconds.',
            'scroll_depth.between'   => 'Scroll depth must be between 0 and 100.',
        ];
    }
}
