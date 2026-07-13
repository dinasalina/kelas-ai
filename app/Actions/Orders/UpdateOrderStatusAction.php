<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateOrderStatusAction
{
    /**
     * Move the order to the given status (or the next one when omitted), recording history.
     *
     * Allowed transitions: any forward move within the fulfilment flow, or cancelling a
     * non-final order (which returns its quantity to product stock).
     *
     * @throws InvalidArgumentException
     */
    public function __invoke(Order $order, User $actingUser, ?OrderStatus $to = null, ?string $note = null): Order
    {
        $from = $order->status;
        $to ??= $from->next();

        if ($to === null || $from->isFinal()) {
            throw new InvalidArgumentException('This order has already reached its final status.');
        }

        if ($to !== OrderStatus::Cancelled && $to->flowIndex() <= $from->flowIndex()) {
            throw new InvalidArgumentException('Orders can only move forward in the fulfilment flow.');
        }

        return DB::transaction(function () use ($order, $actingUser, $from, $to, $note) {
            if ($to === OrderStatus::Cancelled) {
                Product::query()->lockForUpdate()->findOrFail($order->product_id)->increment('stock', $order->quantity);
            }

            $order->status = $to;
            $order->processed_by_staff_id = $actingUser->id;
            $order->save();

            $order->statusHistories()->create([
                'from_status' => $from,
                'to_status' => $to,
                'changed_by_staff_id' => $actingUser->id,
                'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            ]);

            return $order;
        });
    }
}
