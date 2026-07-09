<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\StoreClinicCaseMessageRequest;
use App\Http\Requests\Clinic\StoreClinicCaseAttachmentRequest;
use App\Services\Clinic\CaseCommunicationService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CaseCommunicationController extends Controller
{
    use ApiResponse;

    public function __construct(private CaseCommunicationService $service)
    {
    }

    public function messages(Request $request, int $id)
    {
        $perPage = (int) $request->input('per_page', 30);
        $result = $this->service->listMessages($id, $perPage);

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function storeMessage(StoreClinicCaseMessageRequest $request, int $id)
    {
        $result = $this->service->sendMessage($id, $request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function attachments(int $id)
    {
        $result = $this->service->listAttachments($id);

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function storeAttachment(StoreClinicCaseAttachmentRequest $request, int $id)
    {
        $result = $this->service->addAttachment($id, $request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }
}
