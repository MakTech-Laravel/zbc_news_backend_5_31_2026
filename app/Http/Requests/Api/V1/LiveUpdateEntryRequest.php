<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\LiveUpdateStatus;
use App\Http\Requests\Concerns\NormalizesDatetimeInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class LiveUpdateEntryRequest extends FormRequest
{
    use NormalizesDatetimeInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('posted_at')) {
            $this->merge([
                'posted_at' => $this->normalizeDatetimeInput((string) $this->input('posted_at')),
            ]);
        }
    }

    public function rules(): array
    {
        $isUpdate = $this->route('id') !== null;

        return [
            'body' => [$isUpdate ? 'sometimes' : 'required', 'string'],
            'posted_at' => ['nullable', 'date'],
            'status' => ['sometimes', new Enum(LiveUpdateStatus::class)],
        ];
    }
}
