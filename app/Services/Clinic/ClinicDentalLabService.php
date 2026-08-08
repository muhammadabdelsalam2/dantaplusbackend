<?php

namespace App\Services\Clinic;

use App\Http\Resources\Clinic\ClinicDentalLabAnalyticsResource;
use App\Http\Resources\Clinic\ClinicDentalLabGalleryResource;
use App\Http\Resources\Clinic\ClinicDentalLabOrderDetailResource;
use App\Http\Resources\Clinic\ClinicDentalLabOrderResource;
use App\Http\Resources\Clinic\ClinicDentalLabResource;
use App\Http\Resources\Clinic\ClinicDentalLabServiceResource;
use App\Models\CaseAttachment;
use App\Models\CaseModel;
use App\Models\ClinicLabPartnership;
use App\Models\DentalLab;
use App\Models\DentalLabReview;
use App\Models\Doctor;
use App\Models\LabService;
use App\Models\Patient;
use App\Repositories\Clinic\DentalLab\ClinicDentalLabRepositoryInterface;
use App\Support\ServiceResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClinicDentalLabService
{
    public function __construct(private ClinicDentalLabRepositoryInterface $repository)
    {
    }

 public function index(array $filters): array
{
    $clinicId = $this->currentClinicId();
    if (! $clinicId) {
        return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
    }

    $dentalLabs = $this->repository->paginateDentalLabs($clinicId, $filters);


    $dentalLabs->getCollection()->transform(function (DentalLab $lab) use ($clinicId) {
        $lab->is_own = (int) $lab->created_by_clinic_id === (int) $clinicId;

        return $lab;
    });

    return ServiceResult::success([
        'items' => ClinicDentalLabResource::collection($dentalLabs->items())->resolve(),
        'pagination' => [
            'current_page' => $dentalLabs->currentPage(),
            'last_page' => $dentalLabs->lastPage(),
            'per_page' => $dentalLabs->perPage(),
            'total' => $dentalLabs->total(),
        ],
    ], 'Dental labs fetched successfully');
}
public function store(array $data): array
{
    $clinicId = $this->currentClinicId();
    if (! $clinicId) {
        return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
    }

    $payload = $this->labPayload($data);

    $lab = DB::transaction(function () use ($clinicId, $payload) {
        $existingLab = $this->repository->findReusableDentalLab(
            $payload['email'] ?? null,
            $payload['phone'] ?? null,
            $payload['name']
        );

        $lab = $existingLab
            ? $this->repository->updateDentalLab($existingLab, array_filter($payload, static fn ($value) => $value !== null))
            : $this->repository->createDentalLab(array_merge($payload, [
                'is_external' => true,
                'created_by_clinic_id' => $clinicId,
            ]));

        $this->repository->upsertPartnership($clinicId, $lab->id, [
            'status' => ClinicLabPartnership::STATUS_ACTIVE,
            'partnership_start_date' => now()->toDateString(),
            'invited_by' => auth()->id(),
        ]);

        return $lab;
    });

    $this->storeGalleryImagesForLab($lab, $data['before_images'] ?? null, 'before');
    $this->storeGalleryImagesForLab($lab, $data['after_images'] ?? null, 'after');
    $this->storeServicesForLab($lab, $data['services'] ?? null);   // ← جديد

    return ServiceResult::success(
        (new ClinicDentalLabResource($this->repository->findDentalLab($clinicId, $lab->id)))->resolve(),
        'Dental lab created successfully',
        201
    );
}

private function storeServicesForLab(DentalLab $lab, ?array $services): void
{
    foreach ($services ?? [] as $service) {
        if (empty($service['name']) || ! isset($service['price'])) {
            continue;
        }

        
        if (! empty($service['id'])) {
            $existingService = $this->repository->findServiceForClinic($this->currentClinicId(), (int) $service['id']);
            if ($existingService && (int) $existingService->lab_id === (int) $lab->id) {
                $existingService->update([
                    'service_name' => $service['name'],
                    'price' => $service['price'],
                    'turnaround_time_days' => $service['turnaround_days'] ?? $existingService->turnaround_time_days,
                ]);
                continue;
            }
        }

        $this->repository->createService([
            'lab_id' => $lab->id,
            'service_name' => $service['name'],
            'price' => $service['price'],
            'turnaround_time_days' => $service['turnaround_days'] ?? null,
        ]);
    }
}


private function storeGalleryImagesForLab(DentalLab $lab, ?array $images, string $type): array
{
    $uploaded = [];

    foreach ($images ?? [] as $image) {
        if (! $image instanceof UploadedFile) {
            continue;
        }

        $path = Storage::disk('public')->putFile('clinic/dental-labs/'.$lab->id.'/'.$type, $image);

        $uploaded[] = $this->repository->createGalleryImage([
            'lab_id' => $lab->id,
            'type' => $type,
            'url' => $path,
            'disk' => 'public',
            'uploaded_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    return $uploaded;
}

    public function show(int $labId): array
    {
        $lab = $this->resolveDentalLab($labId);
        if (! $lab) {
            return ServiceResult::error('Dental lab not found.', null, null, 404);
        }

        return ServiceResult::success((new ClinicDentalLabResource($lab))->resolve(), 'Dental lab fetched successfully');
    }

public function update(int $labId, array $data): array
{
    $clinicId = $this->currentClinicId();
    if (! $clinicId) {
        return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
    }

    $lab = $this->resolveDentalLab($labId);
    if (! $lab) {
        return ServiceResult::error('Dental lab not found.', null, null, 404);
    }

    // بس الكلينيك اللي عملت add للاب هي اللي تقدر تعدّل
    if ((int) $lab->created_by_clinic_id !== (int) $clinicId) {
        return ServiceResult::error('You are not allowed to edit this dental lab.', null, null, 403);
    }

    $updatedLab = $this->repository->updateDentalLab($lab, $this->labPayload($data));

    $this->storeGalleryImagesForLab($updatedLab, $data['before_images'] ?? null, 'before');
    $this->storeGalleryImagesForLab($updatedLab, $data['after_images'] ?? null, 'after');
    $this->storeServicesForLab($updatedLab, $data['services'] ?? null);

    return ServiceResult::success(
        (new ClinicDentalLabResource($this->repository->findDentalLab($clinicId, $updatedLab->id)))->resolve(),
        'Dental lab updated successfully'
    );
}

    public function destroy(int $labId): array
{
    $clinicId = $this->currentClinicId();
    $lab = $this->resolveDentalLab($labId);
    if (! $lab || ! $clinicId) {
        return ServiceResult::error('Dental lab not found.', null, null, 404);
    }

    if ((int) $lab->created_by_clinic_id !== (int) $clinicId) {
        return ServiceResult::error('You are not allowed to delete this dental lab.', null, null, 403);
    }

    DB::transaction(function () use ($clinicId, $lab) {
        $this->repository->deletePartnership($clinicId, $lab->id);

        if (
            $lab->is_external
            && ! $lab->users()->exists()
            && ! $lab->partnerships()->exists()
            && ! $lab->cases()->exists()
        ) {
            foreach ($lab->galleryImages as $image) {
                if ($image->url && Storage::disk($image->disk ?? 'public')->exists($image->url)) {
                    Storage::disk($image->disk ?? 'public')->delete($image->url);
                }
            }

            $this->repository->deleteDentalLab($lab);
        }
    });

    return ServiceResult::success(null, 'Dental lab detached successfully');
}

    public function storeService(int $labId, array $data): array
    {
        $lab = $this->resolveDentalLab($labId);
        if (! $lab) {
            return ServiceResult::error('Dental lab not found.', null, null, 404);
        }

        $service = $this->repository->createService([
            'lab_id' => $lab->id,
            'service_name' => $data['service_name'],
            'price' => $data['price'],
            'turnaround_time_days' => $data['turnaround_time_days'],
        ]);

        return ServiceResult::success((new ClinicDentalLabServiceResource($service))->resolve(), 'Dental lab service created successfully', 201);
    }

    public function deleteService(int $serviceId): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $service = $this->repository->findServiceForClinic($clinicId, $serviceId);
        if (! $service) {
            return ServiceResult::error('Dental lab service not found.', null, null, 404);
        }

        if ($this->repository->serviceHasActiveOrders($clinicId, $service)) {
            return ServiceResult::error('Cannot delete a service linked to active orders.', null, null, 422);
        }

        $this->repository->deleteService($service);

        return ServiceResult::success(null, 'Dental lab service deleted successfully');
    }

    public function indexOrders(array $filters): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $orders = $this->repository->paginateOrders($clinicId, $filters);

        return ServiceResult::success([
            'items' => ClinicDentalLabOrderResource::collection($orders->items())->resolve(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ], 'Dental lab orders fetched successfully');
    }

    public function storeOrder(array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $lab = $this->repository->findDentalLab($clinicId, (int) $data['dental_lab_id']);
        if (! $lab) {
            return ServiceResult::error('Dental lab not found.', null, ['dental_lab_id' => ['Dental lab not found.']], 422);
        }

        $patient = Patient::query()->where('clinic_id', $clinicId)->find($data['patient_id']);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, ['patient_id' => ['Patient not found.']], 422);
        }

        $service = null;
        if (! empty($data['lab_service_id'])) {
            $service = $this->repository->findServiceForClinic($clinicId, (int) $data['lab_service_id']);
            if (! $service || (int) $service->lab_id !== (int) $lab->id) {
                return ServiceResult::error('Dental lab service not found.', null, ['lab_service_id' => ['Dental lab service not found.']], 422);
            }
        }

        $doctorId = $this->resolveDentistDoctorId($clinicId, $data['dentist_id'] ?? null);
        if (! $doctorId) {
            return ServiceResult::error('Dentist not found for this clinic.', null, ['dentist_id' => ['The selected dentist id is invalid.']], 422);
        }

        $order = $this->repository->createOrder([
            'case_number' => $this->generateCaseNumber(),
            'clinic_id' => $clinicId,
            'lab_id' => $lab->id,
            'patient_id' => $patient->id,
            'dentist_id' => $doctorId,
            'status' => $this->mapLegacyStatusToCaseStatus($data['status'] ?? 'pending'),
            'priority' => CaseModel::PRIORITY_NORMAL,
            'due_date' => $data['due_date'],
            'case_type' => $service?->service_name ?? ($data['case_type'] ?? 'General Lab Case'),
            'lab_service_id' => $service?->id,
            'description' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
            'delivered_at' => ($data['status'] ?? null) === 'delivered' ? now() : null,
        ]);

        $this->refreshPartnershipMetrics($clinicId, $lab->id);

        return ServiceResult::success(
            (new ClinicDentalLabOrderResource($this->repository->findOrder($clinicId, $order->id)))->resolve(),
            'Dental lab order created successfully',
            201
        );
    }

    public function storeOrderForLab(int $labId, array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $lab = $this->repository->findDentalLab($clinicId, $labId);
        if (! $lab) {
            return ServiceResult::error('Dental lab not found.', null, null, 404);
        }

        $patient = Patient::query()->where('clinic_id', $clinicId)->find($data['patient_id']);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, ['patient_id' => ['Patient not found.']], 422);
        }

        $doctorId = $this->resolveDentistDoctorId($clinicId, $data['dentist_id'] ?? null);
        if (! $doctorId) {
            return ServiceResult::error('Dentist not found for this clinic.', null, ['dentist_id' => ['The selected dentist id is invalid.']], 422);
        }

        // Resolve a real LabService record when possible, so the case can be linked
        // via lab_service_id rather than only storing a free-text description.
        $service = null;
        if (! empty($data['service_id'])) {
            $service = $this->repository->findServiceForClinic($clinicId, (int) $data['service_id']);
            if (! $service || (int) $service->lab_id !== (int) $lab->id) {
                $service = null;
            }
        }

        $order = DB::transaction(function () use ($clinicId, $lab, $patient, $doctorId, $data, $service) {
            $description = collect([
                'Service: ' . ($data['service'] ?? ('Case Type #' . $data['case_type_id'])),
                'Material: ' . ($data['material'] ?? ('Material #' . $data['material_id'])),
                'Shade: ' . ($data['shade'] ?? ('Shade #' . $data['shade_id'])),
                filled($data['description'] ?? null) ? 'Description: ' . $data['description'] : null,
                filled($data['notes'] ?? null) ? 'Notes: ' . $data['notes'] : null,
            ])->filter()->implode("\n");

            $order = $this->repository->createOrder([
                'case_number' => $this->generateCaseNumber(),
                'clinic_id' => $clinicId,
                'lab_id' => $lab->id,
                'patient_id' => $patient->id,
                'dentist_id' => $doctorId,
                'status' => CaseModel::STATUS_PENDING,
                'priority' => CaseModel::PRIORITY_NORMAL,
                'due_date' => $data['delivery_date'],
                'case_type' => $service?->service_name ?? ($data['service'] ?? ('Case Type #' . $data['case_type_id'])),
                'lab_service_id' => $service?->id,
                'tooth_numbers' => $data['tooth_numbers'] ?? null,
                'description' => $description,
                'created_by' => auth()->id(),
            ]);

            $files = collect($data['files'] ?? [])
                ->when(! empty($data['file_upload']), fn ($collection) => $collection->push($data['file_upload']))
                ->filter(fn ($file) => $file instanceof UploadedFile);

            foreach ($files as $file) {
                $path = Storage::disk('public')->putFile('clinic/lab-orders/'.$order->id, $file);
                CaseAttachment::query()->create([
                    'case_id' => $order->id,
                    'uploaded_by' => auth()->id(),
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'attachment_type' => 'lab_order_file',
                ]);
            }

            return $order;
        });

        $this->refreshPartnershipMetrics($clinicId, $lab->id);

        return ServiceResult::success([
            'order' => (new ClinicDentalLabOrderResource($this->repository->findOrder($clinicId, $order->id)))->resolve(),
            'prototype_fields' => [
                'Lab' => $lab->name,
                'Service' => $data['service'] ?? ('Case Type #' . $data['case_type_id']),
                'Material' => $data['material'] ?? ('Material #' . $data['material_id']),
                'Shade' => $data['shade'] ?? ('Shade #' . $data['shade_id']),
                'Delivery Date' => $data['delivery_date'],
                'File Upload' => ! empty($data['file_upload']) || ! empty($data['files']),
                'Notes' => $data['notes'] ?? null,
            ],
        ], 'Dental lab order sent successfully', 201);
    }

    public function rate(int $labId, array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $lab = $this->repository->findDentalLab($clinicId, $labId);
        if (! $lab) {
            return ServiceResult::error('Dental lab not found.', null, null, 404);
        }

        $review = DentalLabReview::query()->create([
            'lab_id' => $lab->id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name,
            'rating' => (int) $data['rating'],
            'comment' => $data['comment'] ?? null,
            'reviewed_at' => now()->toDateString(),
        ]);

        $lab->update(['rating' => round((float) $lab->reviews()->avg('rating'), 1)]);

        return ServiceResult::success([
            'id' => $review->id,
            'lab_id' => $lab->id,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'average_rating' => (float) $lab->fresh()->rating,
            'reviewed_at' => optional($review->reviewed_at)->toDateString(),
        ], 'Dental lab rating submitted successfully', 201);
    }

    public function updateOrderStatus(int $orderId, array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $order = $this->repository->findOrder($clinicId, $orderId);
        if (! $order) {
            return ServiceResult::error('Dental lab order not found.', null, null, 404);
        }

        $legacyStatus = $data['status'];
        $order = $this->repository->updateOrder($order, [
            'status' => $this->mapLegacyStatusToCaseStatus($legacyStatus),
            'delivered_at' => $legacyStatus === 'delivered'
                ? ($data['delivered_at'] ?? now())
                : (in_array($legacyStatus, ['pending', 'accepted'], true) ? null : $order->delivered_at),
        ]);

        $this->refreshPartnershipMetrics($clinicId, $order->lab_id);

        return ServiceResult::success((new ClinicDentalLabOrderResource($order))->resolve(), 'Dental lab order status updated successfully');
    }

    // public function uploadGallery(int $labId, array $data): array
    // {
    //     $lab = $this->resolveDentalLab($labId);
    //     if (! $lab) {
    //         return ServiceResult::error('Dental lab not found.', null, null, 404);
    //     }

    //     $uploaded = [];

    //     foreach ($data['images'] as $image) {
    //         if (! $image instanceof UploadedFile) {
    //             continue;
    //         }

    //         $path = Storage::disk('public')->putFile('clinic/dental-labs/'.$lab->id.'/'.$data['type'], $image);

    //         $uploaded[] = $this->repository->createGalleryImage([
    //             'lab_id' => $lab->id,
    //             'type' => $data['type'],
    //             'url' => $path,
    //             'disk' => 'public',
    //             'uploaded_by' => auth()->id(),
    //             'created_at' => now(),
    //         ]);
    //     }

    //     return ServiceResult::success(
    //         ClinicDentalLabGalleryResource::collection($uploaded)->resolve(),
    //         'Dental lab gallery uploaded successfully',
    //         201
    //     );
    // }
    public function uploadGallery(int $labId, array $data): array
{
    $lab = $this->resolveDentalLab($labId);
    if (! $lab) {
        return ServiceResult::error('Dental lab not found.', null, null, 404);
    }

    $uploaded = $this->storeGalleryImagesForLab($lab, $data['images'], $data['type']);

    return ServiceResult::success(
        ClinicDentalLabGalleryResource::collection($uploaded)->resolve(),
        'Dental lab gallery uploaded successfully',
        201
    );
}


    public function analytics(): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $analytics = $this->repository->analytics($clinicId);
        $analytics['recent_orders'] = $this->repository->recentOrders($clinicId, 10);

        return ServiceResult::success(
            (new ClinicDentalLabAnalyticsResource($analytics))->resolve(),
            'Dental lab analytics fetched successfully'
        );
    }

    public function showOrder(int $orderId): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $order = $this->repository->findOrder($clinicId, $orderId);
        if (! $order) {
            return ServiceResult::error('Dental lab order not found.', null, null, 404);
        }

        return ServiceResult::success(
            (new ClinicDentalLabOrderDetailResource($order))->resolve(),
            'Dental lab order fetched successfully'
        );
    }

    public function prototypeIndex(): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $labs = DentalLab::query()
            ->with(['latestReview'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->whereHas('partnerships', fn ($query) => $query->where('clinic_id', $clinicId))
            ->orderBy('name')
            ->get()
            ->map(fn (DentalLab $lab) => $this->prototypeLabPayload($lab))
            ->values();

        return ServiceResult::success($labs, 'Dental labs fetched successfully');
    }

    public function prototypeShow(int $labId): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $lab = DentalLab::query()
            ->with(['reviews' => fn ($query) => $query->latest('id'), 'latestReview'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->whereHas('partnerships', fn ($query) => $query->where('clinic_id', $clinicId))
            ->find($labId);

        if (! $lab) {
            return ServiceResult::error('Dental lab not found.', null, null, 404);
        }

        return ServiceResult::success([
            'lab' => $this->prototypeLabPayload($lab),
            'reviews' => $lab->reviews->map(fn (DentalLabReview $review) => [
                'doctor_name' => $review->user_name,
                'rating' => (int) $review->rating,
                'comment' => $review->comment,
                'created_at' => optional($review->created_at)->toISOString(),
            ])->values(),
        ], 'Dental lab fetched successfully');
    }

    public function prototypeStoreOrder(array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $lab = DentalLab::query()
            ->whereHas('partnerships', fn ($query) => $query->where('clinic_id', $clinicId))
            ->find($data['lab_id']);
        if (! $lab) {
            return ServiceResult::error('Dental lab not found.', null, ['lab_id' => ['Dental lab not found for this clinic.']], 422);
        }

        $patient = Patient::query()->where('clinic_id', $clinicId)->find($data['patient_id']);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, ['patient_id' => ['Patient not found for this clinic.']], 422);
        }

        $dentistId = $this->resolveDentistDoctorId($clinicId, (int) $data['dentist_id']);
        if (! $dentistId) {
            return ServiceResult::error('Dentist not found.', null, ['dentist_id' => ['The selected dentist id is invalid.']], 422);
        }
        $dentist = Doctor::query()->find($dentistId);

        $caseType = $this->caseTypeName((int) $data['case_type_id']);
        $materialId = (int) ($data['material_id'] ?? 0);
        $shadeId = (int) ($data['shade_id'] ?? 0);
        $material = $materialId > 0 ? $this->materialName($materialId) : null;
        $shade = $shadeId > 0 ? $this->shadeName($shadeId) : null;

       $order = DB::transaction(function () use (
    $clinicId,
    $lab,
    $patient,
    $dentist,
    $data,
    $caseType,
    $materialId,
    $shadeId,
    $material,
    $shade
) {
            $order = $this->repository->createOrder([
                'case_number' => $this->generateCaseNumber(),
                'clinic_id' => $clinicId,
                'lab_id' => $lab->id,
                'patient_id' => $patient->id,
                'dentist_id' => $dentist->id,
                'status' => CaseModel::STATUS_PENDING,
                'priority' => CaseModel::PRIORITY_NORMAL,
                'due_date' => $data['delivery_date'],
                'case_type' => $caseType,
                'tooth_numbers' => $data['tooth_numbers'],
                'tooth_chart_3d' => [
                    'material_id' => $materialId ?: null,
                    'material' => $material,
                    'shade_id' => $shadeId ?: null,
                    'shade' => $shade,
                ],
                'description' => trim(collect([
                    $data['description'] ?? null,
                    $material ? 'Material: ' . $material : null,
                    $shade ? 'Shade: ' . $shade : null,
                ])->filter()->implode("\n")),
                'created_by' => auth()->id(),
            ]);

            foreach (($data['files'] ?? []) as $file) {
                $path = Storage::disk('public')->putFile('clinic/lab-orders/' . $order->id, $file);
                CaseAttachment::query()->create([
                    'case_id' => $order->id,
                    'uploaded_by' => auth()->id(),
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'attachment_type' => 'lab_order_file',
                ]);
            }

            return $order;
        });

        $this->refreshPartnershipMetrics($clinicId, $lab->id);

        return ServiceResult::success([
            'order_id' => $order->id,
            'status' => 'created',
        ], 'Dental lab order created successfully', 201);
    }

    private function resolveDentalLab(int $labId): ?DentalLab
    {
        $clinicId = $this->currentClinicId();

        return $clinicId ? $this->repository->findDentalLab($clinicId, $labId) : null;
    }

    private function prototypeLabPayload(DentalLab $lab): array
    {
        $latestReview = $lab->relationLoaded('latestReview') ? $lab->latestReview : null;

        return [
            'id' => $lab->id,
            'name' => $lab->name,
            'location' => $lab->city ?? $lab->address,
            'rating' => round((float) ($lab->reviews_avg_rating ?? $lab->rating ?? 0), 1),
            'reviews_count' => (int) ($lab->reviews_count ?? 0),
            'latest_review' => $latestReview ? [
                'rating' => (int) $latestReview->rating,
                'comment' => $latestReview->comment,
                'reviewed_by' => $latestReview->user_name,
                'reviewed_at' => optional($latestReview->reviewed_at)->toDateString(),
            ] : null,
            'avg_delivery_days' => (float) ($lab->avg_delivery_days ?? 0),
            'on_time_percentage' => (float) ($lab->on_time_percentage ?? 0),
            'rejection_rate' => (float) ($lab->rejection_rate ?? 0),
        ];
    }

    private function refreshPartnershipMetrics(int $clinicId, int $labId): void
    {
        $aggregate = CaseModel::query()
            ->where('clinic_id', $clinicId)
            ->where('lab_id', $labId)
            ->selectRaw('COUNT(*) as total_cases_sent, MAX(created_at) as last_case_date')
            ->first();

        $this->repository->upsertPartnership($clinicId, $labId, [
            'total_cases_sent' => (int) ($aggregate?->total_cases_sent ?? 0),
            'last_case_date' => $aggregate?->last_case_date
                ? Carbon::parse($aggregate->last_case_date)->toDateString()
                : null,
        ]);
    }

    private function labPayload(array $data): array
    {
        return [
            'name' => $data['name'] ?? null,
            'contact_person' => $data['contact_person'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'avg_delivery_days' => $data['avg_delivery_days'] ?? null,
            'response_speed' => $data['response_speed'] ?? null,
            'working_hours' => $data['working_hours'] ?? null,
            'status' => Arr::get($data, 'status', DentalLab::STATUS_ACTIVE),
        ];
    }

    private function currentClinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }

    private function mapLegacyStatusToCaseStatus(string $status): string
    {
        return match ($status) {
            'accepted' => CaseModel::STATUS_ACCEPTED,
            'delivered' => CaseModel::STATUS_DELIVERED,
            default => CaseModel::STATUS_PENDING,
        };
    }

    private function resolveDoctorId(int $clinicId): ?int
    {
        return Doctor::query()
            ->whereHas('user', fn ($query) => $query->where('clinic_id', $clinicId))
            ->value('id');
    }

    private function resolveDentistDoctorId(int $clinicId, ?int $dentistId = null): ?int
    {
        if (! $dentistId) {
            return $this->resolveDoctorId($clinicId);
        }

        $byDoctorId = Doctor::query()
            ->whereKey($dentistId)
            ->whereHas('user', fn ($query) => $query->where('clinic_id', $clinicId))
            ->value('id');

        if ($byDoctorId) {
            return (int) $byDoctorId;
        }

        return Doctor::query()
            ->where('user_id', $dentistId)
            ->whereHas('user', function ($query) use ($clinicId) {
                $query
                    ->where('clinic_id', $clinicId)
                    ->where(function ($roleQuery) {
                        $roleQuery
                            ->whereIn('role', ['dentist', 'doctor'])
                            ->orWhereHas('roles', fn ($role) => $role->whereIn('name', ['dentist', 'doctor']));
                    });
            })
            ->value('id');
    }

    private function caseTypeName(int $id): string
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

        return $names->get($id - 1) ?? [
            1 => 'Crown',
            2 => 'Bridge',
            3 => 'Veneer',
            4 => 'Denture',
        ][$id] ?? 'Case Type #' . $id;
    }

    private function materialName(int $id): string
    {
        return [
            1 => 'Zirconia',
            2 => 'Porcelain',
            3 => 'E-max',
            4 => 'Metal Ceramic',
        ][$id] ?? 'Material #' . $id;
    }

    private function shadeName(int $id): string
    {
        return [
            1 => 'Shade A1',
            2 => 'Shade A2',
            3 => 'Shade B1',
            4 => 'Shade C1',
        ][$id] ?? 'Shade #' . $id;
    }

    private function generateCaseNumber(): string
    {
        do {
            $number = 'LO-' . Str::upper(Str::random(6));
        } while (CaseModel::query()->where('case_number', $number)->exists());

        return $number;
    }
}
