<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImpersonationAudit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'impersonator_id',
        'impersonated_user_id',
        'impersonated_role',
        'guard',
        'ip_address',
        'user_agent',
    ];
}
