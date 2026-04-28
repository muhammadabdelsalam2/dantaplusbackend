<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ClinicDentalLabGalleryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lab_id' => $this->lab_id,
            'dental_lab_id' => $this->lab_id,
            'provider_id' => $this->lab_id,
            'type' => $this->type,
            'image_path' => $this->url,
            'image_url' => str_starts_with($this->url, 'http')
                ? $this->url
                : Storage::disk($this->disk ?? 'public')->url($this->url),
        ];
    }
}
