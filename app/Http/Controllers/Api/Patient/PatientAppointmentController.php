<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Requests\Patient\CancelPatientAppointmentRequest;
use App\Http\Requests\Patient\StorePatientAppointmentRequest;
use App\Http\Resources\Patient\PatientAppointmentResource;
use App\Models\Branch;
use App\Models\ClinicAppointment;
use App\Models\Notification;
use App\Models\Service;
use App\Models\User;
use App\Services\Clinic\Settings\ClinicAppointmentSettingsService;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PatientAppointmentController extends BasePatientController
{
    private const DEFAULT_WORKING_HOURS_FROM = '09:00';

    private const DEFAULT_WORKING_HOURS_TO = '17:00';

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
        if ($this->isResponse($patient)) {
            return $patient;
        }

        $data = $request->validated();
        $notes = $data['notes'] ?? $data['reason'] ?? $data['complaint'] ?? null;
        $doctorId = $data['doctor_user_id'] ?? $data['doctor_id'] ?? null;

        $service = $this->availableServicesQuery($patient->clinic_id)
            ->where('id', $data['service_id'])
            ->first();

        if (! $service) {
            return ApiResponse::error('Selected service not found for this clinic', 422);
        }

        // الشرط الوحيد: الدكتور تابع لنفس عيادة المريض. مفيش أي شرط ربط دكتور-فرع محدد.
        $doctorExists = User::query()
            ->where('id', $doctorId)
            ->where('clinic_id', $patient->clinic_id)
            ->role('doctor')
            ->exists();

        if (! $doctorExists) {
            return ApiResponse::error('Selected doctor not found in this clinic', 422);
        }

        // الشرط الوحيد: الفرع تابع لنفس عيادة المريض ونشط. مفيش شرط تاني.
        $branch = Branch::query()
            ->where('id', $data['branch_id'])
            ->where('clinic_id', $patient->clinic_id)
            ->where('status', 'Active')
            ->first();

        if (! $branch) {
            return ApiResponse::error('Selected branch not found or inactive', 422);
        }

        if (! $this->slotIsAvailable($patient->clinic_id, $doctorId, $data['appointment_at'], $branch)) {
            return ApiResponse::error('This time slot is no longer available, please choose another', 422);
        }

        // تأكيد إن الميعاد ده مش محجوز بالفعل لنفس الدكتور (race condition)
        $slotTaken = ClinicAppointment::query()
            ->where('clinic_id', $patient->clinic_id)
            ->where('doctor_user_id', $doctorId)
            ->where('appointment_at', $data['appointment_at'])
            ->where('branch_id', $data['branch_id'])
            ->whereNotIn('status', ['cancelled'])
            ->exists();

        if ($slotTaken) {
            return ApiResponse::error('This time slot is no longer available, please choose another', 422);
        }

        $appointment = ClinicAppointment::create([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'doctor_user_id' => $doctorId,
            'patient_name' => $patient->user?->name ?? 'Patient #'.$patient->id,
            'patient_phone' => $patient->phone ?: $patient->user?->phone,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'appointment_at' => $data['appointment_at'],
            'duration_minutes' => $data['duration_minutes'] ?? $data['duration'] ?? 30,
            'branch_id' => $data['branch_id'],
            'branch' => $branch->name,
            'room' => $data['room'] ?? null,
            'payment_type' => $data['payment_type'] ?? null,
            'status' => 'pending',
            'notes' => $notes,
        ]);

        Notification::query()->create([
            'title' => 'Appointment request submitted',
            'message' => 'تم طلب حجز ميعاد',
            'type' => 'appointment',
            'status' => 'sent',
            'audience_type' => 'user',
            'audience_id' => $request->user()->id,
            'priority' => 'medium',
            'delivery_method' => ['in_app'],
            'delivery_methods' => ['in_app'],
            'user_id' => $request->user()->id,
            'role' => 'patient',
            'is_read' => false,
            'link' => '/patient/appointments/'.$appointment->id,
        ]);

        return ApiResponse::success(new PatientAppointmentResource($appointment->load('doctor')), 'Appointment requested successfully, awaiting clinic approval', 201);
    }

    public function services(Request $request)
    {
        $patient = $this->currentPatient($request);
        if ($this->isResponse($patient)) {
            return $patient;
        }

        $services = $this->availableServicesQuery($patient->clinic_id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($services);
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
            'notes' => trim((string) $appointment->notes.($reason ? "\nCancellation reason: {$reason}" : '')),
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
        if ($this->isResponse($patient)) {
            return $patient;
        }

        // الفرع (لو اتبعت) لازم يكون تابع لنفس عيادة المريض بس - مفيش شرط ربط دكتور-فرع.
        $request->validate([
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $patient->clinic_id)->where('status', 'Active')
                ),
            ],
        ]);

        // كل دكاترة العيادة بيترجعوا بغض النظر عن الفرع، لأن مفيش جدول ربط دكتور-فرع فعليًا.
        // الفرع بيتفلتر منطقيًا وقت الحجز/السلوتس بس (اتنين تابعين لنفس العيادة = مقبول).
        $doctors = User::query()
            ->where('clinic_id', $patient->clinic_id)
            ->role('doctor')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return ApiResponse::success($doctors, 'Doctors retrieved successfully');
    }

    public function branches(Request $request)
    {
        $patient = $this->currentPatient($request);
        if ($this->isResponse($patient)) {
            return $patient;
        }

        $branches = Branch::query()
            ->where('clinic_id', $patient->clinic_id)
            ->where('status', 'Active')
            ->select('id', 'name', 'code', 'address', 'city', 'phone', 'working_hours_from', 'working_hours_to', 'status')
            ->orderBy('name')
            ->get();

        return ApiResponse::success($branches, 'Branches retrieved successfully');
    }

    public function availableSlots(Request $request, int $doctorId)
    {
        $patient = $this->currentPatient($request);
        if ($this->isResponse($patient)) {
            return $patient;
        }

        // الشرط الوحيد: الدكتور تابع لنفس عيادة المريض.
        $doctorExists = User::query()
            ->where('id', $doctorId)
            ->where('clinic_id', $patient->clinic_id)
            ->role('doctor')
            ->exists();

        if (! $doctorExists) {
            return ApiResponse::error('Doctor not found', 404);
        }

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $patient->clinic_id)->where('status', 'Active')
                ),
            ],
        ]);

        $date = $data['date'];

        // الشرط الوحيد: الفرع تابع لنفس عيادة المريض ونشط.
        $branch = Branch::query()
            ->where('id', $data['branch_id'])
            ->where('clinic_id', $patient->clinic_id)
            ->where('status', 'Active')
            ->first();

        if (! $branch) {
            return ApiResponse::error('Branch not found or inactive', 404);
        }

        $slotDuration = $this->resolveSlotDuration();
        [$startTime, $endTime] = $this->branchWorkingHours($branch);

        $dayStart = Carbon::parse("{$date} {$startTime}");
        $dayEnd = Carbon::parse("{$date} {$endTime}");

        $booked = ClinicAppointment::query()
            ->where('clinic_id', $patient->clinic_id)
            ->where('doctor_user_id', $doctorId)
            ->whereDate('appointment_at', $date)
            ->where('branch_id', $branch->id)
            ->whereNotIn('status', ['cancelled'])
            ->get();

        $slots = [];
        $cursor = $dayStart->copy();

        while ($cursor->copy()->addMinutes($slotDuration)->lte($dayEnd)) {
            $time = $cursor->format('H:i');
            $slotEnd = $cursor->copy()->addMinutes($slotDuration);

            $hasConflict = $booked->contains(function ($appointment) use ($cursor, $slotEnd) {
                $appointmentStart = Carbon::parse($appointment->appointment_at);
                $appointmentEnd = $appointmentStart->copy()->addMinutes((int) ($appointment->duration_minutes ?? $appointment->duration ?? 30));

                return $cursor->lt($appointmentEnd) && $slotEnd->gt($appointmentStart);
            });

            $isPast = $cursor->isToday() && $cursor->lessThanOrEqualTo(now());

            $slots[] = [
                'time' => $time,
                'available' => ! $hasConflict && ! $isPast,
            ];

            $cursor->addMinutes($slotDuration);
        }

        return ApiResponse::success($slots, 'Available slots retrieved successfully');
    }

    private function slotIsAvailable(int $clinicId, int $doctorId, string $appointmentAt, Branch $branch): bool
    {
        $appointment = Carbon::parse($appointmentAt);
        $slotDuration = $this->resolveSlotDuration();
        [$startTime, $endTime] = $this->branchWorkingHours($branch);
        $dayStart = Carbon::parse($appointment->toDateString().' '.$startTime);
        $dayEnd = Carbon::parse($appointment->toDateString().' '.$endTime);
        $appointmentEnd = $appointment->copy()->addMinutes($slotDuration);

        if ($appointment->lt($dayStart) || $appointmentEnd->gt($dayEnd)) {
            return false;
        }

        return ! ClinicAppointment::query()
            ->where('clinic_id', $clinicId)
            ->where('doctor_user_id', $doctorId)
            ->where('branch_id', $branch->id)
            ->whereDate('appointment_at', $appointment->toDateString())
            ->whereNotIn('status', ['cancelled'])
            ->get()
            ->contains(function ($booked) use ($appointment, $appointmentEnd) {
                $bookedStart = Carbon::parse($booked->appointment_at);
                $bookedEnd = $bookedStart->copy()->addMinutes((int) ($booked->duration_minutes ?? $booked->duration ?? 30));

                return $appointment->lt($bookedEnd) && $appointmentEnd->gt($bookedStart);
            });
    }

    /**
     * مصدر الحقيقة الوحيد لساعات العمل هو الفرع (branches.working_hours_from/to).
     * لو القيمة فاضية/NULL بيرجع default عام (09:00 - 17:00). مفيش أي اعتماد على
     * جدول الدكاترة، لأن الداتا هناك غير موثوقة وسبّبت باجات قبل كده.
     */
    private function branchWorkingHours(Branch $branch): array
    {
        return [
            filled($branch->working_hours_from) ? $branch->working_hours_from : self::DEFAULT_WORKING_HOURS_FROM,
            filled($branch->working_hours_to) ? $branch->working_hours_to : self::DEFAULT_WORKING_HOURS_TO,
        ];
    }

    /**
     * قيمة slot_duration بتيجي من إعدادات العيادة (appointments settings)، لكن لو القيمة
     * غايبة أو غير منطقية (زي 480 دقيقة = يوم كامل بدل سلوت فردي)، بترجع default معقول
     * (30 دقيقة) عشان منقعش تاني في مشكلة "سلوت واحد بس بيغطي اليوم كله".
     */
    private function resolveSlotDuration(): int
    {
        $settingsResult = $this->settingsService->show();
        $settings = $settingsResult['data'] ?? [];
        $duration = (int) ($settings['slot_duration'] ?? $settings['default_duration'] ?? 30);

        if ($duration < 5 || $duration > 120) {
            return 30;
        }

        return $duration;
    }

    private function availableServicesQuery(int $clinicId)
    {
        return Service::query()
            ->where('is_active', true)
            ->where(function ($query) use ($clinicId) {
                $query->where('is_base', true)
                    ->orWhere('created_by_clinic_id', $clinicId);
            });
    }
}
