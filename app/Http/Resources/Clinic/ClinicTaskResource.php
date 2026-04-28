<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $doctorUser = $this->assigneeDoctor?->user;

        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'due_date' => optional($this->due_date)?->toDateString(),
            'assignee' => $this->assigneeUser ? [
                'type' => 'user',
                'id' => $this->assigneeUser->id,
                'name' => $this->assigneeUser->name,
            ] : ($doctorUser ? [
                'type' => 'doctor',
                'id' => $this->assigneeDoctor->id,
                'user_id' => $doctorUser->id,
                'name' => $doctorUser->name,
            ] : null),
            'created_by' => $this->creator?->name,
            'created_at' => optional($this->created_at)?->toISOString(),
        ];
    }
}
