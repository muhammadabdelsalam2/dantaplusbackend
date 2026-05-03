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

    /**
     * Store a new support ticket (for clinic users)
     */
    public function store(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'nullable|string|in:Low,Medium,High,Urgent',
            'category' => 'nullable|string|max:100',
        ]);

        $user = auth()->user();

        $result = $this->service->createTicket([
            'reporter_type' => 'clinic',
            'reporter_id' => $user->id,
            'clinic_id' => $user->clinic_id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'priority' => $validated['priority'] ?? 'Medium',
            'category' => $validated['category'] ?? 'General',
            'status' => 'Open',
        ]);

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
