<?php

namespace App\Http\Requests\Clinic;

use App\Models\ClinicTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClinicTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assign_to_user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:assign_to_doctor_id'],
            'assign_to_doctor_id' => ['nullable', 'integer', 'exists:doctors,id', 'required_without:assign_to_user_id'],
            'priority' => ['sometimes', 'required', Rule::in(ClinicTask::PRIORITIES)],
            'status' => ['sometimes', 'required', Rule::in(ClinicTask::STATUSES)],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
