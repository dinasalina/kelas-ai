<?php

use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('place-order:127.0.0.1');
});

test('customer buys with a coupon and staff processes the order through to completion', function () {
    $product = Product::factory()->create([
        'name' => 'Nasi Lemak Ayam Goreng',
        'price' => 20,
        'stock' => 10,
    ]);

    $coupon = Coupon::factory()->create([
        'code' => 'SAVE10',
        'value' => 10,
    ]);

    $zone = DeliveryZone::factory()->create([
        'name' => 'Dalam Bandar',
        'fee' => 3,
    ]);

    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
    ]);

    // --- Customer places an order using the coupon ---
    $page = visit('/');

    $page->assertSee('Nasi Lemak Ayam Goreng')
        ->click('Tempah')
        ->fill('customerName', 'Ali bin Ahmad')
        ->fill('customerPhone', '019-8887777')
        ->fill('customerAddress', 'No 5, Jalan Dagang, Shah Alam')
        ->select('deliveryZoneId', (string) $zone->id)
        ->fill('couponCode', 'SAVE10')
        ->click('Tempah Sekarang')
        ->assertPathBeginsWith('/resit/')
        ->assertSee('Diskaun (SAVE10)')
        ->assertSee('RM 21.00')
        ->assertNoJavascriptErrors();

    $order = Order::firstOrFail();

    expect($order->coupon_id)->toBe($coupon->id);
    expect($order->delivery_zone_id)->toBe($zone->id);
    expect((float) $order->discount_amount)->toBe(2.0);
    expect((float) $order->total_price)->toBe(21.0);
    expect($order->status)->toBe(OrderStatus::Pending);

    // --- Staff logs in and processes the order to completion ---
    $page = visit('/login');

    $page->fill('email', 'admin@example.com')
        ->fill('password', 'password')
        ->click('@login-button')
        ->assertPathIs('/dashboard');

    $page->navigate('/staff/orders?view=table')
        ->assertSee($order->order_number)
        ->click('Disahkan →')
        ->click('Sedang Disediakan →')
        ->click('Dalam Penghantaran →')
        ->click('Selesai →')
        ->assertSee('Selesai');

    expect($order->fresh()->status)->toBe(OrderStatus::Completed);

    // --- Dashboard reflects the newly completed order ---
    $page->navigate('/dashboard')
        ->assertSee('RM 21.00')
        ->assertSee('1 pesanan selesai')
        ->assertSee($order->order_number);

    // --- Coupon usage count increased ---
    $page->navigate('/staff/coupons')
        ->assertSee('SAVE10')
        ->assertSee('1 kali');
});
