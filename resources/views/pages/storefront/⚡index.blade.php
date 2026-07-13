<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::storefront')] #[Title('Produk')] class extends Component {
    #[Url]
    public ?int $category = null;

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Category>
     */
    #[Computed]
    public function categories(): \Illuminate\Database\Eloquent\Collection
    {
        return Category::orderBy('name')->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    #[Computed]
    public function products(): \Illuminate\Database\Eloquent\Collection
    {
        return Product::query()
            ->active()
            ->with('category')
            ->when($this->category, fn ($query) => $query->where('category_id', $this->category))
            ->orderBy('name')
            ->get();
    }
}; ?>

<div class="space-y-10 py-10">
    <div class="mx-auto max-w-2xl space-y-4 text-center">
        <flux:badge color="lime" size="sm" class="mx-auto">{{ __('Tiada akaun diperlukan · Bayar semasa terima (COD)') }}</flux:badge>

        <h1 class="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">
            {{ __('Produk Kami') }}
        </h1>

        <p class="text-zinc-500 dark:text-zinc-400">
            {{ __('Pilih produk kegemaran anda dan buat tempahan terus — mudah, pantas, tanpa perlu mendaftar.') }}
        </p>
    </div>

    @if ($this->categories->isNotEmpty())
        <div class="flex flex-wrap items-center justify-center gap-2">
            <button
                type="button"
                wire:click="$set('category', null)"
                class="rounded-full px-4 py-1.5 text-sm font-medium transition {{ $category === null ? 'bg-zinc-900 text-white shadow dark:bg-white dark:text-zinc-900' : 'bg-white text-zinc-600 ring-1 ring-zinc-200 hover:bg-zinc-100 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700 dark:hover:bg-zinc-700' }}"
            >
                {{ __('Semua') }}
            </button>

            @foreach ($this->categories as $categoryOption)
                <button
                    type="button"
                    wire:click="$set('category', {{ $categoryOption->id }})"
                    class="rounded-full px-4 py-1.5 text-sm font-medium transition {{ $category === $categoryOption->id ? 'bg-zinc-900 text-white shadow dark:bg-white dark:text-zinc-900' : 'bg-white text-zinc-600 ring-1 ring-zinc-200 hover:bg-zinc-100 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700 dark:hover:bg-zinc-700' }}"
                >
                    {{ $categoryOption->name }}
                </button>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse ($this->products as $product)
            <div
                wire:key="product-{{ $product->id }}"
                class="group flex flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
            >
                <div class="relative aspect-4/3 overflow-hidden bg-zinc-100 dark:bg-zinc-800">
                    @if ($product->image_path)
                        <img
                            src="{{ asset('storage/'.$product->image_path) }}"
                            alt="{{ $product->name }}"
                            loading="lazy"
                            class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                        />
                    @else
                        <div class="flex h-full w-full items-center justify-center">
                            <flux:icon.photo class="size-10 text-zinc-300 dark:text-zinc-600" />
                        </div>
                    @endif

                    <div class="absolute top-3 left-3">
                        <span class="rounded-full bg-white/90 px-2.5 py-1 text-xs font-medium text-zinc-700 shadow-sm backdrop-blur dark:bg-zinc-900/80 dark:text-zinc-200">
                            {{ $product->category->name }}
                        </span>
                    </div>

                    @unless ($product->isOrderable())
                        <div class="absolute inset-0 flex items-center justify-center bg-zinc-900/60 backdrop-blur-[2px]">
                            <span class="rounded-full bg-white px-4 py-1.5 text-sm font-semibold text-zinc-900">
                                {{ __('Habis Stok') }}
                            </span>
                        </div>
                    @endunless
                </div>

                <div class="flex flex-1 flex-col gap-2 p-5">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="font-semibold text-zinc-900 dark:text-white">{{ $product->name }}</h2>
                    </div>

                    @if ($product->description)
                        <p class="line-clamp-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $product->description }}</p>
                    @endif

                    @if ($product->isOrderable() && $product->stock <= 5)
                        <p class="text-xs font-medium text-amber-600 dark:text-amber-400">
                            {{ __('Stok tinggal :count sahaja!', ['count' => $product->stock]) }}
                        </p>
                    @endif

                    <div class="mt-auto flex items-center justify-between gap-3 pt-3">
                        <span class="text-lg font-bold text-zinc-900 dark:text-white">
                            {{ Number::currency($product->price, in: 'MYR', locale: 'ms') }}
                        </span>

                        @if ($product->isOrderable())
                            <flux:button variant="primary" size="sm" icon="shopping-bag" wire:click="$dispatch('open-order-form', { productId: {{ $product->id }} })">
                                {{ __('Tempah') }}
                            </flux:button>
                        @else
                            <flux:button variant="filled" size="sm" disabled>
                                {{ __('Habis Stok') }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 text-center">
                <flux:icon.shopping-bag class="mx-auto size-10 text-zinc-300 dark:text-zinc-600" />
                <p class="mt-3 text-zinc-500 dark:text-zinc-400">{{ __('Tiada produk buat masa ini. Sila kembali semula nanti.') }}</p>
            </div>
        @endforelse
    </div>

    <livewire:storefront.order-form-modal />
</div>
