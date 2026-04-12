<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function view(User $authUser, User $user): bool
    {
        return (int) $authUser->lab_id === (int) $user->lab_id
            && $authUser->hasRole('lab_admin');
    }

    public function update(User $authUser, User $user): bool
    {
        return (int) $authUser->lab_id === (int) $user->lab_id
            && $authUser->hasRole('lab_admin');
    }

    public function delete(User $authUser, User $user): bool
    {
        return (int) $authUser->lab_id === (int) $user->lab_id
            && $authUser->hasRole('lab_admin')
            && (int) $authUser->id !== (int) $user->id;
    }
}
