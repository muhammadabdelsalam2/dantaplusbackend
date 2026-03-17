<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Services\Lab\Clinic\ClinicService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ClinicCaseController extends Controller
{
    use ApiResponse;

    public function __construct(private ClinicService $service)
    {
    }

    public function index(Request $request, int $clinic)
    {
        $perPage = (int) ($request->input('per_page', 15));
        $result = $this->service->listCases($clinic, $perPage);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
