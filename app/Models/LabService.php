<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabService extends Model
{
    use HasFactory;

    protected $table = 'lab_services';

    protected $fillable = [
        'lab_id',
        'service_name',
        'price',
        'turnaround_time_days',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'turnaround_time_days' => 'integer',
        ];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class, 'lab_id');
    }
}
