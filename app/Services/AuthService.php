<?php

namespace App\Services;

use App\Enums\OtpType;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Factories\UserFactory;
use App\Models\Doctor;
use App\Models\patient;
use App\Models\User;
use App\Support\ServiceResult;
use Exception;

class AuthService
{
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }




    public function login(array $credentials)
    {
        if (!auth()->attempt($credentials)) {
            return ServiceResult::error(
                'Invalid credentials',
                401
            );
        }

        $user = auth()->user();

        // تحقق إذا المستخدم لم يفعّل حسابه
        if (!$user->is_verified) {
            // أرسل OTP مرة تانية
            app()->make(OtpService::class)->generate(
                $user->email,
                OtpType::REGISTER->value,
                'email',
                $user->id
            );

            return ServiceResult::error(
                'Your account is not verified. OTP has been resent to your email.',
                route('api.auth.verifyAccount'), // next API endpoint
                null,
                403
            );
        }

        return ServiceResult::success([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $user,
        ], 'Login successful');
    }

    /**
     * Register User + Generate OTP
     */
    public function registerPatient(array $data)
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'is_verified' => false, // until OTP verified
        ], );

        $user->assignRole('patient');

        // Generate OTP using Enum
        $otp = $this->otpService->generate(
            $user->email,
            OtpType::REGISTER->value,
            'email',
            $user->id
        );

        // return $user;
        return ServiceResult::success([
            'otp' => $otp,
            'user' => $user,
        ], 'Register Successfully and Otp Sended To Your Email');
    }

    /**
     * Verify OTP for registration
     */
    public function verifyRegistrationOtp(string $identifier, string $code)
    {
        $this->otpService->verify($identifier, $code, OtpType::REGISTER->value);

        $user = User::where('email', $identifier)->firstOrFail();
        $user->update(['is_verified' => true]);

        return $user;
    }

    /**
     * Login using OTP
     */
    public function loginWithOtp(string $identifier, string $method = 'email')
    {
        $user = User::where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->firstOrFail();

        if (!$user->is_verified) {
            throw new Exception('User not verified yet.');
        }

        // Generate OTP for login using Enum
        $this->otpService->generate(
            $identifier,
            OtpType::LOGIN->value,
            $method,
            $user->id
        );

        return $user;
    }

    /**
     * Verify OTP for login
     */
    public function verifyLoginOtp(string $identifier, string $code)
    {
        $this->otpService->verify($identifier, $code, OtpType::LOGIN->value);

        $user = User::where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->firstOrFail();

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Forgot password - Generate OTP
     */
    public function forgotPassword(string $identifier)
    {
        $user = User::where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->firstOrFail();

        $this->otpService->generate(
            $identifier,
            OtpType::RESET_PASSWORD->value,
            filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone',
            $user->id
        );

        return $user;
    }

    /**
     * Reset password using OTP
     */
    public function resetPassword(string $identifier, string $code, string $newPassword)
    {
        $this->otpService->verify($identifier, $code, OtpType::RESET_PASSWORD->value);

        $user = User::where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->firstOrFail();

        $user->update([
            'password' => bcrypt($newPassword)
        ]);

        return $user;
    }
}
