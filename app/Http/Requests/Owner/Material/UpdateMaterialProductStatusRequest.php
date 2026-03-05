<?php

namespace App\Http\Requests\Owner\Material;

use App\Models\MaterialProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialProductStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([MaterialProduct::STATUS_ACTIVE, MaterialProduct::STATUS_INACTIVE])],
        ];
    }
}

