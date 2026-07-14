<?php

use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $this->get(route('staff.delivery-zones.index'))->assertRedirect(route('login'));
});

test('staff can view the delivery zones page', function () {
    $staff = User::factory()->staff()->create();
    DeliveryZone::factory()->create(['name' => 'Dalam Bandar']);

    $this->actingAs($staff)
        ->get(route('staff.delivery-zones.index'))
        ->assertOk()
        ->assertSee('Kawasan Penghantaran')
        ->assertSee('Dalam Bandar');
});

test('staff can create a delivery zone', function () {
    $staff = User::factory()->staff()->create();

    Livewire::actingAs($staff)
        ->test('staff.delivery-zone-form-modal')
        ->call('open')
        ->set('name', 'Pinggir Bandar')
        ->set('fee', '5.50')
        ->call('save')
        ->assertHasNoErrors();

    $zone = DeliveryZone::where('name', 'Pinggir Bandar')->firstOrFail();

    expect((float) $zone->fee)->toBe(5.5);
    expect($zone->is_active)->toBeTrue();
});

test('staff can edit a delivery zone', function () {
    $staff = User::factory()->staff()->create();
    $zone = DeliveryZone::factory()->create(['fee' => 3]);

    Livewire::actingAs($staff)
        ->test('staff.delivery-zone-form-modal')
        ->call('open', $zone->id)
        ->set('fee', '7.00')
        ->set('isActive', false)
        ->call('save')
        ->assertHasNoErrors();

    expect((float) $zone->fresh()->fee)->toBe(7.0);
    expect($zone->fresh()->is_active)->toBeFalse();
});

test('staff can delete a delivery zone with no orders', function () {
    $staff = User::factory()->staff()->create();
    $zone = DeliveryZone::factory()->create();

    Livewire::actingAs($staff)
        ->test('pages::staff.delivery-zones.index')
        ->call('delete', $zone->id);

    expect(DeliveryZone::find($zone->id))->toBeNull();
});

test('deleting a delivery zone with existing orders is blocked', function () {
    $staff = User::factory()->staff()->create();
    $zone = DeliveryZone::factory()->create();
    Order::factory()->create(['delivery_zone_id' => $zone->id]);

    Livewire::actingAs($staff)
        ->test('pages::staff.delivery-zones.index')
        ->call('delete', $zone->id)
        ->assertForbidden();

    expect(DeliveryZone::find($zone->id))->not->toBeNull();
});
