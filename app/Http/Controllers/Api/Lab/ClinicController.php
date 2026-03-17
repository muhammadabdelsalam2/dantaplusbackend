<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\Clinic\ClinicFilterRequest;
use App\Services\Lab\Clinic\ClinicService;
use App\Support\ApiResponse;

class ClinicController extends Controller
{
    use ApiResponse;

    public function __construct(private ClinicService $service)
    {
    }

    public function index(ClinicFilterRequest $request)
    {
        $result = $this->service->list($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function show(int $clinic)
    {
        $result = $this->service->show($clinic);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
