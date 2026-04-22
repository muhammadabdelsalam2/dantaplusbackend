<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    //

    protected $fillable = [
        'type',
        'team_id',
        'name',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'chat_participants');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function scopeAccessibleBy($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {

            $q->whereHas('team', function ($q2) use ($userId) {
                $q2->where('owner_id', $userId);
            })

                ->orWhereHas('participants', function ($q3) use ($userId) {
                    $q3->where('user_id', $userId);
                });

        });
    }

}
