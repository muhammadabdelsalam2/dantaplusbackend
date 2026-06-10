<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommunicationController extends Controller
{
    use ApiResponse;

    public function list(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clinic_id' => ['required', 'integer', 'exists:clinics,id'],
            'contact_type' => ['required', 'string', Rule::in(CommunicationConversation::CONTACT_TYPES)],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        if ($response = $this->ensureCurrentClinic($data['clinic_id'])) {
            return $response;
        }

        $conversations = CommunicationConversation::query()
            ->where('clinic_id', $data['clinic_id'])
            ->where('contact_type', $data['contact_type'])
            ->when(! empty($data['search']), function ($query) use ($data) {
                if ($data['contact_type'] === CommunicationConversation::CONTACT_TYPE_LAB) {
                    $query->whereHas('lab', fn ($builder) => $builder->where('name', 'like', '%' . $data['search'] . '%'));

                    return;
                }

                $query->whereHas('supplier', fn ($builder) => $builder->where('name', 'like', '%' . $data['search'] . '%'));
            })
            ->with([
                'lab:id,name',
                'supplier:id,name,deleted_at',
                'latestMessage' => fn ($query) => $query->with('sender:id,name'),
            ])
            ->withCount([
                'messages as unread_count' => fn ($query) => $query
                    ->where('sender_type', '!=', 'clinic')
                    ->where('is_read', false),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (CommunicationConversation $conversation) => $this->mapConversation($conversation))
            ->values();

        return ApiResponse::success($conversations, 'Conversations fetched successfully.');
    }

    public function messages(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clinic_id' => ['required', 'integer', 'exists:clinics,id'],
            'conversation_id' => ['required', 'integer', 'exists:communication_conversations,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($response = $this->ensureCurrentClinic($data['clinic_id'])) {
            return $response;
        }

        $conversation = $this->conversationQuery($data['clinic_id'])->find($data['conversation_id']);

        if (! $conversation) {
            return ApiResponse::error('Conversation not found.', 404);
        }

        $perPage = $data['per_page'] ?? 25;

        $paginated = $conversation->messages()
            ->with('sender:id,name')
            ->orderBy('id')
            ->paginate($perPage);

        $conversation->messages()
            ->where('sender_type', '!=', 'clinic')
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return ApiResponse::success([
            'items' => collect($paginated->items())
                ->map(fn (CommunicationMessage $message) => $this->mapMessage($message))
                ->values(),
            'pagination' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 'Messages fetched successfully.');
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clinic_id' => ['required', 'integer', 'exists:clinics,id'],
            'sent_by' => ['nullable', 'integer', 'exists:users,id'],
            'conversation_id' => ['nullable', 'integer', 'exists:communication_conversations,id'],
            'contact_type' => ['nullable', 'string', Rule::in(CommunicationConversation::CONTACT_TYPES)],
            'contact_id' => ['nullable', 'integer'],
            'type' => ['required', 'string', Rule::in(['text', 'attachment'])],
            'body' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        if ($response = $this->ensureCurrentClinic($data['clinic_id'])) {
            return $response;
        }

        if (empty($data['conversation_id']) && (empty($data['contact_type']) || empty($data['contact_id']))) {
            return ApiResponse::error(
                'conversation_id or contact_type and contact_id are required.',
                422,
                ['conversation_id' => ['conversation_id or contact_type and contact_id are required.']]
            );
        }

        if ($data['type'] === 'text' && empty($data['body'])) {
            return ApiResponse::error(
                'body is required when type is text.',
                422,
                ['body' => ['body is required when type is text.']]
            );
        }

        if ($data['type'] === 'attachment' && ! $request->hasFile('attachment')) {
            return ApiResponse::error(
                'attachment is required when type is attachment.',
                422,
                ['attachment' => ['attachment is required when type is attachment.']]
            );
        }

        $message = DB::transaction(function () use ($data, $request) {
            $conversation = $this->resolveConversationForSend($data);
            $attachment = $request->file('attachment');
            $attachmentPath = $this->storeAttachment($data['clinic_id'], $attachment);

            $message = $conversation->messages()->create([
                'company_id' => $conversation->company_id,
                'sender_type' => 'clinic',
                'sender_id' => $data['sent_by'] ?? auth()->id(),
                'sender_name' => auth()->user()?->name,
                'text' => $data['body'] ?? null,
                'type' => $data['type'],
                'message_type' => $data['type'],
                'content' => $data['body'] ?? null,
                'attachment_url' => $attachmentPath,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachment?->getClientOriginalName(),
                'is_system_message' => false,
                'is_read' => false,
            ]);

            $conversation->update([
                'last_message_text' => $this->resolveLastMessageText($message),
                'last_message_at' => $message->created_at,
                'last_message_sender_id' => $message->sender_id,
            ]);

            return $message->load('sender:id,name');
        });

        return ApiResponse::success(
            $this->mapMessage($message),
            'Message sent successfully.',
            201
        );
    }

    public function read(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clinic_id' => ['required', 'integer', 'exists:clinics,id'],
            'conversation_id' => ['required', 'integer', 'exists:communication_conversations,id'],
        ]);

        if ($response = $this->ensureCurrentClinic($data['clinic_id'])) {
            return $response;
        }

        $conversation = $this->conversationQuery($data['clinic_id'])->find($data['conversation_id']);

        if (! $conversation) {
            return ApiResponse::error('Conversation not found.', 404);
        }

        $updated = $conversation->messages()
            ->where('sender_type', '!=', 'clinic')
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return ApiResponse::success([
            'updated_count' => $updated,
        ], 'Conversation marked as read successfully.');
    }

    public static function createSystemMessage(int $conversationId, string $body): void
    {
        DB::transaction(function () use ($conversationId, $body) {
            $conversation = CommunicationConversation::query()->findOrFail($conversationId);

            $message = $conversation->messages()->create([
                'company_id' => $conversation->company_id,
                'sender_type' => 'clinic',
                'sender_id' => null,
                'sender_name' => 'System',
                'text' => $body,
                'type' => 'system',
                'message_type' => 'system',
                'content' => $body,
                'is_system_message' => true,
                'is_read' => true,
            ]);

            $conversation->update([
                'last_message_text' => $body,
                'last_message_at' => $message->created_at,
                'last_message_sender_id' => null,
            ]);
        });
    }

    private function conversationQuery(int $clinicId)
    {
        return CommunicationConversation::query()->where('clinic_id', $clinicId);
    }

    private function ensureCurrentClinic(int $clinicId): ?JsonResponse
    {
        $currentClinicId = (int) (auth()->user()?->clinic_id ?? 0);

        if (! $currentClinicId || $currentClinicId !== (int) $clinicId) {
            return ApiResponse::error(
                'You are not authorized to access this clinic.',
                403,
                ['clinic_id' => ['The selected clinic is invalid for the current user.']]
            );
        }

        return null;
    }

    private function resolveConversationForSend(array $data): CommunicationConversation
    {
        if (! empty($data['conversation_id'])) {
            $conversation = $this->conversationQuery($data['clinic_id'])->find($data['conversation_id']);

            if (! $conversation) {
                throw ValidationException::withMessages([
                    'conversation_id' => ['The selected conversation_id is invalid.'],
                ]);
            }

            return $conversation;
        }

        $this->validateContact($data['contact_type'], (int) $data['contact_id']);

        return CommunicationConversation::query()->firstOrCreate(
            [
                'clinic_id' => $data['clinic_id'],
                'contact_type' => $data['contact_type'],
                'contact_id' => $data['contact_id'],
            ],
            [
                'lab_id' => $data['contact_type'] === CommunicationConversation::CONTACT_TYPE_LAB ? $data['contact_id'] : null,
                'company_id' => $data['contact_type'] === CommunicationConversation::CONTACT_TYPE_SUPPLIER ? $data['contact_id'] : null,
                'status' => CommunicationConversation::STATUS_ACTIVE,
                'last_message_at' => null,
            ]
        );
    }

    private function validateContact(string $contactType, int $contactId): void
    {
        $exists = match ($contactType) {
            CommunicationConversation::CONTACT_TYPE_LAB => DB::table('dental_labs')->where('id', $contactId)->exists(),
            CommunicationConversation::CONTACT_TYPE_SUPPLIER => DB::table('material_companies')->where('id', $contactId)->exists(),
            default => false,
        };

        if (! $exists) {
            throw ValidationException::withMessages([
                'contact_id' => ['The selected contact_id is invalid.'],
            ]);
        }
    }

   private function storeAttachment(int $clinicId, ?UploadedFile $attachment): ?string
{
    if (! $attachment instanceof UploadedFile) {
        return null;
    }

    return Storage::disk('public')->put(
        'clinic/communication/' . $clinicId . '/attachments',
        $attachment
    );
}

    private function resolveLastMessageText(CommunicationMessage $message): string
    {
        if ($message->type === 'attachment') {
            return $message->text ?: 'Attachment';
        }

        return $message->text ?: '';
    }

    private function mapConversation(CommunicationConversation $conversation): array
    {
        $lastMessage = $conversation->latestMessage;

        return [
            'id' => $conversation->id,
            'clinic_id' => $conversation->clinic_id,
            'contact_type' => $conversation->contact_type,
            'contact_id' => $conversation->contact_id,
            'contact_name' => $conversation->contact_type === CommunicationConversation::CONTACT_TYPE_LAB
                ? $conversation->lab?->name
                : $conversation->supplier?->name,
            'last_message_at' => optional($conversation->last_message_at)?->toDateTimeString(),
            'last_message' => $lastMessage ? $this->mapMessage($lastMessage) : null,
            'unread_count' => (int) ($conversation->unread_count ?? 0),
        ];
    }

   private function mapMessage(CommunicationMessage $message): array
{
    $attachmentPath = $message->attachment_path ?: $message->attachment_url;

    return [
        'id' => $message->id,
        'conversation_id' => $message->conversation_id,
        'sender_type' => $message->sender_type,
        'sender_id' => $message->sender_id,
        'sender_name' => $message->sender_name ?: $message->sender?->name,
        'type' => $message->type,
        'body' => $message->content ?? $message->text,
        'attachment_name' => $message->attachment_name,
        'attachment_path' => $attachmentPath
            ? Storage::disk('public')->url($attachmentPath)
            : null,
        'is_system_message' => (bool) $message->is_system_message,
        'read_at' => optional($message->read_at)?->toDateTimeString(),
        'created_at' => optional($message->created_at)?->toDateTimeString(),
    ];
}
}
