<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Reports')] class extends Component {
    public function mount(): void
    {
        $this->authorize('viewAny', Order::class);
    }

    /**
     * @return array{total: float, count: int}
     */
    #[Computed]
    public function todaySales(): array
    {
        return $this->salesSummary(now()->startOfDay(), now()->endOfDay());
    }

    /**
     * @return array{total: float, count: int}
     */
    #[Computed]
    public function monthSales(): array
    {
        return $this->salesSummary(now()->startOfMonth(), now()->endOfMonth());
    }

    /**
     * @return array{total: float, count: int}
     */
    protected function salesSummary(CarbonInterface $from, CarbonInterface $to): array
    {
        $query = Order::query()
            ->where('status', OrderStatus::Completed)
            ->whereBetween('created_at', [$from, $to]);

        return [
            'total' => (float) $query->clone()->sum('total_price'),
            'count' => $query->clone()->count(),
        ];
    }

    /**
     * Average minutes from order placement to completion, or null when no orders are completed.
     */
    #[Computed]
    public function averageCompletionMinutes(): ?int
    {
        $durations = OrderStatusHistory::query()
            ->where('to_status', OrderStatus::Completed)
            ->with('order:id,created_at')
            ->get()
            ->map(fn (OrderStatusHistory $history) => $history->order
                ? max(0, (int) $history->order->created_at->diffInMinutes($history->created_at))
                : null)
            ->filter(fn (?int $minutes) => $minutes !== null);

        return $durations->isEmpty() ? null : (int) round($durations->avg());
    }

    /**
     * @return \Illuminate\Support\Collection<int, object{name: string, units_sold: int, revenue: float}>
     */
    #[Computed]
    public function bestSellingProducts(): \Illuminate\Support\Collection
    {
        return Order::query()
            ->where('orders.status', OrderStatus::Completed)
            ->join('products', 'products.id', '=', 'orders.product_id')
            ->groupBy('products.id', 'products.name')
            ->selectRaw('products.name as name, SUM(orders.quantity) as units_sold, SUM(orders.total_price) as revenue')
            ->orderByDesc('units_sold')
            ->limit(10)
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    #[Computed]
    public function staffPerformance(): \Illuminate\Support\Collection
    {
        return User::query()
            ->withCount('processedOrders')
            ->get()
            ->filter(fn (User $user) => $user->processed_orders_count > 0)
            ->sortByDesc('processed_orders_count')
            ->values();
    }
}; ?>

<section class="w-full space-y-8">
    <div>
        <flux:heading size="xl">{{ __('Reports') }}</flux:heading>
        <flux:subheading>{{ __('Sales performance based on completed orders') }}</flux:subheading>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <flux:card class="space-y-2">
            <flux:subheading>{{ __("Today's sales") }}</flux:subheading>
            <flux:heading size="xl">{{ Number::currency($this->todaySales['total'], in: 'MYR', locale: 'ms') }}</flux:heading>
            <flux:text>{{ trans_choice(':count order|:count orders', $this->todaySales['count'], ['count' => $this->todaySales['count']]) }}</flux:text>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:subheading>{{ __("This month's sales") }}</flux:subheading>
            <flux:heading size="xl">{{ Number::currency($this->monthSales['total'], in: 'MYR', locale: 'ms') }}</flux:heading>
            <flux:text>{{ trans_choice(':count order|:count orders', $this->monthSales['count'], ['count' => $this->monthSales['count']]) }}</flux:text>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:subheading>{{ __('Purata Masa Siap') }}</flux:subheading>
            <flux:heading size="xl">
                @if ($this->averageCompletionMinutes === null)
                    —
                @elseif ($this->averageCompletionMinutes >= 60)
                    {{ intdiv($this->averageCompletionMinutes, 60) }} {{ __('jam') }} {{ $this->averageCompletionMinutes % 60 }} {{ __('minit') }}
                @else
                    {{ $this->averageCompletionMinutes }} {{ __('minit') }}
                @endif
            </flux:heading>
            <flux:text>{{ __('Dari pesanan masuk hingga selesai') }}</flux:text>
        </flux:card>
    </div>

    <div>
        <flux:heading size="lg" class="mb-4">{{ __('Best-selling products') }}</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Product') }}</flux:table.column>
                <flux:table.column>{{ __('Units sold') }}</flux:table.column>
                <flux:table.column>{{ __('Revenue') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->bestSellingProducts as $row)
                    <flux:table.row wire:key="best-selling-{{ $row->name }}">
                        <flux:table.cell>{{ $row->name }}</flux:table.cell>
                        <flux:table.cell>{{ $row->units_sold }}</flux:table.cell>
                        <flux:table.cell>{{ Number::currency($row->revenue, in: 'MYR', locale: 'ms') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3">{{ __('No completed orders yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <div>
        <flux:heading size="lg" class="mb-4">{{ __('Staff performance') }}</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Staff') }}</flux:table.column>
                <flux:table.column>{{ __('Orders processed') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->staffPerformance as $staffMember)
                    <flux:table.row wire:key="staff-performance-{{ $staffMember->id }}">
                        <flux:table.cell>{{ $staffMember->name }}</flux:table.cell>
                        <flux:table.cell>{{ $staffMember->processed_orders_count }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="2">{{ __('No orders processed yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
