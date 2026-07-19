<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PatientRadiologyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $imageUrl = $this->fileUrl($this->file_path);
        $status = Str::lower((string) $this->status);

        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'patient_id' => $this->patient_id,
            'modality' => $this->modality,
            'notes' => $this->notes,
            'status' => $this->status,
            'file_path' => $this->file_path,
            'image_url' => $imageUrl,
            'before_image_url' => in_array($status, ['before', 'pre', 'pre-treatment'], true) ? $imageUrl : null,
            'after_image_url' => in_array($status, ['after', 'post', 'post-treatment'], true) ? $imageUrl : null,
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }

    private function fileUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = trim($path);

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (Str::startsWith($path, 'storage/')) {
            return url($path);
        }

        if (Str::startsWith($path, 'public/')) {
            $path = Str::after($path, 'public/');
        }

        return url(Storage::url($path));
    }
}
