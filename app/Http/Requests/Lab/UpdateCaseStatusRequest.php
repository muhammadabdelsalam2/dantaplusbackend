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
            'assigned_technician_id' => [
                'nullable',
                'integer',
                \Illuminate\Validation\Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('lab_id', auth()->user()?->lab_id);
                }),
            ],
            'technician_id' => [
                'nullable',
                'integer',
                \Illuminate\Validation\Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('lab_id', auth()->user()?->lab_id);
                }),
            ],
            'delivery_rep_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'generate_invoice' => ['sometimes', 'boolean'],
            'assign_for_delivery' => ['sometimes', 'boolean'],
            'scheduled_for' => ['nullable', 'date'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'pickup_notes' => ['nullable', 'string', 'max:1000'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->isMethod('PATCH') && ! $this->filled('status')) {
            $this->merge($this->parseMultipartPatchFields());
        }

        if (! $this->filled('assigned_technician_id') && $this->filled('technician_id')) {
            $this->merge(['assigned_technician_id' => $this->input('technician_id')]);
        }
    }

    private function parseMultipartPatchFields(): array
    {
        $contentType = (string) $this->headers->get('content-type');
        if (! str_contains($contentType, 'multipart/form-data') || ! preg_match('/boundary=(.*)$/', $contentType, $matches)) {
            return [];
        }

        $boundary = '--' . trim($matches[1], '"');
        $fields = [];

        foreach (explode($boundary, $this->getContent()) as $part) {
            if (! str_contains($part, 'Content-Disposition: form-data;')) {
                continue;
            }

            if (! preg_match('/name="([^"]+)"/', $part, $nameMatch)) {
                continue;
            }

            $segments = preg_split("/\r\n\r\n/", $part, 2);
            if (count($segments) !== 2) {
                continue;
            }

            $fields[$nameMatch[1]] = trim($segments[1], "\r\n-");
        }

        return $fields;
    }
}
