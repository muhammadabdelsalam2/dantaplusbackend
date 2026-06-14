<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Repositories\MaterialProductRepository;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    use ApiResponse;

    public function __construct(private MaterialProductRepository $materialProductRepository)
    {
    }

    public function index(Request $request)
    {

        if (! auth()->user()?->clinic_id) {
            return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
'category' => ['nullable', 'string', 'max:255'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $materials = $this->materialProductRepository->paginateCatalog($validated, (int) ($validated['per_page'] ?? 15));

        return ApiResponse::success([
            'items' => collect($materials->items())->map(fn ($material) => $this->formatMaterial($material))->values(),
            'pagination' => [
                'current_page' => $materials->currentPage(),
                'last_page' => $materials->lastPage(),
                'per_page' => $materials->perPage(),
                'total' => $materials->total(),
            ],
        ], 'Materials fetched successfully');
    }

    public function filters()
    {
        if (! auth()->user()?->clinic_id) {
            return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
        }

        return ApiResponse::success($this->materialProductRepository->catalogFilters(), 'Material filters fetched successfully');
    }

    public function show(int $material)
    {
        if (! auth()->user()?->clinic_id) {
            return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
        }

        $material = $this->materialProductRepository->findCatalogById($material);
        if (! $material) {
            return ApiResponse::error('Material not found.', 404);
        }

        return ApiResponse::success($this->formatMaterial($material), 'Material fetched successfully');
    }

  private function formatMaterial($material): array
{
    return [
        'id' => $material->id,
        'company_id' => $material->company_id,
        'company_name' => $material->company?->name,


        'company_status' => strtolower($material->company?->status ?? 'unknown'),

        'name' => $material->name,
        'brand' => $material->brand,
        'barcode' => $material->barcode,
        'description' => $material->description,
        'category' => $material->category,
        'category_object' => $material->category_object,
        'price' => (float) $material->price,
        'stock' => (int) $material->stock,
        'status' => strtolower((string) $material->status),
        'image_url' => $material->image_path ? asset('storage/' . $material->image_path) : $material->image_url,
        'rating' => (float) ($material->rating ?? 0),
        'review_count' => (int) ($material->review_count ?? 0),
        'estimated_delivery_time' => $material->estimated_delivery_time,
        'created_at' => optional($material->created_at)?->toISOString(),
        'updated_at' => optional($material->updated_at)?->toISOString(),
    ];
}
}
