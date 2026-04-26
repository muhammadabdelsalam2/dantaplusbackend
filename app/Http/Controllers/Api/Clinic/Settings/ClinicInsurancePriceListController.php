<?php

namespace App\Http\Controllers\Api\Clinic\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\Settings\ImportInsurancePriceListRequest;
use App\Http\Requests\Clinic\Settings\StoreInsurancePriceListRequest;
use App\Http\Requests\Clinic\Settings\UpdateInsurancePriceListRequest;
use App\Services\Clinic\Settings\ClinicInsurancePriceListService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ClinicInsurancePriceListController extends Controller
{
    use ApiResponse;

    public function __construct(private ClinicInsurancePriceListService $service)
    {
    }

    public function index(Request $request)
    {
        $result = $this->service->index($request->integer('year') ?: null);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(StoreInsurancePriceListRequest $request)
    {
        $result = $this->service->store($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function import(ImportInsurancePriceListRequest $request)
    {
        $result = $this->service->import($request->validated(), $request->file('file'));

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdateInsurancePriceListRequest $request, int $id)
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
