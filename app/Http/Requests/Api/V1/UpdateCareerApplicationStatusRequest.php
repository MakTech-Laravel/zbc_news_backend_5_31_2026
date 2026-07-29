<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\CareerApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCareerApplicationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(CareerApplicationStatus::filterable())],
        ];
    }
}
