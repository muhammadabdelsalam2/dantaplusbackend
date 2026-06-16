<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientAppointmentRatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('patient') === true;
    }

    public function rules(): array
    {
        return [
            'doctor_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'clinic_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
