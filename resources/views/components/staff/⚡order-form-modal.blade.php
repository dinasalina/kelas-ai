<?php

use App\Actions\Orders\PlaceOrderAction;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public ?int $productId = null;

    public string $customerName = '';

    public string $customerPhone = '';

    public string $customerAddress = '';

    public int $quantity = 1;

    public string $couponCode = '';

    #[On('open-staff-order-form')]
    public function open(): void
    {
        $this->authorize('create', Order::class);

        $this->resetValidation();
        $this->productId = null;
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerAddress = '';
        $this->quantity = 1;
        $this->couponCode = '';

        Flux::modal('staff-order-form')->show();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    #[Computed]
    public function products(): \Illuminate\Database\Eloquent\Collection
    {
        return Product::query()->active()->where('stock', '>', 0)->orderBy('name')->get();
    }

    public function placeOrder(PlaceOrderAction $placeOrder): void
    {
        $this->authorize('create', Order::class);

        $validated = $this->validate([
            'productId' => ['required', 'exists:products,id'],
            'customerName' => ['required', 'string', 'max:255'],
            'customerPhone' => ['required', 'string', 'max:30'],
            'customerAddress' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
            'couponCode' => ['nullable', 'string', 'max:50'],
        ]);

        $product = Product::findOrFail($validated['productId']);

        $coupon = null;

        if (trim($validated['couponCode']) !== '') {
            $coupon = Coupon::query()->whereRaw('UPPER(code) = ?', [strtoupper(trim($validated['couponCode']))])->first();

            if (! $coupon || ! $coupon->isValid($product->price * $validated['quantity'])) {
                $this->addError('couponCode', __('This coupon code is not valid.'));

                return;
            }
        }

        try {
            $placeOrder(
                product: $product,
                customerName: $validated['customerName'],
                customerPhone: $validated['customerPhone'],
                customerAddress: $validated['customerAddress'],
                quantity: $validated['quantity'],
                placedByStaff: Auth::user(),
                coupon: $coupon,
            );
        } catch (InsufficientStockException $exception) {
            $this->addError('quantity', $exception->getMessage());

            return;
        } catch (InvalidCouponException $exception) {
            $this->addError('couponCode', $exception->getMessage());

            return;
        }

        Flux::modal('staff-order-form')->close();
        Flux::toast(variant: 'success', text: __('Order created.'));
        $this->dispatch('staff-order-placed');
    }
}; ?>

<flux:modal name="staff-order-form" class="max-w-lg">
    <form wire:submit="placeOrder" class="space-y-6">
        <flux:heading size="lg">{{ __('New manual order') }}</flux:heading>

        <flux:select wire:model="productId" :label="__('Product')" placeholder="{{ __('Select a product') }}">
            @foreach ($this->products as $product)
                <flux:select.option value="{{ $product->id }}">{{ $product->name }} ({{ $product->stock }} {{ __('in stock') }})</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="customerName" :label="__('Customer name')" required />

        <flux:input wire:model="customerPhone" :label="__('Phone number')" required />

        <flux:textarea wire:model="customerAddress" :label="__('Address')" required />

        <flux:input wire:model="quantity" :label="__('Quantity')" type="number" min="1" required />

        <flux:input wire:model="couponCode" :label="__('Coupon code (optional)')" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="primary" type="submit">{{ __('Create order') }}</flux:button>
        </div>
    </form>
</flux:modal>
