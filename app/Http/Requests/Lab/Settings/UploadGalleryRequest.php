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
        
        if ($this->hasFile('files') && !is_array($this->file('files'))) {
            $this->files->set('files', [$this->file('files')]);
        }
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:before,after'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            'files' => ['required', 'array', 'min:1'],
            'files.*' => [
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
            'files.required' => 'يجب رفع صورة واحدة على الأقل.',
            'files.array' => 'يجب إرسال الصور كمصفوفة.',
            'files.*.file' => 'الملف المرفوع غير صالح.',
            'files.*.image' => 'يجب أن يكون الملف صورة.',
            'files.*.mimes' => 'الصيغ المسموحة هي JPG وJPEG وPNG وWEBP.',
            'files.*.max' => 'حجم الصورة يجب ألا يتجاوز 10 ميجابايت.',
        ];
    }
}
