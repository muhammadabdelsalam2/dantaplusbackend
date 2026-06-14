<?php

namespace App\Http\Requests\Clinic;

use App\Models\ClinicTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexClinicTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
         
            'search'      => ['nullable', 'string', 'max:255'],

            'priority'    => ['nullable', Rule::in(ClinicTask::PRIORITIES)],

            'status'      => ['nullable', Rule::in(ClinicTask::STATUSES)],
            'assignee_id' => ['nullable', 'integer'],
            'date_from'   => ['nullable', 'date'],
            'date_to'     => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
