<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\StoreDentalChartEntryRequest;
use App\Http\Requests\Clinic\StorePatientLabCaseRequest;
use App\Http\Requests\Clinic\StorePatientNoteRequest;
use App\Http\Requests\Clinic\StorePatientRequest;
use App\Http\Requests\Clinic\IndexClinicPatientsRequest;
use App\Http\Requests\Clinic\UpdatePatientRequest;
use App\Http\Requests\Clinic\UploadPatientRadiologyRequest;
use App\Services\Clinic\PatientService;
use App\Support\ApiResponse;

class PatientController extends Controller
{
    use ApiResponse;

    public function __construct(private PatientService $service)
    {
    }

    public function index(IndexClinicPatientsRequest $request)
    {
        $result = $this->service->index($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    //

    public function store(StorePatientRequest $request)
    {
        $result = $this->service->create($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function show(int $id)
    {
        $result = $this->service->show($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdatePatientRequest $request, int $id)
    {
        $result = $this->service->update($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function dentalChart(int $id)
    {
        $result = $this->service->dentalChart($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function storeDentalChart(StoreDentalChartEntryRequest $request, int $id)
    {
        $result = $this->service->recordDentalChart($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function radiology(int $id)
    {
        $result = $this->service->radiology($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

   public function uploadRadiology(UploadPatientRadiologyRequest $request, int $id)
{
    $result = $this->service->uploadRadiology(
        $id,
        $request->validated(),
        $request->file('file'),
        $request->file('before_image'),
        $request->file('after_image'),
    );

    if (! $result['success']) {
        return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    return ApiResponse::success($result['data'], $result['message'], $result['code']);
}

    public function labCases(int $id)
    {
        $result = $this->service->labCases($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function sendLabCase(StorePatientLabCaseRequest $request, int $id)
    {
        $result = $this->service->sendLabCase($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function discussion(int $id)
    {
        $result = $this->service->discussion($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function storeDiscussion(StorePatientNoteRequest $request, int $id)
    {
        $result = $this->service->addDiscussion($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function analytics(int $id)
    {
        $result = $this->service->analytics($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
    public function documents(int $id)
{
    $result = $this->service->documents($id);

    if (! $result['success']) {
        return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    return ApiResponse::success($result['data'], $result['message'], $result['code']);
}

public function uploadDocument(UploadPatientDocumentRequest $request, int $id)
{
    $result = $this->service->uploadDocument($id, $request->validated(), $request->file('file'));

    if (! $result['success']) {
        return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    return ApiResponse::success($result['data'], $result['message'], $result['code']);
}
}
