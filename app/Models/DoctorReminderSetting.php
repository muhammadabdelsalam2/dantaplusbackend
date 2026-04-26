<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorReminderSetting extends Model
{
    protected $fillable = [
        'clinic_id',
        'is_enabled',
        'send_time',
        'channels',
        'message_template',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'channels' => 'array',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
