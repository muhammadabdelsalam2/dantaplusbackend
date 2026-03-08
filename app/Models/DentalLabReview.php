<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DentalLabReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_id',
        'user_id',
        'user_name',
        'rating',
        'comment',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'reviewed_at' => 'date',
        ];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class, 'lab_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
