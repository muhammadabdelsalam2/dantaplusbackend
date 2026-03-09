<?php

namespace App\Http\Requests\Owner\Maintenance;

use Illuminate\Foundation\Http\FormRequest;

class ReviewMaintenanceAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
