<?php

namespace App\Http\Requests\Clinic;

use App\Models\ClinicTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClinicTaskRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $statusMap = [
            'To Do' => 'todo',
            'In Progress' => 'in_progress',
            'Done' => 'done',
        ];

        $payload = [];
        if ($this->has('task_title') && ! $this->has('title')) {
            $payload['title'] = $this->input('task_title');
        }
        if ($this->has('assignee_id') && ! $this->has('assign_to_user_id')) {
            $payload['assign_to_user_id'] = $this->input('assignee_id');
            $payload['assign_to_doctor_id'] = null;
        }
        if ($this->has('status')) {
            $payload['status'] = $statusMap[$this->input('status')] ?? $this->input('status');
        }

        $this->merge($payload);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'task_title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'assign_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assign_to_doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'priority' => ['sometimes', 'required', Rule::in(ClinicTask::PRIORITIES)],
            'status' => ['sometimes', 'required', Rule::in(ClinicTask::STATUSES)],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
