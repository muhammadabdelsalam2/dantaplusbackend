<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'caseNumber' => $this->case_number,
            'clinicId' => $this->clinic_id,
            'labId' => $this->lab_id,
            'patientId' => $this->patient_id,
            'dentistId' => $this->dentist_id,
            'status' => $this->status,
            'priority' => $this->priority,
            'dueDate' => optional($this->due_date)->toDateString(),
            'caseType' => $this->case_type,
            'toothNumbers' => $this->tooth_numbers,
            'description' => $this->description,
            'assignedTechnicianId' => $this->assigned_technician_id,
            'assignedDeliveryId' => $this->assigned_delivery_id,
            'createdBy' => $this->created_by,
            'completedAt' => optional($this->completed_at)->toISOString(),
            'deliveredAt' => optional($this->delivered_at)->toISOString(),
            'createdAt' => optional($this->created_at)->toISOString(),
            'updatedAt' => optional($this->updated_at)->toISOString(),

            'clinic' => $this->whenLoaded('clinic', fn () => [
                'id' => $this->clinic?->id,
                'name' => $this->clinic?->name,
            ]),
            'lab' => $this->whenLoaded('lab', fn () => [
                'id' => $this->lab?->id,
                'name' => $this->lab?->name,
            ]),
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient?->id,
                'name' => $this->patient?->user?->name,
            ]),
            'dentist' => $this->whenLoaded('dentist', fn () => [
                'id' => $this->dentist?->id,
                'name' => $this->dentist?->user?->name,
            ]),
            'technician' => $this->whenLoaded('technician', fn () => [
                'id' => $this->technician?->id,
                'name' => $this->technician?->name,
            ]),
            'deliveryRep' => $this->whenLoaded('deliveryRep', fn () => [
                'id' => $this->deliveryRep?->id,
                'name' => $this->deliveryRep?->name,
            ]),
            'attachments' => $this->whenLoaded('attachments', fn () => CaseAttachmentResource::collection($this->attachments)),
        ];
    }
}
