<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabPayment extends Model
{
    use HasFactory;

    public const METHODS = ['Cash', 'Bank Transfer', 'Stripe', 'PayPal', 'Manual Payment'];

    protected $fillable = [
        'lab_invoice_id',
        'lab_id',
        'recorded_by',
        'amount',
        'method',
        'status',
        'transaction_reference',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(LabInvoice::class, 'lab_invoice_id');
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class, 'lab_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LabPaymentTransaction::class);
    }
}
