<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\Support\IndexSupportTicketsRequest;
use App\Http\Requests\Owner\Support\StoreSupportReplyRequest;
use App\Http\Requests\Owner\Support\UpdateSupportTicketRequest;
use App\Services\Owner\SupportCenterService;
use App\Support\ApiResponse;

class SupportCenterController extends Controller
{
    use ApiResponse;

    public function __construct(private SupportCenterService $service) {}

    public function index(IndexSupportTicketsRequest $request)
    {
        $result = $this->service->listTickets($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function show(int $id)
    {
        $result = $this->service->showTicket($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdateSupportTicketRequest $request, int $id)
    {
        $result = $this->service->updateTicket($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function storeReply(StoreSupportReplyRequest $request, int $id)
    {
        $result = $this->service->addReply($id, $request->validated());

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

    public function agents()
    {
        $result = $this->service->listAgents();

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
