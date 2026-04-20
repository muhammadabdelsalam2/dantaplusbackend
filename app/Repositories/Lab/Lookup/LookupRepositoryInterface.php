<?php

namespace App\Repositories\Lab\Lookup;

use Illuminate\Support\Collection;

interface LookupRepositoryInterface
{
    public function getPatientsByLab(int $labId, ?string $search = null): Collection;

    public function getDentistsByLab(int $labId, ?string $search = null): Collection;

    public function getTechniciansByLab(int $labId, ?string $search = null): Collection;
}
