<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreArticleCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:article_comments,id'],
            'guest_name' => ['nullable', 'string', 'max:120'],
            'guest_email' => ['nullable', 'email', 'max:190'],
        ];
    }
}
