<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyndicatePrice extends Model
{
    protected $fillable = [
        'clinic_id',
        'year',
        'code',
        'service_name',
        'category',
        'price',
        'is_active_year',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'price' => 'decimal:2',
            'is_active_year' => 'boolean',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
