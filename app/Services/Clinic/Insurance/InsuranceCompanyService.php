<?php

namespace App\Services\Clinic\Insurance;

use App\DTOs\Clinic\Insurance\InsuranceCompanyData;
use App\Http\Resources\Clinic\Insurance\InsuranceCompanyResource;
use App\Models\InsurancePriceList;
use App\Repositories\Clinic\Insurance\InsuranceCompanyRepository;
use App\Support\ServiceResult;

class InsuranceCompanyService
{
    public function __construct(private InsuranceCompanyRepository $repository)
    {
    }

    public function index(): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $companies = $this->repository->listForClinic($clinicId);

        return ServiceResult::success(
            InsuranceCompanyResource::collection($companies)->resolve(),
            'Insurance companies fetched successfully'
        );
    }

    public function show(int $companyId): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $company = $this->repository->findForClinic($clinicId, $companyId);
        if (! $company) {
            return ServiceResult::error('Insurance company not found.', null, null, 404);
        }

        return ServiceResult::success(
            (new InsuranceCompanyResource($company))->resolve(),
            'Insurance company fetched successfully'
        );
    }

    public function store(array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $dto = InsuranceCompanyData::fromArray($data);
        $validation = $this->validateReferencePriceList($clinicId, $dto->syndicatePriceListId);
        if ($validation !== null) {
            return $validation;
        }

        $company = $this->repository->create([
            'clinic_id' => $clinicId,
            ...$dto->toArray(),
        ]);

        return $this->show($company->id + 0);
    }

    public function update(int $companyId, array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $company = $this->repository->findForClinic($clinicId, $companyId);
        if (! $company) {
            return ServiceResult::error('Insurance company not found.', null, null, 404);
        }

        if (array_key_exists('syndicate_price_list_id', $data)) {
            $validation = $this->validateReferencePriceList($clinicId, $data['syndicate_price_list_id']);
            if ($validation !== null) {
                return $validation;
            }
        }

        $this->repository->update($company, $data);

        return $this->show($companyId);
    }

    public function destroy(int $companyId): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $company = $this->repository->findForClinic($clinicId, $companyId);
        if (! $company) {
            return ServiceResult::error('Insurance company not found.', null, null, 404);
        }

        $this->repository->delete($company);

        return ServiceResult::success(null, 'Insurance company deleted successfully');
    }

    private function validateReferencePriceList(int $clinicId, ?int $priceListId): ?array
    {
        if (! $priceListId) {
            return null;
        }

        $exists = InsurancePriceList::query()
            ->where('clinic_id', $clinicId)
            ->whereKey($priceListId)
            ->exists();

        if ($exists) {
            return null;
        }

        return ServiceResult::error(
            'Selected syndicate price list was not found for this clinic.',
            null,
            ['syndicate_price_list_id' => ['Selected syndicate price list is invalid for this clinic.']],
            422
        );
    }

    private function currentClinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }
}
