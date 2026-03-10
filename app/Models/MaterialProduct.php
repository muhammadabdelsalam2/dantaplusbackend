<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialProduct extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'company_id',
        'image_url',
        'name',
        'brand',
        'description',
        'category',
        'price',
        'stock',
        'status',
    ];

    protected $appends = [
        'category_object',
        'status_object',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(MaterialCompany::class, 'company_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(MaterialOrderItem::class, 'product_id');
    }

    public function getImageUrlAttribute($value)
    {
        return $value ? asset($value) : null;
    }

    public function getCategoryObjectAttribute(): ?array
    {
        $item = collect(config('material_market.product_category_items', []))
            ->firstWhere('key', $this->category);

        return $item ? [
            'key' => $item['key'],
            'label' => $item['label'],
        ] : null;
    }

    public function getStatusObjectAttribute(): array
    {
        return [
            'key' => $this->status,
            'label' => ucfirst($this->status),
        ];
    }
}
