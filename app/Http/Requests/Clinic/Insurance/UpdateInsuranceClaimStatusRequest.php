<?php

namespace App\Http\Requests\Clinic\Insurance;

use App\Models\Clinic\Insurance\InsuranceClaim;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInsuranceClaimStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_merge(InsuranceClaim::statuses(), ['under_review']))],
            'approved_amount' => ['required_if:status,' . InsuranceClaim::STATUS_APPROVED_WITH_LIMIT, 'nullable', 'numeric', 'min:0'],
        ];
    }
}
