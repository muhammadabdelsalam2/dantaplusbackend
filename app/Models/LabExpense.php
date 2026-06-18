<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_id',
        'lab_expense_category_id',
        'title',
        'amount',
        'payment_method',
        'expense_date',
        'vendor',
        'notes',
        'attachment_path',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class, 'lab_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LabExpenseCategory::class, 'lab_expense_category_id');
    }
}
