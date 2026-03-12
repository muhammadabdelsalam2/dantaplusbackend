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
        $tickets = $this->ticketRepository->paginateForLab((int) $user->lab_id, $filters, $perPage);

        $items = collect($tickets->items())->map(function (LabSupportTicket $ticket) {
            return [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
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
            'created_by' => (int) $user->id,
            'title' => $data['title'],
            'category' => $data['category'],
            'priority' => $data['priority'] ?? LabSupportTicket::PRIORITY_MEDIUM,
            'status' => LabSupportTicket::STATUS_OPEN,
            'description' => $data['description'],
            'attachment_url' => $attachmentUrl,
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

        if (!$ticket) {
            return ServiceResult::error('Support ticket not found.', null, null, 404);
        }

        return ServiceResult::success($this->mapTicketDetails($ticket), 'Support ticket fetched successfully');
    }

    private function mapTicketDetails(?LabSupportTicket $ticket): array
    {
        if (!$ticket) {
            return [];
        }

        return [
            'id' => $ticket->id,
            'title' => $ticket->title,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'description' => $ticket->description,
            'attachment_url' => $ticket->attachment_url ? asset('storage/' . $ticket->attachment_url) : null,
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
}
