<?php

namespace App\Services\Communication;

use App\Http\Resources\CommunicationConversationResource;
use App\Http\Resources\CommunicationMessageResource;
use App\Models\CaseModel;
use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use App\Models\LabInvoice;
use App\Models\Notification;
use App\Repositories\CommunicationConversationRepository;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConversationService
{
    public function __construct(private CommunicationConversationRepository $conversationRepository) {}

    public function listContacts(array $filters): array
    {
        $user = auth()->user();

        if (! $user?->clinic_id && ! $user?->lab_id) {
            return ServiceResult::error('No clinic or lab linked to this account', null, null, 403);
        }

        $filters['clinic_id'] = $filters['clinic_id'] ?? $user?->clinic_id;
        $filters['lab_id'] = $filters['lab_id'] ?? $user?->lab_id;

        $perPage = (int) ($filters['per_page'] ?? 20);
        $conversations = $this->conversationRepository->paginateConversations($filters, $perPage);

        return ServiceResult::success([
            'items' => CommunicationConversationResource::collection($conversations->items())->resolve(),
            'pagination' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ], 'Contacts fetched successfully');
    }

    public function listMessages(int $conversationId, int $perPage = 30): array
    {
        $conversation = $this->conversationRepository->findConversationById($conversationId);
        if (! $conversation || ! $this->canAccessConversation($conversation)) {
            return ServiceResult::error('Conversation not found', null, null, 404);
        }

        $messages = $this->conversationRepository->paginateMessages($conversationId, $perPage);
        $this->conversationRepository->markIncomingAsReadForUser($conversationId, auth()->id());

        return ServiceResult::success([
            'items' => CommunicationMessageResource::collection($messages->items())->resolve(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ], 'Conversation messages fetched successfully');
    }

    public function sendMessage(int $conversationId, array $data): array
    {
        return DB::transaction(function () use ($conversationId, $data) {
            $conversation = $this->conversationRepository->findConversationById($conversationId);
            if (! $conversation || ! $this->canAccessConversation($conversation)) {
                return ServiceResult::error('Conversation not found', null, null, 404);
            }

            $sender = auth()->user();

            $message = $this->conversationRepository->createMessage([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender?->id,
                'sender_name' => $data['sender_name'] ?? $sender?->name,
                'sender_type' => $data['sender_type'] ?? $this->resolveSenderType($conversation),
                'text' => $data['text'] ?? null,
                'type' => $data['type'] ?? CommunicationMessage::TYPE_TEXT,
                'related_id' => $data['related_id'] ?? null,
                'attachment_url' => $data['attachment_url'] ?? null,
                'is_system_message' => (bool) ($data['is_system_message'] ?? false),
                'is_read' => false,
            ]);

            $this->conversationRepository->updateConversation($conversation, [
                'last_message_text' => $message->text ?: 'Attachment',
                'last_message_at' => now(),
                'last_message_sender_id' => $sender?->id,
            ]);

            $this->notifyMessageRecipient($conversation, $message, $sender?->id, $sender?->name);

            return ServiceResult::success(
                (new CommunicationMessageResource($message))->resolve(),
                'Message sent successfully',
                201
            );
        });
    }

    public function sendCase(int $conversationId, int $caseId): array
    {
        return DB::transaction(function () use ($conversationId, $caseId) {
            $conversation = $this->conversationRepository->findConversationById($conversationId);
            if (! $conversation || ! $this->canAccessConversation($conversation)) {
                return ServiceResult::error('Conversation not found', null, null, 404);
            }

            [$labId, $clinicId] = $this->conversationLabClinic($conversation);

            $case = CaseModel::query()
                ->with(['patient.user:id,name'])
                ->where('id', $caseId)
                ->where('lab_id', $labId)
                ->where('clinic_id', $clinicId)
                ->first();

            if (! $case) {
                $actualCase = CaseModel::query()->select(['id', 'case_number', 'lab_id', 'clinic_id'])->find($caseId);
                Log::warning('Communication send-case lookup failed.', [
                    'conversation_id' => $conversation->id,
                    'requested_case_id' => $caseId,
                    'requested_case_id_type' => gettype($caseId),
                    'comparison_lab_id' => $labId,
                    'comparison_lab_id_type' => gettype($labId),
                    'comparison_clinic_id' => $clinicId,
                    'comparison_clinic_id_type' => gettype($clinicId),
                    'conversation_lab_id' => $conversation->lab_id,
                    'conversation_lab_id_type' => gettype($conversation->lab_id),
                    'conversation_clinic_id' => $conversation->clinic_id,
                    'conversation_clinic_id_type' => gettype($conversation->clinic_id),
                    'actual_case' => $actualCase?->toArray(),
                ]);

                return ServiceResult::error('Case not found for this conversation.', null, null, 404);
            }

            $sender = auth()->user();
            $caseNumber = $case->case_number ?: ('CASE-' . $case->id);

            $message = $this->conversationRepository->createMessage([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender?->id,
                'sender_name' => $sender?->name,
                'sender_type' => $this->resolveSenderType($conversation),
                'text' => 'Case Details',
                'content' => 'Case Details',
                'type' => CommunicationMessage::TYPE_SYSTEM,
                'message_type' => 'case',
                'related_id' => $case->id,
                'related_type' => 'case',
                'attachment_name' => $caseNumber,
                'is_system_message' => true,
                'is_read' => false,
            ]);

            $this->conversationRepository->updateConversation($conversation, [
                'last_message_text' => "Shared case: {$caseNumber}",
                'last_message_at' => now(),
                'last_message_sender_id' => $sender?->id,
            ]);

            $this->notifyMessageRecipient($conversation, $message, $sender?->id, $sender?->name);

            return ServiceResult::success(
                (new CommunicationMessageResource($message))->resolve(),
                'Case sent successfully',
                201
            );
        });
    }

    public function sendInvoice(int $conversationId, int $invoiceId): array
    {
        return DB::transaction(function () use ($conversationId, $invoiceId) {
            $conversation = $this->conversationRepository->findConversationById($conversationId);
            if (! $conversation || ! $this->canAccessConversation($conversation)) {
                return ServiceResult::error('Conversation not found', null, null, 404);
            }

            [$labId, $clinicId] = $this->conversationLabClinic($conversation);

            $invoice = LabInvoice::query()
                ->with(['items:id,lab_invoice_id,patient_name,case_number'])
                ->where('id', $invoiceId)
                ->where('lab_id', $labId)
                ->where('clinic_id', $clinicId)
                ->first();

            if (! $invoice) {
                $actualInvoice = LabInvoice::query()->select(['id', 'invoice_number', 'lab_id', 'clinic_id'])->find($invoiceId);
                Log::warning('Communication send-invoice lookup failed.', [
                    'conversation_id' => $conversation->id,
                    'requested_invoice_id' => $invoiceId,
                    'requested_invoice_id_type' => gettype($invoiceId),
                    'comparison_lab_id' => $labId,
                    'comparison_lab_id_type' => gettype($labId),
                    'comparison_clinic_id' => $clinicId,
                    'comparison_clinic_id_type' => gettype($clinicId),
                    'conversation_lab_id' => $conversation->lab_id,
                    'conversation_lab_id_type' => gettype($conversation->lab_id),
                    'conversation_clinic_id' => $conversation->clinic_id,
                    'conversation_clinic_id_type' => gettype($conversation->clinic_id),
                    'actual_invoice' => $actualInvoice?->toArray(),
                ]);

                return ServiceResult::error('Invoice not found for this conversation.', null, null, 404);
            }

            $sender = auth()->user();
            $invoiceNumber = $invoice->invoice_number ?: ('INV-' . $invoice->id);

            $message = $this->conversationRepository->createMessage([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender?->id,
                'sender_name' => $sender?->name,
                'sender_type' => $this->resolveSenderType($conversation),
                'text' => 'Invoice Sent',
                'content' => 'Invoice Sent',
                'type' => CommunicationMessage::TYPE_SYSTEM,
                'message_type' => 'invoice',
                'related_id' => $invoice->id,
                'related_type' => 'invoice',
                'attachment_name' => $invoiceNumber,
                'is_system_message' => true,
                'is_read' => false,
            ]);

            $this->conversationRepository->updateConversation($conversation, [
                'last_message_text' => "Shared invoice: {$invoiceNumber}",
                'last_message_at' => now(),
                'last_message_sender_id' => $sender?->id,
            ]);

            $this->notifyMessageRecipient($conversation, $message, $sender?->id, $sender?->name);

            return ServiceResult::success(
                (new CommunicationMessageResource($message))->resolve(),
                'Invoice sent successfully',
                201
            );
        });
    }

    public function updateConversationStatus(int $conversationId, array $data): array
    {
        $conversation = $this->conversationRepository->findConversationById($conversationId);
        if (! $conversation || ! $this->canAccessConversation($conversation)) {
            return ServiceResult::error('Conversation not found', null, null, 404);
        }

        $updated = $this->conversationRepository->updateConversation($conversation, [
            'status' => $data['status'],
        ]);

        return ServiceResult::success(
            (new CommunicationConversationResource($updated))->resolve(),
            'Conversation status updated successfully'
        );
    }

    public function markRead(int $conversationId): array
    {
        $conversation = $this->conversationRepository->findConversationById($conversationId);
        if (! $conversation || ! $this->canAccessConversation($conversation)) {
            return ServiceResult::error('Conversation not found', null, null, 404);
        }

        $this->conversationRepository->markIncomingAsReadForUser($conversationId, auth()->id());

        return ServiceResult::success(null, 'Conversation marked as read');
    }

    private function canAccessConversation(CommunicationConversation $conversation): bool
    {
        $user = auth()->user();

        if ($user?->lab_id && (int) $conversation->lab_id === (int) $user->lab_id) {
            return true;
        }

        if ($user?->clinic_id && (int) $conversation->clinic_id === (int) $user->clinic_id) {
            return true;
        }

        return false;
    }

    private function resolveSenderType(CommunicationConversation $conversation): string
    {
        $user = auth()->user();

        if ($user?->lab_id && (int) $conversation->lab_id === (int) $user->lab_id) {
            return 'lab';
        }

        if ($user?->clinic_id && (int) $conversation->clinic_id === (int) $user->clinic_id) {
            return 'clinic';
        }

        return 'user';
    }

    private function notifyMessageRecipient(CommunicationConversation $conversation, CommunicationMessage $message, ?int $senderId, ?string $senderName): void
    {
        $senderType = $message->sender_type;
        $audienceType = $senderType === 'lab' ? 'clinic' : 'lab';
        $audienceId = $senderType === 'lab' ? $conversation->clinic_id : $conversation->lab_id;

        if (! $audienceId) {
            return;
        }

        Notification::query()->create([
            'title' => 'New Message',
            'message' => $message->text ?: 'New attachment received.',
            'type' => 'message',
            'status' => 'Sent',
            'audience_type' => $audienceType,
            'audience_id' => $audienceId,
            'priority' => 'Normal',
            'delivery_methods' => ['system'],
            'is_read' => false,
            'sender_id' => $senderId,
            'sender_name' => $senderName,
            'link' => '/communication',
        ]);
    }

    private function conversationLabClinic(CommunicationConversation $conversation): array
    {
        $user = auth()->user();

        $labId = (int) ($user?->lab_id ?: $conversation->lab_id);
        $clinicId = (int) ($conversation->clinic_id ?: ($user?->clinic_id ?: 0));

        return [$labId ?: null, $clinicId ?: null];
    }
}
