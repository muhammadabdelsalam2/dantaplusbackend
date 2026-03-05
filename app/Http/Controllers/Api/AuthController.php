<?php

namespace App\Http\Controllers\Api;


use App\Enums\OtpType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterDoctorRequest;
use App\Http\Requests\Auth\RegisterPatientRequest;
use App\Models\User;
use App\Services\AuthService;
use App\Support\ApiResponse;
use App\Services\OtpService;
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

    public function verifyAccount(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string', // email أو phone
            'otp' => 'required|string',
        ]);
//         $request->validate([
//     'identifier' => 'required|email',
//     'otp' => 'required|string',
// ]);

        $identifier = $request->identifier;
        $code = $request->otp;

        try {
            // تحقق من OTP
            $this->authService->verifyRegistrationOtp($identifier, $code);

            // جلب المستخدم بعد التحقق
            $user = User::where('email', $identifier)
                ->orWhere('phone', $identifier)
                ->firstOrFail();

            // إنشاء API token بعد التحقق
            $token = $user->createToken('auth_token')->plainTextToken;

            return ApiResponse::success([
                'user' => $user,
                'token' => $token,
            ], 'Account verified successfully');

        } catch (\Exception $e) {
            $message = $e->getMessage();

            // إذا OTP انتهت مدته، أرسل OTP جديد تلقائي
            if ($message === 'OTP expired.') {
                $newOtp = app()->make(OtpService::class)->generate(
                    $identifier,
                    OtpType::REGISTER->value,
                    filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone',
                    User::where('email', $identifier)->orWhere('phone', $identifier)->first()->id
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
}
