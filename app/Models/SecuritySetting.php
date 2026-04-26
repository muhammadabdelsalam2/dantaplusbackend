<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecuritySetting extends Model
{
    protected $table = 'security_settings';

    protected $fillable = [
        'clinic_id',
        'enable_2fa',
        'backup_schedule',
        'retention_days',
    ];

    protected function casts(): array
    {
        return [
            'enable_2fa' => 'boolean',
            'retention_days' => 'integer',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
