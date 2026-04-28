<?php

namespace App\Repositories\Clinic\Task;

use App\Models\ClinicTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ClinicTaskRepository implements ClinicTaskRepositoryInterface
{
    public function paginate(int $clinicId, array $filters): LengthAwarePaginator
    {
        return ClinicTask::query()
            ->with(['assigneeUser:id,name', 'assigneeDoctor.user:id,name', 'creator:id,name'])
            ->where('clinic_id', $clinicId)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['assignee_id'] ?? null, function (Builder $query, int $assigneeId) {
                $query->where(function (Builder $innerQuery) use ($assigneeId) {
                    $innerQuery
                        ->where('assign_to_user_id', $assigneeId)
                        ->orWhereHas('assigneeDoctor', fn (Builder $doctorQuery) => $doctorQuery->where('user_id', $assigneeId));
                });
            })
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('due_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('due_date', '<=', $date))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function findForClinic(int $clinicId, int $taskId): ?ClinicTask
    {
        return ClinicTask::query()
            ->with(['assigneeUser:id,name', 'assigneeDoctor.user:id,name', 'creator:id,name'])
            ->where('clinic_id', $clinicId)
            ->find($taskId);
    }

    public function create(array $data): ClinicTask
    {
        return ClinicTask::query()->create($data);
    }

    public function update(ClinicTask $task, array $data): ClinicTask
    {
        $task->update($data);

        return $task->refresh()->load(['assigneeUser:id,name', 'assigneeDoctor.user:id,name', 'creator:id,name']);
    }

    public function delete(ClinicTask $task): void
    {
        $task->delete();
    }
}
