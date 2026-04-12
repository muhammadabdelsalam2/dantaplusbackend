<?php

namespace App\Policies;

use App\Models\CaseModel;
use App\Models\User;

class CasePolicy
{
    public function view(User $user, CaseModel $case): bool
    {
        if ((int) $user->lab_id !== (int) $case->lab_id) {
            return false;
        }

        if ($user->hasRole('lab_technician')) {
            return (int) $case->assigned_technician_id === (int) $user->id
                || (int) $case->created_by === (int) $user->id;
        }

        return $user->hasAnyRole(['lab_admin', 'lab_receptionist']);
    }

    public function update(User $user, CaseModel $case): bool
    {
        if ((int) $user->lab_id !== (int) $case->lab_id) {
            return false;
        }

        if ($user->hasRole('lab_technician')) {
            return (int) $case->assigned_technician_id === (int) $user->id;
        }

        return $user->hasAnyRole(['lab_admin', 'lab_receptionist']);
    }

    public function delete(User $user, CaseModel $case): bool
    {
        return (int) $user->lab_id === (int) $case->lab_id
            && $user->hasRole('lab_admin');
    }
}
