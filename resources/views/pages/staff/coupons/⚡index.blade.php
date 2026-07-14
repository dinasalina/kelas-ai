<?php

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Coupons')] class extends Component {
    public function mount(): void
    {
        $this->authorize('viewAny', Coupon::class);
    }

    #[Computed]
    public function coupons()
    {
        return Coupon::withCount('orders')->orderBy('code')->get();
    }

    public function delete(int $couponId): void
    {
        $coupon = Coupon::findOrFail($couponId);

        $this->authorize('delete', $coupon);

        $coupon->delete();
    }

    #[On('coupon-saved')]
    public function refreshCoupons(): void
    {
        // Recomputes the computed property on the next render.
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Kupon') }}</flux:heading>
            <flux:subheading>{{ __('Urus kod kupon diskaun untuk pesanan') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="$dispatch('open-coupon-form')">
            {{ __('Tambah kupon') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Kod') }}</flux:table.column>
            <flux:table.column>{{ __('Jenis') }}</flux:table.column>
            <flux:table.column>{{ __('Nilai') }}</flux:table.column>
            <flux:table.column>{{ __('Min. Pesanan') }}</flux:table.column>
            <flux:table.column>{{ __('Luput') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Digunakan') }}</flux:table.column>
            <flux:table.column>{{ __('Tindakan') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->coupons as $coupon)
                <flux:table.row wire:key="coupon-{{ $coupon->id }}">
                    <flux:table.cell class="font-mono">{{ $coupon->code }}</flux:table.cell>
                    <flux:table.cell>{{ $coupon->type->label() }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $coupon->type === CouponType::Percentage ? rtrim(rtrim($coupon->value, '0'), '.').'%' : Number::currency($coupon->value, in: 'MYR', locale: 'ms') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $coupon->min_order_amount !== null ? Number::currency($coupon->min_order_amount, in: 'MYR', locale: 'ms') : '-' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $coupon->expires_at?->format('d/m/Y') ?? '-' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$coupon->is_active ? 'lime' : 'zinc'">
                            {{ $coupon->is_active ? __('Aktif') : __('Tidak aktif') }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ __(':count kali', ['count' => $coupon->orders_count]) }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
                            <flux:button size="sm" wire:click="$dispatch('open-coupon-form', { couponId: {{ $coupon->id }} })">
                                {{ __('Edit') }}
                            </flux:button>

                            @if ($coupon->orders_count === 0)
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    wire:click="delete({{ $coupon->id }})"
                                    wire:confirm="{{ __('Padam kupon ini?') }}"
                                >
                                    {{ __('Padam') }}
                                </flux:button>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <livewire:staff.coupon-form-modal />
</section>
