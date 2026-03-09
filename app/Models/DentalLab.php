<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class DentalLab extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'Active';

    public const STATUS_INACTIVE = 'Inactive';

    public const RESPONSE_SPEED_FAST = 'Fast';

    public const RESPONSE_SPEED_MEDIUM = 'Medium';

    public const RESPONSE_SPEED_SLOW = 'Slow';

    protected $fillable = [
        'name',
        'contact_person',
        'address',
        'city',
        'phone',
        'email',
        'working_hours',
        'avg_delivery_days',
        'response_speed',
        'status',
        'logo_url',
        'rating',
        'is_external',
        'date_added',
        'on_time_percentage',
        'rejection_rate',
    ];

    protected function casts(): array
    {
        return [
            'avg_delivery_days' => 'decimal:2',
            'rating' => 'decimal:1',
            'is_external' => 'boolean',
            'date_added' => 'date',
            'on_time_percentage' => 'decimal:2',
            'rejection_rate' => 'decimal:2',
        ];
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (! $value) {
                    return null;
                }

                if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                    return $value;
                }

                if (str_starts_with($value, '/storage/')) {
                    return asset($value);
                }

                if (str_starts_with($value, 'storage/')) {
                    return asset('/'.$value);
                }

                return asset(Storage::url($value));
            },
        );
    }

    public function services(): HasMany
    {
        return $this->hasMany(DentalLabService::class, 'lab_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(DentalLabReview::class, 'lab_id');
    }

    public function partnerships(): HasMany
    {
        return $this->hasMany(ClinicLabPartnership::class, 'lab_id');
    }

    public function clinics(): BelongsToMany
    {
        return $this->belongsToMany(Clinic::class, 'clinic_lab_partnerships', 'lab_id', 'clinic_id')
            ->withPivot(['status', 'total_cases_sent'])
            ->withTimestamps();
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(CommunicationConversation::class, 'lab_id');
    }
}
