<?php

use App\Models\DeliveryZone;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kawasan Penghantaran')] class extends Component {
    public function mount(): void
    {
        $this->authorize('viewAny', DeliveryZone::class);
    }

    #[Computed]
    public function deliveryZones()
    {
        return DeliveryZone::withCount('orders')->orderBy('name')->get();
    }

    public function delete(int $deliveryZoneId): void
    {
        $deliveryZone = DeliveryZone::findOrFail($deliveryZoneId);

        $this->authorize('delete', $deliveryZone);

        $deliveryZone->delete();
    }

    #[On('delivery-zone-saved')]
    public function refreshDeliveryZones(): void
    {
        // Recomputes the computed property on the next render.
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Kawasan Penghantaran') }}</flux:heading>
            <flux:subheading>{{ __('Tetapkan kawasan dan caj penghantaran untuk tempahan') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="$dispatch('open-delivery-zone-form')">
            {{ __('Tambah kawasan') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Nama') }}</flux:table.column>
            <flux:table.column>{{ __('Caj') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Pesanan') }}</flux:table.column>
            <flux:table.column>{{ __('Tindakan') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->deliveryZones as $zone)
                <flux:table.row wire:key="zone-{{ $zone->id }}">
                    <flux:table.cell>{{ $zone->name }}</flux:table.cell>
                    <flux:table.cell>{{ Number::currency($zone->fee, in: 'MYR', locale: 'ms') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$zone->is_active ? 'lime' : 'zinc'">
                            {{ $zone->is_active ? __('Aktif') : __('Tidak Aktif') }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $zone->orders_count }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
                            <flux:button size="sm" wire:click="$dispatch('open-delivery-zone-form', { deliveryZoneId: {{ $zone->id }} })">
                                {{ __('Edit') }}
                            </flux:button>

                            @if ($zone->orders_count === 0)
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    wire:click="delete({{ $zone->id }})"
                                    wire:confirm="{{ __('Padam kawasan ini?') }}"
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

    <livewire:staff.delivery-zone-form-modal />
</section>
