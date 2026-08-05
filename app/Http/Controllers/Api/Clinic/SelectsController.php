<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CaseModel;
use App\Models\DentalLab;
use App\Models\LabService;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SelectsController extends Controller
{
    public function labs(): JsonResponse
    {
        $clinicId = $this->clinicId();

        return response()->json([
            'data' => DentalLab::query()
                ->whereHas('partnerships', fn ($query) => $query->where('clinic_id', $clinicId))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (DentalLab $lab) => ['id' => $lab->id, 'name' => $lab->name])
                ->values(),
        ]);
    }

    public function labServices(Request $request, int $labId): JsonResponse
    {
        $clinicId = $this->clinicId();
        $search = $request->query('search');

        return response()->json([
            'data' => LabService::query()
                ->where('lab_id', $labId)
                ->whereHas('lab.partnerships', fn ($query) => $query->where('clinic_id', $clinicId))
                ->when($search, fn ($query, string $term) => $query->where('service_name', 'like', "%{$term}%"))
                ->orderBy('service_name')
                ->get()
                ->map(fn (LabService $service) => [
                    'id' => $service->id,
                    'name' => $service->service_name,
                    'price' => (float) $service->price,
                    'turnaround' => $service->turnaround_time_days . ' days',
                ])
                ->values(),
        ]);
    }

    public function caseTypes(): JsonResponse
    {
        $names = LabService::query()
            ->select('service_name')
            ->distinct()
            ->orderBy('service_name')
            ->pluck('service_name')
            ->merge(CaseModel::query()->select('case_type')->distinct()->pluck('case_type'))
            ->filter()
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            $names = collect(['Crown', 'Bridge', 'Veneer', 'Denture']);
        }

        return response()->json([
            'data' => $names->map(fn (string $name, int $index) => [
                'id' => $index + 1,
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

    public function patients(): JsonResponse
    {
        $clinicId = $this->clinicId();

        return response()->json([
            'data' => Patient::query()
                ->with('user:id,name')
                ->where('clinic_id', $clinicId)
                ->orderBy('id')
                ->get()
                ->map(fn (Patient $patient) => [
                    'id' => $patient->id,
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

    private function usersByRole(string $role): JsonResponse
    {
        $clinicId = $this->clinicId();

        return response()->json([
            'data' => User::query()
                ->where('clinic_id', $clinicId)
                ->role($role)
                ->orderBy('name')
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
