<?php

use App\Models\Order;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::storefront')] #[Title('Jejak Pesanan')] class extends Component {
    public string $orderNumber = '';

    public string $phone = '';

    public function track(): void
    {
        $validated = $this->validate([
            'orderNumber' => ['required', 'string', 'max:30'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $order = Order::query()
            ->whereRaw('UPPER(order_number) = ?', [strtoupper(trim($validated['orderNumber']))])
            ->first();

        $phoneDigits = preg_replace('/\D/', '', $validated['phone']);

        if (! $order || $phoneDigits === '' || preg_replace('/\D/', '', $order->customer_phone) !== $phoneDigits) {
            $this->addError('orderNumber', __('Tiada pesanan dijumpai dengan kombinasi no. pesanan dan no. telefon ini.'));

            return;
        }

        $this->redirect(URL::signedRoute('receipt.show', $order));
    }
}; ?>

<div class="mx-auto max-w-md space-y-8 py-16">
    <div class="space-y-3 text-center">
        <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-zinc-900 dark:bg-white">
            <flux:icon.magnifying-glass class="size-7 text-white dark:text-zinc-900" />
        </div>

        <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ __('Jejak Pesanan Anda') }}</h1>

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Masukkan no. pesanan (tertera pada resit anda) dan no. telefon yang digunakan semasa tempahan.') }}
        </p>
    </div>

    <form wire:submit="track" class="space-y-4 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:input wire:model="orderNumber" :label="__('No. Pesanan')" placeholder="ORD-20260713-XXXX" required autofocus />

        <flux:input wire:model="phone" :label="__('No. Telefon')" type="tel" placeholder="012-3456789" required />

        <flux:button variant="primary" type="submit" class="w-full" icon="magnifying-glass">
            {{ __('Semak Status') }}
        </flux:button>
    </form>

    <p class="text-center text-sm text-zinc-400 dark:text-zinc-500">
        <a href="{{ route('home') }}" class="underline-offset-2 hover:underline" wire:navigate>{{ __('← Kembali ke halaman utama') }}</a>
    </p>
</div>
