<?php

namespace App\Models\Clinic\Insurance;

use App\Models\Clinic;
use App\Models\Service;
use App\Models\InsurancePriceListItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceClaimItem extends Model
{
    protected $fillable = [
        'insurance_claim_id',
        'insurance_price_list_item_id',
        'service_id',
        'code',
        'service_name',
        'category_id',
        'category_name',
        'unit_price',
        'quantity',
        'total_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'total_amount' => 'decimal:2',
        ];
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class, 'insurance_claim_id');
    }

    public function priceListItem(): BelongsTo
    {
        return $this->belongsTo(InsurancePriceListItem::class, 'insurance_price_list_item_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
