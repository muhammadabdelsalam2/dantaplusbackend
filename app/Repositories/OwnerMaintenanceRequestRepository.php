<?php

namespace App\Repositories;

use App\Models\OwnerMaintenanceRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OwnerMaintenanceRequestRepository
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return OwnerMaintenanceRequest::query()
            ->with(['clinic:id,name', 'company:id,name'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['clinic_id'] ?? null, fn ($query, $clinicId) => $query->where('clinic_id', $clinicId))
            ->when($filters['company_id'] ?? null, fn ($query, $companyId) => $query->where('assigned_company_id', $companyId))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('request_code', 'like', "%{$search}%")
                        ->orWhere('equipment', 'like', "%{$search}%")
                        ->orWhere('issue_description', 'like', "%{$search}%")
                        ->orWhereHas('clinic', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findById(int $id): ?OwnerMaintenanceRequest
    {
        return OwnerMaintenanceRequest::query()->find($id);
    }

    public function create(array $data): OwnerMaintenanceRequest
    {
        return OwnerMaintenanceRequest::create($data);
    }

    public function update(OwnerMaintenanceRequest $request, array $data): OwnerMaintenanceRequest
    {
        $request->update($data);

        return $request->refresh();
    }
}
