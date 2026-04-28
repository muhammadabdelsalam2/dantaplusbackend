<?php

namespace App\Services\Clinic;

use App\Http\Resources\Clinic\ClinicTaskResource;
use App\Models\Doctor;
use App\Models\User;
use App\Repositories\Clinic\Task\ClinicTaskRepositoryInterface;
use App\Support\ServiceResult;

class TaskService
{
    public function __construct(private ClinicTaskRepositoryInterface $repository)
    {
    }

    public function index(array $filters): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $tasks = $this->repository->paginate($clinicId, $filters);

        return ServiceResult::success([
            'items' => ClinicTaskResource::collection($tasks->items())->resolve(),
            'pagination' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ], 'Tasks fetched successfully');
    }

    public function store(array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $assigneeError = $this->validateAssignee($clinicId, $data);
        if ($assigneeError) {
            return $assigneeError;
        }

        $task = $this->repository->create([
            'clinic_id' => $clinicId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'assign_to_user_id' => $data['assign_to_user_id'] ?? null,
            'assign_to_doctor_id' => $data['assign_to_doctor_id'] ?? null,
            'priority' => $data['priority'],
            'status' => $data['status'] ?? 'todo',
            'due_date' => $data['due_date'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return ServiceResult::success(
            (new ClinicTaskResource($this->repository->findForClinic($clinicId, $task->id)))->resolve(),
            'Task created successfully',
            201
        );
    }

    public function update(int $taskId, array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $task = $this->repository->findForClinic($clinicId, $taskId);
        if (! $task) {
            return ServiceResult::error('Task not found.', null, null, 404);
        }

        $assigneeError = $this->validateAssignee($clinicId, $data);
        if ($assigneeError) {
            return $assigneeError;
        }

        $task = $this->repository->update($task, [
            'title' => $data['title'] ?? $task->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $task->description,
            'assign_to_user_id' => array_key_exists('assign_to_user_id', $data) ? $data['assign_to_user_id'] : $task->assign_to_user_id,
            'assign_to_doctor_id' => array_key_exists('assign_to_doctor_id', $data) ? $data['assign_to_doctor_id'] : $task->assign_to_doctor_id,
            'priority' => $data['priority'] ?? $task->priority,
            'status' => $data['status'] ?? $task->status,
            'due_date' => array_key_exists('due_date', $data) ? $data['due_date'] : $task->due_date,
        ]);

        return ServiceResult::success((new ClinicTaskResource($task))->resolve(), 'Task updated successfully');
    }

    public function delete(int $taskId): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $task = $this->repository->findForClinic($clinicId, $taskId);
        if (! $task) {
            return ServiceResult::error('Task not found.', null, null, 404);
        }

        $this->repository->delete($task);

        return ServiceResult::success(null, 'Task deleted successfully');
    }

    private function validateAssignee(int $clinicId, array $data): ?array
    {
        if (! empty($data['assign_to_user_id'])) {
            $user = User::query()
                ->where('clinic_id', $clinicId)
                ->find($data['assign_to_user_id']);

            if (! $user) {
                return ServiceResult::error('Assignee user not found.', null, ['assign_to_user_id' => ['Assignee user not found.']], 422);
            }
        }

        if (! empty($data['assign_to_doctor_id'])) {
            $doctor = Doctor::query()
                ->whereHas('user', fn ($query) => $query->where('clinic_id', $clinicId))
                ->find($data['assign_to_doctor_id']);

            if (! $doctor) {
                return ServiceResult::error('Assignee doctor not found.', null, ['assign_to_doctor_id' => ['Assignee doctor not found.']], 422);
            }
        }

        return null;
    }

    private function currentClinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }
}
