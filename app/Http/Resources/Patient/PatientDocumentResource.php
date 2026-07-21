<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class PatientDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'patient_id' => $this->patient_id,
            'document_type' => $this->document_type,
            'title' => $this->title,
            'file_path' => $this->file_path,
            // PatientDocumentResource
'file_url' => URL::temporarySignedRoute('patient.documents.download.signed', now()->addDays(7), ['id' => $this->id]),
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'notes' => $this->notes,
            'created_at' => optional($this->created_at)?->toISOString(),
        ], static fn ($value) => $value !== null);
    }
}
