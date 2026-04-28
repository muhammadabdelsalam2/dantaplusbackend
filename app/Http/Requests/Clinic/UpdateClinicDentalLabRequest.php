<?php

namespace App\Http\Requests\Clinic;

class UpdateClinicDentalLabRequest extends StoreClinicDentalLabRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'avg_delivery_days' => ['nullable', 'numeric', 'min:0'],
            'response_speed' => ['nullable', 'string', 'max:50'],
            'working_hours' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
        ];
    }
}
