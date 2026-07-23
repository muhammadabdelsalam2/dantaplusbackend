<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\AnalyticsRequest;
use App\Services\Lab\AnalyticsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(private AnalyticsService $service)
    {
    }

    public function overview(AnalyticsRequest $request): JsonResponse
    {
        $result = $this->service->overview($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function clinics(): JsonResponse
    {
        return $this->respond($this->service->clinics());
    }

    public function doctors(AnalyticsRequest $request): JsonResponse
    {
        return $this->respond($this->service->doctors($request->validated()));
    }

    public function caseTypes(): JsonResponse
    {
        return $this->respond($this->service->caseTypes());
    }

    public function stats(AnalyticsRequest $request): JsonResponse
    {
        return $this->slice($request, 'stats', 'Lab analytics stats fetched successfully');
    }

    public function caseTypeBreakdown(AnalyticsRequest $request): JsonResponse
    {
        return $this->slice($request, 'caseTypeBreakdown', 'Case type breakdown fetched successfully');
    }

    public function monthlyCompletedCases(AnalyticsRequest $request): JsonResponse
    {
        return $this->slice($request, 'monthlyCompletedCases', 'Monthly completed cases fetched successfully');
    }

    public function performanceOverview(AnalyticsRequest $request): JsonResponse
    {
        return $this->slice($request, 'performanceOverview', 'Performance overview fetched successfully');
    }

    public function detailedCases(AnalyticsRequest $request): JsonResponse
    {
        return $this->slice($request, 'detailedCaseList', 'Detailed case list fetched successfully');
    }

    private function slice(AnalyticsRequest $request, string $key, string $message): JsonResponse
    {
        $result = $this->service->overview($request->validated());
        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'][$key] ?? null, $message, $result['code']);
    }

    private function respond(array $result): JsonResponse
    {
        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
