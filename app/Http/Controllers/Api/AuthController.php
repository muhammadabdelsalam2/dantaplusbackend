<?php

namespace App\Http\Controllers\Api;

use App\Enums\OtpType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterDoctorRequest;
use App\Http\Requests\Auth\RegisterPatientRequest;
use App\Models\User;
use App\Services\AuthService;
use App\Services\OtpService;
use App\Support\ApiResponse;
use App\Support\UserRoleManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private AuthService $authService)
    {
    }

    public function login(LoginRequest $request)
    {
        $data = $request->validated();
        $identifier = $data['identifier'] ?? $data['email'] ?? null;

        $user = User::query()
            ->where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();

        if (!$user) {
            return ApiResponse::error('Invalid credentials', 401);
        }



        if ((int) ($user->is_active ?? 1) !== 1) {
            Auth::guard('web')->logout();

            return ApiResponse::error('Account is inactive', 403);
        }

        $user = $user->fresh();
        $token = $user->createToken('postman')->plainTextToken;
        $role = UserRoleManager::primaryRole($user);

        return ApiResponse::success([
            'user' => $this->formatAuthenticatedUser($user, $role),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Logged in successfully');
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Logged out successfully');
    }

    public function registerDoctor(RegisterDoctorRequest $request)
    {
        $result = $this->authService->registerDoctor($request->validated());

        return response()->json($result, 201);
    }

    public function registerPatient(RegisterPatientRequest $request)
    {
        $result = $this->authService->registerPatient($request->validated());

        return response()->json($result, 201);
    }

    public function verifyAccount(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'otp' => 'required|string',
        ]);

        $identifier = $request->identifier;
        $code = $request->otp;

        try {
            $this->authService->verifyRegistrationOtp($identifier, $code);

            $user = User::where('email', $identifier)
                ->orWhere('phone', $identifier)
                ->firstOrFail();

            $token = $user->createToken('auth_token')->plainTextToken;

            return ApiResponse::success([
                'user' => $user,
                'token' => $token,
            ], 'Account verified successfully');
        } catch (\Exception $e) {
            $message = $e->getMessage();

            if ($message === 'OTP expired.') {
                $user = User::where('email', $identifier)
                    ->orWhere('phone', $identifier)
                    ->first();

                app(OtpService::class)->generate(
                    $identifier,
                    OtpType::REGISTER->value,
                    filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone',
                    $user?->id
                );

                return ApiResponse::error(
                    'OTP expired. A new OTP has been sent.',
                    403,
                    ['new_otp_sent' => true]
                );
            }

            return ApiResponse::error($message, 400);
        }
    }

    private function formatAuthenticatedUser(User $user, ?string $role): array
    {
        $payload = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
        ];

        if (UserRoleManager::isLabScopedRole($role) && $user->lab_id) {
            $payload['lab_id'] = $user->lab_id;
        }

        if (UserRoleManager::isCompanyScopedRole($role) && $user->company_id) {
            $payload['company_id'] = $user->company_id;
        }

        if (UserRoleManager::isClinicScopedRole($role) && $user->clinic_id) {
            $payload['clinic_id'] = $user->clinic_id;
        }

        return array_filter($payload, static fn($value) => $value !== null);
    }
}
