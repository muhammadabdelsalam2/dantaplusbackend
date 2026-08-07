<?php

namespace App\Services\Lab\Clinic;

use App\Http\Resources\Lab\Clinic\ClinicDetailResource;
use App\Http\Resources\Lab\Clinic\ClinicPartnershipResource;
use App\Models\Doctor;
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
            // 1. Create the clinic record
            $clinic = $this->repository->createClinic([
                'name'              => $data['name'],
                'owner_name'        => $data['owner_name'] ?? null,
                'phone'             => $data['phone'],
                'email'             => $data['email'] ?? null,
                'address'           => $data['address'] ?? null,
                'subdomain'         => null,
                'clinic_type'       => $data['clinic_type'] ?? null,
                'is_external'       => true,
                'notes'             => $data['notes'] ?? null,
                'added_by'          => auth()->id(),
                'registration_date' => null,
                'status'            => 'Active',
                'subscription_plan' => 'Basic',
                'payment_method'    => 'Manual',
                'max_users'         => 0,
                'max_branches'      => 0,
            ]);

            // 2. Create the lab–clinic partnership
            $partnership = $this->repository->createPartnership([
                'lab_id'                 => $labId,
                'clinic_id'              => $clinic->id,
                'status'                 => 'Active',
                'partnership_start_date' => now()->toDateString(),
                'total_cases_sent'       => 0,
                'invited_by'             => auth()->id(),
            ]);

            // 3. Create doctor RECORDS ONLY — no User account at all.
            // These are just names attached to the external clinic; nobody
            // logs in as them, so there's no reason to touch the users table.
            $createdDoctors = [];
            if (! empty($data['doctors']) && is_array($data['doctors'])) {
                foreach ($data['doctors'] as $doctorName) {
                    $doctorName = trim((string) $doctorName);
                    if ($doctorName === '') {
                        continue;
                    }

                    $doctor = Doctor::create([
                        'user_id'   => null,
                        'name'      => $doctorName,
                        'clinic_id' => $clinic->id,
                    ]);

                    $createdDoctors[] = [
                        'doctor_id' => $doctor->id,
                        'name'      => $doctor->name,
                    ];
                }
            }

            return ServiceResult::success([
                'clinic'      => (new ClinicDetailResource($clinic))->resolve(),
                'partnership' => (new ClinicPartnershipResource($partnership))->resolve(),
                'doctors'     => $createdDoctors,
            ], 'External clinic added successfully.', 201);
        });
    }

    private function currentLabId(): ?int
    {
        return auth()->user()?->lab_id;
    }
}
