<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Support\ApiResponse;
use App\Traits\HasSuperAdminScope;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LabSelectController extends Controller
{
    use ApiResponse, HasSuperAdminScope;

    public function show(Request $request, string $resource)
    {
        $superAdmin = $this->isSuperAdmin();
        $labId      = auth()->user()?->lab_id;

        // Super admin bypasses the lab_id requirement entirely.
        // Regular users must be linked to a lab.
        if (! $superAdmin && ! $labId) {
            return ApiResponse::error('Lab account is not linked to a dental lab.', 403);
        }

        $search   = $request->query('search');
        $clinicId = $request->query('clinic_id');

        $items = match ($resource) {
            'clinics'           => $this->clinics($search),
            'patients'          => $this->patients($clinicId, $search, $superAdmin),
            'dentists'          => $this->dentists($clinicId, $search, $superAdmin),
            'technicians'       => $this->technicians($labId, $search, $superAdmin),
            'delivery_reps'     => $this->deliveryReps($labId, $search, $superAdmin),
            'suppliers'         => $this->suppliers($search),
            'materials'         => $this->materials($labId, $search, $superAdmin),
            'material_products' => $this->materialProducts(),
            'clinic_types'      => $this->clinicTypes(),
            'lab_roles'         => $this->labRoles(),
            'case_types'        => $this->caseTypes($labId, $superAdmin),
            default             => null,
        };

        if ($items === null) {
            return ApiResponse::error('Unsupported select resource.', 422);
        }

        return ApiResponse::success($items, 'Select options fetched successfully');
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function clinics(?string $search): array
    {
        return Clinic::query()
            ->select('id', 'name', 'email', 'clinic_type')
            ->when($search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(fn ($c) => [
                'id'          => $c->id,
                'name'        => $c->name,
                'email'       => $c->email,
                'clinic_type' => $c->clinic_type?->value,
            ])
            ->values()->all();
    }

    private function clinicTypes(): array
    {
        return collect(\App\Enums\ClinicType::cases())
            ->map(fn ($type) => [
                'value' => $type->value,
                'label' => $type->value,
            ])
            ->values()->all();
    }

    /**
     * Patients select.
     *
     * - Super admin: returns all patients (no clinic_id filter).
     * - Regular lab user: requires clinic_id; returns [] if missing.
     */
    private function patients(?string $clinicId, ?string $search, bool $superAdmin): array
    {
        if (! $superAdmin && ! $clinicId) {
            return [];
        }

        return Patient::query()
            ->with('user:id,name')
            ->when(! $superAdmin && $clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->when($search, fn ($q, $s) =>
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%"))
            )
            ->limit(50)
            ->get()
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->user?->name])
            ->values()->all();
    }

    private function labRoles(): array
    {
        return collect(\App\Support\UserRoleManager::labRoles())
            ->map(fn ($role) => [
                'value' => $role,
                'label' => str($role)->replace('_', ' ')->title()->toString(),
            ])
            ->values()->all();
    }

    /**
     * Dentists select.
     *
     * - Super admin: returns all doctors system-wide.
     * - Regular lab user: requires clinic_id; returns [] if missing.
     */
    private function dentists(?string $clinicId, ?string $search, bool $superAdmin): array
    {
        if (! $superAdmin && ! $clinicId) {
            return [];
        }

        return Doctor::query()
            ->when(
                ! $superAdmin && $clinicId,
                fn ($q) => $q->whereHas('user', fn ($u) => $u->where('clinic_id', $clinicId))
            )
            ->with('user:id,name')
            ->when($search, fn ($q, $s) =>
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%"))
            )
            ->limit(50)
            ->get()
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->user?->name])
            ->values()->all();
    }

    private function technicians(?int $labId, ?string $search, bool $superAdmin): array
    {
        return User::query()
            ->when(! $superAdmin && $labId, fn ($q) => $q->where('lab_id', $labId))
            ->role('lab_technician')
            ->when($search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->select('id', 'name')
            ->limit(50)
            ->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->values()->all();
    }

    private function deliveryReps(?int $labId, ?string $search, bool $superAdmin): array
    {
        return User::query()
            ->when(! $superAdmin && $labId, fn ($q) => $q->where('lab_id', $labId))
            ->role('delivery_representative')
            ->when($search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->select('id', 'name')
            ->limit(50)
            ->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->values()->all();
    }

    private function materials(?int $labId, ?string $search, bool $superAdmin): array
    {
        return \App\Models\LabMaterial::query()
            ->when(! $superAdmin && $labId, fn ($q) => $q->where('lab_id', $labId))
            ->when($search, fn ($q, $s) =>
                $q->where('name', 'like', "%{$s}%")
            )
            ->select('id', 'name')
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(fn ($m) => [
                'id'   => $m->id,
                'name' => $m->name,
            ])
            ->values()
            ->all();
    }

    private function suppliers(?string $search): array
    {
        return \App\Models\MaterialCompany::query()
            ->when($search, fn ($q, $s) =>
                $q->where('name', 'like', "%{$s}%")
            )
            ->select('id', 'name')
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(fn ($s) => [
                'id'   => $s->id,
                'name' => $s->name,
            ])
            ->values()
            ->all();
    }

    private function materialProducts(): array
    {
        return \App\Models\Product::query()
            ->with(['company', 'categoryRelation'])
            ->orderByDesc('id')
            ->get()
            ->toArray();
    }

    /**
     * Case types select.
     *
     * - Super admin: returns all distinct case_types across the entire system.
     * - Regular lab user: returns only case_types used by this lab (from `cases` table).
     *
     * Response shape: {id, name, code} — same as SelectsController::caseTypes().
     */
    private function caseTypes(?int $labId, bool $superAdmin): array
    {
        $names = CaseModel::query()
            ->select('case_type')
            ->distinct()
            ->when(! $superAdmin && $labId, fn ($q) => $q->where('lab_id', $labId))
            ->whereNotNull('case_type')
            ->where('case_type', '!=', '')
            ->orderBy('case_type')
            ->pluck('case_type')
            ->filter()
            ->unique()
            ->values();

        return $names
            ->map(fn (string $name, int $index) => [
                'id'   => $index + 1,
                'name' => $name,
                'code' => Str::upper(Str::slug($name, '_')),
            ])
            ->values()
            ->all();
    }
}
