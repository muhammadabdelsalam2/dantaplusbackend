<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\AssignTechnicianRequest;
use App\Http\Requests\Lab\StoreCaseMessageRequest;
use App\Http\Requests\Lab\StoreCaseRequest;
use App\Http\Requests\Lab\UpdateCaseRequest;
use App\Http\Requests\Lab\UpdateCaseStatusRequest;
use App\Models\CaseModel;
use App\Services\Lab\CaseCommunicationService;
use App\Services\Lab\CaseService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CaseController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CaseService $caseService,
        private CaseCommunicationService $communicationService,
    ) {}

    public function index(Request $request)
    {
        $result = $this->caseService->listCases($request->all());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function show(int $id)
    {
        $case = $this->case($id);
        $this->authorize('view', $case);

        $result = $this->caseService->showCase($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(StoreCaseRequest $request)
    {
        $result = $this->caseService->createCase($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdateCaseRequest $request, int $id)
    {
        $case = $this->case($id);
        $this->authorize('update', $case);

        $result = $this->caseService->updateCase($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function updateStatus(UpdateCaseStatusRequest $request, int $id)
    {
        $case = $this->case($id);
        $this->authorize('update', $case);

        $result = $this->caseService->updateStatus($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function assignTechnician(AssignTechnicianRequest $request, int $id)
    {
        $case = $this->case($id);
        $this->authorize('update', $case);

        $result = $this->caseService->assignTechnician($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function messages(Request $request, int $id)
    {
        $case = $this->case($id);
        $this->authorize('view', $case);

        $perPage = (int) ($request->input('per_page', 30));
        $result = $this->communicationService->listMessages($id, $perPage);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function storeMessage(StoreCaseMessageRequest $request, int $id)
    {
        $case = $this->case($id);
        $this->authorize('update', $case);

        $result = $this->communicationService->sendMessage($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function notes(Request $request, int $id)
    {
        return $this->messages($request, $id);
    }

    public function storeNote(StoreCaseMessageRequest $request, int $id)
    {
        return $this->storeMessage($request, $id);
    }

    public function complete(Request $request, int $id)
    {
        $case = $this->case($id);
        $this->authorize('update', $case);

        $data = $request->validate([
            'generate_invoice' => ['nullable', 'boolean'],
            'assign_for_delivery' => ['nullable', 'boolean'],
            'delivery_rep_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'scheduled_for' => ['nullable', 'date'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'pickup_notes' => ['nullable', 'string', 'max:1000'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->caseService->completeCase($id, $data);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function labOrder(int $id)
    {
        $case = $this->case($id);
        $this->authorize('view', $case);

        $result = $this->caseService->labOrder($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function publicLabOrder(string $token)
    {
        $data = $this->caseService->publicLabOrder($token);

        if (! $data) {
            abort(404);
        }

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.lab-order', ['labOrder' => $data])
                ->download('lab-order-' . $data['case_number'] . '.pdf');
        }

        return response()
            ->view('pdf.lab-order', ['labOrder' => $data])
            ->header('Content-Disposition', 'attachment; filename="lab-order-' . $data['case_number'] . '.html"');
    }

    public function storeAttachment(StoreCaseMessageRequest $request, int $id)
    {
        $case = $this->case($id);
        $this->authorize('update', $case);

        $result = $this->communicationService->addAttachment($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function activityLog(Request $request, int $id)
    {
        $case = $this->case($id);
        $this->authorize('view', $case);

        $range = $request->query('range', 'all');
        $result = $this->communicationService->listActivityLogs($id, $range === 'all' ? null : $range);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    private function case(int $id): CaseModel
    {
        return CaseModel::query()
            ->where('lab_id', auth()->user()?->lab_id)
            ->findOrFail($id);
    }
}
