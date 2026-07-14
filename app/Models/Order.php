<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string|null $order_number
 * @property int $product_id
 * @property int|null $coupon_id
 * @property int|null $delivery_zone_id
 * @property int|null $placed_by_staff_id
 * @property int|null $processed_by_staff_id
 * @property string $customer_name
 * @property string $customer_phone
 * @property string $customer_address
 * @property int $quantity
 * @property string $unit_price
 * @property string $discount_amount
 * @property string $delivery_fee
 * @property string $total_price
 * @property OrderStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['customer_name', 'customer_phone', 'customer_address', 'quantity'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total_price' => 'decimal:2',
            'status' => OrderStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return BelongsTo<DeliveryZone, $this>
     */
    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function placedByStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by_staff_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processedByStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_staff_id');
    }

    /**
     * @return HasMany<OrderStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function isGuestOrder(): bool
    {
        return $this->placed_by_staff_id === null;
    }

    /**
     * Generate a unique, human-friendly order number (e.g. ORD-20260713-8K2F).
     */
    public static function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (static::where('order_number', $number)->exists());

        return $number;
    }
}
