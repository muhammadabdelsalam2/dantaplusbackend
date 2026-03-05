<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialCompany extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'Active';
    public const STATUS_INACTIVE = 'Inactive';

    protected $fillable = [
        'name',
        'email',
        'commission_percentage',
        'logo_url',
        'description',
        'phone',
        'website',
        'country',
        'city',
        'address',
        'categories',
        'status',
        'is_featured',
        'rating',
        'last_commission_update',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'commission_percentage' => 'decimal:2',
            'is_featured' => 'boolean',
            'rating' => 'integer',
            'last_commission_update' => 'datetime',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(MaterialProduct::class, 'company_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MaterialOrder::class, 'supplier_company_id');
    }
    public function getLogoUrlAttribute($value)
{
    return $value ? asset($value) : null;
}
}
