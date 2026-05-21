<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappBotSetting extends Model
{
    protected $fillable = [
        'clinic_id',
        'is_enabled',
        'welcome_message',
        'out_of_hours_message',
        'start_time',
        'end_time',
        'language',
        'require_deposit',
        'deposit_amount',
        'allowed_services',
        'ai_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'require_deposit' => 'boolean',
            'ai_enabled' => 'boolean',
            'deposit_amount' => 'decimal:2',
            'allowed_services' => 'array',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
