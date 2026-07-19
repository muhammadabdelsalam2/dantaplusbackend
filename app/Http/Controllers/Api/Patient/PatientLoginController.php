<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PatientLoginRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Str;

class PatientLoginController extends Controller
{
    use ApiResponse;

    public function __invoke(PatientLoginRequest $request)
    {
        $data = $request->validated();

        $user = User::query()
            ->where('phone', $data['phone'])
            ->whereHas('roles', fn ($q) => $q->where('name', 'patient'))
            ->first();

        if (! $user || Str::lower(trim($user->name)) !== Str::lower(trim($data['name']))) {
            return ApiResponse::error('Invalid name or phone number.', 422);
        }

        if (! $user->is_active) {
            return ApiResponse::error('This account is not active.', 403);
        }

        $patient = $user->patient; // تأكد إن العلاقة دي معرفة في User model

        $token = $user->createToken('patient-portal')->plainTextToken;

        return ApiResponse::success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => 'patient',
                'clinic_id' => $user->clinic_id,
                'patient_id' => $patient?->id,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Logged in successfully');
    }
}
