<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

test('the tracking page renders', function () {
    $this->get(route('track'))
        ->assertOk()
        ->assertSee('Jejak Pesanan');
});

test('a customer can track an order with the correct order number and phone', function () {
    $order = Order::factory()->create(['customer_phone' => '012-3456789']);

    Livewire::test('pages::storefront.track')
        ->set('orderNumber', strtolower($order->order_number))
        ->set('phone', '0123456789')
        ->call('track')
        ->assertHasNoErrors()
        ->assertRedirect(URL::signedRoute('receipt.show', $order));
});

test('tracking with the wrong phone number is rejected', function () {
    $order = Order::factory()->create(['customer_phone' => '012-3456789']);

    Livewire::test('pages::storefront.track')
        ->set('orderNumber', $order->order_number)
        ->set('phone', '019-9999999')
        ->call('track')
        ->assertHasErrors('orderNumber');
});

test('tracking an unknown order number is rejected', function () {
    Livewire::test('pages::storefront.track')
        ->set('orderNumber', 'ORD-99999999-XXXX')
        ->set('phone', '012-3456789')
        ->call('track')
        ->assertHasErrors('orderNumber');
});

test('the receipt shows the tracking timeline and order number', function () {
    $staff = User::factory()->staff()->create();
    $order = Order::factory()->preparing()->create();

    $order->statusHistories()->create(['from_status' => null, 'to_status' => 'pending']);
    $order->statusHistories()->create(['from_status' => 'pending', 'to_status' => 'confirmed', 'changed_by_staff_id' => $staff->id]);
    $order->statusHistories()->create(['from_status' => 'confirmed', 'to_status' => 'preparing', 'note' => 'Sedang dimasak']);

    $this->get(URL::signedRoute('receipt.show', $order))
        ->assertOk()
        ->assertSee('Status Pesanan')
        ->assertSee($order->order_number)
        ->assertSee('Sedang Disediakan')
        ->assertSee('Sedang dimasak');
});

test('a cancelled order shows its cancellation history on the receipt', function () {
    $order = Order::factory()->cancelled()->create();

    $order->statusHistories()->create(['from_status' => null, 'to_status' => 'pending']);
    $order->statusHistories()->create(['from_status' => 'pending', 'to_status' => 'cancelled', 'note' => 'Stok bermasalah']);

    $this->get(URL::signedRoute('receipt.show', $order))
        ->assertOk()
        ->assertSee('Dibatalkan')
        ->assertSee('Stok bermasalah');
});
