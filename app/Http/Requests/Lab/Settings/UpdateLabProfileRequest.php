<?php

namespace App\Http\Requests\Lab\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLabProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lab_name' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'working_hours' => ['nullable', 'string', 'max:100'],
            'logo_url' => ['nullable', 'file','image','mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }
}
