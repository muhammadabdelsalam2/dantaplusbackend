<?php

namespace App\Http\Resources\Clinic\Insurance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsuranceCompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'name' => $this->name,
            'code' => $this->code,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'email' => $this->email,
            'coverage' => $this->coverage,
            'payment_terms' => $this->payment_terms,
            'status' => $this->is_active ? 'active' : 'inactive',
            'notes' => $this->notes,
            'is_active' => (bool) $this->is_active,
            'syndicate_price_list' => $this->syndicatePriceList ? [
                'id' => $this->syndicatePriceList->id,
                'name' => $this->syndicatePriceList->name,
                'year' => $this->syndicatePriceList->year,
            ] : null,
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ], static fn ($value) => $value !== null);
    }
}
