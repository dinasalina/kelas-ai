<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $this->get(route('staff.reports.index'))->assertRedirect(route('login'));
});

test('daily and monthly sales totals only count completed orders in range', function () {
    $staff = User::factory()->staff()->create();

    $this->travelTo(now()->setDate(2026, 7, 15)->setTime(10, 0));

    $productA = Product::factory()->create(['price' => 10]);
    $productB = Product::factory()->create(['price' => 20]);

    // Completed today: counts toward today + this month.
    Order::factory()->for($productA)->completed()->create(['quantity' => 2, 'unit_price' => 10, 'total_price' => 20]);

    // Completed earlier this month: counts toward this month only.
    $this->travelTo(now()->setDate(2026, 7, 1)->setTime(9, 0));
    Order::factory()->for($productB)->completed()->create(['quantity' => 1, 'unit_price' => 20, 'total_price' => 20]);

    // Pending order: should not count toward sales at all.
    Order::factory()->for($productA)->create(['quantity' => 1, 'unit_price' => 10, 'total_price' => 10]);

    // Completed last month: should not count toward this month.
    $this->travelTo(now()->subMonth());
    Order::factory()->for($productA)->completed()->create(['quantity' => 1, 'unit_price' => 10, 'total_price' => 10]);

    $this->travelTo(now()->setDate(2026, 7, 15)->setTime(10, 0));

    $component = Livewire::actingAs($staff)->test('pages::staff.reports.index');

    expect($component->instance()->todaySales)->toBe(['total' => 20.0, 'count' => 1]);
    expect($component->instance()->monthSales)->toBe(['total' => 40.0, 'count' => 2]);
});

test('best selling products are ranked by units sold', function () {
    $staff = User::factory()->staff()->create();

    $popular = Product::factory()->create(['name' => 'Popular Product']);
    $rare = Product::factory()->create(['name' => 'Rare Product']);

    Order::factory()->for($popular)->completed()->create(['quantity' => 5]);
    Order::factory()->for($popular)->completed()->create(['quantity' => 3]);
    Order::factory()->for($rare)->completed()->create(['quantity' => 1]);
    Order::factory()->for($rare)->create(['quantity' => 100]); // pending, should not count

    $bestSelling = Livewire::actingAs($staff)
        ->test('pages::staff.reports.index')
        ->instance()
        ->bestSellingProducts;

    expect($bestSelling->first()->name)->toBe('Popular Product');
    expect((int) $bestSelling->first()->units_sold)->toBe(8);
    expect((int) $bestSelling->last()->units_sold)->toBe(1);
});

test('staff performance counts orders processed per staff member', function () {
    $admin = User::factory()->admin()->create();
    $staffA = User::factory()->staff()->create(['name' => 'Staff A']);
    $staffB = User::factory()->staff()->create(['name' => 'Staff B']);

    Order::factory()->count(3)->create(['processed_by_staff_id' => $staffA->id]);
    Order::factory()->count(1)->create(['processed_by_staff_id' => $staffB->id]);
    Order::factory()->create(['processed_by_staff_id' => null]);

    $performance = Livewire::actingAs($admin)
        ->test('pages::staff.reports.index')
        ->instance()
        ->staffPerformance;

    expect($performance->firstWhere('id', $staffA->id)->processed_orders_count)->toBe(3);
    expect($performance->firstWhere('id', $staffB->id)->processed_orders_count)->toBe(1);
});
