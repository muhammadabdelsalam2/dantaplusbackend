<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Services\Lab\LookupService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    use ApiResponse;

    public function __construct(private LookupService $service)
    {
    }

    public function patients(Request $request)
    {
        $result = $this->service->getPatients($request->query('search'));

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function dentists(Request $request)
    {
        $result = $this->service->getDentists($request->query('search'));

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function technicians(Request $request)
    {
        $result = $this->service->getTechnicians($request->query('search'));

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
