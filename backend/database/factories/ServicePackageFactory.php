<?php

namespace Database\Factories;

use App\Models\ServicePackage;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServicePackageFactory extends Factory
{
    protected $model = ServicePackage::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->words(3, true) . ' Repair Package',
            'description' => $this->faker->sentence(12),
            'device_category' => $this->faker->randomElement(['Phone', 'Smartphone', 'Laptop', 'Tablet', 'Gaming Console']),
            'fixed_price' => $this->faker->randomElement([1200, 1800, 2500, 3200, 4500]),
            'estimated_turnaround_hours' => $this->faker->randomElement([2, 4, 12, 24]),
            'warranty_months' => $this->faker->randomElement([3, 6, 12]),
            'inclusions' => [
                'OEM diagnostic check',
                'Certified component installation',
                'Post-repair functional stress test',
            ],
            'is_popular' => $this->faker->boolean(40),
            'is_active' => true,
            'order_count' => $this->faker->numberBetween(5, 100),
        ];
    }
}
