<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageMention extends Model
{
    //
    protected $fillable = [
        'message_id',
        'user_id',
    ];
}
