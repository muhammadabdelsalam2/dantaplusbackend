<?php

namespace App\Http\Controllers\Api\Clinic\Insurance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\Insurance\StoreInsuranceCompanyRequest;
use App\Http\Requests\Clinic\Insurance\UpdateInsuranceCompanyRequest;
use App\Services\Clinic\Insurance\InsuranceCompanyService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class InsuranceCompanyController extends Controller
{
    use ApiResponse;

    public function __construct(private InsuranceCompanyService $service)
    {
    }

    public function index(Request $request)
    {
        $result = $this->service->index([
            'search' => $request->string('search')->toString() ?: null,
        ]);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(StoreInsuranceCompanyRequest $request)
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

    public function update(UpdateInsuranceCompanyRequest $request, int $id)
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

    public function priceListItems(int $id)
    {
        $clinicId = auth()->user()?->clinic_id;
        if (!$clinicId) {
            return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
        }

        $company = \App\Models\InsuranceCompany::where('clinic_id', $clinicId)->find($id);
        if (!$company) {
            return ApiResponse::error('Insurance company not found.', 404);
        }

        $priceList = $company->syndicate_price_list_id
            ? \App\Models\InsurancePriceList::find($company->syndicate_price_list_id)
            : null;

        if (!$priceList) {
            return ApiResponse::success([], 'No price list found for this insurance company', 200);
        }

        $coverage = $this->coveragePercent($company->coverage);

        $items = $priceList->items()
            ->paginate(per_page: request()->integer('per_page', 50));

        return ApiResponse::success([
            'price_list' => [
                'id' => $priceList->id,
                'name' => $priceList->name,
                'insurance_company_id' => $priceList->insurance_company_id,
            ],
            'coverage_percentage' => $coverage,
            'items' => collect($items->items())->map(fn ($item) => [
                'id' => $item->id,
                'service_id' => $item->service_id,
                'code' => $item->code ?? $item->item_code,
                'item_code' => $item->item_code,
                'service_name' => $item->service_name,
                'category_id' => $item->category_id,
                'category_name' => $item->category_name,
                'price' => (float) $item->price,
                'covered_amount' => round(((float) $item->price * $coverage) / 100, 2),
                'patient_share_amount' => round((float) $item->price - (((float) $item->price * $coverage) / 100), 2),
                'notes' => $item->notes,
            ])->values(),
            'pagination' => [
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
            ],
        ], 'Price list items retrieved successfully', 200);
    }

    private function coveragePercent(?string $coverage): float
    {
        preg_match('/\d+(\.\d+)?/', (string) $coverage, $matches);

        return min((float) ($matches[0] ?? 0), 100);
    }
}
