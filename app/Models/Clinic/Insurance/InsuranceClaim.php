<?php

namespace App\Models\Clinic\Insurance;

use App\Models\Clinic;
use App\Models\ClinicAppointment;
use App\Models\ClinicInvoice;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InsuranceClaim extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PARTIALLY_APPROVED = 'partially_approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'clinic_id',
        'insurance_company_id',
        'patient_id',
        'appointment_id',
        'clinic_invoice_id',
        'claim_number',
        'title',
        'description',
        'service_date',
        'coverage_percentage',
        'gross_amount',
        'patient_share_amount',
        'insurance_share_amount',
        'approved_amount',
        'paid_amount',
        'status',
        'notes',
        'status_notes',
        'submitted_at',
        'reviewed_at',
        'settled_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'coverage_percentage' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'patient_share_amount' => 'decimal:2',
            'insurance_share_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_SUBMITTED,
            self::STATUS_APPROVED,
            self::STATUS_PARTIALLY_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_PAID,
            self::STATUS_CANCELLED,
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompany::class, 'insurance_company_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(ClinicAppointment::class, 'appointment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ClinicInvoice::class, 'clinic_invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
