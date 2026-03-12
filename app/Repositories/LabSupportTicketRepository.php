<?php

namespace App\Repositories;

use App\Models\LabSupportTicket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class LabSupportTicketRepository
{
    public function paginateForLab(int $labId, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return LabSupportTicket::query()
            ->with(['creator:id,name,email'])
            ->where('lab_id', $labId)
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $query, $priority) => $query->where('priority', $priority))
            ->when($filters['search'] ?? null, function (Builder $query, $search) {
                $query->where(function (Builder $q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForLabById(int $labId, int $id): ?LabSupportTicket
    {
        return LabSupportTicket::query()
            ->with(['creator:id,name,email'])
            ->where('lab_id', $labId)
            ->find($id);
    }

    public function create(array $data): LabSupportTicket
    {
        return LabSupportTicket::query()->create($data);
    }
}
