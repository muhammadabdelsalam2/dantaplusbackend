<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_invoice_id',
        'case_id',
        'lab_service_id',
        'patient_id',
        'technician_id',
        'case_number',
        'patient_name',
        'service_name',
        'teeth_numbers',
        'fdi_teeth_numbers',
        'dental_chart',
        'quantity',
        'unit_price',
        'materials_cost',
        'subtotal',
        'tax',
        'discount',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'teeth_numbers' => 'array',
            'fdi_teeth_numbers' => 'array',
            'dental_chart' => 'array',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'materials_cost' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(LabInvoice::class, 'lab_invoice_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(LabService::class, 'lab_service_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(LabInvoiceItemMaterial::class);
    }
}
