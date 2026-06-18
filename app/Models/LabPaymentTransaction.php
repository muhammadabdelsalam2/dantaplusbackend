<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabPaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_payment_id',
        'lab_invoice_id',
        'lab_id',
        'provider',
        'transaction_reference',
        'amount',
        'status',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(LabPayment::class, 'lab_payment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(LabInvoice::class, 'lab_invoice_id');
    }
}
