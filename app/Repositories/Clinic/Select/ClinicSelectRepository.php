<?php

namespace App\Repositories\Clinic\Select;

use App\Models\ClinicAppointment;
use App\Models\Branch;
use App\Models\ClinicExpenseCategory;
use App\Models\ClinicInvoice;
use App\Models\ClinicLabPartnership;
use App\Models\InsuranceCompany;
use App\Models\MaterialCategory;
use App\Models\MaterialCompany;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Models\Clinic\Insurance\InsuranceClaim;
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

    public function services(int $clinicId, array $filters = []): Collection
    {
        $search = $filters['search'] ?? null;

        return Service::query()
            ->where('is_active', true)
            ->where(function ($query) use ($clinicId) {
                $query->where('is_base', true)
                    ->orWhere('created_by_clinic_id', $clinicId);
            })
            ->when($search, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
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
public function inventoryUnits(int $clinicId, array $filters = []): Collection
{
    return collect(['piece', 'box', 'pack', 'bottle', 'tube', 'cartridge', 'set', 'ml', 'g'])
        ->map(fn (string $unit) => (object) [
            'id' => $unit,
            'name' => str($unit)->replace('_', ' ')->title()->toString(),
        ]);
}
public function claimStatuses(int $clinicId, array $filters = []): Collection
{
    return collect(InsuranceClaim::reportStatuses())
        ->map(fn (string $status) => (object) [
            'id' => $status,
            'name' => match ($status) {
                InsuranceClaim::STATUS_SUBMITTED => 'Submitted',
                'under_review' => 'Under Review',
                InsuranceClaim::STATUS_APPROVED => 'Approved',
                InsuranceClaim::STATUS_APPROVED_WITH_LIMIT => 'Approved with Limit',
                InsuranceClaim::STATUS_PAID => 'Paid',
                InsuranceClaim::STATUS_REJECTED => 'Rejected',
                default => str($status)->replace('_', ' ')->title()->toString(),
            },
        ]);
}
public function rooms(int $clinicId, array $filters = []): Collection
{
    $search = $filters['search'] ?? null;
    $branchId = $filters['branch_id'] ?? null;

    return \App\Models\Room::query()
        ->where('clinic_id', $clinicId)
        ->where('is_active', true)
        ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
        ->when($search, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
        ->orderBy('name')
        ->get(['id', 'name']);
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
public function branches(int $clinicId, array $filters = []): Collection
{
    $search = $filters['search'] ?? null;

    return Branch::query()
        ->where('clinic_id', $clinicId)
        ->when($search, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
        ->orderBy('name')
        ->get(['id', 'name']);
}
}
