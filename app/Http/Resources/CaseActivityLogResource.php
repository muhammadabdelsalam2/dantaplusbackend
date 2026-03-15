<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'caseId' => $this->case_id,
            'actorId' => $this->actor_id,
            'actorName' => $this->actor_name,
            'action' => $this->action,
            'oldStatus' => $this->old_status,
            'newStatus' => $this->new_status,
            'notes' => $this->notes,
            'payload' => $this->payload,
            'createdAt' => optional($this->created_at)->toISOString(),
        ];
    }
}
