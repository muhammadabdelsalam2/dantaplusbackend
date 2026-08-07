<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\StoreAppointmentRequest;
use App\Http\Requests\Clinic\UpdateAppointmentRequest;
use App\Services\Clinic\AppointmentService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    use ApiResponse;

    public function __construct(private AppointmentService $service)
    {
    }

    
    public function store(StoreAppointmentRequest $request)
    {
        $result = $this->service->create($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    
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

   
    public function update(UpdateAppointmentRequest $request, int $id)
    {
        $result = $this->service->update($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    
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

   public function approve(Request $request, int $id)
    {
        $validated = $request->validate([
            
            'insurance_company_id' => ['nullable', 'integer', 'exists:insurance_companies,id'],
            'authorization_code' => ['nullable', 'string', 'max:255'],
            'ref_id' => ['nullable', 'string', 'max:255'],
            'approval_number' => ['nullable', 'string', 'max:255'],
            'policy_number' => ['nullable', 'string', 'max:255'],
            'approval_date' => ['nullable', 'date_format:Y-m-d'],
            'coverage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'coverage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
            'services' => ['nullable', 'array'],
            'services.*.service_name' => ['required_with:services', 'string', 'max:255'],
            'services.*.amount' => ['required_with:services', 'numeric', 'min:0'],
            'services.*.co_pay' => ['nullable', 'numeric', 'min:0'],
            'services.*.tooth_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);
 
        
        if (!empty($validated['insurance_company_id']) || !empty($validated['authorization_code']) || !empty($validated['coverage'])) {
            return $this->createInsuranceApproval($request, $id);
        }
 
        
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
    public function createInsuranceApproval(Request $request, int $id)
    {
        $validated = $request->validate([
            // Insurance Approval fields
            'insurance_company_id' => ['required', 'integer', 'exists:insurance_companies,id'],
            'authorization_code' => ['nullable', 'string', 'max:255'],
            'ref_id' => ['nullable', 'string', 'max:255'],
            'policy_number' => ['nullable', 'string', 'max:255'],
            'approval_date' => ['nullable', 'date_format:Y-m-d'],
            'coverage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'coverage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
            'services' => ['nullable', 'array'],
            'services.*.service_name' => ['required_with:services', 'string', 'max:255'],
            'services.*.amount' => ['required_with:services', 'numeric', 'min:0'],
            'services.*.co_pay' => ['nullable', 'numeric', 'min:0'],
            'services.*.tooth_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);
 
        $result = $this->service->createInsuranceApprovalFromAppointment($id, $validated);
 
        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }
 
        return ApiResponse::success($result['data'], $result['message'], $result['code']);
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

    public function sendToLab(Request $request, int $id)
    {
        $validated = $request->validate([
            'lab_id' => ['required', 'integer', 'exists:dental_labs,id'],
            'service_id' => ['required', 'integer', 'exists:lab_services,id'],
            'tooth_numbers' => ['required', 'array', 'min:1'],
            'tooth_numbers.*' => ['integer', 'min:1', 'max:32'],
            'material_id' => ['required', 'integer', 'min:1'],
            'shade_id' => ['required', 'integer', 'min:1'],
            'is_3d' => ['nullable', 'boolean'],
            'delivery_date' => ['required', 'date'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:10240'],
            'notes' => ['nullable', 'string'],
        ]);

        $result = $this->service->sendToLab($id, $validated);

        return $result['success']
            ? response()->json(['success' => true, 'data' => $result['data']], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
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