<?php

namespace App\Http\Requests\Lab;

use App\Models\DentalLab;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDentalLabStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:dental_labs,id'],
            'status' => ['required', Rule::in([DentalLab::STATUS_ACTIVE, DentalLab::STATUS_INACTIVE])],
        ];
    }
}
