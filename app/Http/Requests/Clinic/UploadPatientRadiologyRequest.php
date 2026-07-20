<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class UploadPatientRadiologyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modality' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
            'file' => ['nullable', 'file', 'max:10240'],
            'before_image' => ['nullable', 'file', 'image', 'max:10240'],
            'after_image' => ['nullable', 'file', 'image', 'max:10240'],
        ];
    }
}
