<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $this->get(route('staff.orders.index'))->assertRedirect(route('login'));
});

test('staff can view the orders page', function () {
    $staff = User::factory()->staff()->create();
    Order::factory()->create();

    $this->actingAs($staff)
        ->get(route('staff.orders.index'))
        ->assertOk()
        ->assertSee('Pesanan');
});

test('staff can advance an order through the full lifecycle in order', function () {
    $staff = User::factory()->staff()->create();
    $order = Order::factory()->create();

    $component = Livewire::actingAs($staff)->test('pages::staff.orders.index');

    foreach ([OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Delivering, OrderStatus::Completed] as $expected) {
        $component->call('advanceStatus', $order->id);
        expect($order->fresh()->status)->toBe($expected);
    }

    expect($order->fresh()->processed_by_staff_id)->toBe($staff->id);

    $component->call('advanceStatus', $order->id);
    expect($order->fresh()->status)->toBe(OrderStatus::Completed);
});

test('each status change is recorded in the order history', function () {
    $staff = User::factory()->staff()->create();
    $order = Order::factory()->create();

    Livewire::actingAs($staff)
        ->test('pages::staff.orders.index')
        ->call('advanceStatus', $order->id);

    $history = $order->statusHistories()->latest('id')->first();

    expect($history->from_status)->toBe(OrderStatus::Pending);
    expect($history->to_status)->toBe(OrderStatus::Confirmed);
    expect($history->changed_by_staff_id)->toBe($staff->id);
});

test('dragging a kanban card forward updates the status', function () {
    $staff = User::factory()->staff()->create();
    $order = Order::factory()->create();

    Livewire::actingAs($staff)
        ->test('pages::staff.orders.index')
        ->call('handleSort', $order->id, 0, OrderStatus::Preparing->value);

    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);
});

test('dragging a kanban card backward is rejected', function () {
    $staff = User::factory()->staff()->create();
    $order = Order::factory()->preparing()->create();

    Livewire::actingAs($staff)
        ->test('pages::staff.orders.index')
        ->call('handleSort', $order->id, 0, OrderStatus::Pending->value);

    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);
});

test('cancelling an order returns its quantity to product stock and records the note', function () {
    $staff = User::factory()->staff()->create();
    $product = Product::factory()->create(['stock' => 5]);
    $order = Order::factory()->for($product)->create(['quantity' => 3]);

    Livewire::actingAs($staff)
        ->test('staff.order-timeline-modal')
        ->call('open', $order->id)
        ->set('targetStatus', OrderStatus::Cancelled->value)
        ->set('note', 'Pelanggan batalkan')
        ->call('updateStatus')
        ->assertHasNoErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
    expect($product->fresh()->stock)->toBe(8);

    $history = $order->statusHistories()->latest('id')->first();
    expect($history->to_status)->toBe(OrderStatus::Cancelled);
    expect($history->note)->toBe('Pelanggan batalkan');
});

test('a completed order cannot be cancelled', function () {
    $staff = User::factory()->staff()->create();
    $product = Product::factory()->create(['stock' => 5]);
    $order = Order::factory()->for($product)->completed()->create(['quantity' => 2]);

    Livewire::actingAs($staff)
        ->test('staff.order-timeline-modal')
        ->call('open', $order->id)
        ->set('targetStatus', OrderStatus::Cancelled->value)
        ->call('updateStatus')
        ->assertHasErrors('targetStatus');

    expect($order->fresh()->status)->toBe(OrderStatus::Completed);
    expect($product->fresh()->stock)->toBe(5);
});

test('staff can create a manual order on behalf of a customer', function () {
    $staff = User::factory()->staff()->create();
    $product = Product::factory()->create(['stock' => 10]);

    Livewire::actingAs($staff)
        ->test('staff.order-form-modal')
        ->call('open')
        ->set('productId', $product->id)
        ->set('customerName', 'Walk-in Customer')
        ->set('customerPhone', '019-8887777')
        ->set('customerAddress', 'Counter')
        ->set('quantity', 2)
        ->call('placeOrder')
        ->assertHasNoErrors();

    $order = Order::firstOrFail();

    expect($order->placed_by_staff_id)->toBe($staff->id);
    expect($order->quantity)->toBe(2);
    expect($order->order_number)->not->toBeNull();
    expect($product->fresh()->stock)->toBe(8);
});
