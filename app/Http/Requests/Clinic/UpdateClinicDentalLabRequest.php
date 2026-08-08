<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClinicDentalLabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'avg_delivery_days' => ['nullable', 'numeric', 'min:0'],
            'response_speed' => ['nullable', 'string', 'max:50'],
            'working_hours' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],

            'before_images' => ['nullable', 'array'],
            'before_images.*' => ['file', 'image', 'max:5120'],
            'after_images' => ['nullable', 'array'],
            'after_images.*' => ['file', 'image', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'avg_delivery_days' => $this->input('avg_delivery_days', $this->input('avg_delivery_time')),
        ]);
    }
}
