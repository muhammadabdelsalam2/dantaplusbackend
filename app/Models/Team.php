<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = ['name', 'owner_id', 'clinic_id'];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'team_users')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function chats()
    {
        return $this->hasMany(Chat::class);
    }
    public function clinic()
    {
        return $this->belongsTo(Clinic::class, 'clinic_id');
    }


    public function scopeOwnedBy($query, $userId)
    {
        return $query->where('owner_id', $userId);
    }

    public function scopeMemberOf($query, $userId)
    {
        return $query->whereHas('members', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }

    public function scopeAccessibleBy($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('owner_id', $userId)
                ->orWhereHas('members', function ($q2) use ($userId) {
                    $q2->where('user_id', $userId);
                });
        });
    }




}
