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
            'progressTracker' => [
                ['key' => 'pending', 'label' => 'Pending', 'active' => $this->status === 'Pending'],
                ['key' => 'accepted', 'label' => 'Accepted', 'active' => $this->status === 'Accepted'],
                ['key' => 'in_progress', 'label' => 'In Progress', 'active' => $this->status === 'In Progress'],
                ['key' => 'completed', 'label' => 'Completed', 'active' => $this->status === 'Completed'],
                ['key' => 'delivered', 'label' => 'Delivered', 'active' => $this->status === 'Delivered'],
            ],

            'clinic' => $this->whenLoaded('clinic', fn () => [
                'id' => $this->clinic?->id,
                'name' => $this->clinic?->name,
                'contact' => $this->clinic?->phone,
                'email' => $this->clinic?->email,
                'address' => $this->clinic?->address,
            ]),
            'lab' => $this->whenLoaded('lab', fn () => [
                'id' => $this->lab?->id,
                'name' => $this->lab?->name,
            ]),
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient?->id,
                'name' => $this->patient?->user?->name,
                'age' => $this->patient?->age,
                'gender' => $this->patient?->gender,
            ]),
            'dentist' => $this->whenLoaded('dentist', fn () => [
                'id' => $this->dentist?->id,
                'name' => $this->dentist?->user?->name,
            ]),
            'technician' => $this->whenLoaded('technician', fn () => [
                'id' => $this->technician?->id,
                'name' => $this->technician?->name,
                'image_url' => $this->technician?->avatar_url ?? null,
                'role' => 'Technician',
            ]),
            'deliveryRep' => $this->whenLoaded('deliveryRep', fn () => [
                'id' => $this->deliveryRep?->id,
                'name' => $this->deliveryRep?->name,
            ]),
            'attachments' => $this->whenLoaded('attachments', fn () => CaseAttachmentResource::collection($this->attachments)),
            'patientInfo' => $this->whenLoaded('patient', fn () => [
                'name' => $this->patient?->user?->name,
                'age' => $this->patient?->age,
                'gender' => $this->patient?->gender,
            ]),
            'originatingClinic' => $this->whenLoaded('clinic', fn () => [
                'name' => $this->clinic?->name,
                'contact' => $this->clinic?->phone,
                'email' => $this->clinic?->email,
            ]),
            'caseVitals' => [
                'caseType' => $this->case_type,
                'toothNumber' => $this->tooth_numbers,
                'dueDate' => optional($this->due_date)->toDateString(),
                'priority' => $this->priority,
                'assignedTo' => $this->technician?->name,
            ],
            'toothChart3d' => $this->tooth_chart_3d,
            'activityLogs' => $this->whenLoaded('activityLogs', fn () => $this->activityLogs->map(fn ($log) => [
                'id' => $log->id,
                'actor_id' => $log->actor_id,
                'actor_name' => $log->actor_name,
                'action' => $log->action,
                'old_status' => $log->old_status,
                'new_status' => $log->new_status,
                'notes' => $log->notes,
                'payload' => $log->payload,
                'created_at' => optional($log->created_at)->toISOString(),
            ])),
        ];
    }
}
