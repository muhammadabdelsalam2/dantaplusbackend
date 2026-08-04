<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\IndexClinicPatientsRequest;
use App\Http\Requests\Clinic\StoreDentalChartEntryRequest;
use App\Http\Requests\Clinic\StorePatientLabCaseRequest;
use App\Http\Requests\Clinic\StorePatientNoteRequest;
use App\Http\Requests\Clinic\StorePatientRequest;
use App\Http\Requests\Clinic\UpdatePatientRequest;
use App\Http\Requests\Clinic\UploadPatientDocumentRequest;
use App\Http\Requests\Clinic\UploadPatientRadiologyRequest;
use App\Services\Clinic\PatientService;
use App\Services\Clinic\Settings\CommunicationPermissionService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    use ApiResponse;

    public function __construct(private PatientService $service)
    {
    }

    public function index(IndexClinicPatientsRequest $request)
    {
        $result = $this->service->index($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    //

    public function store(StorePatientRequest $request)
    {
        $result = $this->service->create($request->validated());

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

    public function update(UpdatePatientRequest $request, int $id)
    {
        $result = $this->service->update($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function dentalChart(int $id)
    {
        $result = $this->service->dentalChart($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function storeDentalChart(StoreDentalChartEntryRequest $request, int $id)
    {
        $result = $this->service->recordDentalChart($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function updateToothPresence(Request $request, int $id)
    {
        $result = $this->service->updateToothPresence($id, $request->validate([
            'tooth_number' => ['required_without:tooth_numbers', 'string', 'max:20'],
            'tooth_numbers' => ['required_without:tooth_number', 'array', 'min:1'],
            'tooth_numbers.*' => ['string', 'max:20', 'distinct'],
            'is_present' => ['required', 'boolean'],
        ]));

        return $this->respond($result);
    }

    public function radiology(Request $request, int $id)
    {
        $result = $this->service->radiology($id, $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'teeth' => ['nullable', 'string', 'max:50'],
            'modality' => ['nullable', 'in:Periapical,Bitewing,Panoramic,CBCT'],
        ]));

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

   public function uploadRadiology(UploadPatientRadiologyRequest $request, int $id)
{
    $result = $this->service->uploadRadiology(
        $id,
        $request->validated(),
        $request->file('file'),
        $request->file('before_image'),
        $request->file('after_image'),
    );

    if (! $result['success']) {
        return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    return ApiResponse::success($result['data'], $result['message'], $result['code']);
}

    public function labCases(int $id)
    {
        $result = $this->service->labCases($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function sendLabCase(StorePatientLabCaseRequest $request, int $id)
    {
        $result = $this->service->sendLabCase($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function discussion(int $id)
    {
        if (! app(CommunicationPermissionService::class)->allows(auth()->user(), 'can_access_patient_discussions')) {
            return ApiResponse::error('You are not allowed to access patient discussions.', 403);
        }

        $result = $this->service->discussion($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function storeDiscussion(StorePatientNoteRequest $request, int $id)
    {
        $permissions = app(CommunicationPermissionService::class);
        if ($request->hasFile('voice_note') && ! $permissions->allows(auth()->user(), 'can_send_voice_notes')) {
            return ApiResponse::error('You are not allowed to send voice notes.', 403);
        }
        if (! $request->hasFile('voice_note') && ! $permissions->allows(auth()->user(), 'can_send_notes')) {
            return ApiResponse::error('You are not allowed to send notes.', 403);
        }

        $result = $this->service->addDiscussion($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function analytics(int $id)
    {
        $result = $this->service->analytics($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function financialPerformance(int $id)
    {
        return $this->respond($this->service->financialPerformance($id));
    }

    public function revenueOverTime(int $id)
    {
        return $this->respond($this->service->revenueOverTime($id));
    }

    public function paymentMethodDistribution(int $id)
    {
        return $this->respond($this->service->paymentMethodDistribution($id));
    }

    public function visitBehavioralTrends(int $id)
    {
        return $this->respond($this->service->visitBehavioralTrends($id));
    }

    public function radiologyReport(int $id, int $recordId)
    {
        return $this->respond($this->service->radiologyReport($id, $recordId));
    }

    public function generateRadiologyReport(Request $request, int $id)
    {
        $data = $request->validate([
            'report_format' => ['nullable', 'in:clinical_summary,before_after_progress'],
            'case_selection' => ['nullable', 'array', 'min:1'],
            'case_selection.*' => ['integer', 'distinct'],
            'findings' => ['nullable', 'string'],
            'diagnosis' => ['nullable', 'string'],
        ]);

        return $this->respond($this->service->generateRadiologyReport($id, $data));
    }

    public function radiologyAppointmentSelect(int $id)
    {
        return $this->respond($this->service->radiologyAppointmentSelect($id));
    }

    public function radiologyTreatmentSelect(int $id)
    {
        return $this->respond($this->service->radiologyTreatmentSelect($id));
    }

    public function radiologyCompare(Request $request, int $id)
    {
        $data = $request->validate([
            'record_ids' => ['required', 'array', 'size:2'],
            'record_ids.*' => ['integer', 'distinct'],
        ]);

        return $this->respond($this->service->radiologyCompare($id, $data['record_ids']));
    }

    public function treatmentsHistory(int $id)
    {
        return $this->respond($this->service->treatmentsHistory($id));
    }

    public function storeTreatment(Request $request, int $id)
    {
        $data = $request->validate([
            'service_name' => ['required_without:title', 'string', 'max:255'],
            'title' => ['required_without:service_name', 'string', 'max:255'],
            'tooth_number' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'max:50'],
            'date' => ['sometimes', 'date'],
            'treatment_date' => ['sometimes', 'date'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'dentist' => ['sometimes', 'integer', 'exists:users,id'],
            'doctor_id' => ['sometimes', 'integer', 'exists:users,id'],
            'payment_method' => ['nullable', 'string', 'max:50'],
        ]);

        return $this->respond($this->service->storeTreatment($id, $data));
    }

    public function treatmentServices()
    {
        return $this->respond($this->service->clinicServicesList());
    }

    public function treatmentDentists()
    {
        return $this->respond($this->service->clinicDentistsList());
    }

    public function invoices(int $id)
    {
        return $this->respond($this->service->invoices($id));
    }

    public function addPayment(Request $request, int $id, int $invoiceId)
    {
        $data = $request->validate([
            'amount_to_pay' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'max:50'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        return $this->respond($this->service->addPayment($id, $invoiceId, $data));
    }

    public function trackLabCase(int $id, int $caseId)
    {
        return $this->respond($this->service->trackLabCase($id, $caseId));
    }

    private function respond(array $result)
    {
        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }
    public function documents(int $id)
{
    $result = $this->service->documents($id);

    if (! $result['success']) {
        return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    return ApiResponse::success($result['data'], $result['message'], $result['code']);
}

public function uploadDocument(UploadPatientDocumentRequest $request, int $id)
{
    $result = $this->service->uploadDocument($id, $request->validated(), $request->file('file'));

    if (! $result['success']) {
        return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    return ApiResponse::success($result['data'], $result['message'], $result['code']);
}

public function approvals(Request $request, int $id)
{
    return $this->respond($this->service->approvals($id, $request->validate([
        'search' => ['nullable', 'string', 'max:255'],
        'status' => ['nullable', 'in:All,Pending,Approved,Rejected,all,pending,approved,rejected'],
    ])));
}

public function storeApproval(Request $request, int $id)
{
    return $this->respond($this->service->createApproval($id, $request->validate([
        'insurance_company_id' => ['required', 'integer', 'exists:insurance_companies,id'],
        'approval_number' => ['required_without:ref_id', 'string', 'max:255'],
        'ref_id' => ['required_without:approval_number', 'string', 'max:255'],
        'date' => ['required', 'date'],
        'coverage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        'coverage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        /////////////////////////////
          'code' => ['nullable', 'string', 'max:255'],
        'expiry_date' => ['nullable', 'date', 'after_or_equal:date'],
        'approved_amount' => ['nullable', 'numeric', 'min:0'],
        'used_amount' => ['nullable', 'numeric', 'min:0'],
        'status' => ['nullable', 'in:Pending,Approved,Rejected,pending,approved,rejected'],
        'documents' => ['nullable', 'array'],
        'documents.*.type' => ['nullable', 'string', 'max:100'],
        'documents.*.name' => ['nullable', 'string', 'max:255'],
        'documents.*.path' => ['nullable', 'string', 'max:1000'],
        'documents.*.url' => ['nullable', 'string', 'max:1000'],
        'services' => ['nullable', 'array'],
        'services.*.service_name' => ['nullable', 'string', 'max:255'],
        'services.*.name' => ['nullable', 'string', 'max:255'],
        'services.*.service_code' => ['nullable', 'string', 'max:100'],
        'services.*.code' => ['nullable', 'string', 'max:100'],
        'services.*.amount' => ['nullable', 'numeric', 'min:0'],
        'services.*.value' => ['nullable', 'numeric', 'min:0'],
        'services.*.co_pay' => ['nullable', 'numeric', 'min:0'],
        'services.*.copay' => ['nullable', 'numeric', 'min:0'],
        'services.*.tooth_number' => ['nullable', 'string', 'max:20'],
        'services.*.tooth' => ['nullable', 'string', 'max:20'],
        'notes' => ['nullable', 'string'],
    ])));
}

public function storeApprovalService(Request $request, int $id, int $approvalId)
{
    $data = $request->validate([
        'service_name' => ['required', 'string', 'max:255'],
        'service_code' => ['nullable', 'string', 'max:100'],
        'amount' => ['required', 'numeric', 'min:0'],
        'requires_dental_lab' => ['nullable', 'boolean'],
        'tooth_number' => ['nullable', 'string', 'max:20'],
        'co_pay' => ['nullable', 'numeric', 'min:0'],
    ]);

    return $this->respond($this->service->addApprovalService($id, $approvalId, $data));
}
public function downloadApprovalSigned(Request $request, int $approval)
{
    $data = $request->validate([
        'clinic_id' => ['required', 'integer'],
    ]);

    $result = $this->service->approvalPdfPayloadForClinic((int) $data['clinic_id'], $approval);

    if (! $result['success']) {
        return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    return response($result['data']['content'], 200, [
        'Content-Type' => $result['data']['content_type'],
        'Content-Disposition' => 'attachment; filename="' . $result['data']['filename'] . '"',
    ]);
}
}
