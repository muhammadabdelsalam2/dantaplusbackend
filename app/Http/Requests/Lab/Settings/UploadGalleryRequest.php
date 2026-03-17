<?php

namespace App\Http\Requests\Lab\Settings;

use App\Enums\GalleryImageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadGalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(GalleryImageType::class)],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }
}
