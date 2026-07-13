<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    /**
     * @return array{total: float, count: int}
     */
    #[Computed]
    public function todaySales(): array
    {
        $query = Order::query()
            ->where('status', OrderStatus::Completed)
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()]);

        return [
            'total' => (float) $query->clone()->sum('total_price'),
            'count' => $query->clone()->count(),
        ];
    }

    #[Computed]
    public function pendingOrdersCount(): int
    {
        return Order::query()->where('status', OrderStatus::Pending)->count();
    }

    #[Computed]
    public function activeProductsCount(): int
    {
        return Product::query()->active()->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Order>
     */
    #[Computed]
    public function recentOrders(): \Illuminate\Database\Eloquent\Collection
    {
        return Order::query()->with('product')->latest()->limit(8)->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Welcome Header Banner -->
    <div class="relative overflow-hidden rounded-2xl bg-linear-to-r from-zinc-900 via-zinc-800 to-zinc-900 p-6 shadow-lg border border-zinc-800 dark:from-zinc-950 dark:via-zinc-900 dark:to-zinc-950 dark:border-zinc-800/30">
        <!-- Decorative subtle gradient glow -->
        <div class="absolute -right-6 -top-6 h-28 w-28 rounded-full bg-neutral-500/10 blur-2xl"></div>
        <div class="absolute -left-6 -bottom-6 h-28 w-28 rounded-full bg-zinc-500/10 blur-2xl"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
                    <span class="text-xs font-semibold tracking-wider uppercase text-zinc-400">
                        {{ __('Ringkasan Stor') }}
                    </span>
                </div>
                <flux:heading size="xl" class="mt-2 !text-white font-bold leading-tight">
                    {{ __('Selamat kembali, :name! 👋', ['name' => auth()->user()->name]) }}
                </flux:heading>
                <flux:text class="mt-1 !text-zinc-400">
                    {{ __('Pantau jualan, urus pesanan, dan pantau status produk anda dari satu tempat.') }}
                </flux:text>
            </div>
            <div class="flex items-center gap-2 self-start md:self-auto bg-zinc-800/50 backdrop-blur-md px-3.5 py-2 rounded-xl border border-zinc-700/50 shadow-inner">
                <flux:icon name="calendar" class="h-4 w-4 text-zinc-300" />
                <span class="text-xs font-semibold text-zinc-300">
                    {{ now()->translatedFormat('l, d F Y') }}
                </span>
            </div>
        </div>
    </div>

    <!-- Stat Cards Grid -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <!-- Today's Sales Card -->
        <div class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-white p-6 shadow-xs hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 dark:border-zinc-800/60 dark:bg-zinc-900/60">
            <div class="absolute right-0 top-0 h-20 w-20 translate-x-4 -translate-y-4 rounded-full bg-emerald-500/5 blur-xl"></div>
            <div class="flex items-center justify-between">
                <flux:subheading class="text-xs font-semibold tracking-wide uppercase text-zinc-500 dark:text-zinc-400">{{ __("Today's sales") }}</flux:subheading>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400 shadow-xs">
                    <flux:icon name="banknotes" class="h-5 w-5" />
                </div>
            </div>
            <div class="mt-4">
                <flux:heading size="xl" class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                    {{ Number::currency($this->todaySales['total'], in: 'MYR', locale: 'ms') }}
                </flux:heading>
                <div class="mt-2 flex items-center gap-1.5">
                    <span class="inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">
                        {{ trans_choice(':count order|:count orders', $this->todaySales['count'], ['count' => $this->todaySales['count']]) }}
                    </flux:text>
                </div>
            </div>
        </div>

        <!-- Pending Orders Card -->
        <div class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-white p-6 shadow-xs hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 dark:border-zinc-800/60 dark:bg-zinc-900/60">
            <div class="absolute right-0 top-0 h-20 w-20 translate-x-4 -translate-y-4 rounded-full bg-amber-500/5 blur-xl"></div>
            <div class="flex items-center justify-between">
                <flux:subheading class="text-xs font-semibold tracking-wide uppercase text-zinc-500 dark:text-zinc-400">{{ __('Pending orders') }}</flux:subheading>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400 shadow-xs">
                    <flux:icon name="clock" class="h-5 w-5" />
                </div>
            </div>
            <div class="mt-4">
                <flux:heading size="xl" class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                    {{ $this->pendingOrdersCount }}
                </flux:heading>
                <div class="mt-2">
                    <a href="{{ route('staff.orders.index', ['status' => 'pending']) }}" wire:navigate class="group inline-flex items-center gap-1 text-xs font-semibold text-zinc-700 hover:text-zinc-950 dark:text-zinc-300 dark:hover:text-white">
                        <span>{{ __('View orders') }}</span>
                        <flux:icon name="chevron-right" class="h-3 w-3 transition-transform group-hover:translate-x-0.5 text-zinc-400 dark:text-zinc-500" />
                    </a>
                </div>
            </div>
        </div>

        <!-- Active Products Card -->
        <div class="relative overflow-hidden rounded-2xl border border-zinc-200/80 bg-white p-6 shadow-xs hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 dark:border-zinc-800/60 dark:bg-zinc-900/60">
            <div class="absolute right-0 top-0 h-20 w-20 translate-x-4 -translate-y-4 rounded-full bg-indigo-500/5 blur-xl"></div>
            <div class="flex items-center justify-between">
                <flux:subheading class="text-xs font-semibold tracking-wide uppercase text-zinc-500 dark:text-zinc-400">{{ __('Active products') }}</flux:subheading>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400 shadow-xs">
                    <flux:icon name="cube" class="h-5 w-5" />
                </div>
            </div>
            <div class="mt-4">
                <flux:heading size="xl" class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                    {{ $this->activeProductsCount }}
                </flux:heading>
                <div class="mt-2">
                    <a href="{{ route('staff.products.index') }}" wire:navigate class="group inline-flex items-center gap-1 text-xs font-semibold text-zinc-700 hover:text-zinc-950 dark:text-zinc-300 dark:hover:text-white">
                        <span>{{ __('View products') }}</span>
                        <flux:icon name="chevron-right" class="h-3 w-3 transition-transform group-hover:translate-x-0.5 text-zinc-400 dark:text-zinc-500" />
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders Table Section -->
    <div class="relative flex-1 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800/60 dark:bg-zinc-900/60 shadow-xs">
        <div class="mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">{{ __('Recent orders') }}</flux:heading>
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                    {{ __('Urusan transaksi terkini pelanggan kedai anda.') }}
                </flux:text>
            </div>
            <a href="{{ route('staff.orders.index') }}" wire:navigate class="group inline-flex items-center gap-1 text-xs font-semibold text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white self-start sm:self-auto">
                <span>{{ __('View all') }}</span>
                <flux:icon name="arrow-right" class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5 text-zinc-400 dark:text-zinc-500" />
            </a>
        </div>

        <div class="overflow-x-auto">
            <flux:table class="w-full">
                <flux:table.columns>
                    <flux:table.column class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Product') }}</flux:table.column>
                    <flux:table.column class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Customer') }}</flux:table.column>
                    <flux:table.column class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Total') }}</flux:table.column>
                    <flux:table.column class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->recentOrders as $order)
                        <flux:table.row wire:key="recent-order-{{ $order->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors duration-150">
                            <flux:table.cell class="py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 shadow-xs border border-zinc-200/50 dark:border-zinc-700/50">
                                        <flux:icon name="shopping-bag" class="h-4.5 w-4.5" />
                                    </div>
                                    <span class="font-semibold text-zinc-900 dark:text-white text-sm">{{ $order->product->name }}</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 text-xs font-bold border border-zinc-200/50 dark:border-zinc-700/50 uppercase shadow-xs">
                                        {{ substr($order->customer_name, 0, 2) }}
                                    </div>
                                    <span class="text-zinc-600 dark:text-zinc-300 text-sm font-medium">{{ $order->customer_name }}</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="py-3.5 font-bold text-zinc-900 dark:text-white text-sm">
                                {{ Number::currency($order->total_price, in: 'MYR', locale: 'ms') }}
                            </flux:table.cell>
                            <flux:table.cell class="py-3.5">
                                <flux:badge
                                    size="sm"
                                    :color="$order->status->color()"
                                    class="font-semibold"
                                >
                                    {{ $order->status->label() }}
                                </flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="py-12 text-center text-zinc-500 dark:text-zinc-400">
                                <div class="flex flex-col items-center justify-center gap-3">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-100 dark:border-zinc-700">
                                        <flux:icon name="inbox" class="h-6 w-6 text-zinc-400 dark:text-zinc-500" />
                                    </div>
                                    <span class="text-sm font-medium">{{ __('No orders yet.') }}</span>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>
</div>
