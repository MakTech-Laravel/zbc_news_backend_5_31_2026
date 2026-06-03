<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\DurationType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class MembershipPlanRequest extends FormRequest
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
            'title'         => ['required', 'string', 'max:255'],
            'sub_title'     => ['required', 'string', 'max:255'],
            'price'         => ['required', 'numeric', 'min:0'],
            'duration'      => ['required', 'integer', 'min:1'],
            'duration_type' => ['required', new Enum(DurationType::class)],
            'status'        => ['sometimes', 'in:active,inactive'],
            'featured'      => ['required', 'array'],
            'featured.*'    => ['string'],
        ];
    }
}
