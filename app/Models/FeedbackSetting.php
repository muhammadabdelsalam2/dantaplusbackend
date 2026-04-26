<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackSetting extends Model
{
    protected $fillable = [
        'clinic_id',
        'is_enabled',
        'channels',
        'delay_minutes',
        'message_template',
        'custom_link',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'channels' => 'array',
            'delay_minutes' => 'integer',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
