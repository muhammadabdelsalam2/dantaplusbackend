<?php

namespace App\Services\Lab\Clinic;

use App\Http\Resources\Lab\Clinic\ClinicDetailResource;
use App\Http\Resources\Lab\Clinic\ClinicPartnershipResource;
use App\Repositories\Lab\Clinic\ClinicRepositoryInterface;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\DB;

class ClinicExternalService
{
    public function __construct(private ClinicRepositoryInterface $repository)
    {
    }

    public function create(array $data): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        return DB::transaction(function () use ($data, $labId) {
            $clinic = $this->repository->createClinic([
                'name' => $data['name'],
                'owner_name' => $data['owner_name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'subdomain' => null,
                'clinic_type' => $data['clinic_type'] ?? null,
                'is_external' => true,
                'notes' => $data['notes'] ?? null,
                'added_by' => auth()->id(),
                'registration_date' => null,
                'status' => 'Active',
                'subscription_plan' => 'Basic',
                'payment_method' => 'Manual',
                'max_users' => 0,
                'max_branches' => 0,
            ]);

            $partnership = $this->repository->createPartnership([
                'lab_id' => $labId,
                'clinic_id' => $clinic->id,
                'status' => 'Active',
                'partnership_start_date' => now()->toDateString(),
                'total_cases_sent' => 0,
                'invited_by' => auth()->id(),
            ]);

            return ServiceResult::success([
                'clinic' => (new ClinicDetailResource($clinic))->resolve(),
                'partnership' => (new ClinicPartnershipResource($partnership))->resolve(),
            ], 'External clinic added successfully.', 201);
        });
    }

    private function currentLabId(): ?int
    {
        return auth()->user()?->lab_id;
    }
}
