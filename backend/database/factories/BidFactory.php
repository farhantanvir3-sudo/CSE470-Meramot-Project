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
        $partPrimary = round($partsCost * 0.65, 2);
        $partSecondary = round($partsCost - $partPrimary, 2);
        $warrantyDays = $this->faker->randomElement([30, 60, 90, 180, 365]);

        return [
            'repair_request_id' => RepairRequest::factory(),
            'shop_id' => RepairShop::factory(),
            'parts_cost' => $partsCost,
            'parts_items' => [
                [
                    'name' => 'Primary Replacement Module',
                    'cost' => $partPrimary,
                    'has_warranty' => true,
                    'warranty_days' => $warrantyDays,
                ],
                [
                    'name' => 'Connector & Seal Kit',
                    'cost' => $partSecondary,
                    'has_warranty' => false,
                    'warranty_days' => 0,
                ],
            ],
            'labor_cost' => $laborCost,
            'proposed_cost' => round($partsCost + $laborCost, 2),
            'estimated_days' => $this->faker->numberBetween(1, 7),
            'has_warranty' => true,
            'warranty_days' => $warrantyDays,
            'line_items' => [
                [
                    'item' => 'Primary Replacement Module',
                    'cost' => $partPrimary,
                    'type' => 'part',
                    'has_warranty' => true,
                    'warranty_days' => $warrantyDays,
                ],
                [
                    'item' => 'Connector & Seal Kit',
                    'cost' => $partSecondary,
                    'type' => 'part',
                    'has_warranty' => false,
                    'warranty_days' => 0,
                ],
                [
                    'item' => 'Labor & Service',
                    'cost' => $laborCost,
                    'type' => 'labor',
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
