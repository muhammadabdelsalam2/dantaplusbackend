<?php

namespace App\Services\Lab;

use App\Models\CaseModel;
use App\Models\DeliveryTask;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryTrackingService
{
    public function paginateForUser(User $user, array $filters): LengthAwarePaginator
    {
        return DeliveryTask::query()
            ->with(['deliveryRep:id,name', 'case:id,case_number,status'])
            ->where('lab_id', $user->lab_id)
            ->when($user->hasRole('delivery_representative'), fn ($q) => $q->where('delivery_rep_user_id', $user->id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when(
                ($filters['delivery_rep_user_id'] ?? null) && ! $user->hasRole('delivery_representative'),
                fn ($q) => $q->where('delivery_rep_user_id', $filters['delivery_rep_user_id'])
            )
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function assign(CaseModel $case, User $deliveryRep, array $data): DeliveryTask
    {
        return DB::transaction(function () use ($case, $deliveryRep, $data) {
            $case->update(['assigned_delivery_id' => $deliveryRep->id]);
         $status = $data['status'] ?? DeliveryTask::STATUS_ASSIGNED;

if ($status !== DeliveryTask::STATUS_ASSIGNED) {
    throw ValidationException::withMessages([
        'status' => ['Initial status must be assigned.']
    ]);
}

return DeliveryTask::create([
    'case_id' => $case->id,
    'lab_id' => $case->lab_id,
    'delivery_rep_user_id' => $deliveryRep->id,
    'status' => $status,
    'scheduled_for' => $data['scheduled_for'] ?? null,
    'assigned_at' => now(),
    'pickup_address' => $data['pickup_address'] ?? null,
    'delivery_address' => $data['delivery_address'] ?? null,
    'pickup_notes' => $data['pickup_notes'] ?? null,
    'delivery_notes' => $data['delivery_notes'] ?? null,
]);
        });
    }

    public function updateLocation(DeliveryTask $task, array $data): DeliveryTask
    {
        $task->update([
            'last_location_lat' => $data['lat'],
            'last_location_lng' => $data['lng'],
            'last_location_at' => now(),
        ]);

        return $task->fresh(['deliveryRep:id,name', 'case:id,case_number,status']);
    }

    public function updateStatus(DeliveryTask $task, array $data): DeliveryTask
    {
        return DB::transaction(function () use ($task, $data) {
          $allowedTransitions = [
    DeliveryTask::STATUS_ASSIGNED => [
        DeliveryTask::STATUS_PICKED_UP,
        DeliveryTask::STATUS_IN_TRANSIT,
        DeliveryTask::STATUS_CANCELLED
    ],
    DeliveryTask::STATUS_PICKED_UP => [
        DeliveryTask::STATUS_IN_TRANSIT,
        DeliveryTask::STATUS_DELIVERED,
        DeliveryTask::STATUS_CANCELLED
    ],
    DeliveryTask::STATUS_IN_TRANSIT => [
        DeliveryTask::STATUS_DELIVERED,
        DeliveryTask::STATUS_CANCELLED
    ],
    DeliveryTask::STATUS_DELIVERED => [],
    DeliveryTask::STATUS_CANCELLED => [],
];

            if ($task->status !== $data['status'] && ! in_array($data['status'], $allowedTransitions[$task->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => ["Invalid delivery transition from {$task->status} to {$data['status']}."],
                ]);
            }

            $task->update(array_filter([
                'status' => $data['status'],
                'picked_up_at' => $data['status'] === 'picked_up' ? now() : null,
                'delivered_at' => $data['status'] === 'delivered' ? now() : null,
                'delivery_notes' => $data['delivery_notes'] ?? null,
            ], fn ($value) => $value !== null));

            if ($data['status'] === 'delivered') {
                $task->case()->update([
                    'status' => CaseModel::STATUS_DELIVERED,
                    'delivered_at' => now(),
                ]);
            }

            return $task->fresh(['deliveryRep:id,name', 'case:id,case_number,status']);
        });
    }

    public function deliveryRepresentativeForLab(int $labId, int $userId): User
    {
        $user = User::query()->where('lab_id', $labId)->findOrFail($userId);

        if (! $user->hasRole('delivery_representative')) {
            throw ValidationException::withMessages([
                'delivery_rep_user_id' => ['The selected user is not a delivery representative.'],
            ]);
        }

        return $user;
    }

    public function mapTask(DeliveryTask $task): array
    {
        return [
            'id' => $task->id,
            'status' => $task->status,
            'scheduled_for' => optional($task->scheduled_for)->toISOString(),
            'assigned_at' => optional($task->assigned_at)->toISOString(),
            'picked_up_at' => optional($task->picked_up_at)->toISOString(),
            'delivered_at' => optional($task->delivered_at)->toISOString(),
            'pickup_address' => $task->pickup_address,
            'delivery_address' => $task->delivery_address,
            'pickup_notes' => $task->pickup_notes,
            'delivery_notes' => $task->delivery_notes,
            'location' => [
                'lat' => $task->last_location_lat,
                'lng' => $task->last_location_lng,
                'updated_at' => optional($task->last_location_at)->toISOString(),
            ],
            'case' => $task->case ? [
                'id' => $task->case->id,
                'case_number' => $task->case->case_number,
                'status' => $task->case->status,
            ] : null,
            'delivery_rep' => $task->deliveryRep ? [
                'id' => $task->deliveryRep->id,
                'name' => $task->deliveryRep->name,
            ] : null,
        ];
    }
}
