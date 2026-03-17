<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\Clinic\InviteClinicRequest;
use App\Services\Lab\Clinic\ClinicInviteService;
use App\Support\ApiResponse;

class ClinicInviteController extends Controller
{
    use ApiResponse;

    public function __construct(private ClinicInviteService $service)
    {
    }

    public function store(InviteClinicRequest $request)
    {
        $result = $this->service->invite($request->validated()['email']);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
