<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DentalLabService extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_id',
        'name',
        'price',
        'turnaround_days',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'turnaround_days' => 'integer',
        ];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class, 'lab_id');
    }
}
