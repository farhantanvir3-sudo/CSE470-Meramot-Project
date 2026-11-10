<?php

namespace Database\Factories;

use App\Models\RepairShop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RepairShopFactory extends Factory
{
    protected $model = RepairShop::class;

    public function definition(): array
    {
        $name = $this->faker->company() . ' Repair Lab';
        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(5)),
            'description' => $this->faker->sentence(12),
            'address' => $this->faker->streetAddress(),
            'city' => 'Dhaka',
            'area' => $this->faker->randomElement(['Banani', 'Gulshan', 'Dhanmondi', 'Uttara', 'Mirpur']),
            'latitude' => $this->faker->latitude(23.72, 23.88),
            'longitude' => $this->faker->longitude(90.35, 90.44),
            'phone' => '017' . $this->faker->numerify('########'),
            'email' => $this->faker->safeEmail(),
            'rating' => $this->faker->randomFloat(2, 3.8, 5.0),
            'review_count' => $this->faker->numberBetween(5, 120),
            'price_level' => $this->faker->numberBetween(1, 3),
            'inspection_fee' => 250.00,
            'base_labor_fee' => 500.00,
            'eta_minutes' => $this->faker->randomElement([35, 45, 60, 90]),
            'has_voucher' => $this->faker->boolean(40),
            'free_delivery' => $this->faker->boolean(40),
            'has_deals' => $this->faker->boolean(40),
            'is_featured' => $this->faker->boolean(30),
            'is_active' => true,
            'is_public' => true,
            'accepts_direct_orders' => true,
            'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR'],
            'specialties' => ['Screen Replacement', 'Battery Service', 'Board Repair'],
        ];
    }
}
