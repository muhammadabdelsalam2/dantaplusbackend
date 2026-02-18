<?php

namespace App\Support;

class ServiceResult
{
    public static function success(
        mixed $data = null,
        string $message = 'Success',
        int $code = 200
    ): array {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'code' => $code,
        ];
    }

    public static function error(
        string $message,
        int $code = 400,
        mixed $errors = null
    ): array {
        return [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'code' => $code,
        ];
    }
}
