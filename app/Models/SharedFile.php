<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedFile extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'conversation_id', 'company_id', 'file_name', 'file_type', 'file_path', 'uploaded_by_type',
        'uploaded_by_id', 'uploaded_by_name', 'related_invoice_id',
    ];

    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by_id'); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class, 'related_invoice_id'); }
}
