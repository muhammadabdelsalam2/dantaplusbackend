<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'base_price',
        'is_base',
        'created_by_clinic_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'is_base' => 'boolean',
            'created_by_clinic_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function clinicPrices(): HasMany
    {
        return $this->hasMany(ClinicServicePrice::class);
    }

    public function creatorClinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'created_by_clinic_id');
    }
}
