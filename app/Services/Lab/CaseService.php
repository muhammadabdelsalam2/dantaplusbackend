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
use Illuminate\Validation\ValidationException;

class CaseService
{
    public function __construct(
        private CaseRepository $caseRepository,
        private NotificationRepository $notificationRepository,
        private NotificationLogRepository $notificationLogRepository,
    ) {}

    public function listCases(array $filters): array
    {
        $user = auth()->user();
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        if ($user?->hasRole('lab_technician')) {
            $filters['restricted_user_id'] = (int) $user->id;
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $cases = $this->caseRepository->paginateForLab($labId, $filters, $perPage);
        $cases->load(['technician:id,name,avatar_url', 'deliveryRep:id,name', 'attachments']);

        return ServiceResult::success([
            'stats' => $this->caseRepository->statsForLab($labId, $filters),
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

        $case->load(['technician:id,name,avatar_url', 'deliveryRep:id,name', 'attachments']);

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

        $attachmentData = null;
        if (isset($data['attachment']) && $data['attachment'] instanceof \Illuminate\Http\UploadedFile) {
            $attachmentFile = $data['attachment'];
            $attachmentData = [
                'file_name' => $attachmentFile->getClientOriginalName(),
                'mime_type' => $attachmentFile->getClientMimeType(),
                'file_size' => $attachmentFile->getSize(),
                'file_path' => $this->storeAttachment($attachmentFile),
            ];
        }

        try {
            return DB::transaction(function () use ($data, $labId, $attachmentData) {
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
                    'tooth_chart_3d' => $data['tooth_chart_3d'] ?? null,
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

                if ($attachmentData) {
                    $attachment = $this->caseRepository->createAttachment($case, [
                        'uploaded_by' => $user?->id,
                        'file_name' => $attachmentData['file_name'],
                        'file_path' => $attachmentData['file_path'],
                        'mime_type' => $attachmentData['mime_type'],
                        'file_size' => $attachmentData['file_size'],
                    ]);
                    $this->caseRepository->createActivityLog($case, [
                        'actor_id' => $user?->id,
                        'actor_name' => $user?->name,
                        'action' => 'uploaded_attachment',
                        'payload' => [
                            'file_name' => $attachment->file_name,
                            'file_path' => $attachment->file_path,
                        ],
                    ]);
                }

                return ServiceResult::success(
                    (new CaseResource($case->refresh()))->resolve(),
                    'Case created successfully',
                    201
                );
            });
        } catch (\Throwable $e) {
            if ($attachmentData && \Illuminate\Support\Facades\Storage::disk('public')->exists($attachmentData['file_path'])) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($attachmentData['file_path']);
            }
            throw $e;
        }
    }

    public function updateCase(int $id, array $data): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $attachmentData = null;
        if (isset($data['attachment']) && $data['attachment'] instanceof \Illuminate\Http\UploadedFile) {
            $attachmentFile = $data['attachment'];
            $attachmentData = [
                'file_name' => $attachmentFile->getClientOriginalName(),
                'mime_type' => $attachmentFile->getClientMimeType(),
                'file_size' => $attachmentFile->getSize(),
                'file_path' => $this->storeAttachment($attachmentFile),
            ];
        }
        unset($data['attachment']);

        try {
            return DB::transaction(function () use ($id, $data, $labId, $attachmentData) {
                $case = $this->caseRepository->findByIdForLab($id, $labId);
                if (! $case) {
                    return ServiceResult::error('Case not found', null, null, 404);
                }

                if ((int) auth()->user()?->lab_id !== (int) $case->lab_id) {
                    return ServiceResult::error('Unauthorized case access', null, null, 403);
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

                if ($attachmentData) {
                    $attachment = $this->caseRepository->createAttachment($updated, [
                        'uploaded_by' => $user?->id,
                        'file_name' => $attachmentData['file_name'],
                        'file_path' => $attachmentData['file_path'],
                        'mime_type' => $attachmentData['mime_type'],
                        'file_size' => $attachmentData['file_size'],
                    ]);
                    $this->caseRepository->createActivityLog($updated, [
                        'actor_id' => $user?->id,
                        'actor_name' => $user?->name,
                        'action' => 'uploaded_attachment',
                        'payload' => [
                            'file_name' => $attachment->file_name,
                            'file_path' => $attachment->file_path,
                        ],
                    ]);
                }

                return ServiceResult::success(
                    (new CaseResource($updated->refresh()))->resolve(),
                    'Case updated successfully'
                );
            });
        } catch (\Throwable $e) {
            if ($attachmentData && \Illuminate\Support\Facades\Storage::disk('public')->exists($attachmentData['file_path'])) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($attachmentData['file_path']);
            }
            throw $e;
        }
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

            if ((int) auth()->user()?->lab_id !== (int) $case->lab_id) {
                return ServiceResult::error('Unauthorized case access', null, null, 403);
            }

            $oldStatus = $case->status;
            $status = $data['status'];
            $payload = $this->buildStatusPayload($case->load('latestDeliveryTask'), $status, $data['assigned_technician_id'] ?? null);

            $updated = $this->caseRepository->update($case, $payload);

            $user = auth()->user();

            if (isset($data['assigned_technician_id'])) {
                $this->validateTechnician((int) $data['assigned_technician_id'], $labId);
                $updated = $this->caseRepository->update($updated, [
                    'assigned_technician_id' => $data['assigned_technician_id'],
                ]);

                $this->caseRepository->createActivityLog($updated, [
                    'actor_id' => $user?->id,
                    'actor_name' => $user?->name,
                    'action' => 'assigned_technician',
                    'payload' => [
                        'assigned_technician_id' => $data['assigned_technician_id'],
                    ],
                ]);
            }

            $isAccepted = $status === CaseModel::STATUS_ACCEPTED;
            $isCompleted = $status === CaseModel::STATUS_COMPLETED;
            $assignForDelivery = $data['assign_for_delivery'] ?? false;
            $deliveryRepId = $data['delivery_rep_user_id'] ?? null;

            if ($isCompleted && $assignForDelivery && empty($deliveryRepId)) {
                throw ValidationException::withMessages([
                    'delivery_rep_user_id' => ['A delivery representative must be selected to assign delivery.'],
                ]);
            }

            $shouldAssignDelivery = false;
            if ($isAccepted && !empty($deliveryRepId)) {
                $shouldAssignDelivery = true;
            } elseif ($assignForDelivery && !empty($deliveryRepId)) {
                $shouldAssignDelivery = true;
            }

            if ($shouldAssignDelivery) {
                $deliveryTrackingService = app(\App\Services\Lab\DeliveryTrackingService::class);
                $deliveryRep = $deliveryTrackingService->deliveryRepresentativeForLab((int) $labId, (int) $deliveryRepId);
                $deliveryTrackingService->assign($updated, $deliveryRep, [
                    'scheduled_for' => $data['scheduled_for'] ?? null,
                    'pickup_address' => $data['pickup_address'] ?? null,
                    'delivery_address' => $data['delivery_address'] ?? null,
                    'pickup_notes' => $data['pickup_notes'] ?? null,
                    'delivery_notes' => $data['delivery_notes'] ?? null,
                ]);

                $this->caseRepository->createActivityLog($updated, [
                    'actor_id' => $user?->id,
                    'actor_name' => $user?->name,
                    'action' => 'assigned_delivery',
                    'payload' => [
                        'delivery_rep_user_id' => $deliveryRep->id,
                        'delivery_rep_name' => $deliveryRep->name,
                    ],
                ]);
            }

            if ($status === CaseModel::STATUS_COMPLETED && ($data['generate_invoice'] ?? false)) {
                $accountingService = app(\App\Services\Lab\Accounting\LabAccountingService::class);

                // Check for existing invoice item for this case to prevent duplicates
                $existingItem = \App\Models\LabInvoiceItem::query()
                    ->where('case_id', $updated->id)
                    ->whereHas('invoice', fn ($q) => $q->where('status', '!=', \App\Models\LabInvoice::STATUS_CANCELLED))
                    ->exists();

                if (! $existingItem) {
                    $service = \App\Models\LabService::query()
                        ->where('lab_id', $updated->lab_id)
                        ->where('service_name', $updated->case_type)
                        ->first();

                    if (! $service || (float)$service->price <= 0) {
                        throw ValidationException::withMessages([
                            'generate_invoice' => ["Cannot generate invoice automatically. No lab service with a valid price was found matching case type '{$updated->case_type}'."],
                        ]);
                    }

                    $invoiceData = [
                        'clinic_id' => $updated->clinic_id,
                        'doctor_id' => $updated->dentist_id,
                        'issue_date' => now()->toDateString(),
                        'due_date' => now()->addDays(15)->toDateString(),
                        'notes' => 'Auto-generated for completed case ' . $updated->case_number,
                        'items' => [
                            [
                                'case_id' => $updated->id,
                                'technician_id' => $updated->assigned_technician_id,
                                'case_number' => $updated->case_number,
                                'patient_name' => $updated->patient?->user?->name,
                                'service_name' => $service->service_name,
                                'lab_service_id' => $service->id,
                                'teeth_numbers' => $updated->tooth_numbers,
                                'quantity' => 1,
                                'unit_price' => (float)$service->price,
                                'materials_cost' => 0,
                                'discount' => 0,
                                'tax' => 0,
                            ],
                        ],
                    ];
                    $accountingService->createInvoice($invoiceData);

                    $this->caseRepository->createActivityLog($updated, [
                        'actor_id' => $user?->id,
                        'actor_name' => $user?->name,
                        'action' => 'generated_invoice',
                        'payload' => [
                            'case_number' => $updated->case_number,
                        ],
                    ]);
                }
            }

            $this->caseRepository->createActivityLog($updated, [
                'actor_id' => $user?->id,
                'actor_name' => $user?->name,
                'action' => 'status_updated',
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

            $updated = $updated->fresh([
                'clinic',
                'lab',
                'patient.user',
                'dentist.user',
                'technician:id,name,avatar_url',
                'deliveryRep:id,name',
                'attachments'
            ]);

            return ServiceResult::success(
                $this->statusResponsePayload($updated, $data),
                'Case status updated successfully'
            );
        });
    }

    public function completeCase(int $id, array $data): array
    {
        $data['status'] = CaseModel::STATUS_COMPLETED;

        return $this->updateStatus($id, $data);
    }

    public function labOrder(int $id): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $case = $this->caseRepository->findByIdForLab($id, $labId);
        if (! $case) {
            return ServiceResult::error('Case not found', null, null, 404);
        }

        return ServiceResult::success($this->labOrderPayload($this->ensureLabOrderToken($case)), 'Lab order fetched successfully');
    }

    public function publicLabOrder(string $token): ?array
    {
        $case = CaseModel::query()
            ->with(['clinic', 'lab', 'patient.user', 'dentist.user', 'technician:id,name,avatar_url', 'attachments'])
            ->where('lab_order_token', $token)
            ->first();

        return $case ? $this->labOrderPayload($case) : null;
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

            if ((int) auth()->user()?->lab_id !== (int) $case->lab_id) {
                return ServiceResult::error('Unauthorized case access', null, null, 403);
            }

            $this->validateTechnician((int) $data['assigned_technician_id'], $labId);

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

            if (in_array($updated->status, [CaseModel::STATUS_PENDING, CaseModel::STATUS_ACCEPTED, CaseModel::STATUS_RECEIVED_BY_LAB], true)) {
                $updated = $this->caseRepository->update($updated, [
                    'status' => CaseModel::STATUS_IN_PROGRESS,
                ]);
            }

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
                (new CaseResource($updated->fresh(['technician:id,name,avatar_url'])))->resolve(),
                'Technician assigned successfully'
            );
        });
    }

    private function validateTechnician(int $technicianId, int $labId): void
    {
        $technician = \App\Models\User::query()
            ->where('lab_id', $labId)
            ->find($technicianId);

        if (! $technician) {
            throw ValidationException::withMessages([
                'assigned_technician_id' => ['The selected technician is invalid or does not belong to your lab.'],
            ]);
        }

        if (! $technician->hasRole('lab_technician')) {
            throw ValidationException::withMessages([
                'assigned_technician_id' => ['The selected user must have the lab_technician role.'],
            ]);
        }
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

    private function buildStatusPayload(CaseModel $case, string $targetStatus, ?int $incomingTechnicianId = null): array
    {
        $allowedTransitions = [
            CaseModel::STATUS_PENDING => [CaseModel::STATUS_RECEIVED_BY_LAB, CaseModel::STATUS_ACCEPTED, CaseModel::STATUS_REJECTED],
            CaseModel::STATUS_RECEIVED_BY_LAB => [CaseModel::STATUS_ACCEPTED, CaseModel::STATUS_REJECTED],
            CaseModel::STATUS_ACCEPTED => [CaseModel::STATUS_IN_PROGRESS, CaseModel::STATUS_COMPLETED],
            CaseModel::STATUS_IN_PROGRESS => [CaseModel::STATUS_COMPLETED, CaseModel::STATUS_REJECTED],
            CaseModel::STATUS_COMPLETED => [CaseModel::STATUS_DELIVERED],
            CaseModel::STATUS_DELIVERED => [],
            CaseModel::STATUS_REJECTED => [],
        ];

        if ($case->status !== $targetStatus && ! in_array($targetStatus, $allowedTransitions[$case->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => ["Invalid case transition from {$case->status} to {$targetStatus}."],
            ]);
        }

        if ($targetStatus === CaseModel::STATUS_IN_PROGRESS && ! $case->assigned_technician_id && ! $incomingTechnicianId) {
            throw ValidationException::withMessages([
                'status' => ['A technician must be assigned before moving the case to In Progress.'],
            ]);
        }

        if ($targetStatus === CaseModel::STATUS_DELIVERED && ! $case->latestDeliveryTask?->delivered_at) {
            throw ValidationException::withMessages([
                'status' => ['Delivery must be completed before marking the case as delivered.'],
            ]);
        }

        return array_filter([
            'status' => $targetStatus,
            'completed_at' => $targetStatus === CaseModel::STATUS_COMPLETED ? now() : null,
            'delivered_at' => $targetStatus === CaseModel::STATUS_DELIVERED ? now() : null,
        ], fn ($value) => $value !== null);
    }

    private function storeAttachment(\Illuminate\Http\UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $filename = (string) Str::uuid() . '.' . $extension;
        \Illuminate\Support\Facades\Storage::disk('public')->putFileAs('cases/attachments', $file, $filename);

        return 'cases/attachments/' . $filename;
    }

    private function ensureLabOrderToken(CaseModel $case): CaseModel
    {
        if (! $case->lab_order_token) {
            $case = $this->caseRepository->update($case, ['lab_order_token' => (string) Str::uuid()]);
        }

        return $case->fresh(['clinic', 'lab', 'patient.user', 'dentist.user', 'technician:id,name,avatar_url', 'attachments']);
    }

    private function statusResponsePayload(CaseModel $case, array $input): array
    {
        $payload = (new CaseResource($case))->resolve();

        if ($case->status === CaseModel::STATUS_COMPLETED) {
            $payload['next_action'] = [
                'assign_for_delivery' => (bool) ($input['assign_for_delivery'] ?? false),
                'requires_delivery_rep' => (bool) ($input['assign_for_delivery'] ?? false) && empty($input['delivery_rep_user_id']),
            ];
        }

        if ($case->status === CaseModel::STATUS_RECEIVED_BY_LAB) {
            $payload['lab_order'] = $this->labOrderPayload($this->ensureLabOrderToken($case));
        }

        return $payload;
    }

    private function labOrderPayload(CaseModel $case): array
    {
        $fileUrl = route('lab.orders.lab-order.public', ['token' => $case->lab_order_token]);

        return [
            'case_id' => $case->id,
            'case_number' => $case->case_number,
            'file_url' => $fileUrl,
            'lab' => [
                'id' => $case->lab?->id,
                'name' => $case->lab?->name,
                'address' => $case->lab?->address ?? null,
                'logo_url' => $case->lab?->logo_url ?? null,
                'qr_code' => $fileUrl,
            ],
            'clinic' => [
                'id' => $case->clinic?->id,
                'name' => $case->clinic?->name,
                'address' => $case->clinic?->address ?? null,
                'phone' => $case->clinic?->phone ?? null,
                'doctor_name' => $case->dentist?->user?->name,
            ],
            'patient' => [
                'id' => $case->patient?->id,
                'name' => $case->patient?->user?->name,
                'file_number' => $case->patient?->file_number ?? $case->patient?->patient_code ?? null,
                'age' => $case->patient?->age ?? null,
                'gender' => $case->patient?->gender ?? null,
            ],
            'case' => [
                'case_type' => $case->case_type,
                'material' => $case->material ?? null,
                'shade' => $case->shade ?? null,
                'delivery_date' => optional($case->due_date)->toDateString(),
                'teeth' => $case->tooth_numbers,
                'teeth_chart' => $case->tooth_chart_3d,
                'clinic_notes' => $case->description,
                'status' => $case->status,
                'priority' => $case->priority,
            ],
            'lab_use' => [
                'assigned_technician' => $case->technician ? [
                    'id' => $case->technician->id,
                    'name' => $case->technician->name,
                    'role' => 'Technician',
                    'image_url' => $case->technician->avatar_url ?? null,
                ] : null,
                'assignment_date' => optional($case->updated_at)->toDateString(),
            ],
            'signatures' => [
                'clinic_signature' => null,
                'lab_receiver_signature' => null,
                'technician_signature' => null,
            ],
        ];
    }
}
