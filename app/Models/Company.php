<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'material_companies';

    protected $fillable = [
        'name',
        'logo_url',
        'logo_path',
        'description',
        'email',
        'phone',
        'website',
        'country',
        'city',
        'address',
        'status',
        'rating',
        'commission_percentage',
        'categories',
        'is_featured',
        'last_commission_update',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'commission_percentage' => 'decimal:2',
            'categories' => 'array',
            'is_featured' => 'boolean',
            'last_commission_update' => 'datetime',
        ];
    }

    public function users(): HasMany { return $this->hasMany(User::class); }
    public function products(): HasMany { return $this->hasMany(Product::class, 'company_id'); }
    public function inventory(): HasMany { return $this->hasMany(InventoryItem::class); }
    public function orders(): HasMany { return $this->hasMany(Order::class, 'company_id'); }
    public function invoices(): HasMany { return $this->hasMany(Invoice::class); }
    public function expenses(): HasMany { return $this->hasMany(CompanyExpense::class); }
    public function conversations(): HasMany { return $this->hasMany(Conversation::class); }
    public function shippingZones(): HasMany { return $this->hasMany(ShippingZone::class); }
    public function settings(): HasOne { return $this->hasOne(CompanySetting::class); }
}
