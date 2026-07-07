<?php

namespace App\Repositories\Clinic\Select;

use Illuminate\Support\Collection;

interface ClinicSelectRepositoryInterface
{
    public function dentalLabs(int $clinicId): Collection;

    public function doctors(int $clinicId): Collection;

    public function patients(int $clinicId): Collection;

    public function staff(int $clinicId): Collection;

    public function dentists(int $clinicId): Collection;

    public function expenseCategories(int $clinicId): Collection;

    public function insuranceCompanies(int $clinicId): Collection;

    public function responseSpeeds(int $clinicId): Collection;
}
