<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('patient') === true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
