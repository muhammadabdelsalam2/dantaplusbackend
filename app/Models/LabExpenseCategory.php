<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabExpenseCategory extends Model
{
    use HasFactory;

    protected $fillable = ['lab_id', 'name', 'status'];

    public function lab(): BelongsTo
    {
        return $this->belongsTo(DentalLab::class, 'lab_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(LabExpense::class);
    }
}
