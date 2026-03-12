<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\Support\IndexLabSupportTicketRequest;
use App\Http\Requests\Lab\Support\StoreLabSupportTicketRequest;
use App\Services\Lab\SupportService;
use App\Support\ApiResponse;

class SupportController extends Controller
{
    use ApiResponse;

    public function __construct(private SupportService $service)
    {
    }

    public function index(IndexLabSupportTicketRequest $request)
    {
        $result = $this->service->listTickets($request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(StoreLabSupportTicketRequest $request)
    {
        $result = $this->service->createTicket($request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function show(int $id)
    {
        $result = $this->service->showTicket($id);

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
