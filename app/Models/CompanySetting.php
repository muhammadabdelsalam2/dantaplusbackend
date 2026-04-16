<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySetting extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id', 'profile', 'communication', 'automation'];

    protected function casts(): array
    {
        return ['profile' => 'array', 'communication' => 'array', 'automation' => 'array'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
