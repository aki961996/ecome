<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

         $productNames = [
            'Smartphone', 'Laptop', 'T-Shirt', 'Jeans', 'Novel', 
            'Textbook', 'Garden Tool', 'Football', 'Basketball', 'Headphones'
        ];

        return [
            'name' => $this->faker->randomElement($productNames) . ' ' . $this->faker->word(),
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'stock_quantity' => $this->faker->numberBetween(0, 100),
            'category_id' => Category::factory(),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
