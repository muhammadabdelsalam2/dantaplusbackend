<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Services\Lab\DashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        private DashboardService $dashboardService,
    ) {}

    /**
     * GET /api/lab/dashboard/stats
     * Returns: active_cases, completed_this_month, pending_deliveries, monthly_revenue
     */
    public function stats(): JsonResponse
    {
        $result = $this->dashboardService->getStats();

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    /**
     * GET /api/lab/dashboard/charts
     * Returns: case_type_distribution, monthly_revenue_chart, cases_by_clinic, wip_by_technician
     * Query params: year (default: current year), month (default: current month)
     */
    public function charts(Request $request): JsonResponse
    {
        $filters = $request->only(['year', 'month']);

        $result = $this->dashboardService->getCharts($filters);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    /**
     * GET /api/lab/dashboard/active-cases
     * Returns paginated active cases table with search
     * Query params: search, status, per_page (default: 10)
     */
    public function activeCases(Request $request): JsonResponse
    {
        $result = $this->dashboardService->getActiveCases($request->only(['search', 'status', 'per_page']));

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
