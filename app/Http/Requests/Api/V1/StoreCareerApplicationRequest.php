<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreCareerApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'career_job_id' => ['required', 'integer', 'exists:career_jobs,id'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'cover_letter' => ['nullable', 'string', 'max:10000'],
            'resume' => [
                'required',
                'file',
                'mimes:pdf,doc,docx',
                'max:20480',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'career_job_id' => 'job',
            'resume' => 'resume / CV',
        ];
    }
}
