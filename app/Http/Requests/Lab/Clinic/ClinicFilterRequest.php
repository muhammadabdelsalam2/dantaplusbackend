<?php

namespace App\Http\Requests\Lab\Clinic;

use App\Enums\PartnershipStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClinicFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(PartnershipStatus::class)],
            'type' => ['nullable', Rule::in(['internal', 'external'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $status = $this->input('status');
        $type = $this->input('type');

        if (in_array($status, ['All Statuses', 'all', 'All', ''], true)) {
            $this->merge(['status' => null]);
        }

        if (in_array($type, ['All Types', 'all', 'All', ''], true)) {
            $this->merge(['type' => null]);
        } elseif (is_string($type)) {
            $this->merge(['type' => strtolower($type)]);
        }
    }
}
