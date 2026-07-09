<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class StoreClinicCaseAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:stl,jpg,jpeg,png,pdf', 'max:10240'],
            'attachment_type' => ['nullable', 'string', 'max:100'],
        ];
    }
}
