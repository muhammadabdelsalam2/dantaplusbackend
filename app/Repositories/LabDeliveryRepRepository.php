<?php

namespace App\Repositories;

use App\Models\LabDeliveryRep;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LabDeliveryRepRepository
{
    public function paginateForLab(int $labId, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return LabDeliveryRep::query()
            ->with(['user:id,name,email,phone,is_active,lab_id,created_at'])
            ->where('lab_id', $labId)
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })->orWhere('assigned_region_city', 'like', "%{$search}%");
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate($perPage);
    }

    public function findForLabById(int $labId, int $id): ?LabDeliveryRep
    {
        return LabDeliveryRep::query()
            ->with(['user:id,name,email,phone,is_active,lab_id,created_at'])
            ->where('lab_id', $labId)
            ->find($id);
    }

    public function create(array $data): LabDeliveryRep
    {
        return LabDeliveryRep::create($data);
    }

    public function update(LabDeliveryRep $rep, array $data): LabDeliveryRep
    {
        $rep->update($data);

        return $rep->refresh()->load(['user:id,name,email,phone,is_active,lab_id,created_at']);
    }

    public function delete(LabDeliveryRep $rep): void
    {
        $rep->delete();
    }
}
