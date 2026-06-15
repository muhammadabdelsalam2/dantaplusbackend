<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class LabSelectController extends Controller
{
    use ApiResponse;

    public function show(Request $request, string $resource)
    {
        $labId = auth()->user()?->lab_id;
        if (! $labId) {
            return ApiResponse::error('Lab account is not linked to a dental lab.', 403);
        }

        $search = $request->query('search');
        $clinicId = $request->query('clinic_id');

        $items = match ($resource) {
            'clinics'       => $this->clinics($search),
            'patients'      => $this->patients($clinicId, $search),
            'dentists'      => $this->dentists($clinicId, $search),
            'technicians'   => $this->technicians($labId, $search),
            'delivery_reps' => $this->deliveryReps($labId, $search),
            'materials'     => $this->materials($labId, $search),
            default         => null,
        };

        if ($items === null) {
            return ApiResponse::error('Unsupported select resource.', 422);
        }

        return ApiResponse::success($items, 'Select options fetched successfully');
    }

    private function clinics(?string $search): array
    {
        return Clinic::query()
            ->select('id', 'name')
            ->when($search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
            ->values()->all();
    }

    private function patients(?int $clinicId, ?string $search): array
    {
        if (! $clinicId) {
            return [];
        }

        return Patient::query()
            ->where('clinic_id', $clinicId)
            ->with('user:id,name')
            ->when($search, fn ($q, $s) =>
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%"))
            )
            ->limit(50)
            ->get()
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->user?->name])
            ->values()->all();
    }

    private function dentists(?int $clinicId, ?string $search): array
    {
        if (! $clinicId) {
            return [];
        }

        return Doctor::query()
            ->whereHas('user', fn ($q) => $q->where('clinic_id', $clinicId))
            ->with('user:id,name')
            ->when($search, fn ($q, $s) =>
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%"))
            )
            ->limit(50)
            ->get()
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->user?->name])
            ->values()->all();
    }

    private function technicians(int $labId, ?string $search): array
    {
        return User::query()
            ->where('lab_id', $labId)
            ->role('lab_technician')
            ->when($search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->select('id', 'name')
            ->limit(50)
            ->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->values()->all();
    }

    private function deliveryReps(int $labId, ?string $search): array
    {
        return User::query()
            ->where('lab_id', $labId)
            ->role('delivery_rep')
            ->when($search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->select('id', 'name')
            ->limit(50)
            ->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->values()->all();
    }
    private function materials(int $labId, ?string $search): array
{
    return \App\Models\LabMaterial::query()
        ->where('lab_id', $labId)
        ->when($search, fn ($q, $s) =>
            $q->where('name', 'like', "%{$s}%")
        )
        ->select('id', 'name')
        ->orderBy('name')
        ->limit(50)
        ->get()
        ->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
        ])
        ->values()
        ->all();
}
}
