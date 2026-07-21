<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreProductRequest;
use App\Http\Requests\Company\UpdateProductRequest;
use App\Models\Category;
use App\Models\MaterialProduct;
use App\Models\Product;
use App\Services\Company\ProductService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(private ProductService $service) {}

    public function index(Request $request) { return ApiResponse::success($this->service->paginate($request->all()), 'Products fetched successfully'); }
    public function store(StoreProductRequest $request) { return ApiResponse::success($this->service->create($request->validated()), 'Product created successfully', 201); }
    public function show(Product $id) { return ApiResponse::success($this->service->show($id), 'Product fetched successfully'); }
    public function update(UpdateProductRequest $request, Product $id) { return ApiResponse::success($this->service->update($id, $request->validated()), 'Product updated successfully'); }
    public function destroy(Product $id) { $this->service->delete($id); return ApiResponse::success(null, 'Product deleted successfully'); }
    public function destroyImage(Product $id, int $imageId) { $this->service->deleteImage($id, $imageId); return ApiResponse::success(null, 'Product image deleted successfully'); }
    public function categories() { return ApiResponse::success(Category::query()->where('status', 'active')->get(), 'Categories fetched successfully'); }
    public function materialsByCompany()
    {
        $companyId = auth()->user()->company_id;

        $materials = MaterialProduct::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'price'])
            ->map(fn (MaterialProduct $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category,
                'price' => (float) $product->price,
            ])
            ->values();

        return ApiResponse::success($materials, 'Materials fetched successfully');
    }
}
