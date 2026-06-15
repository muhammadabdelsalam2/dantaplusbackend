<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Category;
class Product extends MaterialProduct
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $table = 'material_products';

    protected $fillable = [
        'company_id', 'category_id', 'name', 'brand', 'description', 'image_path', 'image_url',
        'price', 'stock', 'status', 'estimated_delivery_time', 'rating', 'review_count', 'created_by', 'updated_by', 'category',
    ];

    protected $appends = [];

    public function company(): BelongsTo { return $this->belongsTo(Company::class, 'company_id'); }
    public function categoryRelation(): BelongsTo { return $this->belongsTo(Category::class, 'category_id'); }
    public function orderItems(): HasMany { return $this->hasMany(OrderItem::class, 'product_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
