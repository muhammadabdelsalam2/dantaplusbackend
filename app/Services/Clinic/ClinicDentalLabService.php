<?php

namespace App\Services\Clinic;

use App\Http\Resources\Clinic\ClinicDentalLabAnalyticsResource;
use App\Http\Resources\Clinic\ClinicDentalLabGalleryResource;
use App\Http\Resources\Clinic\ClinicDentalLabOrderDetailResource;
use App\Http\Resources\Clinic\ClinicDentalLabOrderResource;
use App\Http\Resources\Clinic\ClinicDentalLabResource;
use App\Http\Resources\Clinic\ClinicDentalLabServiceResource;
use App\Models\CaseModel;
use App\Models\ClinicLabPartnership;
use App\Models\DentalLab;
use App\Models\Doctor;
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
                : $this->repository->createDentalLab(array_merge($payload, ['is_external' => true]));

            $this->repository->upsertPartnership($clinicId, $lab->id, [
                'status' => ClinicLabPartnership::STATUS_ACTIVE,
                'partnership_start_date' => now()->toDateString(),
                'invited_by' => auth()->id(),
            ]);

            return $lab;
        });

        return ServiceResult::success(
            (new ClinicDentalLabResource($this->repository->findDentalLab($clinicId, $lab->id)))->resolve(),
            'Dental lab created successfully',
            201
        );
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
        $lab = $this->resolveDentalLab($labId);
        if (! $lab) {
            return ServiceResult::error('Dental lab not found.', null, null, 404);
        }

        $updatedLab = $this->repository->updateDentalLab($lab, $this->labPayload($data));

        return ServiceResult::success(
            (new ClinicDentalLabResource($this->repository->findDentalLab($this->currentClinicId(), $updatedLab->id)))->resolve(),
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

        $doctorId = $this->resolveDoctorId($clinicId);
        if (! $doctorId) {
            return ServiceResult::error('No doctor profile is linked to this clinic yet.', null, null, 422);
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

    public function uploadGallery(int $labId, array $data): array
    {
        $lab = $this->resolveDentalLab($labId);
        if (! $lab) {
            return ServiceResult::error('Dental lab not found.', null, null, 404);
        }

        $uploaded = [];

        foreach ($data['images'] as $image) {
            if (! $image instanceof UploadedFile) {
                continue;
            }

            $path = Storage::disk('public')->putFile('clinic/dental-labs/'.$lab->id.'/'.$data['type'], $image);

            $uploaded[] = $this->repository->createGalleryImage([
                'lab_id' => $lab->id,
                'type' => $data['type'],
                'url' => $path,
                'disk' => 'public',
                'uploaded_by' => auth()->id(),
                'created_at' => now(),
            ]);
        }

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

    private function resolveDentalLab(int $labId): ?DentalLab
    {
        $clinicId = $this->currentClinicId();

        return $clinicId ? $this->repository->findDentalLab($clinicId, $labId) : null;
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

    private function generateCaseNumber(): string
    {
        do {
            $number = 'LO-' . Str::upper(Str::random(6));
        } while (CaseModel::query()->where('case_number', $number)->exists());

        return $number;
    }
}
