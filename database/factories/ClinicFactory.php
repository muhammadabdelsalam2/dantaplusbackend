<?php

namespace Database\Factories;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClinicFactory extends Factory
{
    protected $model = Clinic::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company.' Dental',
            'owner_name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'phone' => $this->faker->phoneNumber,
            'address' => $this->faker->address,
            'subdomain' => $this->faker->slug.'.dentaplus.com',
            'clinic_type' => $this->faker->randomElement([
                'General Dentist',
                'Orthodontics',
                'Prosthodontics',
                'Pediatric Dentistry',
                'Endodontics',
                'Periodontics',
                'Oral Surgery',
            ]),
            'is_external' => false,
            'notes' => $this->faker->sentence,
            'status' => 'Active',
            'subscription_plan' => 'Basic',
            'payment_method' => 'Manual',
            'start_date' => now()->subMonth(),
            'expiry_date' => now()->addMonths(11),
            'max_users' => 5,
            'max_branches' => 2,
            'registration_date' => now()->subMonths(6)->toDateString(),
        ];
    }

    public function external(): static
    {
        return $this->state(function () {
            return [
                'is_external' => true,
                'subdomain' => null,
            ];
        });
    }
}
