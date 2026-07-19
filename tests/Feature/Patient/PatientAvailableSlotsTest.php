<?php

namespace Tests\Feature\Patient;

use App\Models\Branch;
use App\Http\Controllers\Api\Patient\PatientAppointmentController;
use App\Services\Clinic\Settings\ClinicAppointmentSettingsService;
use ReflectionMethod;
use Tests\TestCase;

class PatientAvailableSlotsTest extends TestCase
{
    public function test_branch_null_working_hours_use_default_day_range(): void
    {
        $controller = new PatientAppointmentController($this->createMock(ClinicAppointmentSettingsService::class));
        $method = new ReflectionMethod($controller, 'branchWorkingHours');

        $branch = new Branch([
            'status' => 'Active',
            'working_hours_from' => null,
            'working_hours_to' => null,
        ]);

        $this->assertSame(['09:00', '17:00'], $method->invoke($controller, $branch));
    }

    public function test_branch_real_working_hours_are_not_replaced(): void
    {
        $controller = new PatientAppointmentController($this->createMock(ClinicAppointmentSettingsService::class));
        $method = new ReflectionMethod($controller, 'branchWorkingHours');

        $branch = new Branch([
            'status' => 'Active',
            'working_hours_from' => '10:00:00',
            'working_hours_to' => '15:00:00',
        ]);

        $this->assertSame(['10:00:00', '15:00:00'], $method->invoke($controller, $branch));
    }
}
