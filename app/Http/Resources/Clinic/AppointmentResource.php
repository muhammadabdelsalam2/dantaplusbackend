<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
   public function toArray(Request $request): array
{
    $duration = (int) ($this->duration_minutes ?? $this->duration ?? 30);
    $start = $this->appointment_at;
    $end = $start ? $start->copy()->addMinutes($duration) : null;
    $paid = (float) $this->invoices->sum('paid');
    $total = (float) $this->invoices->sum('total');
    $paymentStatus = $total > 0 && $paid >= $total ? 'Paid' : 'Unpaid';
    $approval = $this->activeInsuranceApproval();
    $isInsurance = $this->payment_type === 'insurance';

    return [
        'id' => $this->id,
        'clinic_id' => $this->clinic_id,
        'patient_id' => $this->patient_id,
        'patient_name' => $this->patient_name,
        'patient_phone' => $this->patient_phone,
        'approved_id' => $isInsurance ? $approval?->id : null,
       
            'insurance_approval' => $this->when($this->relationLoaded('insuranceApproval') && $this->insuranceApproval, function () {
                $approval = $this->insuranceApproval;
                
                return [
                    'id' => $approval->id,
                    'code' => $approval->code,
                    'approval_number' => $approval->approval_number,
                    'ref_id' => $approval->ref_id,
                    'authorization_code' => $approval->approval_number ?? $approval->ref_id,
                    'insurance_company_id' => $approval->insurance_company_id,
                    'insurance_company' => $approval->company?->name,
                    'status' => $approval->status,
                    'date' => optional($approval->date)?->toDateString(),
                    'expiry_date' => optional($approval->expiry_date)?->toDateString(),
                    'coverage' => (float) $approval->coverage_percent,
                    'coverage_percent' => (float) $approval->coverage_percent,
                    'approved_amount' => (float) $approval->approved_amount,
                    'used_amount' => (float) $approval->used_amount,
                    'policy_number' => $approval->approval_number,            
                   
                    'services' => $approval->services->map(fn ($service) => [
                        'service_name' => $service->service_name,
                        'service_code' => $service->service_code,
                        'amount' => (float) $service->amount,
                        'co_pay' => (float) $service->co_pay,
                        'tooth_number' => $service->tooth_number,
                    ])->values()->all(),
                    
                    
                    'has_attachment' => !empty(collect($approval->documents ?? [])->firstWhere('type', 'Attachment')),
                    'attachment' => collect($approval->documents ?? [])->firstWhere('type', 'Attachment') ? [
                        'name' => collect($approval->documents ?? [])->firstWhere('type', 'Attachment')['name'] ?? 'Attachment',
                        'url' => collect($approval->documents ?? [])->firstWhere('type', 'Attachment')['url'],
                    ] : null,
                ];
            }),
        'insurance_approval_required' => $isInsurance && ! $approval,
        'patient' => $this->patient ? [
            'id' => $this->patient->id,
            'name' => $this->patient->user?->name ?? $this->patient_name,
            'phone' => $this->patient->phone ?? $this->patient?->user?->phone ?? $this->patient_phone,
            'approved_id' => $isInsurance ? $approval?->id : null,
            'profile_url' => url('/api/clinic/patients/' . $this->patient->id),
        ] : null,
        'doctor' => $this->doctor ? [
            'id' => $this->doctor->id,
            'name' => $this->doctor->name,
        ] : null,
        'service_id' => $this->service_id,
        'service_name' => $this->service_name,
        'appointment_at' => optional($this->appointment_at)?->toISOString(),
        'date' => optional($this->appointment_at)?->toDateString(),
        'time' => optional($this->appointment_at)?->format('H:i'),
        'time_label' => $start && $end ? $start->format('H:i') . ' - ' . $end->format('H:i') . " ({$duration} min)" : null,
        'duration' => $duration,
        'duration_minutes' => $duration,
        'branch_id' => $this->branch_id,
        'branch' => $this->branch,
        'room_id' => $this->room_id,
        'room' => $this->room,
        'payment_type' => $this->payment_type,
        'payment_status' => $paymentStatus,
        'status' => $this->status,
        'status_label' => str($this->status)->replace('_', ' ')->title()->toString(),
        'color' => $this->calendarColor(),
        'calendar_card' => [
            'title' => $this->patient_name,
            'subtitle' => $this->service_name,
            'duration' => $duration,
            'color' => $this->calendarColor(),
        ],
        'details' => [
            'status' => str($this->status)->replace('_', ' ')->title()->toString(),
            'date' => optional($this->appointment_at)?->translatedFormat('l, j F Y'),
            'time' => $start && $end ? $start->format('H:i') . ' - ' . $end->format('H:i') . " ({$duration} min)" : null,
            'dentist' => $this->doctor?->name,
            'payment' => $paymentStatus,
            'actions' => [
                'view_patient_file_url' => $this->patient_id ? url('/api/clinic/patients/' . $this->patient_id) : null,
                'edit_url' => url('/api/clinic/appointments/' . $this->id),
                'delete_url' => url('/api/clinic/appointments/' . $this->id . '/cancel'),
            ],
        ],
        'menu' => $this->actionMenu(),
        'notes' => $this->notes,
        'rescheduled_from_id' => $this->rescheduled_from_id,
        'cancelled_at' => optional($this->cancelled_at)?->toISOString(),
        'completed_at' => optional($this->completed_at)?->toISOString(),
    ];
}

private function calendarColor(): string
{
    return match ($this->status) {
        'pending' => '#facc15',
        'confirmed' => '#3b82f6',
        'arrived', 'attended' => '#8b5cf6',
        'completed' => '#22c55e',
        'cancelled' => '#ef4444',
        default => '#6366f1',
    };
}

private function activeInsuranceApproval(): ?\App\Models\InsuranceApproval
{
    if ($this->payment_type !== 'insurance' || ! $this->patient_id) {
        return null;
    }

    $query = \App\Models\InsuranceApproval::query()
        ->where('clinic_id', $this->clinic_id)
        ->where('patient_id', $this->patient_id)
        ->where('status', 'Approved')
        ->where(function ($query) {
            $query->whereNull('expiry_date')
                  ->orWhereDate('expiry_date', '>=', now()->toDateString());
        });

    if (\Illuminate\Support\Facades\Schema::hasColumn('insurance_approvals', 'appointment_id')) {
        $directApproval = (clone $query)
            ->where('appointment_id', $this->id)
            ->latest('date')
            ->latest('id')
            ->first();

        if ($directApproval) {
            return $directApproval;
        }
    }

    return $query->latest('date')->latest('id')->first();
}

private function approvalSummary(\App\Models\InsuranceApproval $approval): array
{
    return [
        'id' => $approval->id,
        'status' => $approval->status,
        'approved_amount' => (float) $approval->approved_amount,
        'coverage_percent' => (float) $approval->coverage_percent,
        'expiry_date' => optional($approval->expiry_date)?->toDateString(),
    ];
}
private function actionMenu(): array
{
    $status = (string) $this->status;
    $confirmed = in_array($status, ['confirmed', 'arrived', 'attended', 'completed'], true);
    $attended = in_array($status, ['arrived', 'attended', 'completed'], true);

    return [
        ['section' => null, 'key' => 'view_patient_profile', 'label' => 'View Patient Profile', 'locked' => false, 'url' => $this->patient_id ? url('/api/clinic/patients/' . $this->patient_id) : null],
        ['section' => null, 'key' => 'appointment_details', 'label' => 'Appointment Details', 'locked' => false, 'url' => url('/api/clinic/appointments/' . $this->id)],
        ['section' => null, 'key' => 'add_clinical_notes', 'label' => 'Add Clinical Notes', 'locked' => false, 'url' => url('/api/clinic/patients/' . $this->patient_id . '/discussion')],
        ['section' => 'CLINICAL', 'key' => 'add_treatment_service', 'label' => 'Add Treatment / Service', 'locked' => ! $confirmed, 'url' => url('/api/clinic/patients/' . $this->patient_id . '/treatments')],
        ['section' => 'CLINICAL', 'key' => 'add_radiology_xray', 'label' => 'Add Radiology / X-Ray', 'locked' => ! $confirmed, 'url' => url('/api/clinic/patients/' . $this->patient_id . '/radiology/upload')],
        ['section' => 'CLINICAL', 'key' => 'send_case_to_lab', 'label' => 'Send Case to Lab', 'locked' => ! $confirmed, 'url' => url('/api/clinic/patients/' . $this->patient_id . '/labs')],
        ['section' => 'STATUS FLOW', 'key' => 'confirm_appointment', 'label' => 'Confirm Appointment', 'locked' => $status !== 'pending' && $status !== 'scheduled', 'url' => url('/api/clinic/appointments/' . $this->id . '/confirm')],
        ['section' => 'STATUS FLOW', 'key' => 'checked_in_attended', 'label' => 'Checked In / Attended', 'locked' => ! $confirmed || $attended, 'url' => url('/api/clinic/appointments/' . $this->id . '/attend')],
        ['section' => 'STATUS FLOW', 'key' => 'mark_completed', 'label' => 'Mark Completed', 'locked' => ! $attended || $status === 'completed', 'url' => url('/api/clinic/appointments/' . $this->id . '/complete')],
        ['section' => 'FINANCIAL', 'key' => 'add_payment', 'label' => 'Add Payment', 'locked' => false, 'url' => url('/api/clinic/appointments/' . $this->id . '/payment')],
        ['section' => 'SCHEDULING', 'key' => 'reschedule', 'label' => 'Reschedule', 'locked' => in_array($status, ['completed', 'cancelled'], true), 'url' => url('/api/clinic/appointments/' . $this->id . '/reschedule')],
        ['section' => 'SCHEDULING', 'key' => 'cancel_appointment', 'label' => 'Cancel Appointment', 'locked' => in_array($status, ['completed', 'cancelled'], true), 'url' => url('/api/clinic/appointments/' . $this->id . '/cancel')],
        ['section' => 'QUICK', 'key' => 'whatsapp_reminder', 'label' => 'WhatsApp Reminder', 'locked' => false, 'url' => url('/api/clinic/appointments/' . $this->id . '/whatsapp-reminder')],
    ];
}
}
