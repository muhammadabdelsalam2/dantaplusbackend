<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalService extends Model
{
    protected $fillable = [
        'insurance_approval_id',
        'service_name',
        'service_code',
        'amount',
        'co_pay',
        'tooth_number',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'co_pay' => 'decimal:2',
        ];
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(InsuranceApproval::class, 'insurance_approval_id');
    }
}
