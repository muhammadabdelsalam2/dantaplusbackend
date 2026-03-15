<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\AssignTechnicianRequest;
use App\Http\Requests\Lab\StoreCaseMessageRequest;
use App\Http\Requests\Lab\StoreCaseRequest;
use App\Http\Requests\Lab\UpdateCaseRequest;
use App\Http\Requests\Lab\UpdateCaseStatusRequest;
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
        $result = $this->caseService->updateCase($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function updateStatus(UpdateCaseStatusRequest $request, int $id)
    {
        $result = $this->caseService->updateStatus($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function assignTechnician(AssignTechnicianRequest $request, int $id)
    {
        $result = $this->caseService->assignTechnician($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function messages(Request $request, int $id)
    {
        $perPage = (int) ($request->get('per_page', 30));
        $result = $this->communicationService->listMessages($id, $perPage);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function storeMessage(StoreCaseMessageRequest $request, int $id)
    {
        $result = $this->communicationService->sendMessage($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function storeAttachment(StoreCaseMessageRequest $request, int $id)
    {
        $result = $this->communicationService->addAttachment($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function activityLog(int $id)
    {
        $result = $this->communicationService->listActivityLogs($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
