<?php

namespace App\Http\Requests\SuperAdmin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UploadProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'file' => ['sometimes', 'file', 'image', 'max:5120'], // 5MB
            'file_base64' => ['sometimes', 'string'],
            'filename' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
