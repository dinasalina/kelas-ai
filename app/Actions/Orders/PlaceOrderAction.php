<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PlaceOrderAction
{
    /**
     * Place an order for the given product, decrementing its stock.
     *
     * @throws InsufficientStockException
     * @throws InvalidCouponException
     */
    public function __invoke(
        Product $product,
        string $customerName,
        string $customerPhone,
        string $customerAddress,
        int $quantity,
        ?User $placedByStaff = null,
        ?Coupon $coupon = null,
    ): Order {
        return DB::transaction(function () use ($product, $customerName, $customerPhone, $customerAddress, $quantity, $placedByStaff, $coupon) {
            /** @var Product $lockedProduct */
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);

            if (! $lockedProduct->is_active) {
                throw new InsufficientStockException('This product is no longer available.');
            }

            if ($quantity > $lockedProduct->stock) {
                throw new InsufficientStockException('Not enough stock available for this order.');
            }

            $subtotal = $lockedProduct->price * $quantity;
            $discountAmount = 0;

            if ($coupon) {
                if (! $coupon->isValid((float) $subtotal)) {
                    throw new InvalidCouponException('This coupon code is not valid for this order.');
                }

                $discountAmount = $coupon->calculateDiscount((float) $subtotal);
            }

            $order = new Order([
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_address' => $customerAddress,
                'quantity' => $quantity,
            ]);
            $order->order_number = Order::generateOrderNumber();
            $order->status = OrderStatus::Pending;
            $order->product_id = $lockedProduct->id;
            $order->coupon_id = $coupon?->id;
            $order->placed_by_staff_id = $placedByStaff?->id;
            $order->unit_price = $lockedProduct->price;
            $order->discount_amount = $discountAmount;
            $order->total_price = $subtotal - $discountAmount;
            $order->save();

            $order->statusHistories()->create([
                'from_status' => null,
                'to_status' => $order->status,
                'changed_by_staff_id' => $placedByStaff?->id,
                'note' => null,
            ]);

            $lockedProduct->decrement('stock', $quantity);

            return $order;
        });
    }
}
