<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin,staff'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {
        Route::livewire('orders', 'pages::staff.orders.index')->name('orders.index');
        Route::livewire('products', 'pages::staff.products.index')->name('products.index');
        Route::livewire('categories', 'pages::staff.categories.index')->name('categories.index');
        Route::livewire('coupons', 'pages::staff.coupons.index')->name('coupons.index');
        Route::livewire('delivery-zones', 'pages::staff.delivery-zones.index')->name('delivery-zones.index');
        Route::livewire('reports', 'pages::staff.reports.index')->name('reports.index');
    });
