<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\Clinic\StoreExternalClinicRequest;
use App\Services\Lab\Clinic\ClinicExternalService;
use App\Support\ApiResponse;

class ClinicExternalController extends Controller
{
    use ApiResponse;

    public function __construct(private ClinicExternalService $service)
    {
    }

    public function store(StoreExternalClinicRequest $request)
    {
        $result = $this->service->create($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
