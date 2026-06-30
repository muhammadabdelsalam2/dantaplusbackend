<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;

class AssignTechnicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_technician_id' => [
                'required',
                'integer',
                \Illuminate\Validation\Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('lab_id', auth()->user()?->lab_id);
                }),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
