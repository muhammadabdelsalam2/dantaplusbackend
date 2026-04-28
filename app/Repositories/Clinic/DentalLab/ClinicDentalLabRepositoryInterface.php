<?php

namespace App\Repositories\Clinic\DentalLab;

use App\Models\CaseModel;
use App\Models\DentalLab;
use App\Models\LabGalleryImage;
use App\Models\LabService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ClinicDentalLabRepositoryInterface
{
    public function paginateDentalLabs(int $clinicId, array $filters): LengthAwarePaginator;

    public function findDentalLab(int $clinicId, int $labId): ?DentalLab;

    public function findReusableDentalLab(?string $email, ?string $phone, string $name): ?DentalLab;

    public function createDentalLab(array $data): DentalLab;

    public function updateDentalLab(DentalLab $lab, array $data): DentalLab;

    public function deleteDentalLab(DentalLab $lab): void;

    public function upsertPartnership(int $clinicId, int $labId, array $data): void;

    public function deletePartnership(int $clinicId, int $labId): void;

    public function createService(array $data): LabService;

    public function findServiceForClinic(int $clinicId, int $serviceId): ?LabService;

    public function deleteService(LabService $service): void;

    public function serviceHasActiveOrders(int $clinicId, LabService $service): bool;

    public function paginateOrders(int $clinicId, array $filters): LengthAwarePaginator;

    public function findOrder(int $clinicId, int $orderId): ?CaseModel;

    public function createOrder(array $data): CaseModel;

    public function updateOrder(CaseModel $order, array $data): CaseModel;

    public function createGalleryImage(array $data): LabGalleryImage;

    public function analytics(int $clinicId): array;

    public function recentOrders(int $clinicId, int $limit = 10): Collection;
}
