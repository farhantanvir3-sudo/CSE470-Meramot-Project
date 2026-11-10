<?php

namespace Database\Factories;

use App\Models\RepairShop;
use App\Models\ShopReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShopReviewFactory extends Factory
{
    protected $model = ShopReview::class;

    public function definition(): array
    {
        return [
            'repair_shop_id' => RepairShop::factory(),
            'customer_id' => User::factory(),
            'rating' => $this->faker->numberBetween(3, 5),
            'review_comment' => $this->faker->sentence(14),
            'is_verified_purchase' => true,
            'is_hidden' => false,
        ];
    }
}
