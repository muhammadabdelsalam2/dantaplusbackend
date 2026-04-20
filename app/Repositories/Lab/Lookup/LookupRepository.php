<?php

namespace App\Repositories\Lab\Lookup;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LookupRepository implements LookupRepositoryInterface
{
    public function getPatientsByLab(int $labId, ?string $search = null): Collection
    {
        return Patient::query()
            ->with('user:id,name')
            ->whereHas('user', function (Builder $query) use ($labId, $search) {
                $query->whereHas('clinic.labPartnerships', function (Builder $partnershipQuery) use ($labId) {
                    $partnershipQuery->where('lab_id', $labId);
                });

                if ($search) {
                    $query->where('name', 'like', '%' . $search . '%');
                }
            })
            ->orderByDesc('id')
            ->get();
    }

    public function getDentistsByLab(int $labId, ?string $search = null): Collection
    {
        return Doctor::query()
            ->with('user:id,name')
            ->whereHas('user', function (Builder $query) use ($labId, $search) {
                $query->whereHas('clinic.labPartnerships', function (Builder $partnershipQuery) use ($labId) {
                    $partnershipQuery->where('lab_id', $labId);
                });

                if ($search) {
                    $query->where('name', 'like', '%' . $search . '%');
                }
            })
            ->orderByDesc('id')
            ->get();
    }

    public function getTechniciansByLab(int $labId, ?string $search = null): Collection
    {
        return User::query()
            ->where('lab_id', $labId)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'lab_technician'))
            ->when($search, fn (Builder $query) => $query->where('name', 'like', '%' . $search . '%'))
            ->orderByDesc('id')
            ->get();
    }
}
