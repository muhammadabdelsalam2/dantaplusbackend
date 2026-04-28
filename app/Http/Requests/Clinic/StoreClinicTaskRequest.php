<?php

namespace App\Http\Requests\Clinic;

use App\Models\ClinicTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClinicTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assign_to_user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:assign_to_doctor_id'],
            'assign_to_doctor_id' => ['nullable', 'integer', 'exists:doctors,id', 'required_without:assign_to_user_id'],
            'priority' => ['required', Rule::in(ClinicTask::PRIORITIES)],
            'status' => ['nullable', Rule::in(ClinicTask::STATUSES)],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
