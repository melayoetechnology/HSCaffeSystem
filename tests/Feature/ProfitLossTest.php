<?php

use App\Enums\ExpenseCategory;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;

test('owner can access profit loss page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('profit-loss.index'));
    $response->assertOk();
});

test('manager can access profit loss page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->manager($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('profit-loss.index'));
    $response->assertOk();
});

test('cashier cannot access profit loss page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->cashier($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('profit-loss.index'));
    $response->assertForbidden();
});

test('profit loss page shows income and expense data', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::Completed,
        'payment_status' => PaymentStatus::Paid,
        'total' => 100000,
        'subtotal' => 90000,
        'tax_amount' => 10000,
    ]);

    Expense::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'category' => ExpenseCategory::Utilities->value,
        'amount' => 30000,
        'expense_date' => now()->format('Y-m-d'),
    ]);

    $response = $this->get(route('profit-loss.index'));
    $response->assertOk();
    $response->assertSee('Total Pendapatan');
    $response->assertSee('Total Pengeluaran');
    $response->assertSee('Laba / Rugi Bersih');
});

test('profit loss page displays income and expense amounts', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::Completed,
        'payment_status' => PaymentStatus::Paid,
        'total' => 200000,
        'subtotal' => 180000,
        'tax_amount' => 20000,
        'service_charge' => 0,
        'discount_amount' => 0,
    ]);

    Expense::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'category' => ExpenseCategory::Utilities->value,
        'amount' => 50000,
        'expense_date' => now()->format('Y-m-d'),
    ]);

    $response = $this->get(route('profit-loss.index'));
    $response->assertOk();

    // Verify income is displayed (Rp 200.000)
    $response->assertSee('Rp 200.000');
    // Verify the breakdown sections are visible
    $response->assertSee('Rincian Pendapatan');
    $response->assertSee('Rincian Pengeluaran');
});
