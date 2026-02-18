<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterDoctorRequest;
use App\Http\Requests\Auth\RegisterPatientRequest;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private AuthService $authService)
    {
    }
    //
    public function login(LoginRequest $request, AuthService $service)
    {
        $result = $service->login($request->validated());
        if (!$result['success']) {
            return ApiResponse::error(
                $result['message'],
                $result['code'],
                $result['errors'] ?? null
            );
        }

        return ApiResponse::success(
            $result['data'],
            $result['message'],
            $result['code']
        );
    }

    public function registerDoctor(RegisterDoctorRequest $request, AuthService $service)
    {
        return response()->json(
            $service->registerDoctor($request->validated()),
            201
        );
    }

    public function registerPatient(RegisterPatientRequest $request, AuthService $service)
    {
        return response()->json(
            $service->registerPatient($request->validated()),
            201
        );
    }
}
