<?php

namespace App\Http\Requests\Owner\Material;

use App\Models\MaterialCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialCompanyStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([MaterialCompany::STATUS_ACTIVE, MaterialCompany::STATUS_INACTIVE])],
        ];
    }
}

