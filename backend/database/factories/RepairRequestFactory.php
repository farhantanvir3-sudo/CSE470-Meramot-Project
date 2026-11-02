<?php

namespace Database\Factories;

use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for RepairRequest model (Feature R7)
 * Assigned: Shanid Sajjatuz Islam [23201465]
 */
class RepairRequestFactory extends Factory
{
    protected $model = RepairRequest::class;

    public function definition(): array
    {
        $deviceTypes = ['Laptop', 'Desktop', 'Mobile Phone', 'Tablet', 'Smartwatch'];
        $brands = ['Apple', 'Dell', 'HP', 'Lenovo', 'Asus', 'Samsung', 'Acer'];
        $statuses = ['PENDING', 'BIDDING', 'ASSIGNED', 'AT_SHOP', 'REPAIRING', 'COMPLETED'];
        $serviceTypes = ['pickup_dropoff', 'walk_in', 'technician_home_visit'];

        $issueCategories = [
            'Screen or display',
            'Battery or charging',
            'Keyboard or touchpad',
            'Performance or overheating',
            'Water damage',
            'Speaker or microphone',
            'Camera issue',
            'Software or OS',
            'Network or connectivity',
            'Data recovery',
            'Parts replacement'
        ];

        return [
            'customer_id' => User::factory(),
            'device_type' => $this->faker->randomElement($deviceTypes),
            'brand' => $this->faker->randomElement($brands),
            'model_name' => $this->faker->word() . ' ' . $this->faker->numberBetween(10, 99),
            'serial_number' => strtoupper($this->faker->bothify('??###??###')),
            'issue_title' => $this->faker->sentence(5),
            'issue_description' => $this->faker->paragraph(3),
            'status' => $this->faker->randomElement($statuses),
            'preferred_service_type' => $this->faker->randomElement($serviceTypes),
            'pickup_address' => $this->faker->address(),
            'photos' => null,
            'ai_diagnostic_summary' => $this->faker->optional(0.5)->sentence(10),
            'selected_bid_id' => null,
            'quoted_final_amount' => null,
        ];
    }

    /**
     * Indicate request is in bidding stage
     */
    public function bidding(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'BIDDING',
        ]);
    }

    /**
     * Indicate request is assigned to vendor
     */
    public function assigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ASSIGNED',
            'approved_at' => now(),
        ]);
    }

    /**
     * Indicate request is completed
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'COMPLETED',
            'approved_at' => now()->subDays(5),
        ]);
    }
}
