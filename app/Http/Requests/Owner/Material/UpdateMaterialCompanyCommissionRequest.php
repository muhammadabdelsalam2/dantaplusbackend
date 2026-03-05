<?php

namespace App\Http\Requests\Owner\Material;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaterialCompanyCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('commissionPercentage')) {
            $this->merge([
                'commission_percentage' => $this->input('commissionPercentage'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
