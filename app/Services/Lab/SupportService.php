<?php

namespace App\Services\Lab;

use App\Models\LabSupportTicket;
use App\Repositories\LabSupportTicketRepository;
use App\Support\ServiceResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupportService
{
    public function __construct(private LabSupportTicketRepository $ticketRepository)
    {
    }

    public function listTickets(array $filters): array
    {
        $user = auth()->user();

        if (!$user || !$user->lab_id) {
            return ServiceResult::error('Authenticated lab account is required.', null, null, 403);
        }

        $perPage = (int) ($filters['per_page'] ?? 10);
        $filters['created_by'] = (int) $user->id;
        $tickets = $this->ticketRepository->paginateForLab((int) $user->lab_id, $filters, $perPage);

        $items = collect($tickets->items())->map(function (LabSupportTicket $ticket) {
            return [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'title' => $ticket->title,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'status_label' => $this->label($ticket->status),
                'preview' => Str::limit((string) $ticket->description, 120),
                'created_at' => optional($ticket->created_at)->toISOString(),
                'updated_at' => optional($ticket->updated_at)->toISOString(),
            ];
        })->values()->all();

        return ServiceResult::success([
            'items' => $items,
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ], 'Support tickets fetched successfully');
    }

    public function createTicket(array $data): array
    {
        $user = auth()->user();

        if (!$user || !$user->lab_id) {
            return ServiceResult::error('Authenticated lab account is required.', null, null, 403);
        }

        $attachmentUrl = null;

        if (isset($data['attachment']) && $data['attachment'] instanceof UploadedFile) {
            $attachmentUrl = $this->storeAttachment($data['attachment']);
        }

        $ticket = $this->ticketRepository->create([
            'lab_id' => (int) $user->lab_id,
            'ticket_number' => $this->generateTicketNumber(),
            'created_by' => (int) $user->id,
            'title' => $data['title'],
            'category' => $data['category'],
            'priority' => $data['priority'] ?? LabSupportTicket::PRIORITY_MEDIUM,
            'status' => LabSupportTicket::STATUS_OPEN,
            'description' => $data['description'],
            'attachment_url' => $attachmentUrl,
        ]);

        $this->ticketRepository->createMessage($ticket, [
            'sender_id' => (int) $user->id,
            'sender_name' => $user->name,
            'sender_type' => 'lab',
            'message' => $data['description'],
        ]);

        $fresh = $this->ticketRepository->findForLabById((int) $user->lab_id, $ticket->id);

        return ServiceResult::success($this->mapTicketDetails($fresh), 'Support ticket created successfully', 201);
    }

    public function showTicket(int $id): array
    {
        $user = auth()->user();

        if (!$user || !$user->lab_id) {
            return ServiceResult::error('Authenticated lab account is required.', null, null, 403);
        }

        $ticket = $this->ticketRepository->findForLabById((int) $user->lab_id, $id);

        if (!$ticket || (int) $ticket->created_by !== (int) $user->id) {
            return ServiceResult::error('Support ticket not found.', null, null, 404);
        }

        return ServiceResult::success($this->mapTicketDetails($ticket), 'Support ticket fetched successfully');
    }

    public function sendMessage(int $id, array $data): array
    {
        $user = auth()->user();

        if (!$user || !$user->lab_id) {
            return ServiceResult::error('Authenticated lab account is required.', null, null, 403);
        }

        $ticket = $this->ticketRepository->findForLabById((int) $user->lab_id, $id);

        if (!$ticket || (int) $ticket->created_by !== (int) $user->id) {
            return ServiceResult::error('Support ticket not found.', null, null, 404);
        }

        $message = $this->ticketRepository->createMessage($ticket, [
            'sender_id' => (int) $user->id,
            'sender_name' => $user->name,
            'sender_type' => 'lab',
            'message' => $data['message'],
        ]);

        return ServiceResult::success($this->mapMessage($message), 'Support ticket message sent successfully', 201);
    }

    private function mapTicketDetails(?LabSupportTicket $ticket): array
    {
        if (!$ticket) {
            return [];
        }

        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'title' => $ticket->title,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'status_label' => $this->label($ticket->status),
            'description' => $ticket->description,
            'attachment_url' => $ticket->attachment_url ? asset('storage/' . $ticket->attachment_url) : null,
            'messages' => $ticket->messages
                ->sortBy('created_at')
                ->values()
                ->map(fn ($message) => $this->mapMessage($message))
                ->all(),
            'lab_id' => $ticket->lab_id,
            'created_by' => [
                'id' => $ticket->creator?->id,
                'name' => $ticket->creator?->name,
                'email' => $ticket->creator?->email,
            ],
            'created_at' => optional($ticket->created_at)->toISOString(),
            'updated_at' => optional($ticket->updated_at)->toISOString(),
        ];
    }

    private function storeAttachment(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $filename = (string) Str::uuid() . '.' . $extension;

        Storage::disk('public')->putFileAs('lab-support', $file, $filename);

        return 'lab-support/' . $filename;
    }

    private function mapMessage(\App\Models\LabSupportTicketMessage $message): array
    {
        return [
            'id' => $message->id,
            'ticket_id' => $message->ticket_id,
            'sender' => [
                'id' => $message->sender_id,
                'name' => $message->sender_name,
                'type' => $message->sender_type,
            ],
            'message' => $message->message,
            'created_at' => optional($message->created_at)->toISOString(),
            'time' => optional($message->created_at)->format('H:i'),
        ];
    }

    private function generateTicketNumber(): string
    {
        do {
            $number = 'SUP-' . random_int(10000, 99999);
        } while (LabSupportTicket::query()->where('ticket_number', $number)->exists());

        return $number;
    }

    private function label(string $value): string
    {
        return str($value)->replace('_', ' ')->title()->toString();
    }
}
