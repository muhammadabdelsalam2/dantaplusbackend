<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class patient extends Model
{
    //
    // Mass assignable attributes
    protected $fillable = [
        'user_id',
        'date_of_birth',
        'gender',
        'phone',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function getAgeAttribute(): ?int
    {
        if ($this->date_of_birth) {
            return now()->diffInYears($this->date_of_birth);
        }
        return null;
    }

    /**
     * Scope to filter by gender
     */
    public function scopeGender($query, $gender)
    {
        return $query->where('gender', $gender);
    }

}
