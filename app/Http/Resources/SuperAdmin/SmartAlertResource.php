<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmartAlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->severity,
            'reviewed' => (bool) $this->is_reviewed,
            'created_at' => optional($this->created_at)->toISOString(),
        ];
    }
}
