<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

test('kitchen staff can access kitchen display', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->kitchen($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('kitchen.index'));
    $response->assertOk();
});

test('cashier cannot access kitchen display', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->cashier($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('kitchen.index'));
    $response->assertForbidden();
});

test('owner can access kitchen display', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner($tenant)->create();
    $this->actingAs($user);

    $response = $this->get(route('kitchen.index'));
    $response->assertOk();
});

test('kitchen display does not show unconfirmed pending orders', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->kitchen($tenant)->create();
    $this->actingAs($user);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::Pending,
    ]);

    Livewire::test('pages::kitchen.index')
        ->assertDontSee($order->order_number);
});

test('kitchen display shows confirmed orders', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->kitchen($tenant)->create();
    $this->actingAs($user);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::Confirmed,
    ]);

    Livewire::test('pages::kitchen.index')
        ->assertSee($order->order_number);
});

test('full order flow through kitchen display', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->kitchen($tenant)->create();
    $this->actingAs($user);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::Confirmed,
    ]);

    $component = Livewire::test('pages::kitchen.index');

    $component->call('startPreparing', $order->id);
    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);

    $component->call('markReady', $order->id);
    expect($order->fresh()->status)->toBe(OrderStatus::Ready);

    $component->call('markServed', $order->id);
    expect($order->fresh()->status)->toBe(OrderStatus::Served);
});
