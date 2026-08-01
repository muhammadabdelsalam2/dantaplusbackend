<?php

namespace App\Http\Requests\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['required_without_all:text,attachment,voice_note,attachments', 'string'],
            'text' => ['required_without_all:note,attachment,voice_note,attachments', 'string'],
            'attachment' => ['nullable', 'file', 'max:10240'],
            'voice_note' => ['nullable', 'file', 'mimetypes:audio/mpeg,audio/mp4,audio/aac,audio/x-m4a,audio/wav,audio/x-wav,audio/webm,audio/ogg,video/webm', 'max:10240'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
            'mentions' => ['nullable', 'array'],
            'mentions.*' => ['integer', 'exists:users,id'],
        ];
    }
}
