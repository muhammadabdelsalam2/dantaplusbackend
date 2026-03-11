<?php

namespace App\Services\Owner;

use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\User;
use App\Repositories\SupportTicketRepository;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportCenterService
{
    public function __construct(private SupportTicketRepository $repository) {}

    public function listTickets(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $tickets = $this->repository->paginate($filters, $perPage);

        $items = collect($tickets->items())
            ->map(fn (SupportTicket $ticket) => $this->mapTicketSummary($ticket))
            ->values()
            ->all();

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

    public function showTicket(int $id): array
    {
        $ticket = $this->repository->findById($id);

        if (! $ticket) {
            return ServiceResult::error('Support ticket not found', null, null, 404);
        }

        $replies = $ticket->replies->map(fn (SupportReply $reply) => $this->mapReply($reply))->values();

        return ServiceResult::success([
            'ticket' => $this->mapTicketDetails($ticket),
            'replies' => $replies,
        ], 'Support ticket fetched successfully');
    }

    public function updateTicket(int $id, array $data): array
    {
        $ticket = $this->repository->findById($id);

        if (! $ticket) {
            return ServiceResult::error('Support ticket not found', null, null, 404);
        }

        $updated = $this->repository->update($ticket, [
            'status' => $data['status'] ?? $ticket->status,
            'priority' => $data['priority'] ?? $ticket->priority,
            'assigned_to' => $data['assigned_to'] ?? $ticket->assigned_to,
            'category' => $data['category'] ?? $ticket->category,
        ]);

        return ServiceResult::success($this->mapTicketDetails($updated), 'Support ticket updated successfully');
    }

    public function addReply(int $ticketId, array $data): array
    {
        return DB::transaction(function () use ($ticketId, $data) {
            $ticket = $this->repository->findById($ticketId);

            if (! $ticket) {
                return ServiceResult::error('Support ticket not found', null, null, 404);
            }

            $sender = auth()->user();
            $senderRole = $data['sender_role'] ?? ($sender?->getRoleNames()->first() ?? 'super-admin');

            $reply = $this->repository->addReply($ticket, [
                'sender_id' => $data['sender_id'] ?? $sender?->id,
                'sender_name' => $data['sender_name'] ?? $sender?->name ?? 'Support Agent',
                'sender_role' => $senderRole,
                'message' => $data['message'],
            ]);

            $this->repository->update($ticket, [
                'last_reply_at' => now(),
            ]);

            return ServiceResult::success($this->mapReply($reply), 'Reply added successfully', 201);
        });
    }

    public function analytics(): array
    {
        $totals = [
            'total_tickets' => SupportTicket::query()->count(),
            'open_tickets' => SupportTicket::query()->where('status', SupportTicket::STATUS_OPEN)->count(),
            'in_progress_tickets' => SupportTicket::query()->where('status', SupportTicket::STATUS_IN_PROGRESS)->count(),
            'resolved_tickets' => SupportTicket::query()->where('status', SupportTicket::STATUS_RESOLVED)->count(),
            'high_priority_tickets' => SupportTicket::query()
                ->where('priority', SupportTicket::PRIORITY_HIGH)
                ->count(),
            'unassigned_tickets' => SupportTicket::query()->whereNull('assigned_to')->count(),
        ];

        return ServiceResult::success($totals, 'Support analytics fetched successfully');
    }

    public function listAgents(): array
    {
        $agents = User::query()
            ->role(['super-admin', 'Admin', 'support-agent'])
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->values()
            ->all();

        return ServiceResult::success(['items' => $agents], 'Support agents fetched successfully');
    }

    public function createTicket(array $data): array
    {
        $ticket = $this->repository->create([
            'code' => $this->generateCode(),
            'reporter_type' => $data['reporter_type'],
            'reporter_id' => $data['reporter_id'],
            'clinic_id' => $data['clinic_id'] ?? null,
            'lab_id' => $data['lab_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'],
            'category' => $data['category'],
            'priority' => $data['priority'] ?? SupportTicket::PRIORITY_MEDIUM,
            'status' => $data['status'] ?? SupportTicket::STATUS_OPEN,
            'assigned_to' => $data['assigned_to'] ?? null,
            'last_reply_at' => null,
        ]);

        return ServiceResult::success($this->mapTicketDetails($ticket), 'Support ticket created successfully', 201);
    }

    private function generateCode(): string
    {
        return 'ST-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
    }

    private function mapTicketSummary(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'code' => $ticket->code,
            'reporterType' => $ticket->reporter_type,
            'reporterId' => $ticket->reporter_id,
            'clinicId' => $ticket->clinic_id,
            'clinicName' => $ticket->clinic?->name,
            'labId' => $ticket->lab_id,
            'labName' => $ticket->lab?->name,
            'title' => $ticket->title,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'assignedTo' => $ticket->assigned_to,
            'assignedToName' => $ticket->assignedTo?->name,
            'lastReplyAt' => optional($ticket->last_reply_at)->toISOString(),
            'createdAt' => optional($ticket->created_at)->toISOString(),
        ];
    }

    private function mapTicketDetails(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'code' => $ticket->code,
            'reporterType' => $ticket->reporter_type,
            'reporterId' => $ticket->reporter_id,
            'clinicId' => $ticket->clinic_id,
            'clinicName' => $ticket->clinic?->name,
            'labId' => $ticket->lab_id,
            'labName' => $ticket->lab?->name,
            'title' => $ticket->title,
            'description' => $ticket->description,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'assignedTo' => $ticket->assigned_to,
            'assignedToName' => $ticket->assignedTo?->name,
            'lastReplyAt' => optional($ticket->last_reply_at)->toISOString(),
            'createdAt' => optional($ticket->created_at)->toISOString(),
            'updatedAt' => optional($ticket->updated_at)->toISOString(),
        ];
    }

    private function mapReply(SupportReply $reply): array
    {
        return [
            'id' => $reply->id,
            'supportTicketId' => $reply->support_ticket_id,
            'senderId' => $reply->sender_id,
            'senderName' => $reply->sender_name,
            'senderRole' => $reply->sender_role,
            'message' => $reply->message,
            'createdAt' => optional($reply->created_at)->toISOString(),
        ];
    }
}
