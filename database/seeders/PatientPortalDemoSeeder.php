<?php

namespace Database\Seeders;

use App\Models\Clinic\Insurance\InsuranceClaim;
use App\Models\Clinic\Insurance\InsuranceClaimItem;
use App\Models\ClinicAppointment;
use App\Models\ClinicInvoice;
use App\Models\ClinicPayment;
use App\Models\InsuranceCompany;
use App\Models\Patient;
use App\Models\PatientAppointmentRating;
use App\Models\PatientDocument;
use App\Models\PatientPaymentRefundRequest;
use App\Models\PatientRadiology;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PatientPortalDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Demo data only for testing the Patient Portal API. Do not run on production without review.
        $clinicId = 26;
        $doctorUserId = 64;

        $patient = Patient::query()
            ->whereBetween('id', [1, 19])
            ->where('clinic_id', $clinicId)
            ->orderBy('id')
            ->first()
            ?: Patient::query()->whereBetween('id', [1, 19])->orderBy('id')->first();

        if (! $patient) {
            $this->command?->warn('No patient found between IDs 1 and 19. Seeder skipped.');
            return;
        }

        $clinicId = $patient->clinic_id ?: $clinicId;

        $appointment = ClinicAppointment::updateOrCreate(
            [
                'clinic_id' => $clinicId,
                'patient_id' => $patient->id,
                'service_name' => 'Patient Portal Demo Consultation',
            ],
            [
                'doctor_user_id' => $doctorUserId,
                'patient_name' => $patient->user?->name ?? 'Demo Patient',
                'patient_phone' => $patient->phone ?: $patient->user?->phone,
                'appointment_at' => now()->addDays(3)->setTime(13, 0),
                'duration_minutes' => 30,
                'branch' => 'Main Branch',
                'room' => 'Room 1',
                'payment_type' => 'cash',
                'status' => 'completed',
                'notes' => 'Demo appointment for Patient Portal GET endpoints.',
            ]
        );

        $invoice = ClinicInvoice::updateOrCreate(
            [
                'clinic_id' => $clinicId,
                'invoice_number' => 'PP-DEMO-' . $patient->id,
            ],
            [
                'patient_id' => $patient->id,
                'doctor_user_id' => $doctorUserId,
                'appointment_id' => $appointment->id,
                'total' => 1500,
                'paid' => 1000,
                'remaining' => 500,
                'status' => 'partial',
                'payment_method' => 'cash',
                'issued_at' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'notes' => 'Demo invoice for Patient Portal.',
            ]
        );

        $payment = ClinicPayment::updateOrCreate(
            [
                'clinic_invoice_id' => $invoice->id,
                'amount' => 1000,
            ],
            [
                'clinic_id' => $clinicId,
                'recorded_by' => $doctorUserId,
                'method' => 'cash',
                'paid_at' => now()->subDay(),
                'notes' => 'Demo payment for Patient Portal.',
            ]
        );

        $document = PatientDocument::updateOrCreate(
            [
                'clinic_id' => $clinicId,
                'patient_id' => $patient->id,
                'document_type' => 'demo',
                'file_path' => 'patient-demo/demo-document.pdf',
            ],
            [
                'uploaded_by' => $doctorUserId,
                'title' => 'Demo Patient Document',
                'original_name' => 'demo-document.pdf',
                'mime_type' => 'application/pdf',
                'size' => 0,
                'notes' => 'Demo document row without a real file.',
            ]
        );

        PatientRadiology::updateOrCreate(
            [
                'clinic_id' => $clinicId,
                'patient_id' => $patient->id,
                'file_path' => 'patient-demo/demo-radiology.png',
            ],
            [
                'modality' => 'xray',
                'status' => 'available',
                'notes' => 'Demo radiology row without a real file.',
            ]
        );

        $company = InsuranceCompany::firstOrCreate(
            [
                'clinic_id' => $clinicId,
                'name' => 'Patient Portal Demo Insurance',
            ],
            [
                'code' => 'PP-DEMO',
                'coverage' => '70%',
                'payment_terms' => 'Demo terms',
                'is_active' => true,
            ]
        );

        $claim = InsuranceClaim::updateOrCreate(
            [
                'clinic_id' => $clinicId,
                'claim_number' => 'PP-CLAIM-' . $patient->id,
            ],
            [
                'insurance_company_id' => $company->id,
                'patient_id' => $patient->id,
                'appointment_id' => $appointment->id,
                'clinic_invoice_id' => $invoice->id,
                'title' => 'Patient Portal Demo Claim',
                'description' => 'Demo insurance claim for Patient Portal.',
                'service_date' => now()->toDateString(),
                'coverage_percentage' => 70,
                'gross_amount' => 1500,
                'patient_share_amount' => 450,
                'insurance_share_amount' => 1050,
                'approved_amount' => 1000,
                'paid_amount' => 800,
                'status' => InsuranceClaim::STATUS_PARTIALLY_APPROVED,
                'notes' => 'Demo claim.',
                'status_notes' => 'Partially approved for demo.',
                'submitted_at' => now()->subDays(2),
                'reviewed_at' => now()->subDay(),
                'created_by' => $doctorUserId,
                'updated_by' => $doctorUserId,
                'patient_consent_required' => true,
                'patient_consent_document_id' => $document->id,
                'patient_consent_uploaded_at' => now()->subDay(),
            ]
        );

        InsuranceClaimItem::updateOrCreate(
            [
                'insurance_claim_id' => $claim->id,
                'service_name' => 'Demo Dental Service',
            ],
            [
                'code' => 'D-DEMO',
                'unit_price' => 1500,
                'quantity' => 1,
                'total_amount' => 1500,
                'notes' => 'Demo claim item.',
            ]
        );

        if (Schema::hasTable('patient_payment_refund_requests')) {
            PatientPaymentRefundRequest::updateOrCreate(
                [
                    'patient_id' => $patient->id,
                    'payment_id' => $payment->id,
                ],
                [
                    'clinic_id' => $clinicId,
                    'invoice_id' => $invoice->id,
                    'reason' => 'Demo refund request for Patient Portal.',
                    'status' => 'pending',
                    'requested_at' => now(),
                ]
            );
        }

        if (Schema::hasTable('patient_appointment_ratings')) {
            PatientAppointmentRating::firstOrCreate(
                [
                    'patient_id' => $patient->id,
                    'appointment_id' => $appointment->id,
                ],
                [
                    'clinic_id' => $clinicId,
                    'doctor_user_id' => $doctorUserId,
                    'doctor_rating' => 5,
                    'clinic_rating' => 5,
                    'comment' => 'Demo rating for Patient Portal.',
                ]
            );
        }
    }
}
