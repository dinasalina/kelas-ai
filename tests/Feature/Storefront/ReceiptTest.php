<?php

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

test('a receipt is viewable by guests with a valid signed url', function () {
    $order = Order::factory()->create(['customer_name' => 'Ali bin Abu']);

    $this->get(URL::signedRoute('receipt.show', $order))
        ->assertOk()
        ->assertSee('Resit Tempahan')
        ->assertSee('Ali bin Abu')
        ->assertSee($order->product->name)
        ->assertSee($order->order_number);
});

test('a receipt is not viewable by guests without a valid signature', function () {
    $order = Order::factory()->create();

    $this->get(route('receipt.show', $order))->assertForbidden();

    $this->get(URL::signedRoute('receipt.show', $order).'tampered')->assertForbidden();
});

test('staff can view a receipt without a signature', function () {
    $staff = User::factory()->staff()->create();
    $order = Order::factory()->create();

    $this->actingAs($staff)
        ->get(route('receipt.show', $order))
        ->assertOk()
        ->assertSee('Resit Tempahan');
});

test('the receipt shows the coupon discount when one was applied', function () {
    $product = Product::factory()->create(['price' => 100, 'stock' => 10]);
    $coupon = Coupon::factory()->create(['code' => 'DISKAUN10', 'type' => CouponType::Percentage, 'value' => 10]);
    $staff = User::factory()->staff()->create();

    $order = Order::factory()->for($product)->create([
        'coupon_id' => $coupon->id,
        'quantity' => 1,
        'unit_price' => 100,
        'discount_amount' => 10,
        'total_price' => 90,
    ]);

    $this->actingAs($staff)
        ->get(route('receipt.show', $order))
        ->assertOk()
        ->assertSee('Diskaun')
        ->assertSee('DISKAUN10');
});

test('a guest is redirected to the signed receipt after placing an order', function () {
    $product = Product::factory()->create(['stock' => 10, 'price' => 25]);

    $component = Livewire::test('storefront.order-form-modal')
        ->call('open', $product->id)
        ->set('customerName', 'Ali')
        ->set('customerPhone', '012-3456789')
        ->set('customerAddress', '123 Jalan Test')
        ->set('quantity', 1)
        ->call('placeOrder')
        ->assertHasNoErrors();

    $order = Order::firstOrFail();

    $component->assertRedirect(URL::signedRoute('receipt.show', $order));

    $this->get(URL::signedRoute('receipt.show', $order))->assertOk();
});
