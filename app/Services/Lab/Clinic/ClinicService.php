<?php

namespace App\Services\Lab\Clinic;

use App\Http\Resources\Lab\Clinic\ClinicCaseResource;
use App\Http\Resources\Lab\Clinic\ClinicDetailResource;
use App\Http\Resources\Lab\Clinic\ClinicListResource;
use App\Repositories\Lab\Clinic\ClinicRepositoryInterface;
use App\Support\ServiceResult;

class ClinicService
{
    public function __construct(private ClinicRepositoryInterface $repository)
    {
    }

    public function list(array $filters): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $clinics = $this->repository->paginateByLab($labId, $filters, $perPage);

        return ServiceResult::success([
            'data' => ClinicListResource::collection($clinics->items())->resolve(),
            'stats' => $this->repository->getStats($labId),
            'meta' => [
                'page' => $clinics->currentPage(),
                'per_page' => $clinics->perPage(),
                'total' => $clinics->total(),
                'last_page' => $clinics->lastPage(),
            ],
        ], 'Partner clinics fetched successfully');
    }

    public function show(int $clinicId): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $clinic = $this->repository->findPartnerClinic($labId, $clinicId);
        if (! $clinic) {
            return ServiceResult::error('Clinic not found', null, null, 404);
        }

        return ServiceResult::success((new ClinicDetailResource($clinic))->resolve(), 'Clinic details fetched successfully');
    }

    public function listCases(int $clinicId, int $perPage = 15): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $clinic = $this->repository->findPartnerClinic($labId, $clinicId);
        if (! $clinic) {
            return ServiceResult::error('Clinic not found', null, null, 404);
        }

        $cases = $this->repository->getCasesForClinic($labId, $clinicId, $perPage);

        return ServiceResult::success([
            'data' => ClinicCaseResource::collection($cases->items())->resolve(),
            'meta' => [
                'page' => $cases->currentPage(),
                'per_page' => $cases->perPage(),
                'total' => $cases->total(),
                'last_page' => $cases->lastPage(),
            ],
        ], 'Clinic cases fetched successfully');
    }

    public function removePartnership(int $clinicId): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $partnership = $this->repository->findPartnership($labId, $clinicId);
        if (! $partnership) {
            return ServiceResult::error('Partnership not found', null, null, 404);
        }

        $this->repository->updatePartnership($partnership, ['status' => 'Ended']);

        return ServiceResult::success(null, 'Clinic partnership removed.');
    }

    private function currentLabId(): ?int
    {
        return auth()->user()?->lab_id;
    }
}
