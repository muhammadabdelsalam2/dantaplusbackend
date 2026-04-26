<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppearanceSetting extends Model
{
    protected $table = 'appearance_settings';

    protected $fillable = [
        'clinic_id',
        'theme',
        'primary_color',
        'language',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
