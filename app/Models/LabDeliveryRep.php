<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class LabDeliveryRep extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'Active';
    public const STATUS_INACTIVE = 'Inactive';

    protected $fillable = [
        'user_id',
        'lab_id',
        'assigned_region_city',
        'whatsapp_number',
        'profile_photo',
        'status',
    ];

    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                $value = $this->profile_photo;

                if (!$value) {
                    return null;
                }

                if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                    return $value;
                }

                if (str_starts_with($value, '/storage/')) {
                    return asset($value);
                }

                if (str_starts_with($value, 'storage/')) {
                    return asset('/' . $value);
                }

                return asset(Storage::url($value));
            }
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class, 'lab_id');
    }
}
