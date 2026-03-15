<?php

namespace App\Http\Requests\Lab;

use App\Models\CaseModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(CaseModel::STATUSES)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
