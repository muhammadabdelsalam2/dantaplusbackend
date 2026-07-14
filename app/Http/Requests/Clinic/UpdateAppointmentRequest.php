<?php

namespace App\Http\Requests\Clinic;

use App\Models\ClinicAppointment;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['sometimes', 'nullable', 'integer', 'exists:patients,id'],
            'patient_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'patient_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'doctor_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'service_name' => ['sometimes', 'string', 'max:255'],
            'appointment_at' => ['sometimes', 'date'],
            'duration' => ['sometimes', 'integer', 'min:5', 'max:480'],
            'duration_minutes' => ['sometimes', 'integer', 'min:5', 'max:480'],
            'branch' => ['sometimes', 'nullable', 'string', 'max:255'],
            'room' => ['sometimes', 'nullable', 'string', 'max:255'],
            'room_id' => ['sometimes', 'nullable', 'integer', 'exists:rooms,id'],
            'payment_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'in:scheduled,confirmed,arrived,attended,completed,cancelled'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $this->checkRoomAvailability($v);
        });
    }

    protected function checkRoomAvailability(Validator $validator): void
    {
        // لو الـ request مبعتش room_id أو appointment_at، ماينفعش نفحص التعارض
        // لأننا مش عارفين هيحصل تغيير في الغرفة/الوقت ولا لأ.
        if (! $this->has('room_id') && ! $this->has('appointment_at')) {
            return;
        }

        $appointment = ClinicAppointment::find($this->route('id'));
        if (! $appointment) {
            return;
        }

        $roomId = $this->input('room_id', $appointment->room_id);
        if (! $roomId) {
            return;
        }

        $appointmentAt = $this->input('appointment_at', $appointment->appointment_at);
        $duration = $this->input('duration_minutes')
            ?? $this->input('duration')
            ?? $appointment->duration_minutes
            ?? $appointment->duration
            ?? 30;

        $start = Carbon::parse($appointmentAt);
        $end = $start->copy()->addMinutes((int) $duration);

        $conflict = ClinicAppointment::query()
            ->where('room_id', $roomId)
            ->where('id', '!=', $appointment->id)
            ->whereNotIn('status', ['cancelled'])
            ->where('appointment_at', '<', $end)
            ->whereRaw(
                'DATE_ADD(appointment_at, INTERVAL COALESCE(duration_minutes, duration, 30) MINUTE) > ?',
                [$start]
            )
            ->exists();

        if ($conflict) {
            $validator->errors()->add('room_id', 'This room is already booked for the selected date and time.');
        }
    }
}
