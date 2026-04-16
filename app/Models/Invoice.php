<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'order_id', 'company_id', 'clinic_id', 'invoice_number', 'issue_date', 'due_date', 'subtotal', 'tax',
        'total_amount', 'status', 'payment_method', 'completion_date', 'order_type',
    ];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'due_date' => 'date', 'completion_date' => 'datetime'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function clinic(): BelongsTo { return $this->belongsTo(Clinic::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
}
