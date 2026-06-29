<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Services\Company\DashboardService;
use App\Support\ApiResponse;
use App\Models\Clinic;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(private DashboardService $service) {}

    public function index()
    {
        return ApiResponse::success($this->service->overview(), 'Dashboard fetched successfully');
    }

    public function orderTrends()
    {
        return ApiResponse::success($this->service->orderTrends(), 'Order trends fetched successfully');
    }
    public function clinic(Request $request){
                $filters = $request->validate([
            'search'   => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = (int) ($filters['per_page'] ?? 50);

        $clinics = Clinic::query()
            ->when($filters['search'] ?? null, fn($q, $s) =>
                $q->where('name', 'like', "%{$s}%")
            )
            ->where('status', 'active')
            ->select('id', 'name', 'phone', 'email', 'address')
            ->latest('id')
            ->paginate($perPage);

        return ApiResponse::success([
            'items' => $clinics->items(),
            'pagination' => [
                'current_page' => $clinics->currentPage(),
                'last_page'    => $clinics->lastPage(),
                'per_page'     => $clinics->perPage(),
                'total'        => $clinics->total(),
            ],
        ], 'Clinics fetched successfully');

    }
}
