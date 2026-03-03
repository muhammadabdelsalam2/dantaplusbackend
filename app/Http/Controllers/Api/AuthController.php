<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterDoctorRequest;
use App\Http\Requests\Auth\RegisterPatientRequest;
use App\Models\User;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private AuthService $authService)
    {
    }

    /**
     * POST /api/login
     */
    public function login(LoginRequest $request)
    {
        $data = $request->validated();

        // ✅ Sanctum يعتمد على web guard غالبًا
        $ok = Auth::guard('web')->attempt([
            'email'    => $data['email'],
            'password' => $data['password'],
        ]);

        if (!$ok) {
            return ApiResponse::error(
                'Invalid credentials',
                401,
                null
            );
        }

        /** @var User $user */
        $user = User::where('email', $data['email'])->first();

        // حماية إضافية (لو عندك is_active)
        if ($user && (int)($user->is_active ?? 1) !== 1) {
            Auth::guard('web')->logout();

            return ApiResponse::error(
                'Account is inactive',
                403,
                null
            );
        }

        // ✅ توليد توكن Sanctum
        $token = $user->createToken('postman')->plainTextToken;

        return ApiResponse::success(
            [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            'Logged in',
            200
        );
    }

    public function registerDoctor(RegisterDoctorRequest $request)
    {
        // خليها على service زي ما هي عندك
        $result = $this->authService->registerDoctor($request->validated());

        return response()->json($result, 201);
    }

    public function registerPatient(RegisterPatientRequest $request)
    {
        $result = $this->authService->registerPatient($request->validated());

        return response()->json($result, 201);
    }
}
