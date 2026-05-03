<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'Success',
        int $code = 200
    ): JsonResponse {
        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    public static function error(
        string $message,
        int $code = 400,
        mixed $errors = null
    ): JsonResponse {
        return response()->json([
            'status' => 'error',
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'code' => $code,
        ], $code);
    }
}
