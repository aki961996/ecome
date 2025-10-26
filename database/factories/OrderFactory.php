<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

         $statuses = ['pending', 'processing', 'completed', 'cancelled', 'failed'];
        return [
           'user_id' => User::factory(),
            'order_number' => 'ORD-' . date('Ymd') . '-' . strtoupper($this->faker->bothify('??##')),
            'total_amount' => $this->faker->randomFloat(2, 20, 500),
            'status' => $this->faker->randomElement($statuses),
            'processed_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 year', 'now'),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
