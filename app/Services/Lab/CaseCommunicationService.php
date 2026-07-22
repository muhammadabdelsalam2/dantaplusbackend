<?php

namespace App\Services\Lab;

use App\Http\Resources\CaseActivityLogResource;
use App\Http\Resources\CaseAttachmentResource;
use App\Http\Resources\CaseMessageResource;
use App\Models\CaseModel;
use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use App\Repositories\CaseMessageRepository;
use App\Repositories\CaseRepository;
use App\Repositories\NotificationLogRepository;
use App\Repositories\NotificationRepository;
use App\Support\ServiceResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CaseCommunicationService
{
    public function __construct(
        private CaseRepository $caseRepository,
        private CaseMessageRepository $caseMessageRepository,
        private NotificationRepository $notificationRepository,
        private NotificationLogRepository $notificationLogRepository,
    ) {}

    public function listMessages(int $caseId, int $perPage = 30): array
    {
        $case = $this->findCaseForLab($caseId);
        if (! $case) {
            return ServiceResult::error('Case not found', null, null, 404);
        }

        $messages = $this->caseMessageRepository->paginateByCaseForViewer($caseId, 'lab', $perPage);

        $this->caseMessageRepository->markUnreadAsRead($caseId, auth()->id());

        return ServiceResult::success([
            'items' => CaseMessageResource::collection($messages->items())->resolve(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ], 'Case messages fetched successfully');
    }

    public function sendMessage(int $caseId, array $data): array
    {
        $case = $this->findCaseForLab($caseId);
        if (! $case) {
            return ServiceResult::error('Case not found', null, null, 404);
        }

        return DB::transaction(function () use ($case, $data) {
            $sender = auth()->user();

            $message = $this->caseMessageRepository->create([
                'case_id' => $case->id,
                'sender_id' => $sender?->id,
                'sender_name' => $data['sender_name'] ?? $sender?->name,
                'sender_type' => $data['sender_type'],
                'message' => $data['message'],
                'is_internal' => (bool) ($data['is_internal'] ?? false),
                'is_read' => false,
                'attachment_url' => $data['attachment_url'] ?? null,
            ]);

            $this->caseRepository->createActivityLog($case, [
                'actor_id' => $sender?->id,
                'actor_name' => $sender?->name,
                'action' => (bool) ($data['is_internal'] ?? false) ? 'internal_note_added' : 'message_sent',
                'notes' => $data['message'],
            ]);

            if (! (bool) ($data['is_internal'] ?? false)) {
                $this->syncCommunicationMessage($case, $message->message, $sender?->id, $sender?->name, $data['sender_type']);
                $from = $sender?->name ?: ($data['sender_name'] ?? 'Clinic');
                $this->notifyCaseParticipants($case, 'case_message', "Message from {$from} on case {$case->case_number}: {$message->message}", $sender?->id, $sender?->name, $data['sender_type']);
            }

            return ServiceResult::success(
                (new CaseMessageResource($message))->resolve(),
                'Message sent successfully',
                201
            );
        });
    }

    public function addAttachment(int $caseId, array $data): array
    {
        $case = $this->findCaseForLab($caseId);
        if (! $case) {
            return ServiceResult::error('Case not found', null, null, 404);
        }

        $file = $data['attachment'];
        $path = $this->storeAttachment($file);

        return DB::transaction(function () use ($case, $data, $file, $path) {
            $user = auth()->user();
            $attachment = $this->caseRepository->createAttachment($case, [
                'uploaded_by' => $user?->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'attachment_type' => $data['attachment_type'] ?? null,
            ]);

            $this->caseRepository->createActivityLog($case, [
                'actor_id' => $user?->id,
                'actor_name' => $user?->name,
                'action' => 'attachment_uploaded',
                'payload' => [
                    'file_name' => $attachment->file_name,
                    'file_path' => $attachment->file_path,
                ],
            ]);

            $this->notifyCaseParticipants($case, 'case_attachment', "New attachment uploaded for case {$case->case_number}.", $user?->id, $user?->name, 'lab');

            return ServiceResult::success(
                (new CaseAttachmentResource($attachment))->resolve(),
                'Attachment uploaded successfully',
                201
            );
        });
    }

    public function listActivityLogs(int $caseId, ?string $range = null): array
    {
        $case = $this->findCaseForLab($caseId);
        if (! $case) {
            return ServiceResult::error('Case not found', null, null, 404);
        }

        $logs = $this->caseRepository->listActivityLogs($caseId, $range);

        return ServiceResult::success([
            'items' => CaseActivityLogResource::collection($logs)->resolve(),
        ], 'Case activity logs fetched successfully');
    }

    private function findCaseForLab(int $caseId): ?CaseModel
    {
        $labId = auth()->user()?->lab_id;
        if (! $labId) {
            return null;
        }

        return $this->caseRepository->findByIdForLab($caseId, $labId);
    }

    private function storeAttachment(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $filename = (string) Str::uuid() . '.' . $extension;
        Storage::disk('public')->putFileAs('cases/attachments', $file, $filename);

        return 'cases/attachments/' . $filename;
    }

    private function notifyCaseParticipants(
        CaseModel $case,
        string $type,
        string $message,
        ?int $senderId,
        ?string $senderName,
        string $senderType,
    ): void {
        $audienceType = $senderType === 'lab' ? 'clinic' : 'lab';
        $audienceId = $senderType === 'lab' ? $case->clinic_id : $case->lab_id;

        $notification = $this->notificationRepository->create([
            'title' => 'Case Update',
            'message' => $message,
            'type' => $type,
            'status' => 'Sent',
            'audience_type' => $audienceType,
            'audience_id' => $audienceId,
            'priority' => 'Normal',
            'delivery_methods' => ['system'],
            'is_read' => false,
            'sender_id' => $senderId,
            'sender_name' => $senderName,
            'link' => null,
        ]);

        $this->notificationLogRepository->create([
            'clinic_id' => $case->clinic_id,
            'doctor_id' => $case->dentist_id,
            'channel' => 'system',
            'status' => 'Sent',
            'message_content' => $notification->message,
            'sent_at' => now(),
        ]);
    }

    private function syncCommunicationMessage(CaseModel $case, string $text, ?int $senderId, ?string $senderName, string $senderType): void
    {
        $conversation = CommunicationConversation::query()->firstOrCreate(
            [
                'clinic_id' => $case->clinic_id,
                'lab_id' => $case->lab_id,
                'contact_type' => CommunicationConversation::CONTACT_TYPE_LAB,
                'context_type' => 'case',
                'context_id' => $case->id,
            ],
            [
                'contact_id' => $case->lab_id,
                'status' => CommunicationConversation::STATUS_ACTIVE,
            ]
        );

        CommunicationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $senderId,
            'sender_name' => $senderName,
            'sender_type' => $senderType,
            'text' => $text,
            'type' => CommunicationMessage::TYPE_TEXT,
            'related_id' => $case->id,
            'related_type' => 'case',
            'is_system_message' => false,
            'is_read' => false,
        ]);

        $conversation->update([
            'last_message_text' => $text,
            'last_message_at' => now(),
            'last_message_sender_id' => $senderId,
        ]);
    }
}
