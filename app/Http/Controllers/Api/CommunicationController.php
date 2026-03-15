<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\StoreMessageRequest;
use App\Http\Requests\Communication\UpdateConversationStatusRequest;
use App\Services\Communication\ConversationService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    use ApiResponse;

    public function __construct(private ConversationService $service) {}

    public function contacts(Request $request)
    {
        $result = $this->service->listContacts($request->all());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function messages(Request $request, int $id)
    {
        $perPage = (int) ($request->get('per_page', 30));
        $result = $this->service->listMessages($id, $perPage);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function storeMessage(StoreMessageRequest $request, int $id)
    {
        $result = $this->service->sendMessage($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function updateStatus(UpdateConversationStatusRequest $request, int $id)
    {
        $result = $this->service->updateConversationStatus($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function markRead(int $id)
    {
        $result = $this->service->markRead($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
