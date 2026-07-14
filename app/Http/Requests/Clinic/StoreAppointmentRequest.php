<?php

namespace App\Http\Requests\Clinic;

use App\Models\ClinicAppointment;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'patient_name' => ['required_without:patient_id', 'nullable', 'string', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:50'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_name' => ['required', 'string', 'max:255'],
            'appointment_at' => ['required', 'date'],
            'duration' => ['nullable', 'integer', 'min:5', 'max:480'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'branch' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'payment_type' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:scheduled,confirmed,arrived,attended,completed,cancelled'],
            'notes' => ['nullable', 'string'],
        ];
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

        $query = ClinicAppointment::query()
            ->where('room_id', $roomId)
            ->whereNotIn('status', ['cancelled'])
            ->where('appointment_at', '<', $end)
            ->whereRaw(
                'DATE_ADD(appointment_at, INTERVAL COALESCE(duration_minutes, duration, 30) MINUTE) > ?',
                [$start]
            );

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            $validator->errors()->add('room_id', 'This room is already booked for the selected date and time.');
        }
    }
}
