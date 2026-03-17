<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabWhatsAppApiLog extends Model
{
    use HasFactory;

    protected $table = 'lab_whatsapp_api_logs';

    public const UPDATED_AT = null;

    protected $fillable = [
        'lab_id',
        'provider',
        'action',
        'details',
        'status',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class, 'lab_id');
    }
}
