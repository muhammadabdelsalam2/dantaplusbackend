<?php

namespace App\Policies;

use App\Models\DeliveryTask;
use App\Models\User;

class DeliveryTaskPolicy
{
    public function view(User $user, DeliveryTask $task): bool
    {
        if ((int) $user->lab_id !== (int) $task->lab_id) {
            return false;
        }

        if ($user->hasRole('delivery_representative')) {
            return (int) $task->delivery_rep_user_id === (int) $user->id;
        }

        return $user->hasAnyRole(['lab_admin', 'lab_receptionist']);
    }

    public function update(User $user, DeliveryTask $task): bool
    {
        if ((int) $user->lab_id !== (int) $task->lab_id) {
            return false;
        }

        if ($user->hasRole('delivery_representative')) {
            return (int) $task->delivery_rep_user_id === (int) $user->id;
        }

        return $user->hasAnyRole(['lab_admin', 'lab_receptionist']);
    }

    public function delete(User $user, DeliveryTask $task): bool
    {
        return (int) $user->lab_id === (int) $task->lab_id
            && $user->hasRole('lab_admin');
    }
}
