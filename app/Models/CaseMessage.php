<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'sender_id',
        'sender_name',
        'sender_type',
        'message',
        'is_internal',
        'is_read',
        'read_at',
        'attachment_url',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
