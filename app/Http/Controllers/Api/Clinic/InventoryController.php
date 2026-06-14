<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryLog;
use App\Repositories\MaterialProductRepository;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    use ApiResponse;

    public function __construct(private MaterialProductRepository $materialProductRepository)
    {
    }

  public function index(Request $request)
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
        }

        $validated = $request->validate([
            'search'   => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],  // category_name في الـ DB
            'supplier' => ['nullable', 'string', 'max:255'],  // ← جديد: اسم السابلير
            'status'   => ['nullable', 'in:in_stock,low_stock,out_of_stock'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = max(1, min((int) ($validated['per_page'] ?? 15), 100));

        $query = InventoryItem::query()
            ->with(['product.company'])
            ->withoutGlobalScopes()
            ->where('clinic_id', $clinicId)
            ->when($validated['search'] ?? null, function ($builder, $search) {
                $builder->where(function ($nestedQuery) use ($search) {
                    $nestedQuery->where('product_name', 'like', "%{$search}%")
                        ->orWhere('category_name', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->when($validated['category'] ?? null, fn ($builder, $category) =>
                $builder->where('category_name', $category)
            )
            
            ->when($validated['supplier'] ?? null, fn ($builder, $supplier) =>
                $builder->where('supplier', 'like', "%{$supplier}%")
            )
            ->when($validated['status'] ?? null, fn ($builder, $status) =>
                $builder->where('status', $status)
            )
            ->latest('id');

        $items      = $query->paginate($perPage);
        $statsQuery = InventoryItem::query()->withoutGlobalScopes()->where('clinic_id', $clinicId);

        return ApiResponse::success([
            'items' => collect($items->items())->map(fn ($item) => $this->formatInventoryItem($item))->values(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
                'per_page'     => $items->perPage(),
                'total'        => $items->total(),
            ],
            'summary' => [
                'total_items'       => (clone $statsQuery)->count(),
                'low_stock_count'   => (clone $statsQuery)->where('status', 'low_stock')->count(),
                'out_of_stock_count'=> (clone $statsQuery)->where('status', 'out_of_stock')->count(),
                'warehouse_value'   => round((float) (clone $statsQuery)
                    ->leftJoin('material_products', 'material_products.id', '=', 'inventory_items.product_id')
                    ->selectRaw('COALESCE(SUM(inventory_items.quantity * material_products.price), 0) as total_value')
                    ->value('total_value'), 2),
            ],
        ], 'Inventory fetched successfully');
    }
    public function show(int $inventory)
    {
        $item = $this->findInventoryItem($inventory);
        if (! $item) {
            return ApiResponse::error('Inventory item not found.', 404);
        }

        return ApiResponse::success($this->formatInventoryItem($item), 'Inventory item fetched successfully');
    }

    public function store(Request $request)
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
        }

        $validated = $request->validate([
            'material_product_id' => ['required', 'integer', 'exists:material_products,id'],
            'quantity' => ['required', 'integer', 'min:0'],
            'minimum_stock_level' => ['required', 'integer', 'min:0'],
            'reorder_quantity' => ['nullable', 'integer', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            'status' => ['nullable', 'in:in_stock,low_stock,out_of_stock'],
        ]);

        $material = $this->materialProductRepository->findCatalogById((int) $validated['material_product_id']);
        if (! $material) {
            return ApiResponse::error('Material not found.', 422, ['material_product_id' => ['Material not found.']]);
        }

        $item = DB::transaction(function () use ($clinicId, $material, $validated) {
            $item = InventoryItem::query()
                ->withoutGlobalScopes()
                ->updateOrCreate(
                    [
                        'clinic_id' => $clinicId,
                        'product_id' => $material->id,
                    ],
                    [
                        'company_id' => $material->company_id,
                        'barcode' => $material->barcode,
                        'product_name' => $material->name,
                        'category_name' => $material->category,
                        'description' => $material->description,
                        'quantity' => $validated['quantity'],
                        'minimum_stock_level' => $validated['minimum_stock_level'],
                        'reorder_quantity' => $validated['reorder_quantity'] ?? 0,
                        'unit' => $validated['unit'],
                        'supplier' => $material->company?->name,
                        'status' => $validated['status'] ?? $this->resolveStatus((int) $validated['quantity'], (int) $validated['minimum_stock_level']),
                        'last_updated_at' => now(),
                    ]
                );

            $this->storeInventoryLog($item, 'seed_stock', (int) $validated['quantity'], 'Initial clinic inventory stock');

            return $item;
        });

        return ApiResponse::success($this->formatInventoryItem($item->fresh(['product.company'])), 'Inventory item created successfully');
    }

    public function update(Request $request, int $inventory)
    {
        $item = $this->findInventoryItem($inventory);
        if (! $item) {
            return ApiResponse::error('Inventory item not found.', 404);
        }

        $validated = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:0'],
            'minimum_stock_level' => ['sometimes', 'integer', 'min:0'],
            'reorder_quantity' => ['sometimes', 'integer', 'min:0'],
            'unit' => ['sometimes', 'string', 'max:50'],
            'status' => ['sometimes', 'in:in_stock,low_stock,out_of_stock'],
        ]);

        DB::transaction(function () use ($item, $validated) {
            $oldQuantity = (int) $item->quantity;
            $quantity = array_key_exists('quantity', $validated) ? (int) $validated['quantity'] : $oldQuantity;
            $minimumStockLevel = array_key_exists('minimum_stock_level', $validated)
                ? (int) $validated['minimum_stock_level']
                : (int) $item->minimum_stock_level;

            $item->update([
                'quantity' => $quantity,
                'minimum_stock_level' => $minimumStockLevel,
                'reorder_quantity' => array_key_exists('reorder_quantity', $validated) ? (int) $validated['reorder_quantity'] : (int) $item->reorder_quantity,
                'unit' => $validated['unit'] ?? $item->unit,
                'status' => $validated['status'] ?? $this->resolveStatus($quantity, $minimumStockLevel),
                'last_updated_at' => now(),
            ]);

            if ($quantity !== $oldQuantity) {
                $this->storeInventoryLog($item->fresh(), 'quantity_update', abs($quantity - $oldQuantity), 'Clinic inventory quantity updated');
            }
        });

        return ApiResponse::success($this->formatInventoryItem($item->fresh(['product.company'])), 'Inventory item updated successfully');
    }

    public function scan(Request $request)
    {
        if (! $this->currentClinicId()) {
            return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
        }

        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:255'],
        ]);

        $material = $this->materialProductRepository->findCatalogByBarcode($validated['barcode']);
        if (! $material) {
            return ApiResponse::error('Material not found for the provided barcode.', 404, ['barcode' => ['Material not found.']]);
        }

        $inventoryItem = InventoryItem::query()
            ->withoutGlobalScopes()
            ->with(['product.company'])
            ->where('clinic_id', $this->currentClinicId())
            ->where('product_id', $material->id)
            ->first();

        return ApiResponse::success([
            'material' => [
                'id' => $material->id,
                'company_id' => $material->company_id,
                'company_name' => $material->company?->name,
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
            ],
            'inventory_item' => $inventoryItem ? $this->formatInventoryItem($inventoryItem) : null,
        ], 'Material fetched successfully');
    }

    private function currentClinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }

    private function findInventoryItem(int $inventoryId): ?InventoryItem
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return null;
        }

        return InventoryItem::query()
            ->with(['product.company'])
            ->withoutGlobalScopes()
            ->where('clinic_id', $clinicId)
            ->find($inventoryId);
    }

    private function storeInventoryLog(InventoryItem $item, string $action, int $amount, ?string $reason): void
    {
        InventoryLog::query()->withoutGlobalScopes()->create([
            'inventory_item_id' => $item->id,
            'company_id' => $item->company_id,
            'clinic_id' => $item->clinic_id,
            'user_id' => auth()->id(),
            'action' => $action,
            'amount' => $amount,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private function resolveStatus(int $quantity, int $minimumStockLevel): string
    {
        if ($quantity <= 0) {
            return 'out_of_stock';
        }

        if ($quantity <= $minimumStockLevel) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    private function formatInventoryItem(InventoryItem $item): array
    {
        return [
            'id' => $item->id,
            'clinic_id' => $item->clinic_id,
            'company_id' => $item->company_id,
            'product_id' => $item->product_id,
            'barcode' => $item->barcode,
            'product_name' => $item->product_name,
            'category_name' => $item->category_name,
            'description' => $item->description,
            'image_url' => $item->image_path ? asset('storage/' . $item->image_path) : ($item->product?->image_url ?? null),
            'quantity' => (int) $item->quantity,
            'minimum_stock_level' => (int) $item->minimum_stock_level,
            'reorder_quantity' => (int) ($item->reorder_quantity ?? 0),
            'unit' => $item->unit,
            'supplier' => $item->supplier,
            'status' => $item->status,
            'is_low_stock' => (int) $item->quantity <= (int) $item->minimum_stock_level,
            'inventory_value' => round((float) $item->quantity * (float) ($item->product?->price ?? 0), 2),
            'last_updated_at' => optional($item->last_updated_at)?->toISOString(),
            'created_at' => optional($item->created_at)?->toISOString(),
            'updated_at' => optional($item->updated_at)?->toISOString(),
        ];
    }
    public function destroy(int $inventory)
    {
        $item = $this->findInventoryItem($inventory);
        if (! $item) {
            return ApiResponse::error('Inventory item not found.', 404);
        }

        DB::transaction(function () use ($item) {
            $this->storeInventoryLog($item, 'deletion', (int) $item->quantity, 'Clinic inventory item deleted');
            $item->delete();
        });

        return ApiResponse::success(null, 'Inventory item deleted successfully');
    }
}
