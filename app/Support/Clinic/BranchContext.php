<?php

namespace App\Support\Clinic;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class BranchContext
{
    public const REQUEST_ATTRIBUTE = 'current_branch_id';

    public function set(Request $request, Branch $branch): void
    {
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $branch->id);
        $request->attributes->set('current_branch', $branch);
    }

    public function id(): ?int
    {
        $branchId = request()->attributes->get(self::REQUEST_ATTRIBUTE);

        return $branchId !== null ? (int) $branchId : null;
    }

    public function branch(): ?Branch
    {
        return request()->attributes->get('current_branch');
    }

    public function applyToAppointments(Builder $query, ?int $branchId = null): Builder
    {
        $branchId ??= $this->id();

        return $branchId ? $query->where('branch_id', $branchId) : $query;
    }

    public function applyToDoctors(Builder $query, ?int $branchId = null): Builder
    {
        $branchId ??= $this->id();

        return $branchId ? $query->where('branch_id', $branchId) : $query;
    }

    public function applyToPatientsThroughAppointments(Builder $query, ?int $branchId = null): Builder
    {
        $branchId ??= $this->id();

        return $branchId
            ? $query->whereHas('appointments', fn (Builder $appointment) => $appointment->where('branch_id', $branchId))
            : $query;
    }

    public function applyToInvoicesThroughAppointments(Builder $query, ?int $branchId = null): Builder
    {
        $branchId ??= $this->id();

        return $branchId
            ? $query->whereHas('appointment', fn (Builder $appointment) => $appointment->where('branch_id', $branchId))
            : $query;
    }

    public function applyToPaymentsThroughInvoiceAppointment(Builder $query, ?int $branchId = null): Builder
    {
        $branchId ??= $this->id();

        return $branchId
            ? $query->whereHas('invoice.appointment', fn (Builder $appointment) => $appointment->where('branch_id', $branchId))
            : $query;
    }
}
