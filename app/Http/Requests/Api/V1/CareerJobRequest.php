<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\CareerEmploymentType;
use App\Enums\CareerJobDepartment;
use App\Enums\CareerJobStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CareerJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('type') && ! $this->has('employment_type')) {
            $this->merge(['employment_type' => $this->input('type')]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190'],
            'department' => ['required', 'string', Rule::in(CareerJobDepartment::values())],
            'employment_type' => ['required', 'string', Rule::in(CareerEmploymentType::values())],
            'location' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:20000'],
            'status' => ['required', 'string', Rule::in(CareerJobStatus::values())],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
