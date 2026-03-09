<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceCompany extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'Active';

    public const STATUS_INACTIVE = 'Inactive';

    protected $fillable = [
        'name',
        'contact_person',
        'phone',
        'email',
        'status',
        'logo_url',
        'ai_rating',
        'feedback',
        'reports',
    ];

    protected function casts(): array
    {
        return [
            'ai_rating' => 'decimal:2',
            'feedback' => 'array',
            'reports' => 'array',
        ];
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(OwnerMaintenanceRequest::class, 'assigned_company_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(AiAlert::class, 'company_id');
    }
}
