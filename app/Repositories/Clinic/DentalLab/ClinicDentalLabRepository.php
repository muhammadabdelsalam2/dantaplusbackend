<?php

namespace App\Repositories\Clinic\DentalLab;

use App\Models\CaseModel;
use App\Models\ClinicLabPartnership;
use App\Models\DentalLab;
use App\Models\LabGalleryImage;
use App\Models\LabService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ClinicDentalLabRepository implements ClinicDentalLabRepositoryInterface
{
    public function paginateDentalLabs(int $clinicId, array $filters): LengthAwarePaginator
    {
        return DentalLab::query()
            ->with([
                'partnerships' => fn ($query) => $query->where('clinic_id', $clinicId),
                'labServices',
                'galleryImages',
                'cases' => fn ($query) => $query
                    ->where('clinic_id', $clinicId)
                    ->with(['patient.user:id,name', 'dentist.user:id,name']),
            ])
            ->whereHas('partnerships', fn ( $query) => $query->where('clinic_id', $clinicId))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function findDentalLab(int $clinicId, int $labId): ?DentalLab
    {
        return DentalLab::query()
            ->with([
                'partnerships' => fn ( $query) => $query->where('clinic_id', $clinicId),
                'labServices',
                'galleryImages',
                'cases' => fn ( $query) => $query
                    ->where('clinic_id', $clinicId)
                    ->with(['patient.user:id,name', 'dentist.user:id,name']),
            ])
            ->whereHas('partnerships', fn ( $query) => $query->where('clinic_id', $clinicId))
            ->find($labId);
    }

    public function findReusableDentalLab(?string $email, ?string $phone, string $name): ?DentalLab
    {
        return DentalLab::query()
            ->where(function ( $query) use ($email, $phone, $name) {
                if ($email) {
                    $query->orWhere('email', $email);
                }

                if ($phone) {
                    $query->orWhere('phone', $phone);
                }

                $query->orWhere('name', $name);
            })
            ->latest('id')
            ->first();
    }

    public function createDentalLab(array $data): DentalLab
    {
        return DentalLab::query()->create($data);
    }

    public function updateDentalLab(DentalLab $lab, array $data): DentalLab
    {
        $lab->update(array_filter($data, static fn ($value) => $value !== null));

        return $lab->refresh();
    }

    public function deleteDentalLab(DentalLab $lab): void
    {
        $lab->delete();
    }

    public function upsertPartnership(int $clinicId, int $labId, array $data): void
    {
        ClinicLabPartnership::query()->updateOrCreate(
            [
                'clinic_id' => $clinicId,
                'lab_id' => $labId,
            ],
            $data
        );
    }

    public function deletePartnership(int $clinicId, int $labId): void
    {
        ClinicLabPartnership::query()
            ->where('clinic_id', $clinicId)
            ->where('lab_id', $labId)
            ->delete();
    }

    public function createService(array $data): LabService
    {
        return LabService::query()->create($data);
    }

    public function findServiceForClinic(int $clinicId, int $serviceId): ?LabService
    {
        return LabService::query()
            ->whereHas('lab.partnerships', fn ( $query) => $query->where('clinic_id', $clinicId))
            ->find($serviceId);
    }

    public function deleteService(LabService $service): void
    {
        $service->delete();
    }

    public function serviceHasActiveOrders(int $clinicId, LabService $service): bool
    {
        return CaseModel::query()
            ->where('clinic_id', $clinicId)
            ->where('lab_id', $service->lab_id)
            ->where('case_type', $service->service_name)
            ->whereNotIn('status', [CaseModel::STATUS_DELIVERED])
            ->exists();
    }

    public function paginateOrders(int $clinicId, array $filters): LengthAwarePaginator
    {
        return CaseModel::query()
            ->with(['lab:id,name', 'patient.user:id,name'])
            ->where('clinic_id', $clinicId)
            ->when($filters['status'] ?? null, function (Builder $query, string $status) {
                if ($status === 'overdue') {
                    $query
                        ->where('status', '!=', CaseModel::STATUS_DELIVERED)
                        ->whereDate('due_date', '<', now()->toDateString());

                    return;
                }

                $statuses = match ($status) {
                    'accepted' => [CaseModel::STATUS_ACCEPTED, CaseModel::STATUS_IN_PROGRESS, CaseModel::STATUS_COMPLETED],
                    'delivered' => [CaseModel::STATUS_DELIVERED],
                    default => [CaseModel::STATUS_PENDING],
                };

                $query->whereIn('status', $statuses);
            })
            ->when($filters['dental_lab_id'] ?? null, fn (Builder $query, int $labId) => $query->where('lab_id', $labId))
            ->when($filters['patient_id'] ?? null, fn (Builder $query, int $patientId) => $query->where('patient_id', $patientId))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('due_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('due_date', '<=', $date))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function findOrder(int $clinicId, int $orderId): ?CaseModel
    {
        return CaseModel::query()
            ->with([
                'lab:id,name,contact_person,phone,address,email',
                'patient:id,user_id,patient_number,phone',
                'patient.user:id,name',
                'dentist:id,user_id',
                'dentist.user:id,name',
                'latestDeliveryTask.deliveryRep:id,name',
            ])
            ->where('clinic_id', $clinicId)
            ->find($orderId);
    }

    public function createOrder(array $data): CaseModel
    {
        return CaseModel::query()->create($data);
    }

    public function updateOrder(CaseModel $order, array $data): CaseModel
    {
        $order->update($data);

        return $order->refresh()->load(['lab:id,name', 'patient.user:id,name']);
    }

    public function createGalleryImage(array $data): LabGalleryImage
    {
        return LabGalleryImage::query()->create($data);
    }

    public function analytics(int $clinicId): array
    {
        $orders = CaseModel::query()
            ->with('lab:id,name')
            ->where('clinic_id', $clinicId)
            ->get();

        $deliveredOrders = $orders->where('status', CaseModel::STATUS_DELIVERED);
        $lateOrders = $orders
            ->filter(fn (CaseModel $order) => $order->status !== CaseModel::STATUS_DELIVERED && $order->due_date && now()->toDateString() > $order->due_date->toDateString())
            ->count();
        $onTimeDelivered = $deliveredOrders
            ->filter(fn (CaseModel $order) => $order->delivered_at && $order->due_date && $order->delivered_at->toDateString() <= $order->due_date->toDateString())
            ->count();
        $lateDelivered = $deliveredOrders
            ->filter(fn (CaseModel $order) => $order->delivered_at && $order->due_date && $order->delivered_at->toDateString() > $order->due_date->toDateString())
            ->count();

        $avgDeliveryTime = (int) round(
            $deliveredOrders
                ->filter(fn (CaseModel $order) => $order->delivered_at && $order->created_at)
                ->avg(fn (CaseModel $order) => $order->created_at->diffInDays($order->delivered_at)) ?? 0
        );

        $onTimeRate = $deliveredOrders->count() > 0
            ? round(($onTimeDelivered / $deliveredOrders->count()) * 100, 2)
            : 0;

        $ordersByDentalLab = $orders->groupBy('lab_id')->map(function (Collection $group) {
            $lab = $group->first()?->lab;

            return [
                'dental_lab_id' => $lab?->id,
                'dental_lab_name' => $lab?->name,
                'orders_count' => $group->count(),
            ];
        })->values()->all();

        return [
            'summary' => [
                'total_orders' => $orders->count(),
                'orders_completed' => $deliveredOrders->count(),
                'late_deliveries' => $lateOrders,
                'avg_delivery_time' => $avgDeliveryTime,
                'on_time_rate' => $onTimeRate,
                'on_time_count' => $onTimeDelivered,
                'late_count' => $lateDelivered,
            ],
            'orders_by_dental_lab' => $ordersByDentalLab,
            'delivery_time_trend' => $this->buildDeliveryTimeTrend($deliveredOrders),
        ];
    }

    public function recentOrders(int $clinicId, int $limit = 10): Collection
    {
        return CaseModel::query()
            ->with(['lab:id,name', 'patient.user:id,name'])
            ->where('clinic_id', $clinicId)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    private function buildDeliveryTimeTrend(Collection $deliveredOrders, int $days = 90): array
    {
        $fromDate = now()->subDays($days)->startOfDay();

        $trend = $deliveredOrders
            ->filter(fn (CaseModel $order) => $order->delivered_at && $order->created_at && $order->delivered_at->gte($fromDate))
            ->groupBy(fn (CaseModel $order) => $order->delivered_at->toDateString())
            ->map(function (Collection $group, string $date) {
                return [
                    'date' => $date,
                    'avg_delivery_days' => round($group->avg(fn (CaseModel $order) => $order->created_at->diffInDays($order->delivered_at)), 2),
                    'orders_count' => $group->count(),
                ];
            })
            ->sortBy('date')
            ->values()
            ->all();

        return $trend;
    }
}
