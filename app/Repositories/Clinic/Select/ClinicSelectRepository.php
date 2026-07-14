<?php

namespace App\Repositories\Clinic\Select;

use App\Models\ClinicAppointment;
use App\Models\ClinicExpenseCategory;
use App\Models\ClinicInvoice;
use App\Models\ClinicLabPartnership;
use App\Models\InsuranceCompany;
use App\Models\MaterialCategory;
use App\Models\MaterialCompany;
use App\Models\Patient;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ClinicSelectRepository implements ClinicSelectRepositoryInterface
{
    public function dentalLabs(int $clinicId, array $filters = []): Collection
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

    public function doctors(int $clinicId, array $filters = []): Collection
    {
        return User::query()
            ->where('clinic_id', $clinicId)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'doctor'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function patients(int $clinicId, array $filters = []): Collection
    {
        $search = $filters['search'] ?? null;

        return Patient::query()
            ->with('user:id,name,phone')
            ->where('clinic_id', $clinicId)
            ->when($search, function ($query, string $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('phone', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->get()
            ->map(fn (Patient $patient) => (object) [
                'id' => $patient->id,
                'name' => $patient->user?->name,
            ]);
    }

    public function staff(int $clinicId, array $filters = []): Collection
    {
        return User::query()
            ->where('clinic_id', $clinicId)
            ->whereDoesntHave('roles', fn (Builder $query) => $query->whereIn('name', ['patient']))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function dentists(int $clinicId, array $filters = []): Collection
    {
        return $this->doctors($clinicId, $filters);
    }

    public function expenseCategories(int $clinicId, array $filters = []): Collection
    {
        return ClinicExpenseCategory::query()
            ->where('clinic_id', $clinicId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function insuranceCompanies(int $clinicId, array $filters = []): Collection
    {
        return InsuranceCompany::query()
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function responseSpeeds(int $clinicId, array $filters = []): Collection
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
    public function materialCompanies(int $clinicId, array $filters = []): Collection
{
    $search = $filters['search'] ?? null;

    return MaterialCompany::query()
        ->whereIn('status', ['Active', 'active'])
        ->when($search, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
        ->orderBy('name')
        ->get(['id', 'name']);
}

public function materialCategories(int $clinicId, array $filters = []): Collection
{
    $search = $filters['search'] ?? null;

    return MaterialCategory::query()
        ->when($search, fn ($query, $search) => $query->where('label', 'like', "%{$search}%"))
        ->orderBy('label')
        ->get()
        ->map(fn (MaterialCategory $category) => (object) [
            'id' => $category->id,
            'name' => $category->label,
        ]);
}
public function rooms(int $clinicId, array $filters = []): Collection
{
    $branch = $filters['branch'] ?? null;
    $search = $filters['search'] ?? null;

    return ClinicAppointment::query()
        ->where('clinic_id', $clinicId)
        ->whereNotNull('room')
        ->where('room', '!=', '')
        ->when($branch, fn ($query, $branch) => $query->where('branch', $branch))
        ->when($search, fn ($query, $search) => $query->where('room', 'like', "%{$search}%"))
        ->distinct()
        ->orderBy('room')
        ->pluck('room')
        ->values()
        ->map(fn ($room, $index) => (object) [
            'id' => $index + 1,
            'name' => $room,
        ]);
}

public function invoices(int $clinicId, array $filters = []): Collection
{
    $search = $filters['search'] ?? null;
    $patientId = $filters['patient_id'] ?? null;

    return ClinicInvoice::query()
        ->where('clinic_id', $clinicId)
        ->when($patientId, fn ($query, $patientId) => $query->where('patient_id', $patientId))
        ->when($search, fn ($query, $search) => $query->where('invoice_number', 'like', "%{$search}%"))
        ->orderByDesc('id')
        ->get(['id', 'invoice_number'])
        ->map(fn (ClinicInvoice $invoice) => (object) [
            'id' => $invoice->id,
            'name' => $invoice->invoice_number,
        ]);
}
}
