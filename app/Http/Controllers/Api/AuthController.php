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

        $ok = Auth::guard("web")->attempt([
            "email" => $data["email"],
            "password" => $data["password"],
        ]);

        if (!$ok) {
            return ApiResponse::error("Invalid credentials", 401);
        }

        /** @var User $user */
        $user = User::where("email", $data["email"])->first();

        if ($user && (int)($user->is_active ?? 1) !== 1) {
            Auth::guard("web")->logout();
            return ApiResponse::error("Account is inactive", 403);
        }

        $token = $user->createToken("postman")->plainTextToken;

        return ApiResponse::success([
            "user" => $user,
            "token" => $token,
            "token_type" => "Bearer",
        ], "Logged in");
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
            "identifier" => "required|string",
            "otp" => "required|string",
        ]);

        $identifier = $request->identifier;
        $code = $request->otp;

        try {
            $this->authService->verifyRegistrationOtp($identifier, $code);

            $user = User::where("email", $identifier)
                ->orWhere("phone", $identifier)
                ->firstOrFail();

            $token = $user->createToken("auth_token")->plainTextToken;

            return ApiResponse::success([
                "user" => $user,
                "token" => $token,
            ], "Account verified successfully");

        } catch (\Exception $e) {
            $message = $e->getMessage();

            if ($message === "OTP expired.") {

                $user = User::where("email", $identifier)
                    ->orWhere("phone", $identifier)
                    ->first();

                app(OtpService::class)->generate(
                    $identifier,
                    OtpType::REGISTER->value,
                    filter_var($identifier, FILTER_VALIDATE_EMAIL) ? "email" : "phone",
                    $user?->id
                );

                return ApiResponse::error(
                    "OTP expired. A new OTP has been sent.",
                    403,
                    ["new_otp_sent" => true]
                );
            }

            return ApiResponse::error($message, 400);
        }
    }
}
