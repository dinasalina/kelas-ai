<?php

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $this->get(route('staff.coupons.index'))->assertRedirect(route('login'));
});

test('staff can view the coupons page', function () {
    $staff = User::factory()->staff()->create();
    Coupon::factory()->create();

    $this->actingAs($staff)
        ->get(route('staff.coupons.index'))
        ->assertOk()
        ->assertSee('Kupon');
});

test('staff can create a percentage coupon', function () {
    $staff = User::factory()->staff()->create();

    Livewire::actingAs($staff)
        ->test('staff.coupon-form-modal')
        ->call('open')
        ->set('code', 'save10')
        ->set('type', CouponType::Percentage)
        ->set('value', '10')
        ->call('save')
        ->assertHasNoErrors();

    $coupon = Coupon::where('code', 'SAVE10')->firstOrFail();

    expect($coupon->type)->toBe(CouponType::Percentage);
    expect((float) $coupon->value)->toBe(10.0);
    expect($coupon->is_active)->toBeTrue();
});

test('staff can create a fixed coupon with min order amount and expiry', function () {
    $staff = User::factory()->staff()->create();

    Livewire::actingAs($staff)
        ->test('staff.coupon-form-modal')
        ->call('open')
        ->set('code', 'JIMAT5')
        ->set('type', CouponType::Fixed)
        ->set('value', '5')
        ->set('minOrderAmount', '20')
        ->set('expiresAt', now()->addMonth()->format('Y-m-d'))
        ->call('save')
        ->assertHasNoErrors();

    $coupon = Coupon::where('code', 'JIMAT5')->firstOrFail();

    expect($coupon->type)->toBe(CouponType::Fixed);
    expect((float) $coupon->min_order_amount)->toBe(20.0);
    expect($coupon->expires_at)->not->toBeNull();
});

test('coupon creation requires a unique code', function () {
    $staff = User::factory()->staff()->create();
    Coupon::factory()->create(['code' => 'DUPLICATE']);

    Livewire::actingAs($staff)
        ->test('staff.coupon-form-modal')
        ->call('open')
        ->set('code', 'duplicate')
        ->set('type', CouponType::Percentage)
        ->set('value', '10')
        ->call('save')
        ->assertHasErrors(['code']);
});

test('coupon creation requires required fields', function () {
    $staff = User::factory()->staff()->create();

    Livewire::actingAs($staff)
        ->test('staff.coupon-form-modal')
        ->call('open')
        ->set('code', '')
        ->set('value', '')
        ->call('save')
        ->assertHasErrors(['code', 'value']);
});

test('percentage coupon value cannot exceed 100', function () {
    $staff = User::factory()->staff()->create();

    Livewire::actingAs($staff)
        ->test('staff.coupon-form-modal')
        ->call('open')
        ->set('code', 'TOOBIG')
        ->set('type', CouponType::Percentage)
        ->set('value', '150')
        ->call('save')
        ->assertHasErrors(['value']);
});

test('staff can edit a coupon', function () {
    $staff = User::factory()->staff()->create();
    $coupon = Coupon::factory()->create(['code' => 'OLDCODE', 'value' => 10]);

    Livewire::actingAs($staff)
        ->test('staff.coupon-form-modal')
        ->call('open', $coupon->id)
        ->set('value', '15')
        ->call('save')
        ->assertHasNoErrors();

    expect((float) $coupon->fresh()->value)->toBe(15.0);
});

test('staff can delete a coupon with no orders', function () {
    $staff = User::factory()->staff()->create();
    $coupon = Coupon::factory()->create();

    Livewire::actingAs($staff)
        ->test('pages::staff.coupons.index')
        ->call('delete', $coupon->id);

    expect(Coupon::find($coupon->id))->toBeNull();
});

test('deleting a coupon with existing orders is blocked', function () {
    $staff = User::factory()->staff()->create();
    $coupon = Coupon::factory()->create();
    Order::factory()->create(['coupon_id' => $coupon->id]);

    Livewire::actingAs($staff)
        ->test('pages::staff.coupons.index')
        ->call('delete', $coupon->id)
        ->assertForbidden();

    expect(Coupon::find($coupon->id))->not->toBeNull();
});
