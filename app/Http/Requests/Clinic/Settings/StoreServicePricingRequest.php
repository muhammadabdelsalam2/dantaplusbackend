<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServicePricingRequest extends FormRequest
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

        if ($this->has('service_name') && ! $this->has('name')) {
            $this->merge(['name' => $this->input('service_name')]);
        }

        if ($this->has('selling_price') && ! $this->has('price')) {
            $this->merge(['price' => $this->input('selling_price')]);
        }

        if ($this->has('service_cost') && ! $this->has('cost')) {
            $this->merge(['cost' => $this->input('service_cost')]);
        }

        if ($this->has('requires_dental_lab') && ! $this->has('has_lab')) {
            $this->merge(['has_lab' => $this->input('requires_dental_lab')]);
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
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'name' => ['required_without:service_id', 'nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'category_name' => ['required_without_all:service_id,category_id', 'nullable', 'string', 'max:255', Rule::in(['General', 'Orthodontics', 'Surgery', 'Restorative', 'Pediatric', 'Hygiene'])],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'lab_cost' => ['nullable', 'required_if:has_lab,true', 'numeric', 'min:0'],
            'has_lab' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
