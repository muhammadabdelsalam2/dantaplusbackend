<?php

namespace Tests\Feature\Lab;

use App\Models\CaseModel;
use App\Models\Clinic;
use App\Models\DentalLab;
use App\Models\Doctor;
use App\Models\LabExpenseCategory;
use App\Models\LabService;
use App\Models\Patient;
use App\Models\User;
use App\Services\Lab\Accounting\LabAccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabAccountingServiceTest extends TestCase
{
    use RefreshDatabase;

    private DentalLab $lab;
    private Clinic $clinic;
    private Doctor $doctor;
    private User $technician;
    private LabAccountingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lab = DentalLab::query()->create([
            'name' => 'Accounting Lab',
            'status' => DentalLab::STATUS_ACTIVE,
        ]);
        $this->clinic = Clinic::factory()->create();

        $doctorUser = User::factory()->create();
        $this->doctor = Doctor::query()->create([
            'user_id' => $doctorUser->id,
            'specialization' => 'Prosthodontics',
            'license_number' => 'DOC-' . uniqid(),
        ]);

        $this->technician = User::factory()->create([
            'lab_id' => $this->lab->id,
            'commission_rates' => ['default' => 20],
        ]);

        $labAdmin = User::factory()->create(['lab_id' => $this->lab->id]);
        $this->actingAs($labAdmin, 'sanctum');

        LabService::query()->create([
            'lab_id' => $this->lab->id,
            'service_name' => 'Crown',
            'price' => 1000,
            'turnaround_time_days' => 5,
        ]);

        LabExpenseCategory::query()->create([
            'lab_id' => $this->lab->id,
            'name' => 'Materials',
            'status' => 'active',
        ]);

        $this->service = app(LabAccountingService::class);
    }

    public function test_generate_monthly_invoices_prevents_duplicates(): void
    {
        $this->completedCase('CASE-001');

        $first = $this->service->generateMonthlyInvoices([
            'month' => now()->format('Y-m'),
            'group_by' => 'clinic',
        ]);
        $second = $this->service->generateMonthlyInvoices([
            'month' => now()->format('Y-m'),
            'group_by' => 'clinic',
        ]);

        $this->assertTrue($first['success']);
        $this->assertSame(1, $first['data']['created_count']);
        $this->assertTrue($second['success']);
        $this->assertSame(0, $second['data']['created_count']);
        $this->assertSame(1, $second['data']['skipped_count']);
    }

    public function test_partial_payment_updates_outstanding_and_profit_summary(): void
    {
        $this->completedCase('CASE-002');
        $generated = $this->service->generateMonthlyInvoices([
            'month' => now()->format('Y-m'),
            'group_by' => 'clinic',
        ]);
        $invoice = $generated['data']['created'][0];

        $payment = $this->service->recordPayment($invoice->id, [
            'amount' => 400,
            'method' => 'Cash',
            'paid_at' => now()->toDateString(),
        ]);

        $summary = $this->service->summary(['month' => now()->format('Y-m')]);

        $this->assertTrue($payment['success']);
        $this->assertSame(400.0, $summary['data']['monthly_income']);
        $this->assertSame(600.0, $summary['data']['total_outstanding']);
        $this->assertSame(400.0, $summary['data']['monthly_profit']);
    }

    public function test_expenses_reduce_profit(): void
    {
        $category = LabExpenseCategory::query()->where('lab_id', $this->lab->id)->firstOrFail();

        $expense = $this->service->createExpense([
            'lab_expense_category_id' => $category->id,
            'title' => 'Ceramic batch',
            'amount' => 150,
            'expense_date' => now()->toDateString(),
        ]);

        $summary = $this->service->summary(['month' => now()->format('Y-m')]);

        $this->assertTrue($expense['success']);
        $this->assertSame(150.0, $summary['data']['monthly_expenses']);
        $this->assertSame(-150.0, $summary['data']['monthly_profit']);
    }

    public function test_technician_earnings_and_top_clinics_use_lab_payments(): void
    {
        $this->completedCase('CASE-003');
        $generated = $this->service->generateMonthlyInvoices([
            'month' => now()->format('Y-m'),
            'group_by' => 'doctor',
        ]);
        $invoice = $generated['data']['created'][0];
        $this->service->recordPayment($invoice->id, [
            'amount' => 1000,
            'method' => 'Manual Payment',
            'paid_at' => now()->toDateString(),
        ]);

        $earnings = $this->service->technicianEarnings([]);
        $topClinics = $this->service->topPayingClinics([]);

        $this->assertTrue($earnings['success']);
        $this->assertSame(1, $earnings['data'][0]['total_cases']);
        $this->assertSame(200.0, $earnings['data'][0]['commission']);
        $this->assertTrue($topClinics['success']);
        $this->assertSame($this->clinic->id, $topClinics['data'][0]['clinic_id']);
        $this->assertSame(1000.0, $topClinics['data'][0]['paid_amount']);
    }

    private function completedCase(string $number): CaseModel
    {
        $patientUser = User::factory()->create();
        $patient = Patient::query()->create([
            'user_id' => $patientUser->id,
            'clinic_id' => $this->clinic->id,
            'date_of_birth' => now()->subYears(30)->toDateString(),
        ]);

        return CaseModel::query()->create([
            'case_number' => $number,
            'clinic_id' => $this->clinic->id,
            'lab_id' => $this->lab->id,
            'patient_id' => $patient->id,
            'dentist_id' => $this->doctor->id,
            'status' => CaseModel::STATUS_COMPLETED,
            'priority' => CaseModel::PRIORITY_NORMAL,
            'due_date' => now()->addDay()->toDateString(),
            'case_type' => 'Crown',
            'tooth_numbers' => [11, 12],
            'assigned_technician_id' => $this->technician->id,
            'completed_at' => now(),
        ]);
    }
}
