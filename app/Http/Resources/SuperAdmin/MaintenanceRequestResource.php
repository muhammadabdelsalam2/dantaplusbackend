<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_code' => $this->request_code,
            'clinic_id' => $this->clinic_id,
            'clinic_name' => $this->clinic?->name,
            'equipment_id' => $this->equipment_id,
            'equipment' => $this->equipmentRecord?->name ?? $this->equipment,
            'malfunction_type' => $this->malfunction_type,
            'description' => $this->issue_description,
            'urgency' => $this->urgency,
            'attachment_url' => $this->attachment_url,
            'status' => $this->status,
            'assigned_company_id' => $this->assigned_company_id,
            'assigned_company_name' => $this->company?->name,
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
