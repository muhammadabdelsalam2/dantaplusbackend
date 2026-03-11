<?php

namespace App\Repositories;

use App\Models\FeedbackReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class FeedbackReportRepository
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return FeedbackReport::query()
            ->with(['clinic:id,name', 'patient.user:id,name'])
            ->when($filters['clinic_id'] ?? null, fn (Builder $q, $clinicId) => $q->where('clinic_id', $clinicId))
            ->when($filters['patient_id'] ?? null, fn (Builder $q, $patientId) => $q->where('patient_id', $patientId))
            ->when($filters['rating'] ?? null, fn (Builder $q, $rating) => $q->where('rating', $rating))
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('submitted_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->whereDate('submitted_at', '<=', $to))
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
