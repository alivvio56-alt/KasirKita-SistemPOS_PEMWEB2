<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('PRD-####')),
            'name' => ucwords(fake()->words(2, true)),
            'description' => fake()->sentence(),
            'price' => fake()->randomElement([8000, 12000, 15000, 18000, 22000, 25000, 35000]),
            'stock' => fake()->numberBetween(10, 60),
            'min_stock' => 5,
            'unit' => 'pcs',
            'is_active' => true,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }
}
