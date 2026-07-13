<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::factory();
        $quantity = fake()->numberBetween(1, 5);

        return [
            'order_number' => Order::generateOrderNumber(),
            'product_id' => $product,
            'placed_by_staff_id' => null,
            'processed_by_staff_id' => null,
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->numerify('01#-#######'),
            'customer_address' => fake()->address(),
            'quantity' => $quantity,
            'unit_price' => 0,
            'total_price' => 0,
            'status' => OrderStatus::Pending,
        ];
    }

    /**
     * Indicate that the order was placed by a staff member on behalf of a customer.
     */
    public function placedByStaff(?User $staff = null): static
    {
        return $this->state(fn (array $attributes) => [
            'placed_by_staff_id' => $staff?->id ?? User::factory()->staff(),
        ]);
    }

    /**
     * Indicate that the order has been confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Confirmed,
        ]);
    }

    /**
     * Indicate that the order is currently being prepared.
     */
    public function preparing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Preparing,
        ]);
    }

    /**
     * Indicate that the order is out for delivery.
     */
    public function delivering(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Delivering,
        ]);
    }

    /**
     * Indicate that the order has been completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Completed,
        ]);
    }

    /**
     * Indicate that the order has been cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Cancelled,
        ]);
    }

    /**
     * Configure the model factory to snapshot pricing from the related product.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Order $order) {
            if ($order->unit_price == 0 && $order->product) {
                $order->unit_price = $order->product->price;
                $order->total_price = $order->product->price * $order->quantity;
            }
        });
    }
}
