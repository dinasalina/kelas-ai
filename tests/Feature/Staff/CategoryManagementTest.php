<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $this->get(route('staff.categories.index'))->assertRedirect(route('login'));
});

test('staff can view the categories page', function () {
    $staff = User::factory()->staff()->create();
    Category::factory()->create();

    $this->actingAs($staff)
        ->get(route('staff.categories.index'))
        ->assertOk()
        ->assertSee('Categories');
});

test('staff can create a category', function () {
    $staff = User::factory()->staff()->create();

    Livewire::actingAs($staff)
        ->test('staff.category-form-modal')
        ->call('open')
        ->set('name', 'Snacks')
        ->call('save')
        ->assertHasNoErrors();

    expect(Category::where('name', 'Snacks')->exists())->toBeTrue();
});

test('staff can edit a category', function () {
    $staff = User::factory()->staff()->create();
    $category = Category::factory()->create(['name' => 'Old Name']);

    Livewire::actingAs($staff)
        ->test('staff.category-form-modal')
        ->call('open', $category->id)
        ->set('name', 'Updated Name')
        ->call('save')
        ->assertHasNoErrors();

    expect($category->fresh()->name)->toBe('Updated Name');
});

test('duplicate category names are rejected', function () {
    $staff = User::factory()->staff()->create();
    Category::factory()->create(['name' => 'Snacks', 'slug' => 'snacks']);

    Livewire::actingAs($staff)
        ->test('staff.category-form-modal')
        ->call('open')
        ->set('name', 'Snacks')
        ->call('save')
        ->assertHasErrors();
});

test('staff can delete a category with no products', function () {
    $staff = User::factory()->staff()->create();
    $category = Category::factory()->create();

    Livewire::actingAs($staff)
        ->test('pages::staff.categories.index')
        ->call('delete', $category->id);

    expect(Category::find($category->id))->toBeNull();
});

test('deleting a category with existing products is blocked', function () {
    $staff = User::factory()->staff()->create();
    $category = Category::factory()->create();
    Product::factory()->for($category)->create();

    Livewire::actingAs($staff)
        ->test('pages::staff.categories.index')
        ->call('delete', $category->id)
        ->assertForbidden();

    expect(Category::find($category->id))->not->toBeNull();
});
