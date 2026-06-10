<?php

namespace App\Repositories\Clinic\Insurance;

use App\Models\Clinic\Insurance\InsuranceClaim;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class InsuranceClaimRepository
{
    public function listForClinic(int $clinicId, array $filters = []): Collection
    {
        return InsuranceClaim::query()
            ->with([
                'company:id,name,code',
                'patient.user:id,name',
                'appointment:id,appointment_at',
                'invoice:id,invoice_number,total,status',
                'items',
                'patientConsent',
            ])
            ->where('clinic_id', $clinicId)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['patient_id'] ?? null, fn (Builder $query, int $patientId) => $query->where('patient_id', $patientId))
            ->when($filters['insurance_company_id'] ?? null, fn (Builder $query, int $companyId) => $query->where('insurance_company_id', $companyId))
            ->latest('id')
            ->get();
    }

    public function findForClinic(int $clinicId, int $claimId): ?InsuranceClaim
    {
        return InsuranceClaim::query()
            ->with([
                'company:id,name,code',
                'patient.user:id,name',
                'appointment:id,appointment_at',
                'invoice:id,invoice_number,total,status',
                'creator:id,name',
                'updater:id,name',
                'items',
                'patientConsent',
            ])
            ->where('clinic_id', $clinicId)
            ->find($claimId);
    }

    public function create(array $attributes): InsuranceClaim
    {
        return InsuranceClaim::query()->create($attributes);
    }

    public function update(InsuranceClaim $claim, array $attributes): InsuranceClaim
    {
        $claim->update($attributes);

        return $claim->refresh()->load([
            'company:id,name,code',
            'patient.user:id,name',
            'appointment:id,appointment_at',
            'invoice:id,invoice_number,total,status',
            'creator:id,name',
            'updater:id,name',
            'items',
            'patientConsent',
        ]);
    }

    public function delete(InsuranceClaim $claim): void
    {
        $claim->delete();
    }
}
