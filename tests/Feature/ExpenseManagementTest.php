<?php

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

test('owner can access expenses page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('expenses.index'));
    $response->assertOk();
});

test('manager can access expenses page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->manager($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('expenses.index'));
    $response->assertOk();
});

test('cashier cannot access expenses page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->cashier($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('expenses.index'));
    $response->assertForbidden();
});

test('owner can create an expense', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    Livewire::test('pages::expenses.index')
        ->set('category', ExpenseCategory::Utilities->value)
        ->set('description', 'Tagihan listrik')
        ->set('amount', '500000')
        ->set('expenseDate', now()->format('Y-m-d'))
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('expenses', [
        'tenant_id' => $tenant->id,
        'category' => ExpenseCategory::Utilities->value,
        'description' => 'Tagihan listrik',
        'amount' => 500000,
    ]);
});

test('owner can edit an expense', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $expense = Expense::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'description' => 'Old Description',
    ]);

    Livewire::test('pages::expenses.index')
        ->call('edit', $expense->id)
        ->set('description', 'New Description')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('expenses', [
        'id' => $expense->id,
        'description' => 'New Description',
    ]);
});

test('owner can delete an expense', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $expense = Expense::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
    ]);

    Livewire::test('pages::expenses.index')
        ->call('delete', $expense->id);

    $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
});

test('cannot edit stock-linked expense', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $ingredient = \App\Models\Ingredient::factory()->create(['tenant_id' => $tenant->id]);
    $movement = \App\Models\StockMovement::factory()->create([
        'tenant_id' => $tenant->id,
        'ingredient_id' => $ingredient->id,
        'user_id' => $user->id,
    ]);

    $expense = Expense::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'stock_movement_id' => $movement->id,
    ]);

    Livewire::test('pages::expenses.index')
        ->call('edit', $expense->id)
        ->assertSet('showForm', false);
});

test('cannot delete stock-linked expense', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $ingredient = \App\Models\Ingredient::factory()->create(['tenant_id' => $tenant->id]);
    $movement = \App\Models\StockMovement::factory()->create([
        'tenant_id' => $tenant->id,
        'ingredient_id' => $ingredient->id,
        'user_id' => $user->id,
    ]);

    $expense = Expense::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'stock_movement_id' => $movement->id,
    ]);

    Livewire::test('pages::expenses.index')
        ->call('delete', $expense->id);

    $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
});

test('expense validation requires category and description', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    Livewire::test('pages::expenses.index')
        ->set('category', '')
        ->set('description', '')
        ->set('amount', '')
        ->call('save')
        ->assertHasErrors(['category', 'description', 'amount']);
});
