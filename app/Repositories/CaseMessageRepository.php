<?php

namespace App\Repositories;

use App\Models\CaseMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CaseMessageRepository
{
    public function paginateByCase(int $caseId, int $perPage = 30): LengthAwarePaginator
    {
        return CaseMessage::query()
            ->where('case_id', $caseId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function create(array $data): CaseMessage
    {
        return CaseMessage::query()->create($data);
    }

    public function markUnreadAsRead(int $caseId, ?int $viewerId = null): void
    {
        $query = CaseMessage::query()
            ->where('case_id', $caseId)
            ->where('is_read', false);

        if ($viewerId) {
            $query->where(function ($q) use ($viewerId) {
                $q->whereNull('sender_id')->orWhere('sender_id', '!=', $viewerId);
            });
        }

        $query->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}
