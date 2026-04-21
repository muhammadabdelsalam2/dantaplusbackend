<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\UpdateMyClinicRequest;
use App\Repositories\ClinicRepository;
use App\Support\ApiResponse;
use Illuminate\Support\Arr;

class ClinicController extends Controller
{
    use ApiResponse;

    public function __construct(private ClinicRepository $clinicRepository)
    {
    }

    public function getMyClinic()
    {
        $clinicId = auth()->user()?->clinic_id;

        if (! $clinicId) {
            return ApiResponse::error('Authenticated user is not assigned to a clinic', 404);
        }

        $clinic = $this->clinicRepository->findById($clinicId, ['modules:id,clinic_id,module']);

        if (! $clinic) {
            return ApiResponse::error('Clinic not found', 404);
        }

        return ApiResponse::success($clinic, 'Clinic details fetched successfully');
    }

    public function updateMyClinic(UpdateMyClinicRequest $request)
    {
        $clinicId = auth()->user()?->clinic_id;

        if (! $clinicId) {
            return ApiResponse::error('Authenticated user is not assigned to a clinic', 404);
        }

        $clinic = $this->clinicRepository->findById($clinicId);

        if (! $clinic) {
            return ApiResponse::error('Clinic not found', 404);
        }

        $data = $request->validated();
        $modules = Arr::pull($data, 'modules');

        $updatedClinic = $this->clinicRepository->update($clinic, $data);

        if (is_array($modules)) {
            $this->clinicRepository->syncModules($updatedClinic, $modules);
        }

        $freshClinic = $this->clinicRepository->findById($updatedClinic->id, ['modules:id,clinic_id,module']);

        return ApiResponse::success($freshClinic, 'Clinic updated successfully');
    }
}
