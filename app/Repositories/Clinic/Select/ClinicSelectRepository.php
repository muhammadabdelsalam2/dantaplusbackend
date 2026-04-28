<?php

namespace App\Repositories\Clinic\Select;

use App\Models\ClinicExpenseCategory;
use App\Models\ClinicLabPartnership;
use App\Models\Patient;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ClinicSelectRepository implements ClinicSelectRepositoryInterface
{
    public function dentalLabs(int $clinicId): Collection
    {
        return ClinicLabPartnership::query()
            ->with('lab:id,name')
            ->where('clinic_id', $clinicId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (ClinicLabPartnership $partnership) => (object) [
                'id' => $partnership->lab_id,
                'name' => $partnership->lab?->name,
            ]);
    }

    public function doctors(int $clinicId): Collection
    {
        return User::query()
            ->where('clinic_id', $clinicId)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'doctor'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function patients(int $clinicId): Collection
    {
        return Patient::query()
            ->with('user:id,name')
            ->where('clinic_id', $clinicId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Patient $patient) => (object) [
                'id' => $patient->id,
                'name' => $patient->user?->name,
            ]);
    }

    public function staff(int $clinicId): Collection
    {
        return User::query()
            ->where('clinic_id', $clinicId)
            ->whereDoesntHave('roles', fn (Builder $query) => $query->whereIn('name', ['patient']))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function dentists(int $clinicId): Collection
    {
        return $this->doctors($clinicId);
    }

    public function expenseCategories(int $clinicId): Collection
    {
        return ClinicExpenseCategory::query()
            ->where('clinic_id', $clinicId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function responseSpeeds(int $clinicId): Collection
    {
        $setting = Setting::query()
            ->where('scope_type', 'clinic')
            ->where('scope_id', $clinicId)
            ->whereIn('group', ['dental_lab_module', 'provider_module'])
            ->where('key', 'response_speeds')
            ->latest('id')
            ->first();

        return collect($setting?->value ?? [])
            ->map(fn ($value, $index) => (object) [
                'id' => $index + 1,
                'name' => (string) $value,
            ]);
    }
}
