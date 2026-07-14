<?php

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    RateLimiter::clear('place-order:127.0.0.1');
});

test('a guest can place an order for an available product', function () {
    $product = Product::factory()->create(['stock' => 10, 'price' => 25]);

    Livewire::test('storefront.order-form-modal')
        ->call('open', $product->id)
        ->set('customerName', 'Ali')
        ->set('customerPhone', '012-3456789')
        ->set('customerAddress', '123 Jalan Test')
        ->set('quantity', 3)
        ->call('placeOrder')
        ->assertHasNoErrors();

    $order = Order::firstOrFail();

    expect($order->product_id)->toBe($product->id);
    expect($order->quantity)->toBe(3);
    expect((float) $order->unit_price)->toBe(25.0);
    expect((float) $order->total_price)->toBe(75.0);
    expect($order->status)->toBe(OrderStatus::Pending);
    expect($order->placed_by_staff_id)->toBeNull();
    expect($order->order_number)->toStartWith('ORD-');
    expect($order->statusHistories()->count())->toBe(1);
    expect($product->fresh()->stock)->toBe(7);
});

test('placing an order requires customer details', function () {
    $product = Product::factory()->create(['stock' => 10]);

    Livewire::test('storefront.order-form-modal')
        ->call('open', $product->id)
        ->set('customerName', '')
        ->set('customerPhone', '')
        ->set('customerAddress', '')
        ->call('placeOrder')
        ->assertHasErrors(['customerName', 'customerPhone', 'customerAddress']);

    expect(Order::count())->toBe(0);
});

test('ordering more than available stock is rejected', function () {
    $product = Product::factory()->create(['stock' => 2]);

    Livewire::test('storefront.order-form-modal')
        ->call('open', $product->id)
        ->set('customerName', 'Ali')
        ->set('customerPhone', '012-3456789')
        ->set('customerAddress', '123 Jalan Test')
        ->set('quantity', 5)
        ->call('placeOrder')
        ->assertHasErrors('quantity');

    expect(Order::count())->toBe(0);
    expect($product->fresh()->stock)->toBe(2);
});

test('ordering an inactive product is rejected', function () {
    $product = Product::factory()->inactive()->create(['stock' => 10]);

    Livewire::test('storefront.order-form-modal')
        ->call('open', $product->id)
        ->set('customerName', 'Ali')
        ->set('customerPhone', '012-3456789')
        ->set('customerAddress', '123 Jalan Test')
        ->set('quantity', 1)
        ->call('placeOrder')
        ->assertHasErrors('quantity');

    expect(Order::count())->toBe(0);
});

test('a valid percentage coupon reduces the order total', function () {
    $product = Product::factory()->create(['stock' => 10, 'price' => 100]);
    $coupon = Coupon::factory()->create(['code' => 'SAVE10', 'type' => CouponType::Percentage, 'value' => 10]);

    Livewire::test('storefront.order-form-modal')
        ->call('open', $product->id)
        ->set('customerName', 'Ali')
        ->set('customerPhone', '012-3456789')
        ->set('customerAddress', '123 Jalan Test')
        ->set('quantity', 2)
        ->set('couponCode', 'save10')
        ->call('placeOrder')
        ->assertHasNoErrors();

    $order = Order::firstOrFail();

    expect($order->coupon_id)->toBe($coupon->id);
    expect((float) $order->discount_amount)->toBe(20.0);
    expect((float) $order->total_price)->toBe(180.0);
});

test('a valid fixed coupon reduces the order total', function () {
    $product = Product::factory()->create(['stock' => 10, 'price' => 50]);
    $coupon = Coupon::factory()->fixed()->create(['code' => 'JIMAT5', 'value' => 5]);

    Livewire::test('storefront.order-form-modal')
        ->call('open', $product->id)
        ->set('customerName', 'Ali')
        ->set('customerPhone', '012-3456789')
        ->set('customerAddress', '123 Jalan Test')
        ->set('quantity', 1)
        ->set('couponCode', 'JIMAT5')
        ->call('placeOrder')
        ->assertHasNoErrors();

    $order = Order::firstOrFail();

    expect($order->coupon_id)->toBe($coupon->id);
    expect((float) $order->discount_amount)->toBe(5.0);
    expect((float) $order->total_price)->toBe(45.0);
});

test('an unknown coupon code is rejected', function () {
    $product = Product::factory()->create(['stock' => 10, 'price' => 25]);

    Livewire::test('storefront.order-form-modal')
        ->call('open', $product->id)
        ->set('customerName', 'Ali')
        ->set('customerPhone', '012-3456789')
        ->set('customerAddress', '123 Jalan Test')
        ->set('quantity', 1)
        ->set('couponCode', 'NOPE')
        ->call('placeOrder')
        ->assertHasErrors('couponCode');

    expect(Order::count())->toBe(0);
});

test('an expired coupon is rejected', function () {
    $product = Product::factory()->create(['stock' => 10, 'price' => 25]);
    Coupon::factory()->expired()->create(['code' => 'EXPIRED']);

    Livewire::test('storefront.order-form-modal')
        ->call('open', $product->id)
        ->set('customerName', 'Ali')
        ->set('customerPhone', '012-3456789')
        ->set('customerAddress', '123 Jalan Test')
        ->set('quantity', 1)
        ->set('couponCode', 'EXPIRED')
        ->call('placeOrder')
        ->assertHasErrors('couponCode');

    expect(Order::count())->toBe(0);
});

test('a coupon below its minimum order amount is rejected', function () {
    $product = Product::factory()->create(['stock' => 10, 'price' => 10]);
    Coupon::factory()->create(['code' => 'MIN50', 'min_order_amount' => 50]);

    Livewire::test('storefront.order-form-modal')
        ->call('open', $product->id)
        ->set('customerName', 'Ali')
        ->set('customerPhone', '012-3456789')
        ->set('customerAddress', '123 Jalan Test')
        ->set('quantity', 1)
        ->set('couponCode', 'MIN50')
        ->call('placeOrder')
        ->assertHasErrors('couponCode');

    expect(Order::count())->toBe(0);
});

test('an order with a delivery zone adds the fee to the total', function () {
    $product = Product::factory()->create(['stock' => 10, 'price' => 20]);
    $zone = DeliveryZone::factory()->create(['fee' => 5]);

    Livewire::test('storefront.order-form-modal')
        ->call('open', $product->id)
        ->set('customerName', 'Ali')
        ->set('customerPhone', '012-3456789')
        ->set('customerAddress', '123 Jalan Test')
        ->set('quantity', 2)
        ->set('deliveryZoneId', (string) $zone->id)
        ->call('placeOrder')
        ->assertHasNoErrors();

    $order = Order::firstOrFail();

    expect($order->delivery_zone_id)->toBe($zone->id);
    expect((float) $order->delivery_fee)->toBe(5.0);
    expect((float) $order->total_price)->toBe(45.0);
});

test('self-pickup orders have no delivery fee', function () {
    $product = Product::factory()->create(['stock' => 10, 'price' => 20]);
    DeliveryZone::factory()->create(['fee' => 5]);

    Livewire::test('storefront.order-form-modal')
        ->call('open', $product->id)
        ->set('customerName', 'Ali')
        ->set('customerPhone', '012-3456789')
        ->set('customerAddress', 'Ambil di kedai')
        ->set('quantity', 1)
        ->set('deliveryZoneId', '')
        ->call('placeOrder')
        ->assertHasNoErrors();

    $order = Order::firstOrFail();

    expect($order->delivery_zone_id)->toBeNull();
    expect((float) $order->delivery_fee)->toBe(0.0);
    expect((float) $order->total_price)->toBe(20.0);
});

test('an inactive delivery zone is rejected', function () {
    $product = Product::factory()->create(['stock' => 10, 'price' => 20]);
    $zone = DeliveryZone::factory()->inactive()->create(['fee' => 5]);

    Livewire::test('storefront.order-form-modal')
        ->call('open', $product->id)
        ->set('customerName', 'Ali')
        ->set('customerPhone', '012-3456789')
        ->set('customerAddress', '123 Jalan Test')
        ->set('quantity', 1)
        ->set('deliveryZoneId', (string) $zone->id)
        ->call('placeOrder')
        ->assertHasErrors('deliveryZoneId');

    expect(Order::count())->toBe(0);
});

test('guest ordering is rate limited after five orders', function () {
    $product = Product::factory()->create(['stock' => 100, 'price' => 10]);

    for ($i = 1; $i <= 5; $i++) {
        Livewire::test('storefront.order-form-modal')
            ->call('open', $product->id)
            ->set('customerName', "Pelanggan {$i}")
            ->set('customerPhone', '012-3456789')
            ->set('customerAddress', '123 Jalan Test')
            ->set('quantity', 1)
            ->call('placeOrder')
            ->assertHasNoErrors();
    }

    Livewire::test('storefront.order-form-modal')
        ->call('open', $product->id)
        ->set('customerName', 'Pelanggan 6')
        ->set('customerPhone', '012-3456789')
        ->set('customerAddress', '123 Jalan Test')
        ->set('quantity', 1)
        ->call('placeOrder')
        ->assertHasErrors('customerName');

    expect(Order::count())->toBe(5);
});
