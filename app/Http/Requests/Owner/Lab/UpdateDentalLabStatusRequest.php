<?php

namespace App\Http\Requests\Owner\Lab;

use App\Models\DentalLab;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDentalLabStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([DentalLab::STATUS_ACTIVE, DentalLab::STATUS_INACTIVE])],
        ];
    }
}
