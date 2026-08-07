<?php

namespace App\Http\Requests\Clinic;

use App\Models\ClinicAppointment;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = auth()->user()?->clinic_id;
        $appointmentId = $this->route('id');

        return [
            // Appointment Fields
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'patient_name' => ['nullable', 'string', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:50'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'service_name' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
            'appointment_at' => ['nullable', 'date'],
            'duration' => ['nullable', 'integer', 'min:5', 'max:480'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('clinic_id', $clinicId))],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'branch' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:pending,scheduled,confirmed,arrived,attended,completed,cancelled'],
            'notes' => ['nullable', 'string'],

            // Payment & Insurance Fields
            'payment_type' => ['nullable', Rule::in(['cash', 'insurance', 'none'])],
            'insurance_company_id' => ['nullable', 'integer', 'exists:insurance_companies,id'],
            'policy_number' => ['nullable', 'string', 'max:255'],
            'authorization_code' => ['nullable', 'string', 'max:255'],
            'approval_date' => ['nullable', 'date_format:Y-m-d'],
            'coverage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // دمج date و time في appointment_at إذا لم تكن موجودة
        if (! $this->filled('appointment_at') && $this->filled('date') && $this->filled('time')) {
            $this->merge(['appointment_at' => $this->input('date') . ' ' . $this->input('time')]);
        }

        // تحويل الحقول المسطحة إلى صيغة الخدمة
        if ($this->filled('insurance_company_id') || $this->filled('policy_number')) {
            $this->merge([
                'insurance_approval' => [
                    'insurance_company_id' => $this->input('insurance_company_id'),
                    'policy_number' => $this->input('policy_number'),
                    'authorization_code' => $this->input('authorization_code'),
                    'approval_date' => $this->input('approval_date'),
                    'coverage' => $this->input('coverage'),
                    'approved_amount' => $this->input('approved_amount'),
                    'attachment' => $this->file('attachment'),
                ],
            ]);
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
        $appointmentId = $this->route('id');

        $query = ClinicAppointment::query()
            ->where('room_id', $roomId)
            ->whereNotIn('status', ['cancelled'])
            ->where('id', '!=', $appointmentId)
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

        if ($query->exists()) {
            $validator->errors()->add('room_id', 'This room is already booked for the selected date and time.');
        }
    }
}
