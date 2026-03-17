<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Services\Lab\Clinic\ClinicService;
use App\Support\ApiResponse;

class ClinicPartnershipController extends Controller
{
    use ApiResponse;

    public function __construct(private ClinicService $service)
    {
    }

    public function destroy(int $clinic)
    {
        $result = $this->service->removePartnership($clinic);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
