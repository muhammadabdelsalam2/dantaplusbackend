<?php

namespace App\Http\Requests\Lab\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_name' => ['required', 'string', 'min:2', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'turnaround_time_days' => ['required', 'integer', 'min:1', 'max:365'],
        ];
    }
}
