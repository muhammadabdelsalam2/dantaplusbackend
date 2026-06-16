<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class BasePatientController extends Controller
{
    use ApiResponse;

    protected function currentPatient(Request $request): Patient|JsonResponse
    {
        $patient = $request->user()
            ?->patient()
            ->with('clinic', 'user')
            ->first();

        if (! $patient) {
            return ApiResponse::error('No patient profile is linked to this account', 403);
        }

        return $patient;
    }

    protected function isResponse(mixed $value): bool
    {
        return $value instanceof JsonResponse;
    }
}
