<?php

namespace App\Repositories\Clinic\Select;

use Illuminate\Support\Collection;

interface ClinicSelectRepositoryInterface
{
    public function dentalLabs(int $clinicId, array $filters = []): Collection;

    public function doctors(int $clinicId, array $filters = []): Collection;

    public function patients(int $clinicId, array $filters = []): Collection;

    public function staff(int $clinicId, array $filters = []): Collection;

    public function dentists(int $clinicId, array $filters = []): Collection;

    public function expenseCategories(int $clinicId, array $filters = []): Collection;

    public function insuranceCompanies(int $clinicId, array $filters = []): Collection;

    public function responseSpeeds(int $clinicId, array $filters = []): Collection;
}
