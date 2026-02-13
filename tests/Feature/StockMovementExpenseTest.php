<?php

use App\Enums\ExpenseCategory;
use App\Enums\StockMovementType;
use App\Models\Expense;
use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;

test('stock movement in with cost creates expense automatically', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $ingredient = Ingredient::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Kopi Arabica',
    ]);

    $movement = StockMovement::create([
        'tenant_id' => $tenant->id,
        'ingredient_id' => $ingredient->id,
        'type' => StockMovementType::In->value,
        'quantity' => 10,
        'cost_per_unit' => 50000,
        'reference' => 'PO-001',
        'notes' => 'Pembelian kopi',
        'user_id' => $user->id,
    ]);

    $expense = Expense::where('stock_movement_id', $movement->id)->first();

    expect($expense)->not->toBeNull();
    expect($expense->category->value)->toBe(ExpenseCategory::StockPurchase->value);
    expect((float) $expense->amount)->toBe(500000.0);
    expect($expense->description)->toContain('Kopi Arabica');
    expect($expense->reference)->toBe('PO-001');
});

test('stock movement in without cost does not create expense', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $ingredient = Ingredient::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    StockMovement::create([
        'tenant_id' => $tenant->id,
        'ingredient_id' => $ingredient->id,
        'type' => StockMovementType::In->value,
        'quantity' => 10,
        'cost_per_unit' => null,
        'user_id' => $user->id,
    ]);

    expect(Expense::count())->toBe(0);
});

test('stock movement out does not create expense', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $ingredient = Ingredient::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    StockMovement::create([
        'tenant_id' => $tenant->id,
        'ingredient_id' => $ingredient->id,
        'type' => StockMovementType::Out->value,
        'quantity' => 5,
        'cost_per_unit' => 50000,
        'user_id' => $user->id,
    ]);

    expect(Expense::count())->toBe(0);
});
