<?php

namespace App\Services;

use App\Exceptions\Auth\InvalidCredentialsException;
use App\Factories\UserFactory;
use App\Models\Doctor;
use App\Models\patient;
use App\Support\ServiceResult;

class AuthService
{
    public function registerDoctor(array $data)
    {
        $user = UserFactory::create($data);
        $user->assignRole('doctor');

        Doctor::create([
            'user_id' => $user->id,
            'specialization' => $data['specialization'],
            'license_number' => $data['license_number'],
        ]);

        return $user;
    }

    public function registerPatient(array $data)
    {
        $user = UserFactory::create($data);
        $user->assignRole('patient');

        patient::create([
            'user_id' => $user->id,
            'phone' => $data['phone'] ?? null,
        ]);

        //  Create API token for this user
        $token = $user->createToken('api')->plainTextToken;

        // Return structured response
        return [
            'user' => $user,
            'token' => $token,
        ];
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

        return ServiceResult::success([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $user,
        ], 'Login successful');
    }
}
