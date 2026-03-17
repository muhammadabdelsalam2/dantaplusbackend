<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\DentalLab;
use App\Services\SuperAdmin\LabNotificationsService;
use App\Support\ApiResponse;

class LabNotificationsController extends Controller
{
    use ApiResponse;

    public function __construct(private LabNotificationsService $labNotificationsService)
    {
    }

    public function index(DentalLab $lab)
    {
        $result = $this->labNotificationsService->listForLab($lab);

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
