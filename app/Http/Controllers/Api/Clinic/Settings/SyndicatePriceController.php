<?php

namespace App\Http\Controllers\Api\Clinic\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\Settings\ImportSyndicatePricesRequest;
use App\Http\Requests\Clinic\Settings\StoreSyndicatePriceRequest;
use App\Services\Clinic\Settings\SyndicatePriceService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class SyndicatePriceController extends Controller
{
    public function __construct(private SyndicatePriceService $service)
    {
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2017', 'max:2100'],
        ]);

        return $this->respond($this->service->index((int) $data['year']));
    }

    public function store(StoreSyndicatePriceRequest $request)
    {
        return $this->respond($this->service->store($request->validated()));
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'year' => ['sometimes', 'integer', 'min:2017', 'max:2100'],
            'code' => ['sometimes', 'nullable', 'string', 'max:100'],
            'service_name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'is_active_year' => ['sometimes', 'boolean'],
        ]);

        return $this->respond($this->service->update($id, $data));
    }

    public function destroy(int $id)
    {
        return $this->respond($this->service->destroy($id));
    }

    public function preview(ImportSyndicatePricesRequest $request)
    {
        return $this->respond($this->service->preview($request->file('file'), (int) $request->validated()['year']));
    }

    public function confirm(ImportSyndicatePricesRequest $request)
    {
        $data = $request->validated();

        return $this->respond($this->service->confirm($request->file('file'), (int) $data['year'], (bool) ($data['is_active_year'] ?? false)));
    }

    private function respond(array $result)
    {
        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }
}
