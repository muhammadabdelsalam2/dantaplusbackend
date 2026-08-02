<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
       return [
    'id'           => $this->id,
    'clinic_id'    => $this->clinic_id,
    'name'         => $this->name,
'image_url' => $this->image_url ? asset($this->image_url) : null,
    'status'       => $this->status,
    'open_reports' => (int) ($this->open_reports_count ?? $this->maintenance_requests_count ?? 0),
    'show_fix_now' => (int) ($this->open_reports_count ?? $this->maintenance_requests_count ?? 0) > 0 || $this->status !== \App\Models\Equipment::STATUS_OPERATIONAL,
    'created_at'   => optional($this->created_at)?->toISOString(),
    'updated_at'   => optional($this->updated_at)?->toISOString(),
];
    }
}
