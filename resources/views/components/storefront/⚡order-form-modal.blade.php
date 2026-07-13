<?php

use App\Actions\Orders\PlaceOrderAction;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Number;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public ?Product $product = null;

    public string $customerName = '';

    public string $customerPhone = '';

    public string $customerAddress = '';

    public int $quantity = 1;

    public string $couponCode = '';

    #[On('open-order-form')]
    public function open(int $productId): void
    {
        $this->resetValidation();
        $this->product = Product::findOrFail($productId);
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerAddress = '';
        $this->quantity = 1;
        $this->couponCode = '';

        Flux::modal('order-form')->show();
    }

    public function placeOrder(PlaceOrderAction $placeOrder): void
    {
        $validated = $this->validate([
            'customerName' => ['required', 'string', 'max:255'],
            'customerPhone' => ['required', 'string', 'max:30'],
            'customerAddress' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
            'couponCode' => ['nullable', 'string', 'max:50'],
        ]);

        if (! $this->product || ! $this->product->isOrderable()) {
            $this->addError('quantity', __('This product is no longer available.'));

            return;
        }

        $coupon = null;

        if (trim($validated['couponCode']) !== '') {
            $coupon = Coupon::query()->whereRaw('UPPER(code) = ?', [strtoupper(trim($validated['couponCode']))])->first();

            if (! $coupon || ! $coupon->isValid($this->product->price * $validated['quantity'])) {
                $this->addError('couponCode', __('This coupon code is not valid.'));

                return;
            }
        }

        try {
            $order = $placeOrder(
                product: $this->product,
                customerName: $validated['customerName'],
                customerPhone: $validated['customerPhone'],
                customerAddress: $validated['customerAddress'],
                quantity: $validated['quantity'],
                coupon: $coupon,
            );
        } catch (InsufficientStockException $exception) {
            $this->addError('quantity', $exception->getMessage());

            return;
        } catch (InvalidCouponException $exception) {
            $this->addError('couponCode', $exception->getMessage());

            return;
        }

        Flux::modal('order-form')->close();

        $this->redirect(URL::signedRoute('receipt.show', $order));
    }
}; ?>

<flux:modal name="order-form" class="max-w-lg">
    @if ($product)
        <form wire:submit="placeOrder" class="space-y-6">
            <flux:heading size="lg">{{ __('Buat Tempahan') }}</flux:heading>

            <div class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800">
                <div class="size-16 shrink-0 overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-700">
                    @if ($product->image_path)
                        <img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" class="h-full w-full object-cover" />
                    @else
                        <div class="flex h-full w-full items-center justify-center">
                            <flux:icon.photo class="size-6 text-zinc-300 dark:text-zinc-500" />
                        </div>
                    @endif
                </div>

                <div class="min-w-0">
                    <p class="truncate font-semibold text-zinc-900 dark:text-white">{{ $product->name }}</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ Number::currency($product->price, in: 'MYR', locale: 'ms') }} / {{ __('unit') }}
                    </p>
                    <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Stok tersedia: :count', ['count' => $product->stock]) }}</p>
                </div>
            </div>

            <flux:input wire:model="customerName" :label="__('Nama')" required autofocus />

            <flux:input wire:model="customerPhone" :label="__('No. Telefon')" type="tel" placeholder="012-3456789" required />

            <flux:textarea wire:model="customerAddress" :label="__('Alamat Penghantaran')" rows="3" required />

            <flux:input wire:model.live="quantity" :label="__('Kuantiti')" type="number" min="1" :max="$product->stock" required />

            <flux:input wire:model="couponCode" :label="__('Kod Kupon (jika ada)')" placeholder="DISKAUN10" />

            <div class="flex items-center justify-between rounded-xl bg-zinc-100 px-4 py-3 dark:bg-zinc-800">
                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Jumlah (sebelum diskaun)') }}</span>
                <span class="text-lg font-bold text-zinc-900 dark:text-white">
                    {{ Number::currency($product->price * max(1, (int) $quantity), in: 'MYR', locale: 'ms') }}
                </span>
            </div>

            <p class="text-xs text-zinc-400 dark:text-zinc-500">
                {{ __('Bayaran dibuat secara tunai semasa penghantaran (COD). Resit akan dipaparkan selepas tempahan disahkan.') }}
            </p>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Batal') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit" icon="shopping-bag">{{ __('Tempah Sekarang') }}</flux:button>
            </div>
        </form>
    @endif
</flux:modal>
