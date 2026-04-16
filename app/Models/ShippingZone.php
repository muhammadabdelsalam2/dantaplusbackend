<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingZone extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id', 'zone_name', 'shipping_cost', 'estimated_delivery_time', 'polygon_coordinates', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['polygon_coordinates' => 'array', 'is_active' => 'boolean'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
