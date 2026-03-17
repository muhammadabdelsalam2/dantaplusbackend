<?php

namespace App\Repositories\Lab\Clinic;

use App\Models\CaseModel;
use App\Models\Clinic;
use App\Models\ClinicLabPartnership;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ClinicRepositoryInterface
{
    public function paginateByLab(int $labId, array $filters, int $perPage = 15): LengthAwarePaginator;

    public function getStats(int $labId): array;

    public function findPartnerClinic(int $labId, int $clinicId): ?Clinic;

    public function findInternalClinicByEmail(string $email): ?Clinic;

    public function partnershipExists(int $labId, int $clinicId, array $statuses): bool;

    public function createClinic(array $data): Clinic;

    public function createPartnership(array $data): ClinicLabPartnership;

    public function getCasesForClinic(int $labId, int $clinicId, int $perPage = 15): LengthAwarePaginator;

    public function findPartnership(int $labId, int $clinicId): ?ClinicLabPartnership;

    public function updatePartnership(ClinicLabPartnership $partnership, array $data): ClinicLabPartnership;
}
