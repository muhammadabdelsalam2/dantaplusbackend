<?php

namespace App\Http\Resources\Lab\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $images = $this->relationLoaded('galleryImages') ? $this->galleryImages : collect();
        $beforeImages = $images->filter(fn ($image) => ($image->type?->value ?? $image->type) === 'before')->values();
        $afterImages = $images->filter(fn ($image) => ($image->type?->value ?? $image->type) === 'after')->values();

        return [
            'lab_id' => $this->id,
            'lab_name' => $this->name,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'working_hours' => $this->working_hours,
            'logo_url' => $this->logo_url,
            'before_images' => $beforeImages->pluck('url')->values()->all(),
            'after_images' => $afterImages->pluck('url')->values()->all(),
            'gallery' => [
                'before_images' => $beforeImages->map(fn ($image) => [
                    'id' => $image->id,
                    'url' => $image->url,
                    'sort_order' => $image->sort_order,
                ])->values()->all(),
                'after_images' => $afterImages->map(fn ($image) => [
                    'id' => $image->id,
                    'url' => $image->url,
                    'sort_order' => $image->sort_order,
                ])->values()->all(),
            ],
        ];
    }
}
