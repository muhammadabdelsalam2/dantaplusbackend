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
        'company_id', 'product_id', 'product_name', 'category_name', 'description', 'image_path',
        'quantity', 'minimum_stock_level', 'unit', 'supplier', 'status', 'last_updated_at',
    ];

    protected function casts(): array
    {
        return ['last_updated_at' => 'datetime'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function logs(): HasMany { return $this->hasMany(InventoryLog::class); }
}
