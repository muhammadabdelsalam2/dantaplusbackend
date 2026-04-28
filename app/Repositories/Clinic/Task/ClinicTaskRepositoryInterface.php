<?php

namespace App\Repositories\Clinic\Task;

use App\Models\ClinicTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ClinicTaskRepositoryInterface
{
    public function paginate(int $clinicId, array $filters): LengthAwarePaginator;

    public function findForClinic(int $clinicId, int $taskId): ?ClinicTask;

    public function create(array $data): ClinicTask;

    public function update(ClinicTask $task, array $data): ClinicTask;

    public function delete(ClinicTask $task): void;
}
