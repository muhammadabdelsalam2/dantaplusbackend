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

    public function listSendableCases(int $conversationId, array $filters = []): array
    {
        $conversation = $this->conversationRepository->findConversationById($conversationId);
        if (! $conversation || ! $this->canAccessConversation($conversation)) {
            return ServiceResult::error('Conversation not found', null, null, 404);
        }

        [$labId, $clinicId] = $this->conversationLabClinic($conversation);

        if (! $clinicId || ! $labId) {
            return ServiceResult::error('Conversation is not linked to a clinic and lab.', null, null, 422);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = max(1, min((int) ($filters['per_page'] ?? 20), 100));

        $cases = $this->sendableCasesQuery($labId, $clinicId, $search)->paginate($perPage);

        return ServiceResult::success([
            'items' => collect($cases->items())->map(fn (CaseModel $case) => $this->mapSendableCase($case))->values()->all(),
            'pagination' => [
                'current_page' => $cases->currentPage(),
                'last_page' => $cases->lastPage(),
                'per_page' => $cases->perPage(),
                'total' => $cases->total(),
            ],
        ], 'Sendable cases fetched successfully');
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
                ->with('patient.user:id,name')
                ->where('clinic_id', $clinicId)
                ->where('lab_id', $labId)
                ->find($caseId);

            if (! $case) {
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

    public function listSendables(int $conversationId, array $filters = []): array
    {
        $conversation = $this->conversationRepository->findConversationById($conversationId);
        if (! $conversation || ! $this->canAccessConversation($conversation)) {
            return ServiceResult::error('Conversation not found', null, null, 404);
        }

        [$labId, $clinicId] = $this->conversationLabClinic($conversation);

        if (! $clinicId || ! $labId) {
            return ServiceResult::error('Conversation is not linked to a clinic and lab.', null, null, 422);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        $limit = max(1, min((int) ($filters['limit'] ?? $filters['per_page'] ?? 50), 100));

        $cases = $this->sendableCasesQuery($labId, $clinicId, $search)
            ->limit($limit)
            ->get()
            ->map(fn (CaseModel $case) => $this->mapSendableCase($case))
            ->values()
            ->all();

        $invoices = $this->sendableInvoicesQuery($labId, $clinicId, $search)
            ->limit($limit)
            ->get()
            ->map(fn (LabInvoice $invoice) => $this->mapSendableInvoice($invoice))
            ->values()
            ->all();

        return ServiceResult::success([
            'conversation_id' => $conversation->id,
            'clinic_id' => $clinicId,
            'lab_id' => $labId,
            'sendable_cases' => $cases,
            'sendable_invoices' => $invoices,
        ], 'Sendable cases and invoices fetched successfully');
    }

    public function listSendableInvoices(int $conversationId, array $filters = []): array
    {
        $conversation = $this->conversationRepository->findConversationById($conversationId);
        if (! $conversation || ! $this->canAccessConversation($conversation)) {
            return ServiceResult::error('Conversation not found', null, null, 404);
        }

        [$labId, $clinicId] = $this->conversationLabClinic($conversation);

        if (! $clinicId || ! $labId) {
            return ServiceResult::error('Conversation is not linked to a clinic and lab.', null, null, 422);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = max(1, min((int) ($filters['per_page'] ?? 20), 100));

        $invoices = $this->sendableInvoicesQuery($labId, $clinicId, $search)->paginate($perPage);

        return ServiceResult::success([
            'items' => collect($invoices->items())->map(fn (LabInvoice $invoice) => $this->mapSendableInvoice($invoice))->values()->all(),
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ], 'Sendable invoices fetched successfully');
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
                ->where('clinic_id', $clinicId)
                ->where('lab_id', $labId)
                ->find($invoiceId);

            if (! $invoice) {
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

    private function mapSendableCase(CaseModel $case): array
    {
        return [
            'id' => $case->id,
            'case_id' => $case->case_number ?: ('CASE-' . $case->id),
            'case_number' => $case->case_number,
            'caseNumber' => $case->case_number,
            'patient_name' => $case->patient?->user?->name,
            'case_type' => $case->case_type,
            'caseType' => $case->case_type,
            'status' => $case->status,
            'due_date' => optional($case->due_date)->toDateString(),
        ];
    }

    private function mapSendableInvoice(LabInvoice $invoice): array
    {
        $firstItem = $invoice->items->first();

        return [
            'id' => $invoice->id,
            'invoice_id' => $invoice->invoice_number ?: ('INV-' . $invoice->id),
            'invoice_number' => $invoice->invoice_number,
            'clinic_id' => $invoice->clinic_id,
            'clinic_name' => $invoice->clinic?->name,
            'patient_name' => $firstItem?->patient_name,
            'issue_date' => optional($invoice->issue_date)->toDateString(),
            'due_date' => optional($invoice->due_date)->toDateString(),
            'amount' => (float) $invoice->total_amount,
            'total_amount' => (float) $invoice->total_amount,
            'remaining_amount' => (float) $invoice->remaining_amount,
            'status' => $invoice->status,
        ];
    }

    private function sendableCasesQuery(int $labId, int $clinicId, string $search)
    {
        return CaseModel::query()
            ->with('patient.user:id,name')
            ->where('lab_id', $labId)
            ->where('clinic_id', $clinicId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('case_number', 'like', "%{$search}%")
                        ->orWhere('case_type', 'like', "%{$search}%")
                        ->orWhereHas('patient.user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id');
    }

    private function sendableInvoicesQuery(int $labId, int $clinicId, string $search)
    {
        return LabInvoice::query()
            ->with(['clinic:id,name,email,phone', 'items:id,lab_invoice_id,patient_name,case_number'])
            ->where('lab_id', $labId)
            ->where('clinic_id', $clinicId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('items', function ($item) use ($search) {
                            $item->where('patient_name', 'like', "%{$search}%")
                                ->orWhere('case_number', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id');
    }

    private function conversationLabClinic(CommunicationConversation $conversation): array
    {
        $user = auth()->user();

        $labId = (int) ($user?->lab_id ?: $conversation->lab_id);
        $clinicId = (int) ($conversation->clinic_id ?: ($user?->clinic_id ?: 0));

        return [$labId ?: null, $clinicId ?: null];
    }
}
