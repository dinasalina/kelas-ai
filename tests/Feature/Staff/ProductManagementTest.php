<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $this->get(route('staff.products.index'))->assertRedirect(route('login'));
});

test('staff can view the products page', function () {
    $staff = User::factory()->staff()->create();
    Product::factory()->create();

    $this->actingAs($staff)
        ->get(route('staff.products.index'))
        ->assertOk()
        ->assertSee('Products');
});

test('staff can create a product', function () {
    $staff = User::factory()->staff()->create();
    $category = Category::factory()->create();

    Livewire::actingAs($staff)
        ->test('staff.product-form-modal')
        ->call('open')
        ->set('categoryId', $category->id)
        ->set('name', 'New Product')
        ->set('price', '19.99')
        ->set('stock', 10)
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::where('name', 'New Product')->firstOrFail();

    expect($product->category_id)->toBe($category->id);
    expect((float) $product->price)->toBe(19.99);
    expect($product->stock)->toBe(10);
    expect($product->is_active)->toBeTrue();
});

test('staff can edit a product', function () {
    $staff = User::factory()->staff()->create();
    $product = Product::factory()->create(['name' => 'Old Name', 'stock' => 5]);

    Livewire::actingAs($staff)
        ->test('staff.product-form-modal')
        ->call('open', $product->id)
        ->set('name', 'Updated Name')
        ->set('stock', 20)
        ->call('save')
        ->assertHasNoErrors();

    expect($product->fresh()->name)->toBe('Updated Name');
    expect($product->fresh()->stock)->toBe(20);
});

test('product creation requires required fields', function () {
    $staff = User::factory()->staff()->create();

    Livewire::actingAs($staff)
        ->test('staff.product-form-modal')
        ->call('open')
        ->set('name', '')
        ->set('price', '')
        ->call('save')
        ->assertHasErrors(['name', 'price']);
});

test('staff can delete a product with no orders', function () {
    $staff = User::factory()->staff()->create();
    $product = Product::factory()->create();

    Livewire::actingAs($staff)
        ->test('pages::staff.products.index')
        ->call('delete', $product->id);

    expect(Product::find($product->id))->toBeNull();
});

test('deleting a product with existing orders is blocked', function () {
    $staff = User::factory()->staff()->create();
    $product = Product::factory()->create();
    Order::factory()->for($product)->create();

    Livewire::actingAs($staff)
        ->test('pages::staff.products.index')
        ->call('delete', $product->id)
        ->assertForbidden();

    expect(Product::find($product->id))->not->toBeNull();
});
