<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class PatientRadiologyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'patient_id' => $this->patient_id,
            'modality' => $this->modality,
            'notes' => $this->notes,
            'status' => $this->status,
            'file_path' => $this->file_path,
// PatientRadiologyResource
'image_url' => URL::temporarySignedRoute('patient.radiology.download.signed', now()->addDays(7), ['id' => $this->id]),
'before_image_url' => $this->before_image_path
    ? URL::temporarySignedRoute('patient.radiology.download.image.signed', now()->addDays(7), ['id' => $this->id, 'type' => 'before'])
    : null,
'after_image_url' => $this->after_image_path
    ? URL::temporarySignedRoute('patient.radiology.download.image.signed', now()->addDays(7), ['id' => $this->id, 'type' => 'after'])
    : null,
        ];
    }

}
