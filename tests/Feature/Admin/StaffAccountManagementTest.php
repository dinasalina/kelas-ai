<?php

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $this->get(route('admin.staff.index'))->assertRedirect(route('login'));
});

test('staff cannot access the staff accounts page', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('admin.staff.index'))
        ->assertForbidden();
});

test('admin can view the staff accounts page', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->staff()->create();

    $this->actingAs($admin)
        ->get(route('admin.staff.index'))
        ->assertOk()
        ->assertSee('Staff Accounts');
});

test('admin can create a new staff account', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('admin.staff-user-form-modal')
        ->call('open')
        ->set('name', 'New Staff')
        ->set('email', 'new-staff@example.com')
        ->set('role', UserRole::Staff->value)
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('save')
        ->assertHasNoErrors();

    $newUser = User::where('email', 'new-staff@example.com')->firstOrFail();

    expect($newUser->role)->toBe(UserRole::Staff);
    expect($newUser->is_active)->toBeTrue();
});

test('admin can edit an existing staff account', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create(['name' => 'Old Name']);

    Livewire::actingAs($admin)
        ->test('admin.staff-user-form-modal')
        ->call('open', $staff->id)
        ->set('name', 'Updated Name')
        ->set('role', UserRole::Admin->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($staff->fresh()->name)->toBe('Updated Name');
    expect($staff->fresh()->role)->toBe(UserRole::Admin);
});

test('staff cannot open the staff account form', function () {
    $staff = User::factory()->staff()->create();

    Livewire::actingAs($staff)
        ->test('admin.staff-user-form-modal')
        ->call('open')
        ->assertForbidden();
});

test('admin can deactivate a staff account and it can no longer log in', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.staff.index')
        ->call('toggleActive', $staff->id);

    expect($staff->fresh()->is_active)->toBeFalse();

    $this->post(route('logout'));

    $this->post(route('login.store'), [
        'email' => $staff->email,
        'password' => 'password',
    ])->assertSessionHasErrors();

    $this->assertGuest();
});

test('admin cannot deactivate their own account', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.staff.index')
        ->call('toggleActive', $admin->id)
        ->assertForbidden();

    expect($admin->fresh()->is_active)->toBeTrue();
});
