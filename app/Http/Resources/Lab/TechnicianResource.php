<?php

namespace App\Http\Resources\Lab;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TechnicianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name ?? '',
            'image_url' => $this->avatar_url ?? null,
            'role' => 'Technician',
        ];
    }
}
