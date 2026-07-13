<?php

use App\Models\Product;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Products')] class extends Component {
    public function mount(): void
    {
        $this->authorize('viewAny', Product::class);
    }

    #[Computed]
    public function products()
    {
        return Product::with('category')->orderBy('name')->get();
    }

    public function delete(int $productId): void
    {
        $product = Product::findOrFail($productId);

        $this->authorize('delete', $product);

        $product->delete();
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Products') }}</flux:heading>
            <flux:subheading>{{ __('Manage the products and stock shown on the storefront') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="$dispatch('open-product-form')">
            {{ __('Add product') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Category') }}</flux:table.column>
            <flux:table.column>{{ __('Price') }}</flux:table.column>
            <flux:table.column>{{ __('Stock') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->products as $product)
                <flux:table.row wire:key="product-{{ $product->id }}">
                    <flux:table.cell>{{ $product->name }}</flux:table.cell>
                    <flux:table.cell>{{ $product->category->name }}</flux:table.cell>
                    <flux:table.cell>{{ Number::currency($product->price, in: 'MYR', locale: 'ms') }}</flux:table.cell>
                    <flux:table.cell>{{ $product->stock }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$product->is_active ? 'lime' : 'zinc'">
                            {{ $product->is_active ? __('Active') : __('Inactive') }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
                            <flux:button size="sm" wire:click="$dispatch('open-product-form', { productId: {{ $product->id }} })">
                                {{ __('Edit') }}
                            </flux:button>

                            @if (! $product->orders()->exists())
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    wire:click="delete({{ $product->id }})"
                                    wire:confirm="{{ __('Delete this product?') }}"
                                >
                                    {{ __('Delete') }}
                                </flux:button>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <livewire:staff.product-form-modal />
</section>
