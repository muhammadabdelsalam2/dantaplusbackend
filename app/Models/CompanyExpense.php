<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyExpense extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'category', 'amount', 'expense_date', 'notes', 'receipt_path'];

    protected function casts(): array
    {
        return ['expense_date' => 'date'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
