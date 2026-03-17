<?php

namespace App\Models;

use App\Enums\GalleryImageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabGalleryImage extends Model
{
    use HasFactory;

    protected $table = 'lab_gallery_images';

    public const UPDATED_AT = null;

    protected $fillable = [
        'lab_id',
        'type',
        'url',
        'disk',
        'sort_order',
        'uploaded_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => GalleryImageType::class,
            'sort_order' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class, 'lab_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
