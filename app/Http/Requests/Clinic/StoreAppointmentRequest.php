<?php

namespace App\Http\Requests\Clinic;

use App\Models\ClinicAppointment;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = auth()->user()?->clinic_id;

        return [
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'patient_name' => ['required_without:patient_id', 'nullable', 'string', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:50'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'service_name' => ['required_without:service_id', 'nullable', 'string', 'max:255'],
            'appointment_at' => ['required_without_all:date,time', 'nullable', 'date'],
            'date' => ['required_without:appointment_at', 'nullable', 'date_format:Y-m-d'],
            'time' => ['required_without:appointment_at', 'nullable', 'date_format:H:i'],
            'duration' => ['nullable', 'integer', 'min:5', 'max:480'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'branch' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('clinic_id', $clinicId))],
            'room' => ['nullable', 'string', 'max:255'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'payment_type' => ['nullable', Rule::in(['cash', 'insurance', 'none'])],
            'status' => ['nullable', 'in:pending,scheduled,confirmed,arrived,attended,completed,cancelled'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('appointment_at') && $this->filled('date') && $this->filled('time')) {
            $this->merge(['appointment_at' => $this->input('date') . ' ' . $this->input('time')]);
        }

        if ($this->input('payment_type') === 'no_payment_type') {
            $this->merge(['payment_type' => 'none']);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $this->checkRoomAvailability($v);
        });
    }

    protected function checkRoomAvailability(Validator $validator, ?int $excludeId = null): void
    {
        $roomId = $this->input('room_id');
        $appointmentAt = $this->input('appointment_at');

        if (! $roomId || ! $appointmentAt) {
            return;
        }

        $duration = $this->input('duration_minutes') ?? $this->input('duration') ?? 30;

        $start = Carbon::parse($appointmentAt);
        $end = $start->copy()->addMinutes((int) $duration);
        $doctorId = $this->input('doctor_id');

        $query = ClinicAppointment::query()
            ->where('room_id', $roomId)
            ->whereNotIn('status', ['cancelled'])
            ->when($this->input('branch_id'), fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($this->input('branch'), fn ($query, $branch) => $query->where('branch', $branch))
            ->where('appointment_at', '<', $end)
            ->whereRaw(
                'DATE_ADD(appointment_at, INTERVAL COALESCE(duration_minutes, duration, 30) MINUTE) > ?',
                [$start]
            )
            ->where(function ($query) use ($doctorId) {
                if ($doctorId) {
                    $query->whereNull('doctor_user_id')->orWhere('doctor_user_id', $doctorId);
                    return;
                }

                $query->whereNull('doctor_user_id');
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            $validator->errors()->add('room_id', 'This room is already booked for the selected date and time.');
        }
    }
}
