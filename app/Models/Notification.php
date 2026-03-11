<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'type',
        'status',
        'audience_type',
        'audience_id',
        'priority',
        'delivery_methods',
        'is_read',
        'read_at',
        'is_test',
        'sender_id',
        'sender_name',
        'link',
    ];

    protected function casts(): array
    {
        return [
            'delivery_methods' => 'array',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'is_test' => 'boolean',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
