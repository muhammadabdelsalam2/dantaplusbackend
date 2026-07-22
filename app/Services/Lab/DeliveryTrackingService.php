<?php

namespace App\Services\Lab;

use App\Models\CaseModel;
use App\Models\DeliveryTask;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryTrackingService
{
    public function paginateForUser(User $user, array $filters): LengthAwarePaginator
    {
        return DeliveryTask::query()
            ->with(['deliveryRep:id,name', 'case:id,case_number,status,clinic_id,patient_id', 'case.clinic', 'case.patient.user'])
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
            $status = $data['status'] ?? DeliveryTask::STATUS_ASSIGNED;

            $existingTask = DeliveryTask::query()
                ->where('case_id', $case->id)
                ->where('lab_id', $case->lab_id)
                ->where('status', '!=', DeliveryTask::STATUS_CANCELLED)
                ->first();

            if ($existingTask) {
                $existingTask->update([
                    'delivery_rep_user_id' => $deliveryRep->id,
                    'scheduled_for' => $data['scheduled_for'] ?? $existingTask->scheduled_for,
                    'pickup_address' => $data['pickup_address'] ?? $existingTask->pickup_address,
                    'delivery_address' => $data['delivery_address'] ?? $existingTask->delivery_address,
                    'pickup_notes' => $data['pickup_notes'] ?? $existingTask->pickup_notes,
                    'delivery_notes' => $data['delivery_notes'] ?? $existingTask->delivery_notes,
                ]);

                $case->update(['assigned_delivery_id' => $deliveryRep->id]);

                return $existingTask->fresh();
            }

            if ($status !== DeliveryTask::STATUS_ASSIGNED) {
                throw ValidationException::withMessages([
                    'status' => ['Initial status must be assigned.']
                ]);
            }

            $task = DeliveryTask::create([
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

            $case->update(['assigned_delivery_id' => $deliveryRep->id]);

            return $task;
        });
    }

    public function updateLocation(DeliveryTask $task, array $data): DeliveryTask
    {
        $task->update([
            'last_location_lat' => $data['lat'],
            'last_location_lng' => $data['lng'],
            'last_location_at' => now(),
        ]);

        return $task->fresh(['deliveryRep:id,name', 'case:id,case_number,status,clinic_id,patient_id', 'case.clinic', 'case.patient.user']);
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

                if ($task->case) {
                    Notification::query()->create([
                        'title' => 'Case Delivered',
                        'message' => "Case {$task->case->case_number} has been delivered.",
                        'type' => 'case_status',
                        'status' => 'Sent',
                        'audience_type' => 'lab',
                        'audience_id' => $task->lab_id,
                        'priority' => 'Normal',
                        'delivery_methods' => ['system'],
                        'is_read' => false,
                        'sender_id' => auth()->id(),
                        'sender_name' => auth()->user()?->name,
                        'link' => '/lab/orders/' . $task->case->id,
                    ]);
                }
            }

            return $task->fresh(['deliveryRep:id,name', 'case:id,case_number,status,clinic_id,patient_id', 'case.clinic', 'case.patient.user']);
        });
    }

    public function confirmReceipt(DeliveryTask $task, array $data): DeliveryTask
    {
        $proofPath = null;
        $proofName = null;
        $proofMime = null;
        $proofSize = null;

        if (isset($data['proof_file']) && $data['proof_file'] instanceof \Illuminate\Http\UploadedFile) {
            $file = $data['proof_file'];
            $extension = $file->getClientOriginalExtension() ?: $file->extension();
            $filename = (string) \Illuminate\Support\Str::uuid() . '.' . $extension;
            \Illuminate\Support\Facades\Storage::disk('public')->putFileAs('delivery-receipts', $file, $filename);

            $proofPath = 'delivery-receipts/' . $filename;
            $proofName = $file->getClientOriginalName();
            $proofMime = $file->getClientMimeType();
            $proofSize = $file->getSize();
        }

        try {
            return DB::transaction(function () use ($task, $data, $proofPath, $proofName, $proofMime, $proofSize) {
                $updateData = [
                    'receipt_confirmed_at' => now(),
                    'receipt_confirmed_by' => auth()->id(),
                ];

                if ($proofPath) {
                    $updateData['receipt_proof_path'] = $proofPath;
                    $updateData['receipt_proof_original_name'] = $proofName;
                    $updateData['receipt_proof_mime_type'] = $proofMime;
                    $updateData['receipt_proof_size'] = $proofSize;
                }

                if (array_key_exists('trip_expense', $data)) {
                    $updateData['trip_expense'] = $data['trip_expense'];
                }
                if (array_key_exists('notes', $data) && filled($data['notes'])) {
                    $updateData['delivery_notes'] = $task->delivery_notes ? $task->delivery_notes . "\nReceipt Notes: " . $data['notes'] : $data['notes'];
                }

                $task->update($updateData);

                if ($task->case) {
                    app(\App\Repositories\CaseRepository::class)->createActivityLog($task->case, [
                        'actor_id' => auth()->id(),
                        'actor_name' => auth()->user()?->name,
                        'action' => 'confirm_delivery_receipt',
                        'notes' => $data['notes'] ?? 'Delivery receipt confirmed',
                        'payload' => [
                            'task_id' => $task->id,
                            'trip_expense' => $updateData['trip_expense'] ?? null,
                            'has_proof_file' => (bool)$proofPath,
                        ],
                    ]);
                }

                return $task->fresh(['deliveryRep:id,name', 'case:id,case_number,status,clinic_id,patient_id', 'case.clinic', 'case.patient.user']);
            });
        } catch (\Throwable $e) {
            if ($proofPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($proofPath)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($proofPath);
            }
            throw $e;
        }
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
                'clinic_name' => $task->case?->clinic?->name,
                'patient_name' => $task->case?->patient?->user?->name,
            ] : null,
            'delivery_rep' => $task->deliveryRep ? [
                'id' => $task->deliveryRep->id,
                'name' => $task->deliveryRep->name,
                'phone' => $task->deliveryRep->phone,
            ] : null,
            'receipt_proof_url' => $task->receipt_proof_path ? asset('storage/' . $task->receipt_proof_path) : null,
            'receipt_proof_original_name' => $task->receipt_proof_original_name,
            'receipt_proof_mime_type' => $task->receipt_proof_mime_type,
            'receipt_proof_size' => $task->receipt_proof_size,
            'trip_expense' => $task->trip_expense,
            'receipt_confirmed_at' => optional($task->receipt_confirmed_at)->toISOString(),
            'receipt_confirmed_by' => $task->receipt_confirmed_by,
        ];
    }
}
