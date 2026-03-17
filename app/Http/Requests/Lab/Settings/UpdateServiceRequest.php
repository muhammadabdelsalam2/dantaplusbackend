<?php

namespace App\Http\Requests\Lab\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'turnaround_time_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ];
    }
}
