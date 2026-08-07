<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CaseModel;
use App\Models\DentalLab;
use App\Models\LabService;
use App\Models\Patient;
use App\Models\User;
use App\Traits\HasSuperAdminScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SelectsController extends Controller
{
    use HasSuperAdminScope;

    public function labs(): JsonResponse
    {
        $clinicId = $this->clinicId();

        $query = DentalLab::query()->orderBy('name');

        // Super admin sees all labs; regular users see only partnered labs.
        if (! $this->isSuperAdmin()) {
            $query->whereHas('partnerships', fn ($q) => $q->where('clinic_id', $clinicId));
        }

        return response()->json([
            'data' => $query
                ->get(['id', 'name'])
                ->map(fn (DentalLab $lab) => ['id' => $lab->id, 'name' => $lab->name])
                ->values(),
        ]);
    }

    public function labServices(Request $request, int $labId): JsonResponse
    {
        $clinicId = $this->clinicId();
        $search   = $request->query('search');

        $query = LabService::query()
            ->where('lab_id', $labId)
            ->when($search, fn ($q, string $term) => $q->where('service_name', 'like', "%{$term}%"))
            ->orderBy('service_name');

        // Super admin is not restricted to partnered-lab services.
        if (! $this->isSuperAdmin()) {
            $query->whereHas('lab.partnerships', fn ($q) => $q->where('clinic_id', $clinicId));
        }

        return response()->json([
            'data' => $query
                ->get()
                ->map(fn (LabService $service) => [
                    'id'          => $service->id,
                    'name'        => $service->service_name,
                    'price'       => (float) $service->price,
                    'turnaround'  => $service->turnaround_time_days . ' days',
                ])
                ->values(),
        ]);
    }

    public function caseTypes(): JsonResponse
    {
        $clinicId = $this->clinicId();

        $labServiceNames = LabService::query()
            ->select('service_name')
            ->distinct()
            ->orderBy('service_name')
            ->pluck('service_name');

        $caseTypeNames = CaseModel::query()
            ->select('case_type')
            ->distinct()
            ->when(! $this->isSuperAdmin() && $clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->pluck('case_type');

        $names = $labServiceNames
            ->merge($caseTypeNames)
            ->filter()
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            $names = collect(['Crown', 'Bridge', 'Veneer', 'Denture']);
        }

        return response()->json([
            'data' => $names->map(fn (string $name, int $index) => [
                'id'   => $index + 1,
                'name' => $name,
                'code' => Str::upper(Str::slug($name, '_')),
            ])->values(),
        ]);
    }

    public function materials(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->materialOptions()]);
    }

    public function shades(Request $request): JsonResponse
    {
        $materialId = $request->integer('material_id') ?: null;
        $shades = collect([
            ['id' => 1, 'material_id' => 1, 'name' => 'Shade A1'],
            ['id' => 2, 'material_id' => 1, 'name' => 'Shade A2'],
            ['id' => 3, 'material_id' => 2, 'name' => 'Shade B1'],
            ['id' => 4, 'material_id' => 3, 'name' => 'Shade C1'],
        ]);

        return response()->json([
            'data' => $shades
                ->when($materialId, fn ($items) => $items->where('material_id', $materialId))
                ->map(fn (array $shade) => ['id' => $shade['id'], 'name' => $shade['name']])
                ->values(),
        ]);
    }

    public function serviceCategories(): JsonResponse
    {
        return response()->json([
            'data' => Category::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Category $category) => ['id' => $category->id, 'name' => $category->name])
                ->values(),
        ]);
    }

    /**
     * Patients select with optional name search.
     *
     * Accepts ?search=<name> query param.
     * Super admin returns patients across all clinics.
     */
    public function patients(Request $request): JsonResponse
    {
        $clinicId = $this->clinicId();
        $search   = $request->query('search');

        $query = Patient::query()
            ->with('user:id,name')
            ->when(! $this->isSuperAdmin() && $clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->when($search, fn ($q, $s) =>
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%"))
            )
            ->orderBy('id')
            ->limit(50);

        return response()->json([
            'data' => $query
                ->get()
                ->map(fn (Patient $patient) => [
                    'id'   => $patient->id,
                    'name' => $patient->user?->name ?? $patient->patient_number,
                ])
                ->values(),
        ]);
    }

    public function dentists(): JsonResponse
    {
        return $this->usersByRole('doctor');
    }

    public function doctors(): JsonResponse
    {
        return $this->usersByRole('doctor');
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function usersByRole(string $role): JsonResponse
    {
        $clinicId = $this->clinicId();

        $query = User::query()
            ->when(! $this->isSuperAdmin() && $clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->role($role)
            ->orderBy('name');

        return response()->json([
            'data' => $query
                ->get(['id', 'name'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
                ->values(),
        ]);
    }

    private function materialOptions()
    {
        return collect([
            ['id' => 1, 'name' => 'Zirconia'],
            ['id' => 2, 'name' => 'Porcelain'],
            ['id' => 3, 'name' => 'E-max'],
            ['id' => 4, 'name' => 'Metal Ceramic'],
        ]);
    }

    private function clinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }
}
