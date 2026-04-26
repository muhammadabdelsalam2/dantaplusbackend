<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InsurancePriceList extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'clinic_id',
        'name',
        'year',
        'notes',
        'is_active',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'is_active' => 'boolean',
            'imported_at' => 'datetime',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InsurancePriceListItem::class);
    }

    public function importLogs(): HasMany
    {
        return $this->hasMany(InsurancePriceListImportLog::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(InsuranceCompany::class, 'syndicate_price_list_id');
    }
}
