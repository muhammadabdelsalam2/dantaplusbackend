<?php

namespace App\Traits;

trait HasSuperAdminScope
{
    /**
     * Returns true if the currently authenticated user has the super-admin role.
     * When true, select endpoints should bypass all scoping filters (lab_id, clinic_id, etc.).
     */
    protected function isSuperAdmin(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }
}
