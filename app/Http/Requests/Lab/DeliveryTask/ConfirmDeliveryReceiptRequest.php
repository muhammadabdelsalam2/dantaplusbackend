<?php

namespace App\Http\Requests\Lab\DeliveryTask;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmDeliveryReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller
    }

    public function rules(): array
    {
        return [
            'proof_file' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx'],
            'trip_expense' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
