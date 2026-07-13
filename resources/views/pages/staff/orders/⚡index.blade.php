<?php

use App\Actions\Orders\UpdateOrderStatusAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Pesanan')] class extends Component {
    #[Url]
    public string $view = 'kanban';

    #[Url]
    public ?string $status = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Order::class);
    }

    /**
     * Orders for the table view.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Order>
     */
    #[Computed]
    public function orders(): \Illuminate\Database\Eloquent\Collection
    {
        return Order::query()
            ->with(['product', 'placedByStaff'])
            ->when($this->status, fn ($query) => $query->where('status', $this->status))
            ->latest()
            ->get();
    }

    /**
     * Orders grouped by status for the kanban view.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Database\Eloquent\Collection<int, Order>>
     */
    #[Computed]
    public function ordersByStatus(): \Illuminate\Support\Collection
    {
        return Order::query()
            ->with(['product', 'placedByStaff'])
            ->where('status', '!=', OrderStatus::Cancelled)
            ->latest()
            ->get()
            ->groupBy(fn (Order $order) => $order->status->value);
    }

    #[Computed]
    public function cancelledCount(): int
    {
        return Order::query()->where('status', OrderStatus::Cancelled)->count();
    }

    /**
     * Handle a kanban card being dropped into a status column.
     */
    public function handleSort(int $orderId, int $position, string $statusValue, UpdateOrderStatusAction $updateStatus): void
    {
        $order = Order::findOrFail($orderId);

        $this->authorize('update', $order);

        $target = OrderStatus::from($statusValue);

        if ($target === $order->status) {
            return;
        }

        try {
            $updateStatus($order, Auth::user(), $target);
        } catch (\InvalidArgumentException) {
            Flux::toast(variant: 'danger', text: __('Pesanan hanya boleh bergerak ke hadapan. Guna butang jejak untuk butiran.'));
        }
    }

    public function advanceStatus(int $orderId, UpdateOrderStatusAction $updateStatus): void
    {
        $order = Order::findOrFail($orderId);

        $this->authorize('update', $order);

        try {
            $updateStatus($order, Auth::user());
        } catch (\InvalidArgumentException) {
            Flux::toast(variant: 'danger', text: __('Pesanan ini telah mencapai status akhir.'));
        }
    }

    #[On('order-status-updated')]
    #[On('staff-order-placed')]
    public function refreshBoard(): void
    {
        // Recomputes the computed properties on the next render.
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Pesanan') }}</flux:heading>
            <flux:subheading>{{ __('Seret kad untuk kemas kini status, atau klik kad untuk timeline penuh') }}</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <div class="flex gap-1">
                <flux:button size="sm" :variant="$view === 'kanban' ? 'primary' : 'filled'" icon="view-columns" wire:click="$set('view', 'kanban')">
                    {{ __('Kanban') }}
                </flux:button>
                <flux:button size="sm" :variant="$view === 'table' ? 'primary' : 'filled'" icon="table-cells" wire:click="$set('view', 'table')">
                    {{ __('Jadual') }}
                </flux:button>
            </div>

            <flux:button variant="primary" icon="plus" wire:click="$dispatch('open-staff-order-form')">
                {{ __('Pesanan Manual') }}
            </flux:button>
        </div>
    </div>

    @if ($view === 'kanban')
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach (\App\Enums\OrderStatus::flow() as $columnStatus)
                <div class="flex flex-col rounded-2xl border border-zinc-200 bg-zinc-100/60 dark:border-zinc-700 dark:bg-zinc-800/40" wire:key="column-{{ $columnStatus->value }}">
                    <div class="flex items-center justify-between px-4 py-3">
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" :color="$columnStatus->color()">{{ $columnStatus->label() }}</flux:badge>
                        </div>
                        <span class="text-xs font-semibold text-zinc-400">
                            {{ $this->ordersByStatus->get($columnStatus->value)?->count() ?? 0 }}
                        </span>
                    </div>

                    <div
                        class="flex min-h-32 flex-1 flex-col gap-3 px-3 pb-3"
                        wire:sort="handleSort"
                        wire:sort:group="orders"
                        wire:sort:group-id="{{ $columnStatus->value }}"
                    >
                        @foreach ($this->ordersByStatus->get($columnStatus->value) ?? [] as $order)
                            <div
                                wire:key="kanban-order-{{ $order->id }}"
                                wire:sort:item="{{ $order->id }}"
                                class="cursor-grab rounded-xl border border-zinc-200 bg-white p-3.5 shadow-sm transition hover:shadow-md active:cursor-grabbing dark:border-zinc-700 dark:bg-zinc-900"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-xs font-semibold text-zinc-400">{{ $order->order_number }}</p>
                                    <p class="shrink-0 text-xs text-zinc-400">{{ $order->created_at->diffForHumans(short: true) }}</p>
                                </div>

                                <p class="mt-1 truncate text-sm font-semibold text-zinc-900 dark:text-white">
                                    {{ $order->product->name }} × {{ $order->quantity }}
                                </p>

                                <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $order->customer_name }}</p>

                                <div class="mt-2.5 flex items-center justify-between gap-2" wire:sort:ignore>
                                    <span class="text-sm font-bold text-zinc-900 dark:text-white">
                                        {{ Number::currency($order->total_price, in: 'MYR', locale: 'ms') }}
                                    </span>

                                    <div class="flex gap-1">
                                        <flux:button size="xs" icon="clock" wire:click="$dispatch('open-order-timeline', { orderId: {{ $order->id }} })" :tooltip="__('Jejak & tukar status')" />
                                        <flux:button size="xs" icon="printer" :href="route('receipt.show', $order)" target="_blank" :tooltip="__('Resit')" />
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        @if ($this->cancelledCount > 0)
            <button type="button" wire:click="$set('view', 'table')" class="text-sm text-zinc-500 underline-offset-2 hover:underline dark:text-zinc-400">
                {{ __(':count pesanan dibatalkan — lihat dalam paparan jadual', ['count' => $this->cancelledCount]) }}
            </button>
        @endif
    @else
        <div class="flex flex-wrap gap-2">
            <flux:button size="sm" :variant="$status === null ? 'primary' : 'filled'" wire:click="$set('status', null)">
                {{ __('Semua') }}
            </flux:button>

            @foreach (\App\Enums\OrderStatus::cases() as $case)
                <flux:button size="sm" :variant="$status === $case->value ? 'primary' : 'filled'" wire:click="$set('status', '{{ $case->value }}')">
                    {{ $case->label() }}
                </flux:button>
            @endforeach
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('No. Pesanan') }}</flux:table.column>
                <flux:table.column>{{ __('Produk') }}</flux:table.column>
                <flux:table.column>{{ __('Pelanggan') }}</flux:table.column>
                <flux:table.column>{{ __('Jumlah') }}</flux:table.column>
                <flux:table.column>{{ __('Sumber') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Tindakan') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->orders as $order)
                    <flux:table.row wire:key="order-{{ $order->id }}">
                        <flux:table.cell class="whitespace-nowrap text-xs font-semibold text-zinc-500">{{ $order->order_number }}</flux:table.cell>
                        <flux:table.cell>{{ $order->product->name }} × {{ $order->quantity }}</flux:table.cell>
                        <flux:table.cell>
                            <div>{{ $order->customer_name }}</div>
                            <flux:text size="sm">{{ $order->customer_phone }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>{{ Number::currency($order->total_price, in: 'MYR', locale: 'ms') }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $order->isGuestOrder() ? __('Pelanggan') : ($order->placedByStaff->name ?? '-') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$order->status->color()">{{ $order->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-2">
                                @if ($order->status->next())
                                    <flux:button size="sm" wire:click="advanceStatus({{ $order->id }})">
                                        {{ $order->status->next()->label() }} →
                                    </flux:button>
                                @endif

                                <flux:button size="sm" icon="clock" wire:click="$dispatch('open-order-timeline', { orderId: {{ $order->id }} })">
                                    {{ __('Jejak') }}
                                </flux:button>

                                <flux:button size="sm" icon="printer" :href="route('receipt.show', $order)" target="_blank">
                                    {{ __('Resit') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <livewire:staff.order-form-modal />
    <livewire:staff.order-timeline-modal />
</section>
