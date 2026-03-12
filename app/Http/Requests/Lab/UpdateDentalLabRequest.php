<?php

namespace App\Http\Requests\Owner\Lab;

use App\Models\DentalLab;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDentalLabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $labId = (int) $this->route('lab');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('dental_labs', 'email')->ignore($labId)],
            'working_hours' => ['nullable', 'string', 'max:255'],
            'avg_delivery_days' => ['sometimes', 'numeric', 'min:0'],
            'response_speed' => ['nullable', Rule::in([
                DentalLab::RESPONSE_SPEED_FAST,
                DentalLab::RESPONSE_SPEED_MEDIUM,
                DentalLab::RESPONSE_SPEED_SLOW,
            ])],
            'status' => ['sometimes', Rule::in([DentalLab::STATUS_ACTIVE, DentalLab::STATUS_INACTIVE])],
            'logo' => ['nullable', 'image', 'max:5120'],
            'services' => ['nullable', 'array'],
            'services.*.name' => ['required_with:services', 'string', 'max:255'],
            'services.*.price' => ['nullable', 'numeric', 'min:0'],
            'services.*.turnaround_days' => ['nullable', 'integer', 'min:0'],
            'is_external' => ['nullable', 'boolean'],
            'date_added' => ['nullable', 'date'],
            'rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'on_time_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rejection_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
