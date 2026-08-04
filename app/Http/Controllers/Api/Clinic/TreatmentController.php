<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\StoreTreatmentRequest;
use App\Services\Clinic\TreatmentService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class TreatmentController extends Controller
{
    use ApiResponse;

    public function __construct(private TreatmentService $service)
    {
    }

    public function index()
    {
        $result = $this->service->index();

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(StoreTreatmentRequest $request)
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
    public function indexForPatient(int $patientId)
{
    $result = $this->service->indexForPatient($patientId);

    if (! $result['success']) {
        return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    return ApiResponse::success($result['data'], $result['message'], $result['code']);
}

public function storeForPatient(StoreTreatmentRequest $request, int $patientId)
{
    $result = $this->service->createForPatient($patientId, $request->validated());

    if (! $result['success']) {
        return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    return ApiResponse::success($result['data'], $result['message'], $result['code']);
}

    public function completeTreatment(Request $request, int $patientId, int $treatmentId)
    {
        $data = $request->validate([
            'final_cost'                  => ['required', 'numeric', 'min:0'],
            'discount'                    => ['nullable', 'numeric', 'min:0'],
            'discount_reason'             => ['nullable', 'string', 'max:255'],
            'full_payment'                => ['nullable', 'boolean'],
            'amount_paid'                 => ['required_if:full_payment,false', 'nullable', 'numeric', 'min:0'],
            'payment_method'              => ['required', 'string', 'max:50'],
            'send_whatsapp_receipt'       => ['nullable', 'boolean'],
            'attach_invoice_to_patient_file' => ['nullable', 'boolean'],
            'schedule_followup_reminder'  => ['nullable', 'boolean'],
        ]);

        $result = $this->service->completeTreatment($patientId, $treatmentId, $data);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
