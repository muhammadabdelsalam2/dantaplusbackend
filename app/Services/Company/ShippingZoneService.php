<?php

namespace App\Services\Company;

use App\Http\Resources\Company\ShippingZoneResource;
use App\Models\ShippingZone;

class ShippingZoneService
{
    public function index(): array
    {
        return ShippingZoneResource::collection(ShippingZone::query()->latest('id')->get())->resolve();
    }

    public function create(array $data): array
    {
        $data['company_id'] = auth()->user()->company_id;
        return (new ShippingZoneResource(ShippingZone::create($data)))->resolve();
    }

    public function update(ShippingZone $zone, array $data): array
    {
        $zone->update($data);
        return (new ShippingZoneResource($zone->fresh()))->resolve();
    }

    public function delete(ShippingZone $zone): void
    {
        $zone->delete();
    }

    public function toggle(ShippingZone $zone): array
    {
        $zone->update(['is_active' => ! $zone->is_active]);
        return (new ShippingZoneResource($zone->fresh()))->resolve();
    }
}
