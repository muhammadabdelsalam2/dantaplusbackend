<?php

namespace App\Repositories\Contracts;

use App\Models\Clinic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ClinicRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function findById(int $clinicId, array $with = []): ?Clinic;

    public function create(array $data): Clinic;

    public function update(Clinic $clinic, array $data): Clinic;

    public function delete(Clinic $clinic): void;

    public function syncModules(Clinic $clinic, array $modules): void;

    public function getBranches(Clinic $clinic): Collection;
}
