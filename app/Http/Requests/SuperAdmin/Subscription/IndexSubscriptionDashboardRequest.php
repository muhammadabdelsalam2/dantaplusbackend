<?php

namespace App\Http\Requests\SuperAdmin\Subscription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSubscriptionDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $search = $this->input('search', $this->input('q'));
        $status = $this->input('status');
        $plan = $this->input('plan');
        $perPage = $this->input('per_page');

        $this->merge([
            'search' => is_string($search) ? trim($search) : $search,
            'status' => is_string($status) ? ucfirst(strtolower(trim($status))) : $status,
            'plan' => is_string($plan) ? ucfirst(strtolower(trim($plan))) : $plan,
            'per_page' => $perPage !== null ? (int) $perPage : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['Paid', 'Pending', 'Overdue'])],
            'plan' => ['nullable', Rule::in(['Basic', 'Standard', 'Premium'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
