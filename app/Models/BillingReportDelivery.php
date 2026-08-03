<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingReportDelivery extends Model
{
    protected $fillable = [
        'clinic_id',
        'doctor_user_id',
        'report_type',
        'sent_to',
        'channel',
        'status',
        'filters',
        'payload',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }
}
