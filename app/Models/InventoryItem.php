<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id', 'clinic_id', 'product_id', 'barcode', 'product_name', 'category_name', 'description', 'image_path',
        'quantity', 'minimum_stock_level', 'reorder_quantity', 'unit', 'supplier', 'status', 'last_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'minimum_stock_level' => 'integer',
            'reorder_quantity' => 'integer',
            'last_updated_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function clinic(): BelongsTo { return $this->belongsTo(Clinic::class); }
    public function product(): BelongsTo { return $this->belongsTo(MaterialProduct::class, 'product_id'); }
    public function logs(): HasMany { return $this->hasMany(InventoryLog::class); }
}
