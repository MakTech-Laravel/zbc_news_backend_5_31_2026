<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\BreakingNewsStatus;
use App\Http\Requests\Concerns\NormalizesDatetimeInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BreakingNewsItemRequest extends FormRequest
{
    use NormalizesDatetimeInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->filled('starts_at')) {
            $merge['starts_at'] = $this->normalizeDatetimeInput((string) $this->input('starts_at'));
        }

        if ($this->filled('expires_at')) {
            $merge['expires_at'] = $this->normalizeDatetimeInput((string) $this->input('expires_at'));
        }

        if ($this->has('headline_override') && $this->input('headline_override') === '') {
            $merge['headline_override'] = null;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'article_id' => [
                Rule::requiredIf($this->isMethod('POST')),
                'integer',
                'exists:articles,id',
            ],
            'enabled' => ['sometimes', 'boolean'],
            'headline_override' => ['nullable', 'string', 'max:255'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'status' => ['sometimes', 'string', Rule::in([
                BreakingNewsStatus::ACTIVE->value,
                BreakingNewsStatus::PAUSED->value,
            ])],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => [
                'nullable',
                'date',
                Rule::when(
                    $this->filled('starts_at'),
                    ['after:starts_at'],
                ),
            ],
        ];
    }
}
