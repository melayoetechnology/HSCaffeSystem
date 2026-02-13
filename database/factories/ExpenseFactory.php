<?php

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        $category = fake()->randomElement(ExpenseCategory::cases());

        return [
            'tenant_id' => Tenant::factory(),
            'category' => $category->value,
            'description' => fake()->sentence(3),
            'amount' => fake()->randomElement([50000, 100000, 250000, 500000, 1000000]),
            'expense_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'reference' => fake()->optional(0.5)->numerify('INV-####'),
            'notes' => fake()->optional(0.3)->sentence(),
            'user_id' => User::factory(),
            'stock_movement_id' => null,
        ];
    }

    public function stockPurchase(): static
    {
        return $this->state(fn () => [
            'category' => ExpenseCategory::StockPurchase->value,
        ]);
    }
}
