<?php

namespace App\Models;

use App\Enums\WhatsAppProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabSetting extends Model
{
    use HasFactory;

    protected $table = 'lab_settings';

    protected $primaryKey = 'lab_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'lab_id',
        'notifications_json',
        'whatsapp_provider',
        'whatsapp_meta_json',
        'whatsapp_twilio_json',
    ];

    protected function casts(): array
    {
        return [
            'notifications_json' => 'array',
            'whatsapp_provider' => WhatsAppProvider::class,
        ];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class, 'lab_id');
    }
}
