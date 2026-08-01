<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicCommunicationPermission extends Model
{
    protected $fillable = [
        'clinic_id',
        'role',
        'can_send_notes',
        'can_send_voice_notes',
        'can_access_patient_discussions',
        'can_delete_messages',
    ];

    protected function casts(): array
    {
        return [
            'can_send_notes' => 'boolean',
            'can_send_voice_notes' => 'boolean',
            'can_access_patient_discussions' => 'boolean',
            'can_delete_messages' => 'boolean',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
