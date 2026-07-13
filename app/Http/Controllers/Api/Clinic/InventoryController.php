<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryLog;
use App\Models\MaterialCompany;
use App\Repositories\MaterialProductRepository;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

    $categoryNames = collect(config('material_market.product_category_items', []))
        ->pluck('label')
        ->filter()
        ->values();

    $validated = $request->validate([
        'material_product_id' => ['nullable', 'integer', 'exists:material_products,id'],
        'material_name' => ['required_without:material_product_id', 'nullable', 'string', 'max:255'],
        'category' => ['nullable', 'string', Rule::in($categoryNames)],
        'initial_qty' => ['required_without:quantity', 'nullable', 'integer', 'min:0'],
        'quantity' => ['required_without:initial_qty', 'nullable', 'integer', 'min:0'],
        'unit_type' => ['required_without:unit', 'nullable', 'string', 'max:50'],
        'unit' => ['required_without:unit_type', 'nullable', 'string', 'max:50'],
        'consumption_per_case' => ['nullable', 'numeric', 'min:0'],
        'min_threshold' => ['required_without:minimum_stock_level', 'nullable', 'integer', 'min:0'],
        'minimum_stock_level' => ['required_without:min_threshold', 'nullable', 'integer', 'min:0'],
        'reorder_qty' => ['nullable', 'integer', 'min:0'],
        'reorder_quantity' => ['nullable', 'integer', 'min:0'],
        'auto_purchase' => ['nullable', 'boolean'],
        'unit_price' => ['nullable', 'numeric', 'min:0'],
        'supplier' => [
            'required_without:material_product_id',
            'nullable',
            'string',
            'max:255',
            Rule::exists('material_companies', 'name'),
        ],
        'status' => ['nullable', 'in:in_stock,low_stock,out_of_stock'],
    ], [
        'supplier.exists' => 'Supplier must match an existing material company.',
        'category.in' => 'Category must be one of the predefined categories.',
    ]);

    $material = ! empty($validated['material_product_id'])
        ? $this->materialProductRepository->findCatalogById((int) $validated['material_product_id'])
        : null;

    if (! empty($validated['material_product_id']) && ! $material) {
        return ApiResponse::error('Material not found.', 422, ['material_product_id' => ['Material not found.']]);
    }

    $supplierCompany = $material?->company;
    if (! $supplierCompany && ! empty($validated['supplier'])) {
        $supplierCompany = MaterialCompany::query()
            ->where('name', $validated['supplier'])
            ->first();
    }

    if (! $supplierCompany) {
        return ApiResponse::error('Supplier not found.', 422, [
            'supplier' => ['Supplier must match an existing material company when material_product_id is not provided.'],
        ]);
    }

    $quantity = (int) ($validated['initial_qty'] ?? $validated['quantity'] ?? 0);
    $minimumStockLevel = (int) ($validated['min_threshold'] ?? $validated['minimum_stock_level'] ?? 0);
    $reorderQuantity = (int) ($validated['reorder_qty'] ?? $validated['reorder_quantity'] ?? 0);
    $unit = $validated['unit_type'] ?? $validated['unit'] ?? 'piece';

    $attributes = [
        'company_id' => $supplierCompany->id,
        'barcode' => $material?->barcode,
        'product_name' => $material?->name ?? $validated['material_name'],
        'category_name' => $material?->category ?? ($validated['category'] ?? null),
        'description' => $material?->description,
        'quantity' => $quantity,
        'minimum_stock_level' => $minimumStockLevel,
        'reorder_quantity' => $reorderQuantity,
        'unit' => $unit,
        'consumption_per_case' => $validated['consumption_per_case'] ?? null,
        'auto_purchase' => (bool) ($validated['auto_purchase'] ?? false),
        'supplier' => $supplierCompany->name,
        'unit_price' => $validated['unit_price'] ?? ($material?->price),
        'status' => $validated['status'] ?? $this->resolveStatus($quantity, $minimumStockLevel),
        'last_updated_at' => now(),
    ];

    $item = DB::transaction(function () use ($clinicId, $material, $attributes, $quantity) {
        $item = $material
            ? InventoryItem::query()
                ->withoutGlobalScopes()
                ->updateOrCreate(
                    [
                        'clinic_id' => $clinicId,
                        'product_id' => $material->id,
                    ],
                    $attributes
                )
            : InventoryItem::query()
                ->withoutGlobalScopes()
                ->create(array_merge($attributes, [
                    'clinic_id' => $clinicId,
                    'product_id' => null,
                ]));

        $this->storeInventoryLog($item, 'seed_stock', $quantity, 'Initial clinic inventory stock');

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
            'initial_qty' => ['sometimes', 'integer', 'min:0'],
            'minimum_stock_level' => ['sometimes', 'integer', 'min:0'],
            'min_threshold' => ['sometimes', 'integer', 'min:0'],
            'reorder_quantity' => ['sometimes', 'integer', 'min:0'],
            'reorder_qty' => ['sometimes', 'integer', 'min:0'],
            'unit' => ['sometimes', 'string', 'max:50'],
            'unit_type' => ['sometimes', 'string', 'max:50'],
            'material_name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'consumption_per_case' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'auto_purchase' => ['sometimes', 'boolean'],
            'unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'supplier' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:in_stock,low_stock,out_of_stock'],
        ]);

        DB::transaction(function () use ($item, $validated) {
            $oldQuantity = (int) $item->quantity;
            $quantity = array_key_exists('initial_qty', $validated)
                ? (int) $validated['initial_qty']
                : (array_key_exists('quantity', $validated) ? (int) $validated['quantity'] : $oldQuantity);
            $minimumStockLevel = array_key_exists('min_threshold', $validated)
                ? (int) $validated['min_threshold']
                : (array_key_exists('minimum_stock_level', $validated) ? (int) $validated['minimum_stock_level'] : (int) $item->minimum_stock_level);

            $reorderQuantity = array_key_exists('reorder_qty', $validated)
                ? (int) $validated['reorder_qty']
                : (array_key_exists('reorder_quantity', $validated) ? (int) $validated['reorder_quantity'] : (int) $item->reorder_quantity);

            $unit = array_key_exists('unit_type', $validated)
                ? $validated['unit_type']
                : ($validated['unit'] ?? $item->unit);

            $supplier = $validated['supplier'] ?? $item->supplier;
            $supplierCompanyId = $item->company_id;
            if (array_key_exists('supplier', $validated) && $validated['supplier']) {
                $supplierCompany = MaterialCompany::query()->where('name', $validated['supplier'])->first();
                if ($supplierCompany) {
                    $supplierCompanyId = $supplierCompany->id;
                    $supplier = $supplierCompany->name;
                }
            }

            $item->update([
                'company_id' => $supplierCompanyId,
                'product_name' => $validated['material_name'] ?? $item->product_name,
                'category_name' => array_key_exists('category', $validated) ? $validated['category'] : $item->category_name,
                'quantity' => $quantity,
                'minimum_stock_level' => $minimumStockLevel,
                'reorder_quantity' => $reorderQuantity,
                'unit' => $unit,
                'consumption_per_case' => array_key_exists('consumption_per_case', $validated)
                    ? $validated['consumption_per_case']
                    : $item->consumption_per_case,
                'auto_purchase' => array_key_exists('auto_purchase', $validated)
                    ? (bool) $validated['auto_purchase']
                    : (bool) $item->auto_purchase,
                'supplier' => $supplier,
                'unit_price' => array_key_exists('unit_price', $validated) ? $validated['unit_price'] : $item->unit_price,
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
            'consumption_per_case' => $item->consumption_per_case !== null ? (float) $item->consumption_per_case : null,
            'auto_purchase' => (bool) ($item->auto_purchase ?? false),
            'supplier' => $item->supplier,
            'unit_price' => $item->unit_price !== null ? (float) $item->unit_price : null,
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
