<?php

namespace App\Repositories;

use App\Models\SupportReply;
use App\Models\SupportTicket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SupportTicketRepository
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = SupportTicket::query()
            ->with(['assignedTo:id,name', 'clinic:id,name', 'lab:id,name'])
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $q, $priority) => $q->where('priority', $priority))
            ->when($filters['assigned_to'] ?? null, fn (Builder $q, $assignedTo) => $q->where('assigned_to', $assignedTo))
            ->when($filters['reporter_type'] ?? null, fn (Builder $q, $reporterType) => $q->where('reporter_type', $reporterType))
            ->when($filters['clinic_id'] ?? null, fn (Builder $q, $clinicId) => $q->where('clinic_id', $clinicId))
            ->when($filters['lab_id'] ?? null, fn (Builder $q, $labId) => $q->where('lab_id', $labId));

        if (! empty($filters['q'])) {
            $search = $filters['q'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderByDesc('last_reply_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findById(int $id): ?SupportTicket
    {
        return SupportTicket::query()
            ->with([
                'assignedTo:id,name',
                'clinic:id,name',
                'lab:id,name',
                'replies' => fn ($q) => $q->orderBy('created_at'),
            ])
            ->find($id);
    }

    public function create(array $data): SupportTicket
    {
        return SupportTicket::query()->create($data);
    }

    public function update(SupportTicket $ticket, array $data): SupportTicket
    {
        $ticket->update($data);

        return $ticket->refresh();
    }

    public function addReply(SupportTicket $ticket, array $data): SupportReply
    {
        return $ticket->replies()->create($data);
    }
}
