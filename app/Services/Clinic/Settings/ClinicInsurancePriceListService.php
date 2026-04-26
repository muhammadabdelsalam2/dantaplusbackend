<?php

namespace App\Services\Clinic\Settings;

use App\DTOs\Clinic\Settings\InsurancePriceListItemData;
use App\Http\Resources\Clinic\Settings\InsurancePriceListResource;
use App\Models\Category;
use App\Models\InsurancePriceList;
use App\Models\InsurancePriceListImportLog;
use App\Models\InsurancePriceListItem;
use App\Models\Service;
use App\Support\ServiceResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ZipArchive;

class ClinicInsurancePriceListService
{
    public function index(?int $year = null): array
    {
        $clinicId = $this->currentClinicId();

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $lists = InsurancePriceList::query()
            ->with(['items' => fn ($query) => $query->with('category:id,name,slug')->orderBy('service_name')])
            ->withCount('items')
            ->where('clinic_id', $clinicId)
            ->when($year, fn (Builder $query) => $query->where('year', $year))
            ->latest('year')
            ->latest('id')
            ->get();

        return ServiceResult::success(
            InsurancePriceListResource::collection($lists)->resolve(),
            'Insurance price lists fetched successfully'
        );
    }

    public function store(array $data): array
    {
        $clinicId = $this->currentClinicId();

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $list = DB::transaction(function () use ($clinicId, $data) {
            $list = InsurancePriceList::query()
                ->withTrashed()
                ->firstOrNew([
                    'clinic_id' => $clinicId,
                    'name' => $data['name'],
                    'year' => $data['year'],
                ]);

            $list->fill([
                'notes' => $data['notes'] ?? $list->notes,
                'is_active' => $data['is_active'] ?? $list->is_active ?? true,
            ]);
            $list->save();

            if ($list->trashed()) {
                $list->restore();
            }

            $this->upsertItems($list, $data['items'] ?? []);

            return $this->loadList($clinicId, $list->id);
        });

        return ServiceResult::success(
            (new InsurancePriceListResource($list))->resolve(),
            'Insurance price list saved successfully',
            201
        );
    }

    public function import(array $data, ?UploadedFile $file = null): array
    {
        $clinicId = $this->currentClinicId();
        $userId = auth()->id();

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $items = $data['items'] ?? null;

        if ($file) {
            $items = $this->parseImportFile($file);

            if ($items === []) {
                return ServiceResult::error(
                    'The uploaded file did not contain valid insurance price list rows.',
                    null,
                    ['file' => ['No valid rows were found in the uploaded file.']],
                    422
                );
            }
        }

        $data['items'] = $items ?? [];
        $data['source_file'] = $data['source_file'] ?? $file?->getClientOriginalName();

        $summary = DB::transaction(function () use ($clinicId, $userId, $data) {
            $items = $data['items'];
            $list = InsurancePriceList::query()
                ->withTrashed()
                ->firstOrNew([
                    'clinic_id' => $clinicId,
                    'name' => $data['name'],
                    'year' => $data['year'],
                ]);

            $isExisting = $list->exists;

            $list->fill([
                'notes' => $data['notes'] ?? $list->notes,
                'is_active' => true,
                'imported_at' => now(),
            ]);
            $list->save();

            if ($list->trashed()) {
                $list->restore();
            }

            [$createdCount, $updatedCount] = $this->upsertItems($list, $data['items']);

            InsurancePriceListImportLog::query()->create([
                'clinic_id' => $clinicId,
                'insurance_price_list_id' => $list->id,
                'import_key' => $data['import_key'] ?? null,
                'source_file' => $data['source_file'] ?? null,
                'payload' => [
                    'year' => $data['year'],
                    'name' => $data['name'],
                    'items_count' => count($items),
                ],
                'imported_count' => $createdCount,
                'updated_count' => $updatedCount + ($isExisting ? 1 : 0),
                'failed_count' => 0,
                'status' => 'completed',
                'created_by' => $userId,
            ]);

            return [
                'price_list' => (new InsurancePriceListResource($this->loadList($clinicId, $list->id)))->resolve(),
                'summary' => [
                    'created_items' => $createdCount,
                    'updated_items' => $updatedCount,
                    'processed_items' => count($items),
                ],
            ];
        }, 5);

        return ServiceResult::success($summary, 'Insurance price list imported successfully');
    }

    public function update(int $id, array $data): array
    {
        $clinicId = $this->currentClinicId();

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $list = InsurancePriceList::query()
            ->where('clinic_id', $clinicId)
            ->find($id);

        if (! $list) {
            return ServiceResult::error('Insurance price list not found.', null, null, 404);
        }

        DB::transaction(function () use ($clinicId, $list, $data) {
            $list->fill($data);
            $list->save();

            if (array_key_exists('items', $data)) {
                $this->upsertItems($list, $data['items']);
            }
        });

        return ServiceResult::success(
            (new InsurancePriceListResource($this->loadList($clinicId, $list->id)))->resolve(),
            'Insurance price list updated successfully'
        );
    }

    public function destroy(int $id): array
    {
        $clinicId = $this->currentClinicId();

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $list = InsurancePriceList::query()
            ->where('clinic_id', $clinicId)
            ->find($id);

        if (! $list) {
            return ServiceResult::error('Insurance price list not found.', null, null, 404);
        }

        $list->delete();

        return ServiceResult::success(null, 'Insurance price list deleted successfully');
    }

    private function upsertItems(InsurancePriceList $list, array $items): array
    {
        $createdCount = 0;
        $updatedCount = 0;

        foreach ($items as $item) {
            $dto = InsurancePriceListItemData::fromArray($item);
            $attributes = $this->resolveItemLookupAttributes($list->id, $dto);
            $serviceName = $dto->serviceName ?? $this->resolveServiceName($dto->serviceId);
            $resolvedCategoryId = $this->resolveCategoryId($dto);
            $resolvedCategoryName = $dto->categoryName ?? Category::query()->find($resolvedCategoryId)?->name;

            $existing = InsurancePriceListItem::query()->where($attributes)->first();

            InsurancePriceListItem::query()->updateOrCreate(
                $attributes,
                [
                    'service_id' => $dto->serviceId,
                    'code' => $dto->code,
                    'item_code' => $dto->code,
                    'service_name' => $serviceName,
                    'category_id' => $resolvedCategoryId,
                    'category_name' => $resolvedCategoryName,
                    'price' => $dto->price,
                    'notes' => $dto->notes,
                ]
            );

            $existing ? $updatedCount++ : $createdCount++;
        }

        return [$createdCount, $updatedCount];
    }

    private function resolveItemLookupAttributes(int $listId, InsurancePriceListItemData $item): array
    {
        if ($item->serviceId) {
            return [
                'insurance_price_list_id' => $listId,
                'service_id' => $item->serviceId,
            ];
        }

        if ($item->code) {
            return [
                'insurance_price_list_id' => $listId,
                'item_code' => $item->code,
            ];
        }

        return [
            'insurance_price_list_id' => $listId,
            'service_name' => $item->serviceName,
        ];
    }

    private function resolveServiceName(?int $serviceId): string
    {
        if (! $serviceId) {
            return 'Unknown Service';
        }

        return Service::query()->find($serviceId)?->name ?? 'Unknown Service';
    }

    private function resolveCategoryId(InsurancePriceListItemData $item): ?int
    {
        if ($item->categoryId) {
            return $item->categoryId;
        }

        if (! $item->categoryName) {
            return Service::query()->find($item->serviceId)?->category_id;
        }

        $name = trim($item->categoryName);
        $slug = 'insurance-category-' . Str::slug($name);

        return Category::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'status' => 'active']
        )->id;
    }

    private function loadList(int $clinicId, int $id): ?InsurancePriceList
    {
        return InsurancePriceList::query()
            ->with(['items' => fn ($query) => $query->with('category:id,name,slug')->orderBy('service_name')])
            ->withCount('items')
            ->where('clinic_id', $clinicId)
            ->find($id);
    }

    private function currentClinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }

    private function parseImportFile(UploadedFile $file): array
    {
        $extension = Str::lower($file->getClientOriginalExtension());

        return match ($extension) {
            'csv', 'txt' => $this->parseCsvFile($file),
            'xlsx' => $this->parseXlsxFile($file),
            default => [],
        };
    }

    private function parseCsvFile(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if (! $handle) {
            return [];
        }

        $headers = null;
        $items = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = $this->normalizeHeaders($row);
                continue;
            }

            $mapped = $this->mapImportedRow($headers, $row);

            if ($mapped !== null) {
                $items[] = $mapped;
            }
        }

        fclose($handle);

        return $items;
    }

    private function parseXlsxFile(UploadedFile $file): array
    {
        if (! class_exists(ZipArchive::class)) {
            return [];
        }

        $zip = new ZipArchive();

        if ($zip->open($file->getRealPath()) !== true) {
            return [];
        }

        $sharedStrings = [];
        $items = [];

        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml !== false) {
            $xml = simplexml_load_string($sharedStringsXml);
            if ($xml !== false) {
                foreach ($xml->si as $item) {
                    $sharedStrings[] = isset($item->t) ? (string) $item->t : collect($item->r ?? [])->pluck('t')->implode('');
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            return [];
        }

        $xml = simplexml_load_string($sheetXml);
        if ($xml === false || ! isset($xml->sheetData)) {
            return [];
        }

        $headers = null;

        foreach ($xml->sheetData->row as $row) {
            $cells = [];

            foreach ($row->c as $cell) {
                $value = '';

                if ((string) $cell['t'] === 's') {
                    $sharedIndex = (int) ($cell->v ?? 0);
                    $value = $sharedStrings[$sharedIndex] ?? '';
                } elseif (isset($cell->v)) {
                    $value = (string) $cell->v;
                }

                $cells[] = $value;
            }

            if ($headers === null) {
                $headers = $this->normalizeHeaders($cells);
                continue;
            }

            $mapped = $this->mapImportedRow($headers, $cells);

            if ($mapped !== null) {
                $items[] = $mapped;
            }
        }

        return $items;
    }

    private function normalizeHeaders(array $headers): array
    {
        return array_map(function ($header) {
            $header = Str::lower(trim((string) $header));

            return str_replace([' ', '-', '.'], '_', $header);
        }, $headers);
    }

    private function mapImportedRow(array $headers, array $row): ?array
    {
        $data = [];

        foreach ($headers as $index => $header) {
            $data[$header] = isset($row[$index]) ? trim((string) $row[$index]) : null;
        }

        $serviceId = $data['service_id'] ?? null;
        $serviceName = $data['service_name'] ?? $data['name'] ?? null;
        $price = $data['price'] ?? null;

        if ($price === null || $price === '') {
            return null;
        }

        if (($serviceId === null || $serviceId === '') && ($serviceName === null || $serviceName === '')) {
            return null;
        }

        return [
            'service_id' => ($serviceId !== null && $serviceId !== '') ? (int) $serviceId : null,
            'code' => $data['code'] ?? $data['item_code'] ?? null,
            'service_name' => $serviceName,
            'category_id' => isset($data['category_id']) && $data['category_id'] !== '' ? (int) $data['category_id'] : null,
            'category_name' => $data['category_name'] ?? $data['category'] ?? null,
            'price' => (float) $price,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
