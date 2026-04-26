<?php

namespace App\Http\Requests\Clinic\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'working_hours_from' => ['nullable', 'date_format:H:i'],
            'working_hours_to' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string'],
            'rooms_count' => ['required', 'integer', 'min:1', 'max:10'],
            'status' => ['nullable', Rule::in(['Active', 'Inactive'])],
        ];
    }
}
