<?php

namespace App\Repositories;

use App\Models\NotificationLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class NotificationLogRepository
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return NotificationLog::query()
            ->with(['clinic:id,name'])
            ->when($filters['clinic_id'] ?? null, fn (Builder $q, $clinicId) => $q->where('clinic_id', $clinicId))
            ->when($filters['doctor_id'] ?? null, fn (Builder $q, $doctorId) => $q->where('doctor_id', $doctorId))
            ->when($filters['channel'] ?? null, fn (Builder $q, $channel) => $q->where('channel', $channel))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('sent_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->whereDate('sent_at', '<=', $to))
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function create(array $data): NotificationLog
    {
        return NotificationLog::query()->create($data);
    }
}
