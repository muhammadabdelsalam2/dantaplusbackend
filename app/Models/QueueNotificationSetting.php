<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueNotificationSetting extends Model
{
    protected $fillable = [
        'clinic_id',
        'is_enabled',
        'notify_next',
        'whatsapp_provider',
        'message_template',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'notify_next' => 'integer',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
