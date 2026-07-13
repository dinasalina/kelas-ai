<?php

use App\Actions\Orders\UpdateOrderStatusAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public ?Order $order = null;

    public string $targetStatus = '';

    public string $note = '';

    #[On('open-order-timeline')]
    public function open(int $orderId): void
    {
        $this->order = Order::with(['product', 'statusHistories.changedByStaff'])->findOrFail($orderId);

        $this->authorize('view', $this->order);

        $this->resetValidation();
        $this->targetStatus = $this->order->status->next()?->value ?? '';
        $this->note = '';

        Flux::modal('order-timeline')->show();
    }

    /**
     * Statuses this order may move to: forward stages plus cancellation.
     *
     * @return array<int, OrderStatus>
     */
    public function availableTransitions(): array
    {
        if (! $this->order || $this->order->status->isFinal()) {
            return [];
        }

        $forward = array_slice(OrderStatus::flow(), $this->order->status->flowIndex() + 1);

        return [...$forward, OrderStatus::Cancelled];
    }

    public function updateStatus(UpdateOrderStatusAction $updateStatus): void
    {
        if (! $this->order) {
            return;
        }

        $this->authorize('update', $this->order);

        $validated = $this->validate([
            'targetStatus' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $updateStatus(
                $this->order,
                Auth::user(),
                OrderStatus::from($validated['targetStatus']),
                $validated['note'] ?: null,
            );
        } catch (\InvalidArgumentException $exception) {
            $this->addError('targetStatus', $exception->getMessage());

            return;
        }

        $this->order->refresh()->load(['product', 'statusHistories.changedByStaff']);
        $this->targetStatus = $this->order->status->next()?->value ?? '';
        $this->note = '';

        Flux::toast(variant: 'success', text: __('Status pesanan dikemas kini.'));
        $this->dispatch('order-status-updated');
    }
}; ?>

<flux:modal name="order-timeline" class="max-w-xl">
    @if ($order)
        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ $order->order_number ?? '#'.str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</flux:heading>
                    <flux:subheading>
                        {{ $order->product->name }} × {{ $order->quantity }} · {{ Number::currency($order->total_price, in: 'MYR', locale: 'ms') }}
                    </flux:subheading>
                    <flux:text size="sm">{{ $order->customer_name }} · {{ $order->customer_phone }}</flux:text>
                </div>

                <flux:badge :color="$order->status->color()">{{ $order->status->label() }}</flux:badge>
            </div>

            <div class="max-h-64 space-y-0 overflow-y-auto">
                @foreach ($order->statusHistories as $history)
                    <div class="relative flex gap-3 pb-5 last:pb-0" wire:key="history-{{ $history->id }}">
                        @unless ($loop->last)
                            <span class="absolute top-3 left-1.25 h-full w-px bg-zinc-200 dark:bg-zinc-700"></span>
                        @endunless

                        <span class="relative mt-1.5 block size-2.5 shrink-0 rounded-full {{ $loop->last ? 'bg-zinc-900 dark:bg-white' : 'bg-zinc-300 dark:bg-zinc-600' }}"></span>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-baseline justify-between gap-2">
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $history->to_status->label() }}</p>
                                <p class="shrink-0 text-xs text-zinc-400">{{ $history->created_at->format('d/m/Y h:i A') }}</p>
                            </div>

                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $history->changedByStaff?->name ?? __('Pelanggan (storefront)') }}
                            </p>

                            @if ($history->note)
                                <p class="mt-1 rounded-lg bg-zinc-100 px-3 py-1.5 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ $history->note }}
                                </p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($this->availableTransitions() !== [])
                <form wire:submit="updateStatus" class="space-y-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:select wire:model="targetStatus" :label="__('Tukar status kepada')">
                        @foreach ($this->availableTransitions() as $transition)
                            <flux:select.option value="{{ $transition->value }}">{{ $transition->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="note" :label="__('Nota (pilihan)')" placeholder="{{ __('cth: Rider dah ambil, sampai ~30 minit') }}" />

                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="filled">{{ __('Tutup') }}</flux:button>
                        </flux:modal.close>

                        <flux:button variant="primary" type="submit">{{ __('Kemas Kini Status') }}</flux:button>
                    </div>
                </form>
            @else
                <div class="flex justify-end border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Tutup') }}</flux:button>
                    </flux:modal.close>
                </div>
            @endif
        </div>
    @endif
</flux:modal>
