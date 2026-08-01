<?php

namespace App\Http\Requests\Clinic;

use App\Models\ClinicTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClinicTaskRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $statusMap = [
            'To Do' => 'todo',
            'In Progress' => 'in_progress',
            'Done' => 'done',
        ];

        $this->merge(array_filter([
            'title' => $this->input('title', $this->input('task_title')),
            'assign_to_user_id' => $this->input('assign_to_user_id', $this->input('assignee_id')),
            'status' => $statusMap[$this->input('status')] ?? $this->input('status'),
        ], static fn ($value) => $value !== null));
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required_without:task_title', 'string', 'max:255'],
            'task_title' => ['required_without:title', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'assign_to_user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:assign_to_doctor_id'],
            'assign_to_doctor_id' => ['nullable', 'integer', 'exists:doctors,id', 'required_without:assign_to_user_id'],
            'priority' => ['required', Rule::in(ClinicTask::PRIORITIES)],
            'status' => ['nullable', Rule::in(ClinicTask::STATUSES)],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
