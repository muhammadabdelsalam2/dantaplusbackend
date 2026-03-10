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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->has('status') ? strtolower($this->status) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                MaterialProduct::STATUS_ACTIVE,
                MaterialProduct::STATUS_INACTIVE
            ])],
        ];
    }
}
