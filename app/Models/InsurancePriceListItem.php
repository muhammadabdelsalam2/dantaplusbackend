<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsurancePriceListItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'insurance_price_list_id',
        'service_id',
        'code',
        'item_code',
        'service_name',
        'category_id',
        'category_name',
        'price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(InsurancePriceList::class, 'insurance_price_list_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
