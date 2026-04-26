<?php

namespace App\Http\Controllers\Api\Clinic\Insurance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\Insurance\StoreInsuranceClaimRequest;
use App\Http\Requests\Clinic\Insurance\UpdateInsuranceClaimRequest;
use App\Services\Clinic\Insurance\InsuranceClaimService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class InsuranceClaimController extends Controller
{
    use ApiResponse;

    public function __construct(private InsuranceClaimService $service)
    {
    }

    public function index(Request $request)
    {
        $result = $this->service->index([
            'status' => $request->string('status')->toString() ?: null,
            'patient_id' => $request->integer('patient_id') ?: null,
            'insurance_company_id' => $request->integer('insurance_company_id') ?: null,
        ]);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(StoreInsuranceClaimRequest $request)
    {
        $result = $this->service->store($request->validated());

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

    public function update(UpdateInsuranceClaimRequest $request, int $id)
    {
        $result = $this->service->update($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function destroy(int $id)
    {
        $result = $this->service->destroy($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
