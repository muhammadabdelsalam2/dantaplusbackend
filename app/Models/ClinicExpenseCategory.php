<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicExpenseCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'name',
        'status',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ClinicExpense::class, 'expense_category_id');
    }
}
