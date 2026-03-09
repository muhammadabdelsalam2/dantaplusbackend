<?php

namespace App\Repositories;

use App\Models\MaintenanceCompany;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MaintenanceCompanyRepository
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return MaintenanceCompany::query()
            ->withCount([
                'maintenanceRequests as total_requests',
                'maintenanceRequests as completed_requests' => fn ($q) => $q
                    ->whereIn('status', ['Resolved', 'Closed']),
            ])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function create(array $data): MaintenanceCompany
    {
        return MaintenanceCompany::create($data);
    }
}
