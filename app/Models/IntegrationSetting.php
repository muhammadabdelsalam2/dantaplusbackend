<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationSetting extends Model
{
    protected $table = 'integrations_settings';

    protected $fillable = [
        'clinic_id',
        'provider',
        'access_token',
        'refresh_token',
        'connected',
    ];

    protected function casts(): array
    {
        return [
            'connected' => 'boolean',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
