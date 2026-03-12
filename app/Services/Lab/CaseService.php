<?php

namespace App\Services\Lab;

use App\Http\Resources\CaseResource;
use App\Models\CaseModel;
use App\Repositories\CaseRepository;
use App\Repositories\NotificationLogRepository;
use App\Repositories\NotificationRepository;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CaseService
{
    public function __construct(
        private CaseRepository $caseRepository,
        private NotificationRepository $notificationRepository,
        private NotificationLogRepository $notificationLogRepository,
    ) {}

    public function listCases(array $filters): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $cases = $this->caseRepository->paginateForLab($labId, $filters, $perPage);

        return ServiceResult::success([
            'items' => CaseResource::collection($cases->items())->resolve(),
            'pagination' => [
                'current_page' => $cases->currentPage(),
                'last_page' => $cases->lastPage(),
                'per_page' => $cases->perPage(),
                'total' => $cases->total(),
            ],
        ], 'Cases fetched successfully');
    }

    public function showCase(int $id): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $case = $this->caseRepository->findByIdForLab($id, $labId);

        if (! $case) {
            return ServiceResult::error('Case not found', null, null, 404);
        }

        return ServiceResult::success((new CaseResource($case))->resolve(), 'Case details fetched successfully');
    }

    public function createCase(array $data): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        if (! empty($data['lab_id']) && (int) $data['lab_id'] !== (int) $labId) {
            return ServiceResult::error('Lab mismatch for this account', null, null, 403);
        }

        return DB::transaction(function () use ($data, $labId) {
            $user = auth()->user();

            $case = $this->caseRepository->create([
                'case_number' => $this->generateCaseNumber(),
                'clinic_id' => $data['clinic_id'],
                'lab_id' => $labId,
                'patient_id' => $data['patient_id'],
                'dentist_id' => $data['dentist_id'],
                'status' => $data['status'] ?? CaseModel::STATUS_PENDING,
                'priority' => $data['priority'] ?? CaseModel::PRIORITY_NORMAL,
                'due_date' => $data['due_date'],
                'case_type' => $data['case_type'],
                'tooth_numbers' => $data['tooth_numbers'] ?? null,
                'description' => $data['description'] ?? null,
                'assigned_technician_id' => $data['assigned_technician_id'] ?? null,
                'assigned_delivery_id' => $data['assigned_delivery_id'] ?? null,
                'created_by' => $user?->id,
            ]);

            $this->caseRepository->createActivityLog($case, [
                'actor_id' => $user?->id,
                'actor_name' => $user?->name,
                'action' => 'case_created',
                'new_status' => $case->status,
                'payload' => [
                    'case_number' => $case->case_number,
                ],
            ]);

            return ServiceResult::success(
                (new CaseResource($case->refresh()))->resolve(),
                'Case created successfully',
                201
            );
        });
    }

    public function updateCase(int $id, array $data): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        return DB::transaction(function () use ($id, $data, $labId) {
            $case = $this->caseRepository->findByIdForLab($id, $labId);
            if (! $case) {
                return ServiceResult::error('Case not found', null, null, 404);
            }

            $updated = $this->caseRepository->update($case, $data);

            $user = auth()->user();
            $this->caseRepository->createActivityLog($updated, [
                'actor_id' => $user?->id,
                'actor_name' => $user?->name,
                'action' => 'case_updated',
                'old_status' => $case->status,
                'new_status' => $updated->status,
                'payload' => $data,
            ]);

            return ServiceResult::success(
                (new CaseResource($updated))->resolve(),
                'Case updated successfully'
            );
        });
    }

    public function updateStatus(int $id, array $data): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        return DB::transaction(function () use ($id, $data, $labId) {
            $case = $this->caseRepository->findByIdForLab($id, $labId);
            if (! $case) {
                return ServiceResult::error('Case not found', null, null, 404);
            }

            $oldStatus = $case->status;
            $status = $data['status'];

            $payload = ['status' => $status];
            if ($status === CaseModel::STATUS_COMPLETED) {
                $payload['completed_at'] = now();
            }
            if ($status === CaseModel::STATUS_DELIVERED) {
                $payload['delivered_at'] = now();
            }

            $updated = $this->caseRepository->update($case, $payload);

            $user = auth()->user();
            $this->caseRepository->createActivityLog($updated, [
                'actor_id' => $user?->id,
                'actor_name' => $user?->name,
                'action' => 'status_changed',
                'old_status' => $oldStatus,
                'new_status' => $status,
                'notes' => $data['notes'] ?? null,
            ]);

            $message = "Case {$updated->case_number} status changed to {$status}.";
            $notification = $this->notificationRepository->create([
                'title' => 'Case Status Updated',
                'message' => $message,
                'type' => 'case_status',
                'status' => 'Sent',
                'audience_type' => 'clinic',
                'audience_id' => $updated->clinic_id,
                'priority' => 'Normal',
                'delivery_methods' => ['system'],
                'is_read' => false,
                'sender_id' => $user?->id,
                'sender_name' => $user?->name,
                'link' => null,
            ]);

            $this->notificationLogRepository->create([
                'clinic_id' => $updated->clinic_id,
                'doctor_id' => $updated->dentist_id,
                'channel' => 'system',
                'status' => 'Sent',
                'message_content' => $notification->message,
                'sent_at' => now(),
            ]);

            return ServiceResult::success(
                (new CaseResource($updated))->resolve(),
                'Case status updated successfully'
            );
        });
    }

    public function assignTechnician(int $id, array $data): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        return DB::transaction(function () use ($id, $data, $labId) {
            $case = $this->caseRepository->findByIdForLab($id, $labId);
            if (! $case) {
                return ServiceResult::error('Case not found', null, null, 404);
            }

            $updated = $this->caseRepository->update($case, [
                'assigned_technician_id' => $data['assigned_technician_id'],
            ]);

            $user = auth()->user();
            $this->caseRepository->createActivityLog($updated, [
                'actor_id' => $user?->id,
                'actor_name' => $user?->name,
                'action' => 'technician_assigned',
                'old_status' => $case->status,
                'new_status' => $updated->status,
                'notes' => $data['notes'] ?? null,
                'payload' => [
                    'assigned_technician_id' => $updated->assigned_technician_id,
                ],
            ]);

            $notification = $this->notificationRepository->create([
                'title' => 'Case Assigned',
                'message' => "You have been assigned to case {$updated->case_number}.",
                'type' => 'case_assignment',
                'status' => 'Sent',
                'audience_type' => 'user',
                'audience_id' => $updated->assigned_technician_id,
                'priority' => 'Normal',
                'delivery_methods' => ['system'],
                'is_read' => false,
                'sender_id' => $user?->id,
                'sender_name' => $user?->name,
                'link' => null,
            ]);

            $this->notificationLogRepository->create([
                'clinic_id' => $updated->clinic_id,
                'doctor_id' => $updated->dentist_id,
                'channel' => 'system',
                'status' => 'Sent',
                'message_content' => $notification->message,
                'sent_at' => now(),
            ]);

            return ServiceResult::success(
                (new CaseResource($updated))->resolve(),
                'Technician assigned successfully'
            );
        });
    }

    private function generateCaseNumber(): string
    {
        do {
            $number = 'CASE-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (CaseModel::query()->where('case_number', $number)->exists());

        return $number;
    }

    private function currentLabId(): ?int
    {
        return auth()->user()?->lab_id;
    }
}
