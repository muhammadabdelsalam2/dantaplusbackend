<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Requests\Patient\CancelPatientAppointmentRequest;
use App\Http\Requests\Patient\StorePatientAppointmentRequest;
use App\Http\Resources\Patient\PatientAppointmentResource;
use App\Models\ClinicAppointment;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use App\Models\User;
use App\Services\Clinic\Settings\ClinicAppointmentSettingsService;
use Carbon\Carbon;

class PatientAppointmentController extends BasePatientController
{
      public function __construct(private ClinicAppointmentSettingsService $settingsService) {}

    public function index(Request $request)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $appointments = ClinicAppointment::query()
            ->with('doctor')
            ->where('patient_id', $patient->id)
            ->where('clinic_id', $patient->clinic_id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->boolean('upcoming'), fn ($query) => $query->where('appointment_at', '>=', now()))
            ->when($request->boolean('past'), fn ($query) => $query->where('appointment_at', '<', now()))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('appointment_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('appointment_at', '<=', $request->query('to')))
            ->latest('appointment_at')
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::success(PatientAppointmentResource::collection($appointments), 'Patient appointments retrieved successfully');
    }

   public function store(StorePatientAppointmentRequest $request)
{
    $patient = $this->currentPatient($request);
    if ($this->isResponse($patient)) return $patient;

    $data = $request->validated();
    $notes = $data['notes'] ?? $data['reason'] ?? $data['complaint'] ?? null;
    $doctorId = $data['doctor_user_id'] ?? $data['doctor_id'] ?? null;

    $doctorExists = User::query()
        ->where('id', $doctorId)
        ->where('clinic_id', $patient->clinic_id)
        ->role('doctor')
        ->exists();

    if (! $doctorExists) {
        return ApiResponse::error('Selected doctor not found in this clinic', 422);
    }

    // تأكيد إن الميعاد ده مش محجوز بالفعل لنفس الدكتور (race condition)
    $slotTaken = ClinicAppointment::query()
        ->where('clinic_id', $patient->clinic_id)
        ->where('doctor_user_id', $doctorId)
        ->where('appointment_at', $data['appointment_at'])
        ->whereNotIn('status', ['cancelled'])
        ->exists();

    if ($slotTaken) {
        return ApiResponse::error('This time slot is no longer available, please choose another', 422);
    }

    $appointment = ClinicAppointment::create([
        'clinic_id'        => $patient->clinic_id,
        'patient_id'       => $patient->id,
        'doctor_user_id'   => $doctorId,
        'patient_name'     => $patient->user?->name ?? 'Patient #' . $patient->id,
        'patient_phone'    => $patient->phone ?: $patient->user?->phone,
        'service_name'     => $data['service_name'],
        'appointment_at'   => $data['appointment_at'],
        'duration_minutes' => $data['duration_minutes'] ?? $data['duration'] ?? 30,
        'branch'           => $data['branch'] ?? null,
        'room'             => $data['room'] ?? null,
        'payment_type'     => $data['payment_type'] ?? null,
        'status'           => 'pending', 
        'notes'            => $notes,
    ]);

    return ApiResponse::success(new PatientAppointmentResource($appointment->load('doctor')), 'Appointment requested successfully, awaiting clinic approval', 201);
}
    public function show(Request $request, int $id)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $appointment = $this->appointmentQuery($patient, $id)->with(['doctor', 'invoices.payments'])->first();

        if (! $appointment) {
            return ApiResponse::error('Appointment not found', 404);
        }

        return ApiResponse::success(new PatientAppointmentResource($appointment), 'Patient appointment retrieved successfully');
    }

    public function cancel(CancelPatientAppointmentRequest $request, int $id)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $appointment = $this->appointmentQuery($patient, $id)->first();

        if (! $appointment) {
            return ApiResponse::error('Appointment not found', 404);
        }

        if (in_array($appointment->status, ['completed', 'cancelled', 'attended'], true)) {
            return ApiResponse::error('This appointment cannot be cancelled', 422);
        }

        $reason = $request->validated('reason');
        $appointment->update([
            'status' => 'cancelled',
            'notes' => trim((string) $appointment->notes . ($reason ? "\nCancellation reason: {$reason}" : '')),
        ]);

        return ApiResponse::success(new PatientAppointmentResource($appointment->fresh('doctor')), 'Appointment cancelled successfully');
    }

    private function appointmentQuery($patient, int $id)
    {
        return ClinicAppointment::query()
            ->where('id', $id)
            ->where('patient_id', $patient->id)
            ->where('clinic_id', $patient->clinic_id);
    }
    public function doctors(Request $request)
    {
        $patient = $this->currentPatient($request);
        if ($this->isResponse($patient)) return $patient;

        $doctors = User::query()
            ->where('clinic_id', $patient->clinic_id)
            ->role('doctor')
            ->select('id', 'name')
            ->get();

        return ApiResponse::success($doctors, 'Doctors retrieved successfully');
    }

    public function availableSlots(Request $request, int $doctorId)
    {
        $patient = $this->currentPatient($request);
        if ($this->isResponse($patient)) return $patient;

        $doctorExists = User::query()
            ->where('id', $doctorId)
            ->where('clinic_id', $patient->clinic_id)
            ->role('doctor')
            ->exists();

        if (! $doctorExists) {
            return ApiResponse::error('Doctor not found', 404);
        }

        $date = $request->query('date', now()->toDateString());

        $settingsResult = $this->settingsService->show();
        $settings = $settingsResult['data'] ?? [];

        $slotDuration = (int) ($settings['slot_duration'] ?? $settings['default_duration'] ?? 30);
        $startTime = $settings['start_time'] ?? '09:00';
        $endTime = $settings['end_time'] ?? '17:00';

        $dayStart = Carbon::parse("{$date} {$startTime}");
        $dayEnd = Carbon::parse("{$date} {$endTime}");

        $booked = ClinicAppointment::query()
            ->where('clinic_id', $patient->clinic_id)
            ->where('doctor_user_id', $doctorId)
            ->whereDate('appointment_at', $date)
            ->whereNotIn('status', ['cancelled'])
            ->get()
            ->map(fn ($a) => Carbon::parse($a->appointment_at)->format('H:i'))
            ->all();

        $slots = [];
        $cursor = $dayStart->copy();

        while ($cursor->lt($dayEnd)) {
            $time = $cursor->format('H:i');
            if (! in_array($time, $booked, true) && $cursor->greaterThan(now())) {
                $slots[] = $time;
            }
            $cursor->addMinutes($slotDuration);
        }

        return ApiResponse::success([
            'date' => $date,
            'slot_duration' => $slotDuration,
            'slots' => $slots,
        ], 'Available slots retrieved successfully');
    }
}
