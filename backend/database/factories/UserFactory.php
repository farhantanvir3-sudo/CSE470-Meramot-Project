<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        $name = $this->faker->name();
        return [
            'username' => Str::slug($name) . rand(100, 9999),
            'name' => $name,
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password123'),
            'phone' => '017' . $this->faker->numerify('########'),
            'address' => $this->faker->streetAddress(),
            'city' => 'Dhaka',
            'area' => $this->faker->randomElement(['Banani', 'Gulshan', 'Dhanmondi', 'Uttara', 'Mirpur']),
            'role' => 'customer',
            'is_email_verified' => true,
            'is_phone_verified' => true,
            'is_active' => true,
        ];
    }
}
