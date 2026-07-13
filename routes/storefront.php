<?php

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::storefront.index')->name('home');
Route::livewire('jejak', 'pages::storefront.track')->name('track');

Route::get('resit/{order}', function (Request $request, Order $order) {
    $user = $request->user();
    $isStaff = $user && ($user->isAdmin() || $user->isStaff());

    abort_unless($isStaff || $request->hasValidSignature(), 403);

    return view('receipts.show', [
        'order' => $order->load(['product', 'coupon', 'statusHistories']),
    ]);
})->name('receipt.show');
