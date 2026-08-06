<?php

namespace App\Services\Clinic;

use App\Http\Resources\Clinic\AppointmentResource;
use App\Models\CaseAttachment;
use App\Models\CaseModel;
use App\Models\Branch;
use App\Models\ClinicAppointment;
use App\Models\ClinicInvoice;
use App\Models\ClinicInvoiceItem;
use App\Models\ClinicPayment;
use App\Models\ClinicServicePrice;
use App\Models\DentalLab;
use App\Models\Doctor;
use App\Models\LabService;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\ReminderLog;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Models\Clinic\Insurance\InsuranceClaim;
use App\Support\Clinic\BranchFilter;
use App\Support\ServiceResult;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AppointmentService
{
    use BranchFilter;

    private const DEFAULT_WORKING_HOURS_FROM = '09:00';
    private const DEFAULT_WORKING_HOURS_TO = '17:00';
    private const PAYMENT_METHODS = ['Cash', 'Card', 'Bank Transfer', 'Insurance', 'Mixed (Split)'];
    private const PAYMENT_TYPES = ['cash', 'insurance', 'none'];

    public function index(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $view = $filters['view'] ?? 'day';
        $date = Carbon::parse($filters['date'] ?? now()->toDateString());
        [$start, $end] = $this->rangeForView($view, $date, $filters);

        $appointments = $this->baseCalendarQuery($clinicId, $filters)
            ->whereBetween('appointment_at', [$start, $end])
            ->orderBy('appointment_at')
            ->get();

        return ServiceResult::success([
            'view' => $view,
            'date' => $date->toDateString(),
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'label' => $this->rangeLabel($view, $start, $end),
            ],
            'navigation' => [
                'today' => now()->toDateString(),
                'previous_date' => $this->navigationDate($view, $date, -1),
                'next_date' => $this->navigationDate($view, $date, 1),
            ],
            'filters' => [
                'branch_id' => $this->selectedBranchId($filters),
                'branch' => $filters['branch'] ?? null,
                'room_id' => $filters['room_id'] ?? null,
                'room' => $filters['room'] ?? null,
            ],
            'working_hours' => $this->workingHoursForFilters($clinicId, $filters),
            'appointments' => AppointmentResource::collection($appointments)->resolve(),
            'calendar' => match ($view) {
                'month' => $this->monthCalendar($appointments, $start, $end),
                'week' => $this->weekCalendar($appointments, $start),
                default => $this->dayCalendar($appointments, $start),
            },
        ], 'Appointments fetched successfully');
    }

    public function show(int $id): array
    {
        $appointment = $this->findClinicAppointment($id);
        if (! $appointment) {
            return ServiceResult::error('Appointment not found.', null, null, 404);
        }

        return ServiceResult::success((new AppointmentResource($appointment))->resolve(), 'Appointment fetched successfully');
    }

    public function create(array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $normalized = $this->normalizeAppointmentData($clinicId, $data);
        if (! $normalized['success']) {
            return $normalized;
        }

        $payload = $normalized['data'];
        $availability = $this->assertAvailable($clinicId, $payload);
        if (! $availability['success']) {
            return $availability;
        }

        $insuranceApprovalData = $data['insurance_approval'] ?? null;

        $appointment = DB::transaction(function () use ($clinicId, $payload, $insuranceApprovalData) {
            $appointment = ClinicAppointment::query()->create([
                'clinic_id' => $clinicId,
                'patient_id' => $payload['patient']?->id,
                'doctor_user_id' => $payload['doctor']?->id,
                'service_id' => $payload['service']?->id,
                'patient_name' => $payload['patient_name'],
                'patient_phone' => $payload['patient_phone'],
                'service_name' => $payload['service_name'],
                'appointment_at' => $payload['appointment_at'],
                'duration_minutes' => $payload['duration_minutes'],
                'duration' => $payload['duration_minutes'],
                'branch_id' => $payload['branch']?->id,
                'branch' => $payload['branch']?->name ?? $payload['branch_name'],
                'room_id' => $payload['room']?->id,
                'room' => $payload['room']?->name ?? $payload['room_name'],
                'payment_type' => $payload['payment_type'],
                'status' => $payload['status'] ?? 'pending',
                'notes' => $payload['notes'],
            ]);

            if ($payload['payment_type'] === 'insurance' && is_array($insuranceApprovalData) && $payload['patient']) {
                app(PatientService::class)->createApprovalForPatient($payload['patient'], $insuranceApprovalData, $appointment->id);
            }

            $this->sendInitialBookingMessage($appointment);

            return $appointment;
        });

        return $this->show($appointment->id);
    }

    public function quickBook(array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $patient = $this->findOrCreatePatient($clinicId, $data['patient_name'], $data['phone_number'] ?? $data['patient_phone'] ?? null);
        $data['patient_id'] = $patient->id;
        $data['patient_phone'] = $patient->phone;
        $data['status'] = $data['status'] ?? 'pending';

        return $this->create($data);
    }

    public function update(int $id, array $data): array
    {
        $appointment = $this->findClinicAppointment($id);
        if (! $appointment) {
            return ServiceResult::error('Appointment not found.', null, null, 404);
        }

        $clinicId = (int) $appointment->clinic_id;
        $normalized = $this->normalizeAppointmentData($clinicId, $data, $appointment);
        if (! $normalized['success']) {
            return $normalized;
        }

        $payload = $normalized['data'];
        $availability = $this->assertAvailable($clinicId, $payload, $appointment->id);
        if (! $availability['success']) {
            return $availability;
        }

        $appointment->update($this->appointmentUpdateAttributes($payload));

        return $this->show($appointment->id);
    }

   public function confirm(int $id): array
{
    return $this->flexibleTransition($id, 'confirmed', 'Appointment confirmed successfully');
}


    public function updateStatus(int $id, string $status, ?string $reason = null): array
    {
        $appointment = $this->findClinicAppointment($id);
        if (! $appointment) {
            return ServiceResult::error('Appointment not found.', null, null, 404);
        }

        $normalizedStatus = Str::of($status)->trim()->lower()->replace(' ', '_')->toString();

        $appointment->update([
            'status' => $normalizedStatus,
            'notes' => $reason
                ? trim((string) $appointment->notes . "\nStatus change reason: {$reason}")
                : $appointment->notes,
            'cancelled_at' => $normalizedStatus === 'cancelled' ? now() : $appointment->cancelled_at,
            'completed_at' => $normalizedStatus === 'completed' ? now() : $appointment->completed_at,
        ]);

        return $this->show($appointment->id);
    }

    public function attend(int $id): array
{
    return $this->flexibleTransition($id, 'attended', 'Appointment marked as attended successfully');
}

public function complete(int $id): array
{
    return $this->flexibleTransition($id, 'completed', 'Appointment completed successfully');
}
    public function reject(int $id, ?string $reason = null): array
    {
        return $this->cancel($id, $reason ?: 'Rejected by clinic');
    }

    public function cancel(int $id, ?string $reason = null): array
    {
        $appointment = $this->findClinicAppointment($id);
        if (! $appointment) {
            return ServiceResult::error('Appointment not found.', null, null, 404);
        }

        if (in_array($appointment->status, ['completed', 'cancelled'], true)) {
            return ServiceResult::error('This appointment cannot be cancelled.', null, null, 422);
        }

        $appointment->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'notes' => trim((string) $appointment->notes . ($reason ? "\nCancellation reason: {$reason}" : '')),
        ]);

        return $this->show($appointment->id);
    }

    public function recordPaymentAndComplete(int $id, array $data): array
    {
        $appointment = $this->findClinicAppointment($id);
        if (! $appointment) {
            return ServiceResult::error('Appointment not found.', null, null, 404);
        }

        if (! in_array($appointment->status, ['attended', 'arrived', 'confirmed'], true)) {
            return ServiceResult::error('Appointment must be confirmed or attended before payment completion.', null, null, 422);
        }

        $summary = $this->paymentSummary($appointment, $data);

        $result = DB::transaction(function () use ($appointment, $data, $summary) {
            $invoice = $this->invoiceForAppointment($appointment, $summary, (bool) ($data['generate_invoice'] ?? $data['generate_attach_invoice'] ?? false));

            if ($summary['paid_now'] > 0) {
                ClinicPayment::query()->create([
                    'clinic_invoice_id' => $invoice->id,
                    'clinic_id' => $appointment->clinic_id,
                    'recorded_by' => auth()->id(),
                    'amount' => $summary['paid_now'],
                    'method' => $data['payment_method'],
                    'paid_at' => now(),
                    'notes' => $data['discount_reason'] ?? null,
                ]);
            }

            $newPaid = round((float) $invoice->payments()->sum('amount'), 2);
            $invoice->update([
                'paid' => $newPaid,
                'remaining' => max(round($summary['total_due'] - $newPaid, 2), 0),
                'status' => $newPaid >= $summary['total_due'] ? 'paid' : ($newPaid > 0 ? 'partial' : 'pending'),
                'payment_method' => $data['payment_method'],
            ]);

            if (($data['payment_method'] ?? null) === 'Insurance') {
                $this->createInsuranceClaim($appointment, $invoice, $summary);
            }

            if ($data['whatsapp_receipt'] ?? false) {
                $this->sendReceiptMessage($appointment, $summary);
            }

            if ($data['add_follow_up_reminder'] ?? false) {
                $this->createFollowUpReminder($appointment);
            }

            $appointment->update(['status' => 'completed', 'completed_at' => now()]);

            return [$invoice->fresh('payments'), $appointment->fresh()];
        });

        return ServiceResult::success([
            'appointment' => (new AppointmentResource($result[1]->load(['doctor:id,name', 'patient.user:id,name', 'invoices.payments'])))->resolve(),
            'summary' => $summary,
            'invoice' => [
                'id' => $result[0]->id,
                'invoice_number' => $result[0]->invoice_number,
                'total' => (float) $result[0]->total,
                'paid' => (float) $result[0]->paid,
                'remaining' => (float) $result[0]->remaining,
                'status' => $result[0]->status,
            ],
        ], 'Payment recorded and appointment completed successfully');
    }

    public function paymentPreview(int $id, array $data = []): array
    {
        $appointment = $this->findClinicAppointment($id);
        if (! $appointment) {
            return ServiceResult::error('Appointment not found.', null, null, 404);
        }

        return ServiceResult::success([
            'payment_methods' => $this->paymentMethods(),
            'summary' => $this->paymentSummary($appointment, $data),
        ], 'Payment summary calculated successfully');
    }

    public function reschedule(int $id, array $data): array
    {
        $appointment = $this->findClinicAppointment($id);
        if (! $appointment) {
            return ServiceResult::error('Appointment not found.', null, null, 404);
        }

        if (in_array($appointment->status, ['completed', 'cancelled'], true)) {
            return ServiceResult::error('This appointment cannot be rescheduled.', null, null, 422);
        }

        $newData = [
            'patient_id' => $appointment->patient_id,
            'patient_name' => $appointment->patient_name,
            'patient_phone' => $appointment->patient_phone,
            'doctor_id' => $data['doctor_id'] ?? $appointment->doctor_user_id,
            'service_id' => $data['service_id'] ?? $appointment->service_id,
            'service_name' => $data['service_name'] ?? $appointment->service_name,
            'appointment_at' => ($data['new_date'] ?? $data['date']) . ' ' . ($data['new_time'] ?? $data['time']),
            'duration_minutes' => $data['duration_minutes'] ?? $appointment->duration_minutes ?? $appointment->duration ?? 30,
            'branch_id' => $data['branch_id'] ?? $appointment->branch_id,
            'branch' => $data['branch'] ?? $appointment->branch,
            'room_id' => $data['room_id'] ?? $appointment->room_id,
            'room' => $data['room'] ?? $appointment->room,
            'payment_type' => $data['payment_type'] ?? $appointment->payment_type,
            'notes' => $data['notes'] ?? 'Rescheduled from original appointment on ' . $appointment->appointment_at?->format('d/m/Y'),
            'status' => 'pending',
        ];

        $normalized = $this->normalizeAppointmentData((int) $appointment->clinic_id, $newData);
        if (! $normalized['success']) {
            return $normalized;
        }

        $availability = $this->assertAvailable((int) $appointment->clinic_id, $normalized['data'], $appointment->id);
        if (! $availability['success']) {
            return $availability;
        }

        $newAppointment = DB::transaction(function () use ($appointment, $normalized) {
            $appointment->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'notes' => trim((string) $appointment->notes . "\nRescheduled to " . Carbon::parse($normalized['data']['appointment_at'])->format('Y-m-d H:i')),
            ]);

            return ClinicAppointment::query()->create(array_merge($this->appointmentUpdateAttributes($normalized['data']), [
                'clinic_id' => $appointment->clinic_id,
                'patient_id' => $normalized['data']['patient']?->id,
                'doctor_user_id' => $normalized['data']['doctor']?->id,
                'rescheduled_from_id' => $appointment->id,
                'status' => 'pending',
            ]));
        });

        return $this->show($newAppointment->id);
    }

    public function move(int $id, array $data): array
    {
        return $this->update($id, [
            'appointment_at' => ($data['date'] ?? Carbon::parse($data['appointment_at'])->toDateString()) . ' ' . ($data['time'] ?? Carbon::parse($data['appointment_at'])->format('H:i')),
            'branch_id' => $data['branch_id'] ?? null,
            'room_id' => $data['room_id'] ?? null,
        ]);
    }

    public function changeDuration(int $id, int $duration): array
    {
        return $this->update($id, ['duration_minutes' => $duration, 'duration' => $duration]);
    }

    public function sendWhatsAppReminder(int $id): array
    {
        $appointment = $this->findClinicAppointment($id);
        if (! $appointment) {
            return ServiceResult::error('Appointment not found.', null, null, 404);
        }

        $this->logWhatsApp($appointment, 'reminder', $this->appointmentMessage($appointment, true));

        return ServiceResult::success(['sent' => true], 'WhatsApp reminder queued successfully');
    }

    public function sendToLab(int $id, array $data): array
    {
        $appointment = $this->findClinicAppointment($id);
        if (! $appointment) {
            return ServiceResult::error('Appointment not found.', null, null, 404);
        }

        if (! $appointment->patient_id) {
            return ServiceResult::error('Appointment is not linked to a patient.', null, ['patient_id' => ['Appointment is not linked to a patient.']], 422);
        }

        $clinicId = (int) $appointment->clinic_id;
        $lab = DentalLab::query()
            ->whereHas('partnerships', fn ($query) => $query->where('clinic_id', $clinicId))
            ->find($data['lab_id']);

        if (! $lab) {
            return ServiceResult::error('Dental lab not found.', null, ['lab_id' => ['Dental lab not found for this clinic.']], 422);
        }

        $service = LabService::query()
            ->where('lab_id', $lab->id)
            ->find($data['service_id']);

        if (! $service) {
            return ServiceResult::error('Lab service not found.', null, ['service_id' => ['Lab service not found for this lab.']], 422);
        }

        $dentistId = $this->doctorModelIdForUser((int) $appointment->doctor_user_id, $clinicId);
        if (! $dentistId) {
            return ServiceResult::error('No dentist profile is linked to this appointment.', null, ['doctor_id' => ['No dentist profile is linked to this appointment.']], 422);
        }

        $material = $this->materialName((int) $data['material_id']);
        $shade = $this->shadeName((int) $data['shade_id']);

        $order = DB::transaction(function () use ($appointment, $clinicId, $lab, $service, $dentistId, $data, $material, $shade) {
            $order = CaseModel::query()->create([
                'case_number' => $this->generateLabCaseNumber(),
                'clinic_id' => $clinicId,
                'lab_id' => $lab->id,
                'patient_id' => $appointment->patient_id,
                'dentist_id' => $dentistId,
                'status' => CaseModel::STATUS_PENDING,
                'priority' => CaseModel::PRIORITY_NORMAL,
                'due_date' => $data['delivery_date'],
                'case_type' => $service->service_name,
                'tooth_numbers' => $data['tooth_numbers'],
                'tooth_chart_3d' => [
                    'is_3d' => (bool) ($data['is_3d'] ?? false),
                    'material_id' => (int) $data['material_id'],
                    'material' => $material,
                    'shade_id' => (int) $data['shade_id'],
                    'shade' => $shade,
                    'appointment_id' => $appointment->id,
                ],
                'description' => trim(collect([
                    'Appointment ID: ' . $appointment->id,
                    'Material: ' . $material,
                    'Shade: ' . $shade,
                    '3D: ' . ((bool) ($data['is_3d'] ?? false) ? 'Yes' : 'No'),
                    filled($data['notes'] ?? null) ? 'Notes: ' . $data['notes'] : null,
                ])->filter()->implode("\n")),
                'created_by' => auth()->id(),
            ]);

            foreach (($data['files'] ?? []) as $file) {
                $path = Storage::disk('public')->putFile('clinic/lab-orders/' . $order->id, $file);
                CaseAttachment::query()->create([
                    'case_id' => $order->id,
                    'uploaded_by' => auth()->id(),
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'attachment_type' => 'appointment_lab_file',
                ]);
            }

            return $order;
        });

        return ServiceResult::success([
            'order_id' => $order->id,
            'appointment_id' => $appointment->id,
            'lab' => ['id' => $lab->id, 'name' => $lab->name],
            'service' => ['id' => $service->id, 'name' => $service->service_name],
            'patient' => [
                'id' => $appointment->patient_id,
                'name' => $appointment->patient?->user?->name ?? $appointment->patient_name,
            ],
            'teeth' => $data['tooth_numbers'],
            'material' => $material,
            'shade' => $shade,
            'delivery_date' => $order->due_date->toDateString(),
            'created_at' => $order->created_at->toISOString(),
        ], 'Case sent to lab successfully', 201);
    }

    public function availableSlots(array $filters): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $branch = Branch::query()->where('clinic_id', $clinicId)->find($filters['branch_id']);
        if (! $branch) {
            return ServiceResult::error('Branch not found.', null, null, 404);
        }

        $doctorId = (int) $filters['doctor_id'];
        $roomId = $filters['room_id'] ?? null;
        $duration = (int) ($filters['duration_minutes'] ?? 30);
        [$from, $to] = $this->branchWorkingHours($branch);
        $date = $filters['date'];
        $cursor = Carbon::parse("{$date} {$from}");
        $dayEnd = Carbon::parse("{$date} {$to}");
        $slots = [];

        while ($cursor->copy()->addMinutes($duration)->lte($dayEnd)) {
            $payload = [
                'doctor' => (object) ['id' => $doctorId],
                'branch' => $branch,
                'room' => $roomId ? (object) ['id' => (int) $roomId] : null,
                'appointment_at' => $cursor->copy(),
                'duration_minutes' => $duration,
            ];
            $available = $this->assertAvailable($clinicId, $payload)['success'] && ! $cursor->lessThanOrEqualTo(now());
            $slots[] = ['time' => $cursor->format('H:i'), 'available' => $available];
            $cursor->addMinutes($duration);
        }

        return ServiceResult::success($slots, 'Available slots retrieved successfully');
    }

    public function paymentMethods(): array
    {
        return collect(self::PAYMENT_METHODS)->map(fn ($method) => ['id' => $method, 'name' => $method])->all();
    }

    public function paymentTypes(): array
    {
        return [
            ['id' => 'cash', 'name' => 'Cash'],
            ['id' => 'insurance', 'name' => 'Insurance'],
            ['id' => 'none', 'name' => 'No payment type yet'],
        ];
    }

    private function normalizeAppointmentData(int $clinicId, array $data, ?ClinicAppointment $existing = null): array
    {
        $patient = ! empty($data['patient_id']) ? Patient::query()->with('user')->where('clinic_id', $clinicId)->find($data['patient_id']) : $existing?->patient;
        if (! empty($data['patient_id']) && ! $patient) {
            return ServiceResult::error('Patient not found.', null, ['patient_id' => ['Patient not found.']], 422);
        }

        $doctorId = $data['doctor_id'] ?? $existing?->doctor_user_id;
        $doctor = $doctorId ? User::query()->where('clinic_id', $clinicId)->role('doctor')->find($doctorId) : null;
        if ($doctorId && ! $doctor) {
            return ServiceResult::error('Doctor not found.', null, ['doctor_id' => ['Doctor not found.']], 422);
        }

        $service = ! empty($data['service_id']) ? Service::query()->find($data['service_id']) : $existing?->service;
        $branch = ! empty($data['branch_id'])
            ? Branch::query()->where('clinic_id', $clinicId)->find($data['branch_id'])
            : ($existing?->branch_id ? Branch::query()->where('clinic_id', $clinicId)->find($existing->branch_id) : null);
        $room = ! empty($data['room_id'])
            ? Room::query()->where('clinic_id', $clinicId)->find($data['room_id'])
            : ($existing?->room_id ? Room::query()->where('clinic_id', $clinicId)->find($existing->room_id) : null);

        if (! empty($data['branch_id']) && ! $branch) {
            return ServiceResult::error('Branch not found.', null, ['branch_id' => ['Branch not found.']], 422);
        }

        if (! empty($data['room_id']) && ! $room) {
            return ServiceResult::error('Room not found.', null, ['room_id' => ['Room not found.']], 422);
        }

        if ($room && $branch && Schema::hasColumn('rooms', 'branch_id') && (int) $room->branch_id !== (int) $branch->id) {
            return ServiceResult::error('Room does not belong to selected branch.', null, ['room_id' => ['Room does not belong to selected branch.']], 422);
        }

        $appointmentAt = Carbon::parse($data['appointment_at'] ?? $existing?->appointment_at);
        $duration = (int) ($data['duration_minutes'] ?? $data['duration'] ?? $existing?->duration_minutes ?? $existing?->duration ?? 30);
        $paymentType = $data['payment_type'] ?? $existing?->payment_type ?? 'none';

        return ServiceResult::success([
            'patient' => $patient,
            'doctor' => $doctor,
            'service' => $service,
            'branch' => $branch,
            'room' => $room,
            'patient_name' => $patient?->user?->name ?? $data['patient_name'] ?? $existing?->patient_name,
            'patient_phone' => $patient?->phone ?? $patient?->user?->phone ?? $data['patient_phone'] ?? $existing?->patient_phone,
            'service_name' => $service?->name ?? $data['service_name'] ?? $existing?->service_name,
            'appointment_at' => $appointmentAt,
            'duration_minutes' => $duration,
            'branch_name' => $branch?->name ?? $data['branch'] ?? $existing?->branch,
            'room_name' => $room?->name ?? $data['room'] ?? $existing?->room,
            'payment_type' => in_array($paymentType, self::PAYMENT_TYPES, true) ? $paymentType : 'none',
            'status' => $data['status'] ?? $existing?->status ?? 'pending',
            'notes' => $data['notes'] ?? $existing?->notes,
        ]);
    }

    private function assertAvailable(int $clinicId, array $payload, ?int $excludeId = null): array
    {
        $start = Carbon::parse($payload['appointment_at']);
        $end = $start->copy()->addMinutes((int) $payload['duration_minutes']);

        if ($payload['branch'] instanceof Branch) {
            [$from, $to] = $this->branchWorkingHours($payload['branch']);
            $dayStart = Carbon::parse($start->toDateString() . ' ' . $from);
            $dayEnd = Carbon::parse($start->toDateString() . ' ' . $to);
            if ($start->lt($dayStart) || $end->gt($dayEnd)) {
                return ServiceResult::error('Selected time is outside branch working hours.', null, ['appointment_at' => ['Selected time is outside branch working hours.']], 422);
            }
        }

        if (empty($payload['doctor']?->id) && empty($payload['room']?->id)) {
            return ServiceResult::success(['available' => true]);
        }

        $doctorId = $payload['doctor']?->id;
        $roomId = $payload['room']?->id;

        $conflict = ClinicAppointment::query()
            ->where('clinic_id', $clinicId)
            ->whereNotIn('status', ['cancelled'])
            ->when($excludeId, fn (Builder $query) => $query->whereKeyNot($excludeId))
            ->whereDate('appointment_at', $start->toDateString())
            ->where(function ($query) use ($doctorId, $roomId) {
                if ($doctorId) {
                    $query->orWhere('doctor_user_id', $doctorId);
                }

                if ($roomId) {
                    $query->orWhere(function ($roomQuery) use ($roomId, $doctorId) {
                        $roomQuery->where('room_id', $roomId)
                            ->where(function ($doctorQuery) use ($doctorId) {
                                if ($doctorId) {
                                    $doctorQuery->whereNull('doctor_user_id')->orWhere('doctor_user_id', $doctorId);
                                    return;
                                }

                                $doctorQuery->whereNull('doctor_user_id');
                            });
                    });
                }
            })
            ->get()
            ->contains(function (ClinicAppointment $appointment) use ($start, $end) {
                $bookedStart = Carbon::parse($appointment->appointment_at);
                $bookedEnd = $bookedStart->copy()->addMinutes((int) ($appointment->duration_minutes ?? $appointment->duration ?? 30));

                return $start->lt($bookedEnd) && $end->gt($bookedStart);
            });

        return $conflict
            ? ServiceResult::error('This time slot is no longer available, please choose another.', null, ['appointment_at' => ['Appointment conflicts with an existing booking.']], 422)
            : ServiceResult::success(['available' => true]);
    }

    private function appointmentUpdateAttributes(array $payload): array
    {
        return [
            'patient_id' => $payload['patient']?->id,
            'doctor_user_id' => $payload['doctor']?->id,
            'service_id' => $payload['service']?->id,
            'patient_name' => $payload['patient_name'],
            'patient_phone' => $payload['patient_phone'],
            'service_name' => $payload['service_name'],
            'appointment_at' => $payload['appointment_at'],
            'duration_minutes' => $payload['duration_minutes'],
            'duration' => $payload['duration_minutes'],
            'branch_id' => $payload['branch']?->id,
            'branch' => $payload['branch']?->name ?? $payload['branch_name'],
            'room_id' => $payload['room']?->id,
            'room' => $payload['room']?->name ?? $payload['room_name'],
            'payment_type' => $payload['payment_type'],
            'notes' => $payload['notes'],
        ];
    }

    private function transition(int $id, array $allowedFrom, string $nextStatus, string $message, array $extra = []): array
    {
        $appointment = $this->findClinicAppointment($id);
        if (! $appointment) {
            return ServiceResult::error('Appointment not found.', null, null, 404);
        }

        if ($allowedFrom !== [] && ! in_array($appointment->status, $allowedFrom, true)) {
            return ServiceResult::error('Appointment status flow is not valid for this action.', null, ['status' => ['Previous status step is required.']], 422);
        }

        $appointment->update(array_merge(['status' => $nextStatus], $extra));

        return ServiceResult::success((new AppointmentResource($appointment->fresh(['doctor:id,name', 'patient.user:id,name', 'invoices.payments'])))->resolve(), $message);
    }
private function flexibleTransition(int $id, string $nextStatus, string $message, array $extra = []): array
{
    $appointment = $this->findClinicAppointment($id);
    if (! $appointment) {
        return ServiceResult::error('Appointment not found.', null, null, 404);
    }

    $updateData = ['status' => $nextStatus];

    if ($nextStatus === 'cancelled') {
        $updateData['cancelled_at'] = now();
    } elseif ($nextStatus === 'completed') {
        $updateData['completed_at'] = now();
    } else {

        $updateData['cancelled_at'] = null;
        $updateData['completed_at'] = null;
    }

    $appointment->update(array_merge($updateData, $extra));

    return ServiceResult::success(
        (new AppointmentResource($appointment->fresh(['doctor:id,name', 'patient.user:id,name', 'invoices.payments'])))->resolve(),
        $message
    );
}
    private function paymentSummary(ClinicAppointment $appointment, array $data): array
    {
        $serviceCost = (float) ($data['total_cost'] ?? $data['service_cost'] ?? $this->serviceCost($appointment));
        $discount = max((float) ($data['discount'] ?? 0), 0);
        $totalDue = max(round($serviceCost - $discount, 2), 0);
        $alreadyPaid = (float) $appointment->invoices->sum('paid');
        $remainingBeforePayment = max(round($totalDue - $alreadyPaid, 2), 0);
        $paidNow = ($data['full_payment'] ?? false)
            ? $remainingBeforePayment
            : min(max((float) ($data['amount_paid_now'] ?? $data['paid_now'] ?? 0), 0), $remainingBeforePayment);

        return [
            'service_cost' => round($serviceCost, 2),
            'discount' => round($discount, 2),
            'discount_reason' => $data['discount_reason'] ?? $data['reason'] ?? null,
            'total_due' => $totalDue,
            'already_paid' => round($alreadyPaid, 2),
            'paid_now' => round($paidNow, 2),
            'remaining_balance' => max(round($remainingBeforePayment - $paidNow, 2), 0),
        ];
    }

    private function serviceCost(ClinicAppointment $appointment): float
    {
        if ($appointment->service_id) {
            $clinicPrice = ClinicServicePrice::query()
                ->where('clinic_id', $appointment->clinic_id)
                ->where('service_id', $appointment->service_id)
                ->value('price');

            if ($clinicPrice !== null) {
                return (float) $clinicPrice;
            }
        }

        return (float) ($appointment->service?->base_price ?? 0);
    }

    private function invoiceForAppointment(ClinicAppointment $appointment, array $summary, bool $attachInvoice): ClinicInvoice
    {
        $invoice = ClinicInvoice::query()->firstOrCreate(
            ['clinic_id' => $appointment->clinic_id, 'appointment_id' => $appointment->id],
            [
                'patient_id' => $appointment->patient_id,
                'doctor_user_id' => $appointment->doctor_user_id,
                'invoice_number' => $this->generateInvoiceNumber(),
                'total' => $summary['total_due'],
                'paid' => 0,
                'remaining' => $summary['total_due'],
                'status' => 'pending',
                'issued_at' => now()->toDateString(),
                'notes' => $attachInvoice ? 'Generated and attached from appointment payment.' : null,
            ]
        );

        $invoice->update(['total' => $summary['total_due'], 'remaining' => max($summary['total_due'] - (float) $invoice->paid, 0)]);

        if (! $invoice->items()->exists()) {
            ClinicInvoiceItem::query()->create([
                'clinic_invoice_id' => $invoice->id,
                'description' => $appointment->service_name,
                'amount' => $summary['service_cost'],
            ]);
        }

        return $invoice->load('payments');
    }

    private function createInsuranceClaim(ClinicAppointment $appointment, ClinicInvoice $invoice, array $summary): void
    {
        if (! $appointment->patient?->insurance_company_id) {
            return;
        }

        InsuranceClaim::query()->firstOrCreate(
            ['clinic_id' => $appointment->clinic_id, 'appointment_id' => $appointment->id],
            [
                'insurance_company_id' => $appointment->patient->insurance_company_id,
                'patient_id' => $appointment->patient_id,
                'clinic_invoice_id' => $invoice->id,
                'claim_number' => 'CLM-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
                'title' => $appointment->service_name,
                'description' => 'Created from appointment payment.',
                'service_date' => $appointment->appointment_at?->toDateString(),
                'coverage_percentage' => 100,
                'gross_amount' => $summary['total_due'],
                'patient_share_amount' => 0,
                'insurance_share_amount' => $summary['total_due'],
                'approved_amount' => 0,
                'paid_amount' => 0,
                'status' => InsuranceClaim::STATUS_SUBMITTED,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]
        );
    }

    private function findOrCreatePatient(int $clinicId, string $name, ?string $phone): Patient
    {
        $patient = Patient::query()
            ->with('user')
            ->where('clinic_id', $clinicId)
            ->where(function ($query) use ($name, $phone) {
                if ($phone) {
                    $query->where('phone', $phone)
                        ->orWhereHas('user', fn ($user) => $user->where('phone', $phone));
                } else {
                    $query->whereHas('user', fn ($user) => $user->where('name', $name));
                }
            })
            ->first();

        if ($patient) {
            return $patient;
        }

        $user = User::query()->create([
            'name' => $name,
            'phone' => $phone,
            'clinic_id' => $clinicId,
            'role' => 'patient',
            'is_active' => true,
            'password' => Str::random(16),
        ]);

        return Patient::query()->create([
            'user_id' => $user->id,
            'clinic_id' => $clinicId,
            'patient_number' => 'PT-' . now()->format('YmdHis') . '-' . $user->id,
            'phone' => $phone,
        ])->load('user');
    }

    private function sendInitialBookingMessage(ClinicAppointment $appointment): void
    {
        $this->logWhatsApp($appointment, 'appointment_request', $this->appointmentMessage($appointment, (bool) $appointment->patient?->user));

        Notification::query()->create([
            'title' => 'New appointment needs confirmation',
            'message' => 'New booking request for ' . $appointment->patient_name,
            'type' => 'appointment',
            'status' => 'sent',
            'audience_type' => 'role',
            'audience_id' => null,
            'priority' => 'medium',
            'delivery_method' => ['in_app'],
            'delivery_methods' => ['in_app'],
            'role' => 'clinic_admin',
            'is_read' => false,
            'link' => '/appointments/' . $appointment->id,
        ]);
    }

    private function sendReceiptMessage(ClinicAppointment $appointment, array $summary): void
    {
        $message = 'Receipt for ' . $appointment->service_name . ': paid ' . number_format($summary['paid_now'], 2) . ', remaining ' . number_format($summary['remaining_balance'], 2) . '.';
        $this->logWhatsApp($appointment, 'receipt', $message);
    }

    private function createFollowUpReminder(ClinicAppointment $appointment): void
    {
        ReminderLog::query()->create([
            'clinic_id' => $appointment->clinic_id,
            'patient_id' => $appointment->patient_id,
            'clinic_appointment_id' => $appointment->id,
            'channel' => 'whatsapp',
            'template' => 'follow_up',
            'status' => 'scheduled',
            'triggered_at' => now()->addWeek(),
            'payload' => ['appointment_id' => $appointment->id, 'service' => $appointment->service_name],
            'created_by' => auth()->id(),
        ]);
    }

    private function logWhatsApp(ClinicAppointment $appointment, string $intent, string $message): void
    {
        WhatsappMessage::query()->create([
            'clinic_id' => $appointment->clinic_id,
            'patient_phone' => $appointment->patient_phone,
            'message' => $message,
            'reply' => null,
            'intent' => $intent,
            'created_at' => now(),
        ]);
    }

    private function appointmentMessage(ClinicAppointment $appointment, bool $withPortalLink): string
    {
        $message = 'Appointment request: ' . $appointment->patient_name . ', ' . $appointment->service_name . ', ' . $appointment->appointment_at?->format('Y-m-d H:i') . ', phone ' . $appointment->patient_phone . '.';
        if ($withPortalLink && $appointment->patient_id) {
            $message .= ' Patient portal: ' . url('/patient/appointments/' . $appointment->id);
        }

        return $message;
    }

    private function baseCalendarQuery(int $clinicId, array $filters): Builder
    {
        return ClinicAppointment::query()
            ->with(['doctor:id,name', 'patient.user:id,name', 'service', 'invoices.payments', 'insuranceApproval'])
            ->where('clinic_id', $clinicId)
            ->when($this->selectedBranchId($filters), fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['branch'] ?? null, fn ($query, $branch) => $query->where('branch', $branch))
            ->when($filters['room_id'] ?? null, fn ($query, $roomId) => $query->where('room_id', $roomId))
            ->when($filters['room'] ?? null, fn ($query, $room) => $query->where('room', $room))
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('patient_name', 'like', "%{$search}%")
                        ->orWhere('service_name', 'like', "%{$search}%")
                        ->orWhereHas('doctor', fn ($doctor) => $doctor->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['patient_name'] ?? null, function ($query, string $patientName) {
                $query->where('patient_name', 'like', "%{$patientName}%");
            });
    }

    private function dayCalendar($appointments, Carbon $date): array
    {
        return [
            'date' => $date->toDateString(),
            'hours' => range(9, 17),
            'items' => AppointmentResource::collection($appointments)->resolve(),
        ];
    }

    private function weekCalendar($appointments, Carbon $start): array
    {
        return collect(range(0, 6))->map(function (int $offset) use ($start, $appointments) {
            $day = $start->copy()->addDays($offset);
            $items = $appointments->filter(fn (ClinicAppointment $appointment) => $appointment->appointment_at->isSameDay($day));

            return [
                'date' => $day->toDateString(),
                'day_name' => $day->format('D'),
                'day_number' => (int) $day->format('j'),
                'items' => AppointmentResource::collection($items)->resolve(),
            ];
        })->all();
    }

    private function monthCalendar($appointments, Carbon $start, Carbon $end): array
    {
        $days = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $items = $appointments->filter(fn (ClinicAppointment $appointment) => $appointment->appointment_at->isSameDay($cursor));
            $days[] = [
                'date' => $cursor->toDateString(),
                'day' => (int) $cursor->format('j'),
                'appointments_count' => $items->count(),
                'dots' => $items->take(4)->map(fn (ClinicAppointment $appointment) => [
                    'service' => $appointment->service_name,
                    'status' => $appointment->status,
                    'color' => (new AppointmentResource($appointment))->resolve()['color'] ?? '#6366f1',
                ])->values()->all(),
            ];
        }

        return $days;
    }

    private function rangeForView(string $view, Carbon $date, array $filters): array
    {
        if (! empty($filters['start_date']) || ! empty($filters['end_date'])) {
            $start = Carbon::parse($filters['start_date'] ?? $filters['end_date'])->startOfDay();
            $end = Carbon::parse($filters['end_date'] ?? $filters['start_date'])->endOfDay();
            return [$start, $end];
        }

        return match ($view) {
            'month' => [$date->copy()->startOfMonth()->startOfDay(), $date->copy()->endOfMonth()->endOfDay()],
            'week' => [$date->copy()->startOfWeek()->startOfDay(), $date->copy()->endOfWeek()->endOfDay()],
            default => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
        };
    }

    private function rangeLabel(string $view, Carbon $start, Carbon $end): string
    {
        return match ($view) {
            'month' => $start->format('F Y'),
            'week' => $start->format('j M') . ' - ' . $end->format('j M Y'),
            default => $start->format('j F Y'),
        };
    }

    private function navigationDate(string $view, Carbon $date, int $direction): string
    {
        return match ($view) {
            'month' => $date->copy()->addMonths($direction)->toDateString(),
            'week' => $date->copy()->addWeeks($direction)->toDateString(),
            default => $date->copy()->addDays($direction)->toDateString(),
        };
    }

    private function workingHoursForFilters(int $clinicId, array $filters): array
    {
        $branchId = $this->selectedBranchId($filters);
        $branch = $branchId ? Branch::query()->where('clinic_id', $clinicId)->find($branchId) : null;
        [$from, $to] = $branch ? $this->branchWorkingHours($branch) : [self::DEFAULT_WORKING_HOURS_FROM, self::DEFAULT_WORKING_HOURS_TO];

        return ['from' => $from, 'to' => $to];
    }

    private function branchWorkingHours(Branch $branch): array
    {
        return [
            filled($branch->working_hours_from) ? $branch->working_hours_from : self::DEFAULT_WORKING_HOURS_FROM,
            filled($branch->working_hours_to) ? $branch->working_hours_to : self::DEFAULT_WORKING_HOURS_TO,
        ];
    }

    private function findClinicAppointment(int $id): ?ClinicAppointment
    {
        return ClinicAppointment::query()
            ->with(['doctor:id,name', 'patient.user:id,name', 'service', 'invoices.payments'])
            ->where('clinic_id', $this->currentClinicId())
            ->find($id);
    }

    private function currentClinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }

    private function generateInvoiceNumber(): string
    {
        do {
            $number = 'INV-APT-' . now()->format('YmdHis') . '-' . random_int(100, 999);
        } while (ClinicInvoice::query()->where('invoice_number', $number)->exists());

        return $number;
    }

    private function doctorModelIdForUser(int $userId, int $clinicId): ?int
    {
        return Doctor::query()
            ->where('user_id', $userId)
            ->whereHas('user', fn ($query) => $query->where('clinic_id', $clinicId))
            ->value('id')
            ?: Doctor::query()
                ->whereHas('user', fn ($query) => $query->where('clinic_id', $clinicId))
                ->value('id');
    }

    private function materialName(int $id): string
    {
        return [
            1 => 'Zirconia',
            2 => 'Porcelain',
            3 => 'E-max',
            4 => 'Metal Ceramic',
        ][$id] ?? 'Material #' . $id;
    }

    private function shadeName(int $id): string
    {
        return [
            1 => 'Shade A1',
            2 => 'Shade A2',
            3 => 'Shade B1',
            4 => 'Shade C1',
        ][$id] ?? 'Shade #' . $id;
    }

    private function generateLabCaseNumber(): string
    {
        do {
            $number = 'LO-' . Str::upper(Str::random(6));
        } while (CaseModel::query()->where('case_number', $number)->exists());

        return $number;
    }
    public function delete(int $id): array
{
    $appointment = $this->findClinicAppointment($id);
    if (!$appointment) {
        return ServiceResult::error('Appointment not found.', null, null, 404);
    }

    if (in_array($appointment->status, ['completed', 'cancelled'], true)) {
        return ServiceResult::error('Cannot delete completed or cancelled appointments.', null, null, 422);
    }

    $appointment->delete();

    return ServiceResult::success(null, 'Appointment deleted successfully');
}
}
