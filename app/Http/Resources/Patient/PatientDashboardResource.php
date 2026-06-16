<?php

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'patient' => new PatientProfileResource($this['patient']),
            'upcoming_appointments_count' => $this['upcoming_appointments_count'],
            'past_appointments_count' => $this['past_appointments_count'],
            'latest_appointment' => $this['latest_appointment']
                ? new PatientAppointmentResource($this['latest_appointment'])
                : null,
            'latest_invoice' => $this['latest_invoice']
                ? new PatientInvoiceResource($this['latest_invoice'])
                : null,
            'total_paid' => (float) $this['total_paid'],
            'remaining_balance' => (float) $this['remaining_balance'],
            'latest_insurance_claim' => $this['latest_insurance_claim']
                ? new PatientInsuranceClaimResource($this['latest_insurance_claim'])
                : null,
            'documents_count' => $this['documents_count'],
            'alerts' => $this['alerts'],
        ];
    }
}
