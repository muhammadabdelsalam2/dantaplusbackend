<?php

namespace App\Http\Requests\Lab\DeliveryRep;

use App\Models\LabDeliveryRep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexDeliveryRepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([
                LabDeliveryRep::STATUS_ACTIVE,
                LabDeliveryRep::STATUS_INACTIVE,
            ])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
