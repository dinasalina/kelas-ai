<?php

use App\Models\Category;
use App\Models\Product;

test('storefront shows only active products', function () {
    Product::factory()->create(['name' => 'Active Product']);
    Product::factory()->inactive()->create(['name' => 'Inactive Product']);

    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('Active Product')
        ->assertDontSee('Inactive Product');
});

test('storefront can filter products by category', function () {
    $categoryA = Category::factory()->create(['name' => 'Category A']);
    $categoryB = Category::factory()->create(['name' => 'Category B']);

    Product::factory()->for($categoryA)->create(['name' => 'Product A']);
    Product::factory()->for($categoryB)->create(['name' => 'Product B']);

    $response = $this->get(route('home', ['category' => $categoryA->id]));

    $response->assertOk()
        ->assertSee('Product A')
        ->assertDontSee('Product B');
});

test('out of stock products are shown but disabled', function () {
    Product::factory()->outOfStock()->create(['name' => 'Sold Out Product']);

    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('Sold Out Product')
        ->assertSee('Habis Stok');
});
