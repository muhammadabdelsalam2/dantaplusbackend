<?php

namespace App\Services\Lab\Clinic;

use App\Http\Resources\Lab\Clinic\ClinicDetailResource;
use App\Http\Resources\Lab\Clinic\ClinicPartnershipResource;
use App\Models\Doctor;
use App\Models\User;
use App\Repositories\Lab\Clinic\ClinicRepositoryInterface;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

            // 3. Create doctor users linked to this clinic (if provided)
            $createdDoctors = [];
            if (! empty($data['doctors']) && is_array($data['doctors'])) {
                foreach ($data['doctors'] as $doctorName) {
                    $doctorName = trim((string) $doctorName);
                    if ($doctorName === '') {
                        continue;
                    }

                    // Create a stub User for this doctor.
                    // No password is set intentionally — external clinic doctors don't log in.
                    $user = User::create([
                        'name'       => $doctorName,
                        'username'   => $this->generateUsername($doctorName),
                        'email'      => null,
                        'phone'      => null,
                        'password'   => null,
                        'clinic_id'  => $clinic->id,
                        'is_active'  => false,
                        'is_verified'=> false,
                        'status'     => 'Active',
                        'role'       => 'doctor',
                    ]);

                    // Assign Spatie role
                    $user->assignRole('doctor');

                    // Create the Doctor profile
                    $doctor = Doctor::create([
                        'user_id' => $user->id,
                    ]);

                    $createdDoctors[] = [
                        'doctor_id' => $doctor->id,
                        'user_id'   => $user->id,
                        'name'      => $user->name,
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

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function currentLabId(): ?int
    {
        return auth()->user()?->lab_id;
    }

    /**
     * Generate a unique username from a doctor's name.
     * Falls back to a random suffix if the base slug is taken.
     */
    private function generateUsername(string $name): string
    {
        $base = Str::slug($name, '_');
        $base = $base ?: 'doctor';

        $username = $base;
        $counter  = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base . '_' . $counter;
            $counter++;
        }

        return $username;
    }
}
