<?php

namespace App\Repositories;

use App\Models\AiAlert;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AiAlertRepository
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return AiAlert::query()
            ->with('company:id,name')
            ->when(isset($filters['is_reviewed']), fn ($query) => $query->where('is_reviewed', (bool) $filters['is_reviewed']))
            ->when($filters['company_id'] ?? null, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->when($filters['severity'] ?? null, fn ($query, $severity) => $query->where('severity', $severity))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findById(int $id): ?AiAlert
    {
        return AiAlert::query()->find($id);
    }

    public function update(AiAlert $alert, array $data): AiAlert
    {
        $alert->update($data);

        return $alert->refresh();
    }
}
