<?php

namespace App\Http\Requests\Lab\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class InviteClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
