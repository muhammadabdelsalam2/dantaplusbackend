<?php

namespace App\Http\Controllers\Api\Lab\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\Settings\StoreUserRequest;
use App\Http\Requests\Lab\Settings\UpdateUserRequest;
use App\Http\Requests\Lab\Settings\UpdateUserStatusRequest;
use App\Services\Lab\Settings\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(private UserService $userService)
    {
    }

    public function index(Request $request)
    {
        $result = $this->userService->getLabUsers($request->only(['per_page']));

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(StoreUserRequest $request)
    {
        $result = $this->userService->createUser($request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

public function update(UpdateUserRequest $request, int $user)
{
    $result = $this->userService->updateUser($user, $request);

    if (!$result['success']) {
        return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    return ApiResponse::success($result['data'], $result['message'], $result['code']);
}
    public function updateStatus(UpdateUserStatusRequest $request, int $user)
    {
        $result = $this->userService->updateStatus($user, $request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
