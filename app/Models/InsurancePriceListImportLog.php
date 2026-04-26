<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsurancePriceListImportLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'insurance_price_list_id',
        'import_key',
        'source_file',
        'payload',
        'imported_count',
        'updated_count',
        'failed_count',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'imported_count' => 'integer',
            'updated_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(InsurancePriceList::class, 'insurance_price_list_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
