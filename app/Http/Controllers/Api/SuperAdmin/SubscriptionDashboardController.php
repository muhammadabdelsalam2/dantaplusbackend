<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\Subscription\IndexSubscriptionDashboardRequest;
use App\Services\SuperAdmin\SubscriptionDashboardService;

class SubscriptionDashboardController extends Controller
{
    public function __construct(
        private SubscriptionDashboardService $service
    ) {}

    public function dashboard()
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->dashboard(),
        ]);
    }

    public function index(IndexSubscriptionDashboardRequest $request)
    {
        $rows = $this->service->index($request->validated());

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $rows->items(),
                'pagination' => [
                    'current_page' => $rows->currentPage(),
                    'per_page' => $rows->perPage(),
                    'total' => $rows->total(),
                    'last_page' => $rows->lastPage(),
                ],
            ],
        ]);
    }
}
