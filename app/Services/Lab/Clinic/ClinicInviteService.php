<?php

namespace App\Services\Lab\Clinic;

use App\Http\Resources\Lab\Clinic\ClinicPartnershipResource;
use App\Repositories\Lab\Clinic\ClinicRepositoryInterface;
use App\Support\ServiceResult;

class ClinicInviteService
{
    public function __construct(private ClinicRepositoryInterface $repository)
    {
    }

    public function invite(string $email): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $clinic = $this->repository->findInternalClinicByEmail($email);
        if (! $clinic) {
            return ServiceResult::error('Clinic not found on the platform.', null, null, 404);
        }

        $exists = $this->repository->partnershipExists($labId, $clinic->id, ['Active', 'Pending']);
        if ($exists) {
            return ServiceResult::error('A partnership already exists with this clinic.', null, null, 422);
        }

        $this->repository->createPartnership([
            'lab_id' => $labId,
            'clinic_id' => $clinic->id,
            'status' => 'Pending',
            'invited_by' => auth()->id(),
        ]);

        // TODO: dispatch ClinicInvited event

        return ServiceResult::success([
            'partnership' => (new ClinicPartnershipResource(
                $this->repository->findPartnership($labId, $clinic->id)
            ))->resolve(),
        ], 'Partnership request sent!', 201);
    }

    private function currentLabId(): ?int
    {
        return auth()->user()?->lab_id;
    }
}
