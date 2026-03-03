<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    //

    use HasFactory;

    protected $table = 'otp_codes';

    /**
     * Mass assignable fields
     */
    protected $fillable = [
        'user_id',
        'identifier',   // email or phone
        'code',         // hashed OTP
        'type',         // register, login, reset_password, etc
        'method',       // email or phone
        'expires_at',
        'verified_at',
        'attempts',
    ];

    /**
     * Casts
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /**
     * Relationship with User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if OTP is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if OTP is verified
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Mark OTP as verified
     */
    public function markAsVerified(): void
    {
        $this->update(['verified_at' => now()]);
    }

    /**
     * Increment attempts
     */
    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }

    /**
     * Scope: latest unverified OTP
     */
    public function scopeLatestUnverified($query, string $identifier, string $type)
    {
        return $query->where('identifier', $identifier)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->latest();
    }
}
