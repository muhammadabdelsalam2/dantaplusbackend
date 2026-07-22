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

    public function update(Request $request, int $id)
    {
        $case = $this->case($id);
        $this->authorize('update', $case);

        $data = $request->all();
        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment');
        }

        $result = $this->caseService->updateCase($id, $data);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function updateStatus(Request $request, int $id)
    {
        $case = $this->case($id);
        $this->authorize('update', $case);

        $result = $this->caseService->updateStatus($id, $request->all());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function accept(Request $request, int $id)
    {
        $case = $this->case($id);
        $this->authorize('update', $case);

        $result = $this->caseService->acceptOrder($id, $request->all());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function start(Request $request, int $id)
    {
        $case = $this->case($id);
        $this->authorize('update', $case);

        $result = $this->caseService->startOrder($id, $request->all());

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

    public function storeMessage(Request $request, int $id)
    {
        $case = $this->case($id);
        $this->authorize('update', $case);

        $result = $this->communicationService->sendMessage($id, $request->all());

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

        $result = $this->caseService->completeCase($id, $request->all());

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

    public function labOrderPdf(int $id)
    {
        $case = $this->case($id);
        $this->authorize('view', $case);

        $result = $this->caseService->labOrder($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return $this->downloadLabOrderPdf($result['data']);
    }

    public function publicLabOrder(string $token)
    {
        $data = $this->caseService->publicLabOrder($token);

        if (! $data) {
            abort(404);
        }

        return $this->downloadLabOrderPdf($data);
    }

    private function downloadLabOrderPdf(array $data)
    {
        $statusKey = strtolower(str_replace([' ', '-'], '_', (string) ($data['case']['status'] ?? '')));
        if (! in_array($statusKey, ['received_by_lab', 'received'], true)) {
            return ApiResponse::error('PDF is only available once the order has been received by the lab.', 422);
        }

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.lab-order', ['labOrder' => $data])
                ->download('lab-order-' . $data['case_number'] . '.pdf');
        }

        return response()
            ->view('pdf.lab-order', ['labOrder' => $data])
            ->header('Content-Disposition', 'attachment; filename="lab-order-' . $data['case_number'] . '.html"');
    }

    public function storeAttachment(Request $request, int $id)
    {
        $case = $this->case($id);
        $this->authorize('update', $case);

        $data = $request->all();
        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment');
        }

        $result = $this->communicationService->addAttachment($id, $data);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function activityLog(Request $request, int $id)
    {
        $case = $this->case($id);
        $this->authorize('view', $case);

        $range = $request->query('filter', $request->query('range', 'all'));
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
