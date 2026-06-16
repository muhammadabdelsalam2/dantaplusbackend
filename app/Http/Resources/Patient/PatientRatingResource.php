<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientRatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'clinic_id' => $this->clinic_id,
            'appointment_id' => $this->appointment_id,
            'doctor_user_id' => $this->doctor_user_id,
            'doctor_rating' => (int) $this->doctor_rating,
            'clinic_rating' => (int) $this->clinic_rating,
            'comment' => $this->comment,
            'created_at' => optional($this->created_at)?->toISOString(),
        ];
    }
}
