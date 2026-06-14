<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\StoreEquipmentReportRequest;
use App\Http\Resources\Clinic\EquipmentResource;
use App\Models\Equipment;
use App\Models\OwnerMaintenanceRequest;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class EquipmentController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $clinicId = auth()->user()?->clinic_id;
        if (! $clinicId) {
            return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
        }

        $validated = $request->validate([
            'search'   => ['nullable', 'string', 'max:255'],
            'status'   => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = max(1, min((int) ($validated['per_page'] ?? 15), 100));

        $query = Equipment::query()
            ->withoutGlobalScopes()
            ->where('clinic_id', $clinicId)
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                           ->orWhere('serial_number', 'like', "%{$search}%")
                           ->orWhere('model', 'like', "%{$search}%");
                });
            })
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id');

        $items = $query->paginate($perPage);

        return ApiResponse::success([
            'items' => collect($items->items())->map(fn ($e) => $this->formatEquipment($e))->values(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
                'per_page'     => $items->perPage(),
                'total'        => $items->total(),
            ],
        ], 'Equipment fetched successfully');
    }

    public function report(StoreEquipmentReportRequest $request, int $equipment)
    {
        $clinicId = auth()->user()?->clinic_id;
        if (! $clinicId) {
            return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
        }

        $equipmentRecord = Equipment::query()
            ->where('clinic_id', $clinicId)
            ->find($equipment);

        if (! $equipmentRecord) {
            return ApiResponse::error('Equipment not found.', 404);
        }

        $validated = $request->validated();
          if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
            $path = $request->file('attachment')->store('equipment-reports', 'public');
            $attachmentUrl = asset('storage/' . $path);
        } else {
            $attachmentUrl = null;
        }
        $validated['attachment_url'] = $attachmentUrl;


        $maintenanceRequest = DB::transaction(function () use ($equipmentRecord, $validated, $clinicId) {
            $requestModel = OwnerMaintenanceRequest::create([
                'request_code' => 'MR-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5)),
                'clinic_id' => $clinicId,
                'equipment_id' => $equipmentRecord->id,
                'equipment' => $equipmentRecord->name,
                'malfunction_type' => $validated['malfunction_type'],
                'issue_description' => $validated['description'],
                'urgency' => $validated['urgency'],
                'attachment_url' => $validated['attachment_url'],
                'status' => OwnerMaintenanceRequest::STATUS_PENDING,
                'created_by' => auth()->id(),
            ]);

            if ($validated['urgency'] === 'critical') {
                $equipmentRecord->update([
                    'status' => Equipment::STATUS_BROKEN,
                ]);
            }

            return $requestModel;
        });

        return ApiResponse::success([
            'request_id' => $maintenanceRequest->id,
            'request_code' => $maintenanceRequest->request_code,
            'equipment_status' => $equipmentRecord->fresh()->status,
        ], 'Maintenance request created successfully', 201);
    }
    public function store(Request $request)
{
    $clinicId = auth()->user()?->clinic_id;
    if (! $clinicId) {
        return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
    }

$validated = $request->validate([
    'name'   => 'required|string|max:255',
    'status' => 'nullable|string|in:operational,broken,under_maintenance',
    'image'  => 'nullable|image|max:5120',
]);

$imageUrl = null;
if ($request->hasFile('image')) {
    $file = $request->file('image');
    $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
    $file->move(public_path('uploads/equipment'), $filename);
    $imageUrl = 'uploads/equipment/' . $filename;
}

$equipment = Equipment::create([
    'name'      => $validated['name'],
    'clinic_id' => $clinicId,
    'status'    => $validated['status'] ?? Equipment::STATUS_OPERATIONAL,
    'image_url' => $imageUrl,
]);

    return ApiResponse::success(
        (new EquipmentResource($equipment->loadCount('maintenanceRequests')))->resolve(),
        'Equipment created successfully',
        201
    );
}
}
