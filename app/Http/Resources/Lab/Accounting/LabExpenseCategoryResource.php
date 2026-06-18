<?php

namespace App\Http\Resources\Lab\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabExpenseCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lab_id' => $this->lab_id,
            'name' => $this->name,
            'status' => $this->status,
        ];
    }
}
