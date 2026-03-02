<?php

namespace App\Http\Requests\Owner\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClinicStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:Active,Suspended'],
        ];
    }
}
