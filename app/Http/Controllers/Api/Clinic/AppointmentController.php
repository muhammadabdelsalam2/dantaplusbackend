<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\StoreAppointmentRequest;
use App\Http\Requests\Clinic\UpdateAppointmentRequest;
use App\Models\CaseAttachment;
use App\Models\CaseModel;
use App\Models\DentalLab;
use App\Models\LabService;
use App\Services\Clinic\AppointmentService;
use App\Support\ApiResponse;
use App\Support\ServiceResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AppointmentController extends Controller
{
    use ApiResponse;

    public function __construct(private AppointmentService $service)
    {
    }

    // ✅ Store - تم تحديثه للحقول المسطحة
    public function store(StoreAppointmentRequest $request)
    {
        $result = $this->service->create($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    // ✅ Quick Book - تم تحديثه للحقول المسطحة
    public function quickBook(Request $request)
    {
        $validated = $request->validate([
            'patient_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'patient_phone' => ['nullable', 'string', 'max:50'],
            'doctor_id' => ['required', 'integer', 'exists:users,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'service_name' => ['required_without:service_id', 'nullable', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'payment_type' => ['nullable', 'in:cash,insurance,none,no_payment_type'],

            // ✨ Insurance fields - مسطحة
            'insurance_company_id' => ['required_if:payment_type,insurance', 'nullable', 'integer', 'exists:insurance_companies,id'],
            'policy_number' => ['required_if:payment_type,insurance', 'nullable', 'string', 'max:255'],
            'authorization_code' => ['required_if:payment_type,insurance', 'nullable', 'string', 'max:255'],
            'approval_date' => ['required_if:payment_type,insurance', 'nullable', 'date_format:Y-m-d'],
            'coverage' => ['required_if:payment_type,insurance', 'nullable', 'numeric', 'min:0', 'max:100'],
            'approved_amount' => ['required_if:payment_type,insurance', 'nullable', 'numeric', 'min:0'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $validated['appointment_at'] = $validated['date'] . ' ' . $validated['time'];

        if (($validated['payment_type'] ?? null) === 'no_payment_type') {
            $validated['payment_type'] = 'none';
        }

        // تحويل الحقول المسطحة إلى صيغة الخدمة
        if ($validated['payment_type'] === 'insurance') {
            $validated['insurance_approval'] = [
                'insurance_company_id' => $validated['insurance_company_id'],
                'policy_number' => $validated['policy_number'],
                'authorization_code' => $validated['authorization_code'],
                'approval_date' => $validated['approval_date'],
                'coverage' => $validated['coverage'],
                'approved_amount' => $validated['approved_amount'],
                'attachment' => $validated['attachment'] ?? null,
            ];
        }

        $result = $this->service->quickBook($validated);

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    // ✅ Update - تم تحديثه للحقول المسطحة
    public function update(UpdateAppointmentRequest $request, int $id)
    {
        $result = $this->service->update($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    // ✅ الطرق الأخرى تبقى كما هي
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'patient_name' => ['nullable', 'string', 'max:255'],
            'view' => ['nullable', 'in:day,week,month'],
            'date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'branch_id' => ['nullable'],
            'branch' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'room_id' => ['nullable', 'integer'],
        ]);

        $result = $this->service->index($validated);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function show(int $id)
    {
        $result = $this->service->show($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function updateStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'max:50'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->respondResult($this->service->updateStatus($id, $validated['status'], $validated['reason'] ?? null));
    }

    public function approve(UpdateAppointmentRequest $request, int $id)
    {
        $result = $this->service->confirm($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function confirm(int $id)
    {
        return $this->respondResult($this->service->confirm($id));
    }

    public function attend(int $id)
    {
        return $this->respondResult($this->service->attend($id));
    }

    public function complete(int $id)
    {
        return $this->respondResult($this->service->complete($id));
    }

    public function paymentPreview(Request $request, int $id)
    {
        $validated = $request->validate([
            'total_cost' => ['nullable', 'numeric', 'min:0'],
            'service_cost' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'amount_paid_now' => ['nullable', 'numeric', 'min:0'],
            'paid_now' => ['nullable', 'numeric', 'min:0'],
            'full_payment' => ['nullable', 'boolean'],
            'discount_reason' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->respondResult($this->service->paymentPreview($id, $validated));
    }

    public function payment(Request $request, int $id)
    {
        $validated = $request->validate([
            'total_cost' => ['nullable', 'numeric', 'min:0'],
            'service_cost' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_reason' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['required', 'in:Cash,Card,Bank Transfer,Insurance,Mixed (Split)'],
            'amount_paid_now' => ['nullable', 'numeric', 'min:0'],
            'paid_now' => ['nullable', 'numeric', 'min:0'],
            'full_payment' => ['nullable', 'boolean'],
            'whatsapp_receipt' => ['nullable', 'boolean'],
            'generate_invoice' => ['nullable', 'boolean'],
            'generate_attach_invoice' => ['nullable', 'boolean'],
            'add_follow_up_reminder' => ['nullable', 'boolean'],
        ]);

        return $this->respondResult($this->service->recordPaymentAndComplete($id, $validated));
    }

    public function reschedule(Request $request, int $id)
    {
        $validated = $request->validate([
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'service_name' => ['nullable', 'string', 'max:255'],
            'new_date' => ['required_without:date', 'nullable', 'date_format:Y-m-d'],
            'new_time' => ['required_without:time', 'nullable', 'date_format:H:i'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'notes' => ['nullable', 'string'],
        ]);

        return $this->respondResult($this->service->reschedule($id, $validated));
    }

    public function move(Request $request, int $id)
    {
        $validated = $request->validate([
            'appointment_at' => ['required_without_all:date,time', 'nullable', 'date'],
            'date' => ['required_without:appointment_at', 'nullable', 'date_format:Y-m-d'],
            'time' => ['required_without:appointment_at', 'nullable', 'date_format:H:i'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
        ]);

        return $this->respondResult($this->service->move($id, $validated));
    }

    public function duration(Request $request, int $id)
    {
        $validated = $request->validate([
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
        ]);

        return $this->respondResult($this->service->changeDuration($id, (int) $validated['duration_minutes']));
    }

    public function cancel(Request $request, int $id)
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        return $this->respondResult($this->service->cancel($id, $validated['reason'] ?? null));
    }

    public function reject(Request $request, int $id)
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        return $this->respondResult($this->service->reject($id, $validated['reason'] ?? null));
    }

    public function whatsappReminder(int $id)
    {
        return $this->respondResult($this->service->sendWhatsAppReminder($id));
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
                'lab_service_id' => $service->id,
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

    public function availableSlots(Request $request)
    {
        $validated = $request->validate([
            'doctor_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
        ]);

        return $this->respondResult($this->service->availableSlots($validated));
    }

    public function paymentTypes()
    {
        return ApiResponse::success($this->service->paymentTypes(), 'Payment types fetched successfully');
    }

    public function paymentMethods()
    {
        return ApiResponse::success($this->service->paymentMethods(), 'Payment methods fetched successfully');
    }

    public function destroy(int $id)
    {
        $result = $this->service->delete($id);
        return $result['success']
            ? ApiResponse::success(null, $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code']);
    }

    private function respondResult(array $result)
    {
        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }
}
