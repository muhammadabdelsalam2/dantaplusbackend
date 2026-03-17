<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabMaterial extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lab_id',
        'name',
        'supplier',
        'stock',
        'low_stock_threshold',
        'cost',
        'purchase_date',
        'expiration_date',
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
            'cost' => 'decimal:2',
            'purchase_date' => 'date',
            'expiration_date' => 'date',
        ];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class, 'lab_id');
    }
}
