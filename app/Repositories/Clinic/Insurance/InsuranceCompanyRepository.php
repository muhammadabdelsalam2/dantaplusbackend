<?php

namespace App\Repositories\Clinic\Insurance;

use App\Models\Clinic\Insurance\InsuranceCompany;
use Illuminate\Database\Eloquent\Collection;

class InsuranceCompanyRepository
{
    public function listForClinic(int $clinicId): Collection
    {
        return InsuranceCompany::query()
            ->with('syndicatePriceList:id,name,year')
            ->where('clinic_id', $clinicId)
            ->latest('id')
            ->get();
    }

    public function findForClinic(int $clinicId, int $companyId): ?InsuranceCompany
    {
        return InsuranceCompany::query()
            ->with('syndicatePriceList:id,name,year')
            ->where('clinic_id', $clinicId)
            ->find($companyId);
    }

    public function create(array $attributes): InsuranceCompany
    {
        return InsuranceCompany::query()->create($attributes);
    }

    public function update(InsuranceCompany $company, array $attributes): InsuranceCompany
    {
        $company->update($attributes);

        return $company->refresh()->load('syndicatePriceList:id,name,year');
    }

    public function delete(InsuranceCompany $company): void
    {
        $company->delete();
    }
}
