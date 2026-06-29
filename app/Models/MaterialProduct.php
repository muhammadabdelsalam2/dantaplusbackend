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

    public const APPROVAL_PENDING  = 'pending';
public const APPROVAL_APPROVED = 'approved';
public const APPROVAL_REJECTED = 'rejected';
    protected $fillable = [
        'company_id',
        'barcode',
        'image_url',
        'name',
        'brand',
        'description',
        'category',
        'price',
        'stock',
        'status',
         'approval_status',
    'rejection_reason',
    'approved_at',
    'approved_by',
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

    public function scopeApproved($query)
{
    return $query->where('approval_status', self::APPROVAL_APPROVED);
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
    // في MaterialProduct model
protected static function booted(): void
{
    static::creating(function (MaterialProduct $product) {
        if (empty($product->barcode)) {
            // توليد مؤقت، هيحدث بعد الـ insert عشان نعرف الـ id
            $product->barcode = '___PENDING___';
        }
    });

    static::created(function (MaterialProduct $product) {
        if ($product->barcode === '___PENDING___') {
            $product->updateQuietly([
                'barcode' => '200' . str_pad($product->id, 4, '0', STR_PAD_LEFT) . strtoupper(\Illuminate\Support\Str::random(6)),
            ]);
        }
    });
}
}
