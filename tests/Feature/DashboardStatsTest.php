<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

test('gross profit is sales minus cost and discounts for completed orders this month', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create(['price' => 10, 'cost_price' => 6]);

    // Completed: 3 units x (10 - 6) margin, minus RM 2 discount = RM 10 profit.
    Order::factory()->for($product)->completed()->create([
        'quantity' => 3,
        'unit_price' => 10,
        'discount_amount' => 2,
        'total_price' => 28,
    ]);

    // Pending order must not count towards profit.
    Order::factory()->for($product)->create(['quantity' => 5, 'unit_price' => 10, 'total_price' => 50]);

    $component = Livewire::actingAs($admin)->test('pages::dashboard');

    expect($component->instance()->monthGrossProfit)->toBe(10.0);
});

test('month growth compares against last month sales', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create(['price' => 10]);

    $this->travelTo(now()->subMonthNoOverflow()->startOfMonth()->addDays(5));
    Order::factory()->for($product)->completed()->create(['quantity' => 1, 'unit_price' => 10, 'total_price' => 100]);

    $this->travelBack();
    Order::factory()->for($product)->completed()->create(['quantity' => 1, 'unit_price' => 10, 'total_price' => 150]);

    $component = Livewire::actingAs($admin)->test('pages::dashboard');

    expect($component->instance()->monthGrowth)->toBe(50.0);
});

test('cancel rate is the share of cancelled orders this month', function () {
    $staff = User::factory()->staff()->create();

    Order::factory()->count(3)->completed()->create();
    Order::factory()->cancelled()->create();

    $component = Livewire::actingAs($staff)->test('pages::dashboard');

    expect($component->instance()->cancelRate)->toBe(25.0);
});

test('low stock products are listed on the dashboard', function () {
    $staff = User::factory()->staff()->create();
    Product::factory()->create(['name' => 'Hampir Habis', 'stock' => 2]);
    Product::factory()->outOfStock()->create(['name' => 'Dah Habis']);
    Product::factory()->create(['name' => 'Stok Banyak', 'stock' => 50]);

    $this->actingAs($staff)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Hampir Habis')
        ->assertSee('Dah Habis');
});

test('gross profit card is hidden from non-admin staff', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Untung Kasar Bulan Ini');
});

test('the sales chart covers thirty days of data', function () {
    $staff = User::factory()->staff()->create();
    Order::factory()->completed()->create(['quantity' => 4, 'unit_price' => 10, 'total_price' => 40]);

    $chart = Livewire::actingAs($staff)->test('pages::dashboard')->instance()->salesChart;

    expect($chart['points'])->toHaveCount(30);
    expect(collect($chart['points'])->sum('total'))->toBe(40.0);
});
