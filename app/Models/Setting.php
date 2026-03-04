<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'scope_type',
        'scope_id',
        'group',
        'key',
        'value',
        'is_encrypted',
    ];

    protected $casts = [
        'value' => 'array',
        'is_encrypted' => 'boolean',
    ];
}
