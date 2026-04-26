<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServicePricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('category') && ! $this->has('category_name')) {
            $this->merge(['category_name' => $this->input('category')]);
        }

        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    ?? $this->input('is_active'),
            ]);
        }

        if ($this->has('has_lab')) {
            $this->merge([
                'has_lab' => filter_var($this->input('has_lab'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    ?? $this->input('has_lab'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'category_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lab_cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'has_lab' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
