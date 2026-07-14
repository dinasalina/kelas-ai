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

    /**
     * @return array{total: float, count: int}
     */
    #[Computed]
    public function monthSales(): array
    {
        $query = Order::query()
            ->where('status', OrderStatus::Completed)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);

        return [
            'total' => (float) $query->clone()->sum('total_price'),
            'count' => $query->clone()->count(),
        ];
    }

    /**
     * Percentage growth of this month's sales vs last month, or null when last month had none.
     */
    #[Computed]
    public function monthGrowth(): ?float
    {
        $lastMonth = (float) Order::query()
            ->where('status', OrderStatus::Completed)
            ->whereBetween('created_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])
            ->sum('total_price');

        if ($lastMonth <= 0) {
            return null;
        }

        return round(($this->monthSales['total'] - $lastMonth) / $lastMonth * 100, 1);
    }

    /**
     * Gross profit this month: (selling price - cost price) x quantity, minus discounts,
     * for completed orders. Delivery fees are treated as pass-through and excluded.
     */
    #[Computed]
    public function monthGrossProfit(): float
    {
        $row = Order::query()
            ->join('products', 'products.id', '=', 'orders.product_id')
            ->where('orders.status', OrderStatus::Completed)
            ->whereBetween('orders.created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('COALESCE(SUM(orders.quantity * (orders.unit_price - products.cost_price)), 0) as margin, COALESCE(SUM(orders.discount_amount), 0) as discounts')
            ->first();

        return (float) $row->margin - (float) $row->discounts;
    }

    #[Computed]
    public function averageOrderValue(): float
    {
        return $this->monthSales['count'] > 0
            ? $this->monthSales['total'] / $this->monthSales['count']
            : 0;
    }

    /**
     * Percentage of this month's orders that were cancelled.
     */
    #[Computed]
    public function cancelRate(): float
    {
        $monthOrders = Order::query()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);

        $total = $monthOrders->clone()->count();

        if ($total === 0) {
            return 0;
        }

        return round($monthOrders->clone()->where('status', OrderStatus::Cancelled)->count() / $total * 100, 1);
    }

    /**
     * Current backlog counts for each in-progress status.
     *
     * @return array<int, array{status: OrderStatus, count: int}>
     */
    #[Computed]
    public function statusPipeline(): array
    {
        $counts = Order::query()
            ->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])
            ->get()
            ->groupBy(fn (Order $order) => $order->status->value)
            ->map->count();

        return collect([OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Delivering])
            ->map(fn (OrderStatus $status) => ['status' => $status, 'count' => $counts->get($status->value, 0)])
            ->all();
    }

    /**
     * Daily completed sales for the last 30 days, with chart geometry precomputed.
     *
     * @return array{points: array<int, array{x: float, y: float, label: string, total: float}>, linePath: string, areaPath: string, max: float}
     */
    #[Computed]
    public function salesChart(): array
    {
        $start = now()->subDays(29)->startOfDay();

        $rows = Order::query()
            ->where('status', OrderStatus::Completed)
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as sale_date, SUM(total_price) as total')
            ->groupBy('sale_date')
            ->pluck('total', 'sale_date');

        $days = collect(range(0, 29))->map(function (int $offset) use ($start, $rows) {
            $date = $start->addDays($offset);

            return [
                'label' => $date->format('d/m'),
                'total' => (float) ($rows[$date->format('Y-m-d')] ?? 0),
            ];
        });

        $max = max($days->max('total'), 1);

        $width = 640;
        $height = 200;
        $padX = 8;
        $padTop = 12;
        $padBottom = 24;
        $innerWidth = $width - $padX * 2;
        $innerHeight = $height - $padTop - $padBottom;

        $points = $days->values()->map(fn (array $day, int $i) => [
            'x' => round($padX + $i * ($innerWidth / 29), 2),
            'y' => round($padTop + $innerHeight - ($day['total'] / $max) * $innerHeight, 2),
            'label' => $day['label'],
            'total' => $day['total'],
        ]);

        $linePath = 'M'.$points->map(fn (array $p) => "{$p['x']},{$p['y']}")->implode(' L');
        $baseline = $padTop + $innerHeight;
        $areaPath = $linePath." L{$points->last()['x']},{$baseline} L{$points->first()['x']},{$baseline} Z";

        return [
            'points' => $points->all(),
            'linePath' => $linePath,
            'areaPath' => $areaPath,
            'max' => $max,
        ];
    }

    /**
     * This month's completed product revenue per category, descending.
     *
     * @return array<int, array{name: string, total: float}>
     */
    #[Computed]
    public function categorySales(): array
    {
        return Order::query()
            ->join('products', 'products.id', '=', 'orders.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.status', OrderStatus::Completed)
            ->whereBetween('orders.created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.name as name, SUM(orders.quantity * orders.unit_price) as total')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'total' => (float) $row->total])
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    #[Computed]
    public function lowStockProducts(): \Illuminate\Database\Eloquent\Collection
    {
        return Product::query()
            ->active()
            ->where('stock', '<=', 5)
            ->orderBy('stock')
            ->limit(6)
            ->get();
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
                    {{ __('Pantau jualan, untung, dan status pesanan anda dari satu tempat.') }}
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

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card class="space-y-1.5">
            <flux:subheading class="text-xs font-semibold tracking-wide uppercase">{{ __('Jualan Hari Ini') }}</flux:subheading>
            <flux:heading size="xl">{{ Number::currency($this->todaySales['total'], in: 'MYR', locale: 'ms') }}</flux:heading>
            <flux:text class="text-xs">{{ __(':count pesanan selesai', ['count' => $this->todaySales['count']]) }}</flux:text>
        </flux:card>

        <flux:card class="space-y-1.5">
            <flux:subheading class="text-xs font-semibold tracking-wide uppercase">{{ __('Jualan Bulan Ini') }}</flux:subheading>
            <flux:heading size="xl">{{ Number::currency($this->monthSales['total'], in: 'MYR', locale: 'ms') }}</flux:heading>
            @if ($this->monthGrowth !== null)
                <div class="flex items-center gap-1.5">
                    <flux:badge size="sm" :color="$this->monthGrowth >= 0 ? 'lime' : 'red'" :icon="$this->monthGrowth >= 0 ? 'arrow-trending-up' : 'arrow-trending-down'">
                        {{ $this->monthGrowth >= 0 ? '+' : '' }}{{ $this->monthGrowth }}%
                    </flux:badge>
                    <flux:text class="text-xs">{{ __('vs bulan lepas') }}</flux:text>
                </div>
            @else
                <flux:text class="text-xs">{{ __(':count pesanan selesai', ['count' => $this->monthSales['count']]) }}</flux:text>
            @endif
        </flux:card>

        @can('manage-staff')
            <flux:card class="space-y-1.5">
                <flux:subheading class="text-xs font-semibold tracking-wide uppercase">{{ __('Untung Kasar Bulan Ini') }}</flux:subheading>
                <flux:heading size="xl" class="{{ $this->monthGrossProfit < 0 ? 'text-red-600! dark:text-red-400!' : '' }}">
                    {{ Number::currency($this->monthGrossProfit, in: 'MYR', locale: 'ms') }}
                </flux:heading>
                <flux:text class="text-xs">{{ __('Jualan - modal - diskaun (tidak termasuk caj hantar)') }}</flux:text>
            </flux:card>
        @endcan

        <flux:card class="space-y-1.5">
            <flux:subheading class="text-xs font-semibold tracking-wide uppercase">{{ __('Purata Nilai Pesanan') }}</flux:subheading>
            <flux:heading size="xl">{{ Number::currency($this->averageOrderValue, in: 'MYR', locale: 'ms') }}</flux:heading>
            <flux:text class="text-xs">{{ __('Kadar batal bulan ini: :rate%', ['rate' => $this->cancelRate]) }}</flux:text>
        </flux:card>
    </div>

    <!-- Pipeline chips -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ($this->statusPipeline as $stage)
            <a
                href="{{ route('staff.orders.index', ['view' => 'table', 'status' => $stage['status']->value]) }}"
                wire:navigate
                class="flex items-center justify-between rounded-xl border border-zinc-200 bg-white px-4 py-3 transition hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-900"
            >
                <div class="flex items-center gap-2">
                    <flux:badge size="sm" :color="$stage['status']->color()">{{ $stage['status']->label() }}</flux:badge>
                </div>
                <span class="text-lg font-bold text-zinc-900 dark:text-white">{{ $stage['count'] }}</span>
            </a>
        @endforeach
    </div>

    <!-- Charts row -->
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <flux:card class="xl:col-span-2 space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Jualan 30 Hari Terkini') }}</flux:heading>
                <flux:text class="text-xs">{{ __('Jumlah jualan harian (pesanan selesai sahaja)') }}</flux:text>
            </div>

            @php $chart = $this->salesChart; @endphp

            <div
                class="relative"
                x-data="{
                    tip: null,
                    points: {{ \Illuminate\Support\Js::from($chart['points']) }},
                    show(i) {
                        const p = this.points[i];
                        this.tip = { left: (p.x / 640 * 100) + '%', label: p.label, total: p.total.toFixed(2) };
                    },
                }"
            >
                <svg viewBox="0 0 640 200" class="w-full" role="img" aria-label="{{ __('Graf jualan 30 hari') }}">
                    @foreach ([0.25, 0.5, 0.75] as $gridRatio)
                        <line x1="8" x2="632" y1="{{ 12 + 164 * $gridRatio }}" y2="{{ 12 + 164 * $gridRatio }}" class="stroke-zinc-100 dark:stroke-zinc-800" stroke-width="1" />
                    @endforeach

                    <line x1="8" x2="632" y1="176" y2="176" class="stroke-zinc-200 dark:stroke-zinc-700" stroke-width="1" />

                    <path d="{{ $chart['areaPath'] }}" class="fill-[#2563eb]/10 dark:fill-[#3b82f6]/15" />
                    <path d="{{ $chart['linePath'] }}" fill="none" class="stroke-[#2563eb] dark:stroke-[#3b82f6]" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

                    @foreach ($chart['points'] as $i => $point)
                        <rect
                            x="{{ $point['x'] - 10.5 }}" y="0" width="21" height="200" fill="transparent"
                            @mouseenter="show({{ $i }})" @mouseleave="tip = null"
                        />
                        <circle
                            cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4"
                            class="fill-[#2563eb] dark:fill-[#3b82f6] opacity-0 transition-opacity"
                            :class="tip && tip.label === '{{ $point['label'] }}' ? 'opacity-100' : 'opacity-0'"
                            stroke="white" stroke-width="2"
                        />
                        @if ($i % 7 === 0 || $i === 29)
                            <text x="{{ $point['x'] }}" y="194" text-anchor="middle" class="fill-zinc-400 dark:fill-zinc-500" font-size="10">{{ $point['label'] }}</text>
                        @endif
                    @endforeach

                    <text x="8" y="10" class="fill-zinc-400 dark:fill-zinc-500" font-size="10">{{ Number::currency($chart['max'], in: 'MYR', locale: 'ms') }}</text>
                </svg>

                <div
                    x-show="tip"
                    x-cloak
                    :style="tip ? { left: tip.left } : {}"
                    class="pointer-events-none absolute top-0 -translate-x-1/2 rounded-lg bg-zinc-900 px-3 py-1.5 text-xs text-white shadow-lg dark:bg-white dark:text-zinc-900"
                >
                    <span x-text="tip ? tip.label : ''"></span>:
                    <span class="font-bold" x-text="tip ? 'RM ' + tip.total : ''"></span>
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Jualan Ikut Kategori') }}</flux:heading>
                <flux:text class="text-xs">{{ __('Bulan ini, pesanan selesai') }}</flux:text>
            </div>

            @php $maxCategory = max(array_column($this->categorySales, 'total') ?: [1]); @endphp

            <div class="space-y-3">
                @forelse ($this->categorySales as $row)
                    <div class="space-y-1">
                        <div class="flex items-baseline justify-between gap-2 text-sm">
                            <span class="text-zinc-700 dark:text-zinc-300">{{ $row['name'] }}</span>
                            <span class="font-semibold text-zinc-900 dark:text-white">{{ Number::currency($row['total'], in: 'MYR', locale: 'ms') }}</span>
                        </div>
                        <div class="h-2.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div class="h-full rounded-full bg-[#2563eb] dark:bg-[#3b82f6]" style="width: {{ max(2, round($row['total'] / $maxCategory * 100)) }}%"></div>
                        </div>
                    </div>
                @empty
                    <flux:text class="text-sm">{{ __('Tiada jualan selesai bulan ini lagi.') }}</flux:text>
                @endforelse
            </div>

            <div class="border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <div class="mb-2 flex items-center justify-between">
                    <flux:heading size="sm">{{ __('Stok Rendah') }}</flux:heading>
                    <a href="{{ route('staff.products.index') }}" wire:navigate class="text-xs font-semibold text-zinc-500 hover:text-zinc-900 dark:hover:text-white">{{ __('Urus stok') }}</a>
                </div>

                <div class="space-y-2">
                    @forelse ($this->lowStockProducts as $product)
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="truncate text-zinc-700 dark:text-zinc-300">{{ $product->name }}</span>
                            <flux:badge size="sm" :color="$product->stock === 0 ? 'red' : 'amber'">
                                {{ $product->stock === 0 ? __('Habis') : __('Baki :count', ['count' => $product->stock]) }}
                            </flux:badge>
                        </div>
                    @empty
                        <flux:text class="text-sm">{{ __('Semua stok mencukupi. 👍') }}</flux:text>
                    @endforelse
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Recent Orders Table Section -->
    <div class="relative flex-1 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800/60 dark:bg-zinc-900/60 shadow-xs">
        <div class="mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">{{ __('Pesanan Terkini') }}</flux:heading>
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                    {{ __('Urusan transaksi terkini pelanggan kedai anda.') }}
                </flux:text>
            </div>
            <a href="{{ route('staff.orders.index') }}" wire:navigate class="group inline-flex items-center gap-1 text-xs font-semibold text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white self-start sm:self-auto">
                <span>{{ __('Lihat semua') }}</span>
                <flux:icon name="arrow-right" class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5 text-zinc-400 dark:text-zinc-500" />
            </a>
        </div>

        <div class="overflow-x-auto">
            <flux:table class="w-full">
                <flux:table.columns>
                    <flux:table.column>{{ __('No. Pesanan') }}</flux:table.column>
                    <flux:table.column>{{ __('Produk') }}</flux:table.column>
                    <flux:table.column>{{ __('Pelanggan') }}</flux:table.column>
                    <flux:table.column>{{ __('Jumlah') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->recentOrders as $order)
                        <flux:table.row wire:key="recent-order-{{ $order->id }}">
                            <flux:table.cell class="whitespace-nowrap text-xs font-semibold text-zinc-500">{{ $order->order_number }}</flux:table.cell>
                            <flux:table.cell>{{ $order->product->name }} × {{ $order->quantity }}</flux:table.cell>
                            <flux:table.cell>{{ $order->customer_name }}</flux:table.cell>
                            <flux:table.cell class="font-semibold">{{ Number::currency($order->total_price, in: 'MYR', locale: 'ms') }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$order->status->color()">{{ $order->status->label() }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="py-12 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('Tiada pesanan lagi.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>
</div>
