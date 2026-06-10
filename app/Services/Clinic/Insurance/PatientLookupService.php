<?php

namespace App\Services\Clinic\Insurance;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PatientLookupService
{
    /**
     * Search patients by various criteria
     */
    public function search(int $clinicId, ?int $patientId = null, ?string $patientNumber = null, ?string $query = null, int $limit = 10): Collection
    {
        $builder = Patient::where('clinic_id', $clinicId)
            ->with(['user', 'appointments' => function ($q) {
                $q->latest()->limit(5);
            }, 'invoices' => function ($q) {
                $q->latest()->limit(5);
            }]);

        if ($patientId) {
            $builder->where('id', $patientId);
        }

        if ($patientNumber) {
            $builder->where('patient_number', $patientNumber);
        }

        if ($query) {
            $builder->where(function (Builder $q) use ($query) {
                $q->where('patient_number', 'like', "%{$query}%")
                    ->orWhere('insurance_number', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%")
                    ->orWhereHas('user', function (Builder $userQ) use ($query) {
                        $userQ->where('name', 'like', "%{$query}%");
                    });
            });
        }

        return $builder->limit($limit)->get();
    }

    /**
     * Format patient data with related information
     */
    public function format(Patient $patient): array
    {
        return [
            'id' => $patient->id,
            'patient_number' => $patient->patient_number,
            'name' => $patient->user?->name,
            'email' => $patient->user?->email,
            'phone' => $patient->phone,
            'date_of_birth' => $patient->date_of_birth?->toDateString(),
            'gender' => $patient->gender,
            'address' => $patient->address,
            'insurance_provider' => $patient->insurance_provider,
            'insurance_number' => $patient->insurance_number,
            'medical_history' => $patient->medical_history,
            'allergies' => $patient->allergies,
            'current_medication' => $patient->current_medication,
            'notes' => $patient->notes,
            'recent_appointments' => $patient->appointments->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'appointment_at' => $appointment->appointment_at?->toISOString(),
                    'doctor' => $appointment->doctor?->name,
                    'status' => $appointment->status,
                ];
            })->toArray(),
            'recent_invoices' => $patient->invoices->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'total' => (float) $invoice->total,
                    'status' => $invoice->status,
                    'created_at' => $invoice->created_at?->toDateString(),
                ];
            })->toArray(),
            'created_at' => $patient->created_at?->toISOString(),
        ];
    }
}
