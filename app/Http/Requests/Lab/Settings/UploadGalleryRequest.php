<?php

namespace App\Http\Requests\Lab\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UploadGalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->hasFile('images') && !is_array($this->file('images'))) {
            $this->files->set('images', [$this->file('images')]);
        }
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:before,after'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            'images' => ['required', 'array', 'min:1'],
            'images.*' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'images.required' => 'يجب رفع صورة واحدة على الأقل.',
            'images.array' => 'يجب إرسال الصور كمصفوفة.',
            'images.*.file' => 'الملف المرفوع غير صالح.',
            'images.*.image' => 'يجب أن يكون الملف صورة.',
            'images.*.mimes' => 'الصيغ المسموحة هي JPG وJPEG وPNG وWEBP.',
            'images.*.max' => 'حجم الصورة يجب ألا يتجاوز 10 ميجابايت.',
        ];
    }
}
