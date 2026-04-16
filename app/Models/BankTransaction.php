<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id', 'transaction_id', 'transaction_date', 'amount', 'source', 'status', 'type', 'matched_invoice_id'];

    protected function casts(): array
    {
        return ['transaction_date' => 'date'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function matchedInvoice(): BelongsTo { return $this->belongsTo(Invoice::class, 'matched_invoice_id'); }
}
