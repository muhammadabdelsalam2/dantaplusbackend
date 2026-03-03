<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Enums\OtpType;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;
use Exception;

class OtpService
{
    /**
     * Generate OTP
     *
     * @param string $identifier Email or Phone
     * @param string $type OTP Type (register, login, reset_password)
     * @param string $method email|phone
     * @param int|null $userId
     * @return OtpCode
     */
    public function generate(string $identifier, string $type, string $method, ?int $userId = null): OtpCode
    {
        // Check for existing, non-expired OTP
        $existingOtp = OtpCode::where('identifier', $identifier)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest('id') // get the latest OTP
            ->first();

        if ($existingOtp) {
            // If OTP exists and valid, resend the same code
            // Since we stored hashed code, we need to keep the plain code somewhere
            // Option 1: Store plain code temporarily in DB (not recommended for security)
            // Option 2: Cache plain code temporarily (better)
            // For example, using cache:
            $plainCode = cache()->get('otp_plain_' . $existingOtp->id);

            if ($plainCode) {
                $this->send($identifier, $plainCode, $method);
            }

            return $existingOtp;
        }

        // Invalidate old OTPs
        OtpCode::where('identifier', $identifier)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->delete();

        // Generate new 6-digit OTP
        $plainCode = random_int(100000, 999999);

        // Store hashed code
        $otp = OtpCode::create([
            'user_id' => $userId,
            'identifier' => $identifier,
            'code' => Hash::make($plainCode),
            'type' => $type,
            'method' => $method,
            'expires_at' => now()->addMinutes(5),
        ]);

        // Cache the plain code for reuse
        cache()->put('otp_plain_' . $otp->id, $plainCode, now()->addMinutes(5));

        // Send OTP
        $this->send($identifier, $plainCode, $method);

        return $otp;
    }

    /**
     * Send OTP via Email or SMS
     */
    public function send(string $identifier, string $code, string $method): void
    {
        if ($method === 'email') {
            Mail::to($identifier)->send(new OtpMail($code));
        } elseif ($method === 'phone') {
            // Use your SMS service class here
            app()->make('SmsService')->send($identifier, "Your OTP is: $code");
        }
    }

    /**
     * Verify OTP
     *
     * @throws Exception
     */
    public function verify(string $identifier, string $code, string $type): bool
    {
        $otp = OtpCode::where('identifier', $identifier)
            ->where('type', $type)
            ->latest()
            ->first();

        if (!$otp) {
            throw new Exception('OTP not found.');
        }

        if ($otp->verified_at) {
            throw new Exception('OTP already used.');
        }

        if ($otp->expires_at < now()) {
            throw new Exception('OTP expired.');
        }

        if ($otp->attempts >= 5) {
            throw new Exception('Too many attempts.');
        }

        if (!Hash::check($code, $otp->code)) {
            $otp->increment('attempts');
            throw new Exception('Invalid OTP.');
        }

        $otp->update([
            'verified_at' => now(),
        ]);

        return true;
    }

    /**
     * Invalidate all OTPs for identifier & type
     */
    public function invalidateOldOtps(string $identifier, string $type): void
    {
        OtpCode::where('identifier', $identifier)
            ->where('type', $type)
            ->delete();
    }
}