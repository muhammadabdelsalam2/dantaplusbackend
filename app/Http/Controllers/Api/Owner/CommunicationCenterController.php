<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Communication\IndexConversationMessagesRequest;
use App\Http\Requests\Owner\Communication\IndexConversationsRequest;
use App\Http\Requests\Owner\Communication\StoreConversationMessageRequest;
use App\Http\Requests\Owner\Communication\UpdateConversationRequest;
use App\Services\Owner\CommunicationCenterService;
use App\Support\ApiResponse;

class CommunicationCenterController extends Controller
{
    use ApiResponse;

    public function __construct(private CommunicationCenterService $service) {}

    public function index(IndexConversationsRequest $request)
    {
        $result = $this->service->listConversations($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function messages(IndexConversationMessagesRequest $request, int $id)
    {
        $result = $this->service->listMessages($id, (int) $request->validated('per_page', 30));

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function storeMessage(StoreConversationMessageRequest $request, int $id)
    {
        $result = $this->service->sendMessage($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdateConversationRequest $request, int $id)
    {
        $result = $this->service->updateConversation($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function analytics()
    {
        $result = $this->service->analytics();

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
