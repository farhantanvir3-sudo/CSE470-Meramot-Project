<?php

namespace Database\Factories;

use App\Models\Bid;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for Bid model (Feature R12/R7 integration)
 * Supporting: Shanid Sajjatuz Islam [23201465] & Farhan Tanvir [22299179]
 */
class BidFactory extends Factory
{
    protected $model = Bid::class;

    public function definition(): array
    {
        $partsCost = $this->faker->randomFloat(2, 500, 5000);
        $laborCost = $this->faker->randomFloat(2, 300, 2000);

        return [
            'repair_request_id' => RepairRequest::factory(),
            'shop_id' => RepairShop::factory(),
            'parts_cost' => $partsCost,
            'labor_cost' => $laborCost,
            'proposed_cost' => $partsCost + $laborCost,
            'estimated_days' => $this->faker->numberBetween(1, 7),
            'warranty_days' => $this->faker->randomElement([30, 60, 90, 180, 365]),
            'line_items' => [
                [
                    'item' => 'Diagnostic Inspection',
                    'cost' => 200,
                ],
                [
                    'item' => 'Parts Replacement',
                    'cost' => $partsCost,
                ],
                [
                    'item' => 'Labor & Service',
                    'cost' => $laborCost,
                ],
            ],
            'technician_note' => $this->faker->optional(0.7)->sentence(12),
            'status' => 'ACTIVE',
        ];
    }

    /**
     * Indicate bid has been accepted
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ACCEPTED',
        ]);
    }

    /**
     * Indicate bid has been declined
     */
    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'DECLINED',
        ]);
    }
}
