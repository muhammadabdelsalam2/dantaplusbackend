<?php

namespace App\Services\Clinic\Settings;

use App\DTOs\Clinic\Settings\ServicePricingData;
use App\Http\Resources\Clinic\Settings\ServicePricingResource;
use App\Models\Category;
use App\Models\ClinicServicePrice;
use App\Models\Service;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClinicServicePricingService
{
    public function index(): array
    {
        $clinicId = $this->currentClinicId();

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $services = $this->serviceQuery($clinicId)->get();

        return ServiceResult::success([
            'categories' => Category::query()
                ->whereIn('id', $services->pluck('category_id')->filter()->unique()->values())
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->values()
                ->all(),
            'services' => ServicePricingResource::collection($services)->resolve(),
        ], 'Service pricing fetched successfully');
    }

    public function store(array $data): array
    {
        $clinicId = $this->currentClinicId();

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $dto = ServicePricingData::fromArray($data);

        $service = DB::transaction(function () use ($clinicId, $data, $dto) {
            if ($dto->serviceId) {
                $service = $this->findAccessibleService($clinicId, $dto->serviceId);

                if (! $service) {
                    return null;
                }
            } else {
                $service = Service::query()->create([
                    'category_id' => $this->resolveCategoryId($data),
                    'name' => $dto->name,
                    'slug' => $this->uniqueSlug($dto->name ?? 'service', $clinicId),
                    'description' => $dto->description,
                    'base_price' => $dto->price ?? 0,
                    'is_base' => false,
                    'created_by_clinic_id' => $clinicId,
                    'is_active' => $dto->isActive ?? true,
                ]);
            }

            ClinicServicePrice::query()->updateOrCreate(
                [
                    'clinic_id' => $clinicId,
                    'service_id' => $service->id,
                ],
                [
                    'price' => $dto->price ?? 0,
                    'cost' => $dto->cost ?? 0,
                    'lab_cost' => $dto->labCost ?? 0,
                    'has_lab' => $dto->hasLab ?? false,
                ]
            );

            return $service;
        });

        if (! $service) {
            return ServiceResult::error('Service not found.', null, ['service_id' => ['Service not found.']], 404);
        }

        return ServiceResult::success(
            (new ServicePricingResource($this->loadServiceForClinic($service->id, $clinicId, true)))->resolve(),
            'Service pricing created successfully',
            201
        );
    }

    public function update(int $serviceId, array $data): array
    {
        $clinicId = $this->currentClinicId();

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $dto = ServicePricingData::fromArray($data);

        $service = $this->findAccessibleService($clinicId, $serviceId);

        if (! $service) {
            return ServiceResult::error('Service not found.', null, null, 404);
        }

        if ($service->is_base && array_intersect(array_keys($data), ['name', 'category_id', 'category_name', 'description', 'is_active']) !== []) {
            return ServiceResult::error(
                'Base service details cannot be modified. Update the clinic price only.',
                null,
                ['service_id' => ['Base service details cannot be modified.']],
                422
            );
        }

        DB::transaction(function () use ($clinicId, $service, $data, $dto) {
            if (! $service->is_base) {
                $payload = [];

                foreach (['name', 'description', 'is_active'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $payload[$field] = $data[$field];
                    }
                }

                if (array_key_exists('name', $payload)) {
                    $payload['slug'] = $this->uniqueSlug($payload['name'], $clinicId, $service->id);
                }

                if (array_key_exists('category_id', $data) || array_key_exists('category_name', $data)) {
                    $payload['category_id'] = $this->resolveCategoryId($data);
                }

                if (array_key_exists('price', $data)) {
                    $payload['base_price'] = $data['price'];
                }

                if ($payload !== []) {
                    $service->update($payload);
                }
            }

            if (array_intersect(['price', 'cost', 'lab_cost', 'has_lab'], array_keys($data)) !== []) {
                ClinicServicePrice::query()->updateOrCreate(
                    [
                        'clinic_id' => $clinicId,
                        'service_id' => $service->id,
                    ],
                    [
                        'price' => $dto->price ?? (float) (ClinicServicePrice::query()->where('clinic_id', $clinicId)->where('service_id', $service->id)->value('price') ?? $service->base_price ?? 0),
                        'cost' => array_key_exists('cost', $data) ? ($dto->cost ?? 0) : (float) (ClinicServicePrice::query()->where('clinic_id', $clinicId)->where('service_id', $service->id)->value('cost') ?? 0),
                        'lab_cost' => array_key_exists('lab_cost', $data) ? ($dto->labCost ?? 0) : (float) (ClinicServicePrice::query()->where('clinic_id', $clinicId)->where('service_id', $service->id)->value('lab_cost') ?? 0),
                        'has_lab' => array_key_exists('has_lab', $data) ? ($dto->hasLab ?? false) : (bool) (ClinicServicePrice::query()->where('clinic_id', $clinicId)->where('service_id', $service->id)->value('has_lab') ?? false),
                    ]
                );
            }
        });

        return ServiceResult::success(
            (new ServicePricingResource($this->loadServiceForClinic($service->id, $clinicId, true)))->resolve(),
            'Service pricing updated successfully'
        );
    }

    public function destroy(int $id): array
    {
        $clinicId = $this->currentClinicId();

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $override = ClinicServicePrice::query()
            ->with('service')
            ->where('clinic_id', $clinicId)
            ->find($id);

        $service = $override?->service ?: $this->findAccessibleService($clinicId, $id);

        if (! $service) {
            return ServiceResult::error('Service pricing not found.', null, null, 404);
        }

        DB::transaction(function () use ($clinicId, $service, $override) {
            if ($override) {
                $override->delete();
            } else {
                ClinicServicePrice::query()
                    ->where('clinic_id', $clinicId)
                    ->where('service_id', $service->id)
                    ->delete();
            }

            if (! $service->is_base) {
                $service->delete();
            }
        });

        return ServiceResult::success(null, 'Service pricing deleted successfully');
    }

    private function currentClinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }

    private function serviceQuery(int $clinicId)
    {
        return Service::query()
            ->with([
                'category:id,name,slug',
                'clinicPrices' => fn ($query) => $query
                    ->where('clinic_id', $clinicId)
                    ->select(['id', 'clinic_id', 'service_id', 'price', 'cost', 'lab_cost', 'has_lab']),
            ])
            ->where(function ($query) use ($clinicId) {
                $query->where('is_base', true)
                    ->orWhere('created_by_clinic_id', $clinicId);
            })
            ->where('is_active', true)
            ->orderBy('category_id')
            ->orderBy('name');
    }

    private function findAccessibleService(int $clinicId, int $serviceId): ?Service
    {
        return Service::query()
            ->whereKey($serviceId)
            ->where(function ($query) use ($clinicId) {
                $query->where('is_base', true)
                    ->orWhere('created_by_clinic_id', $clinicId);
            })
            ->first();
    }

    private function loadServiceForClinic(int $serviceId, int $clinicId, bool $includeInactive = false): ?Service
    {
        return Service::query()
            ->with([
                'category:id,name,slug',
                'clinicPrices' => fn ($query) => $query
                    ->where('clinic_id', $clinicId)
                    ->select(['id', 'clinic_id', 'service_id', 'price', 'cost', 'lab_cost', 'has_lab']),
            ])
            ->whereKey($serviceId)
            ->where(function ($query) use ($clinicId) {
                $query->where('is_base', true)
                    ->orWhere('created_by_clinic_id', $clinicId);
            })
            ->when(! $includeInactive, fn ($query) => $query->where('is_active', true))
            ->first();
    }

    private function resolveCategoryId(array $data): ?int
    {
        if (! empty($data['category_id'])) {
            return (int) $data['category_id'];
        }

        if (empty($data['category_name'])) {
            return null;
        }

        $name = trim((string) $data['category_name']);
        $slug = 'clinic-service-' . Str::slug($name);

        return Category::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'status' => 'active']
        )->id;
    }

    private function uniqueSlug(string $name, int $clinicId, ?int $ignoreServiceId = null): string
    {
        $base = Str::slug($name);
        $slug = $base !== '' ? $base : ('service-' . Str::lower(Str::random(6)));
        $original = $slug;
        $counter = 1;

        while (Service::query()
            ->where('slug', $slug)
            ->when($ignoreServiceId, fn ($query) => $query->where('id', '!=', $ignoreServiceId))
            ->where(function ($query) use ($clinicId) {
                $query->where('is_base', true)
                    ->orWhere('created_by_clinic_id', $clinicId);
            })
            ->exists()) {
            $slug = $original . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
