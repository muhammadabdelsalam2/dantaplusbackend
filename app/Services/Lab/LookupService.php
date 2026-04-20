<?php

namespace App\Services\Lab;

use App\Http\Resources\Lab\DentistResource;
use App\Http\Resources\Lab\PatientResource;
use App\Http\Resources\Lab\TechnicianResource;
use App\Repositories\Lab\Lookup\LookupRepositoryInterface;
use App\Support\ServiceResult;

class LookupService
{
    public function __construct(private LookupRepositoryInterface $repository)
    {
    }

    public function getPatients(?string $search = null): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $patients = $this->repository->getPatientsByLab($labId, $this->normalizeSearch($search));

        return ServiceResult::success(
            PatientResource::collection($patients)->resolve(),
            'Data fetched successfully'
        );
    }

    public function getDentists(?string $search = null): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $dentists = $this->repository->getDentistsByLab($labId, $this->normalizeSearch($search));

        return ServiceResult::success(
            DentistResource::collection($dentists)->resolve(),
            'Data fetched successfully'
        );
    }

    public function getTechnicians(?string $search = null): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $technicians = $this->repository->getTechniciansByLab($labId, $this->normalizeSearch($search));

        return ServiceResult::success(
            TechnicianResource::collection($technicians)->resolve(),
            'Data fetched successfully'
        );
    }

    private function currentLabId(): ?int
    {
        return auth()->user()?->lab_id;
    }

    private function normalizeSearch(?string $search): ?string
    {
        $search = trim((string) $search);

        return $search !== '' ? $search : null;
    }
}
