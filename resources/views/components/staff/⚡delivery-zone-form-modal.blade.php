<?php

use App\Models\DeliveryZone;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public ?DeliveryZone $deliveryZone = null;

    public string $name = '';

    public string $fee = '';

    public bool $isActive = true;

    #[On('open-delivery-zone-form')]
    public function open(?int $deliveryZoneId = null): void
    {
        $this->resetValidation();
        $this->deliveryZone = $deliveryZoneId ? DeliveryZone::findOrFail($deliveryZoneId) : null;

        if ($this->deliveryZone) {
            $this->authorize('update', $this->deliveryZone);
            $this->name = $this->deliveryZone->name;
            $this->fee = (string) $this->deliveryZone->fee;
            $this->isActive = $this->deliveryZone->is_active;
        } else {
            $this->authorize('create', DeliveryZone::class);
            $this->name = '';
            $this->fee = '';
            $this->isActive = true;
        }

        Flux::modal('delivery-zone-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'fee' => ['required', 'numeric', 'min:0'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'fee' => $validated['fee'],
            'is_active' => $this->isActive,
        ];

        if ($this->deliveryZone) {
            $this->authorize('update', $this->deliveryZone);
            $this->deliveryZone->update($attributes);

            Flux::toast(variant: 'success', text: __('Kawasan penghantaran dikemas kini.'));
        } else {
            $this->authorize('create', DeliveryZone::class);
            DeliveryZone::create($attributes);

            Flux::toast(variant: 'success', text: __('Kawasan penghantaran ditambah.'));
        }

        Flux::modal('delivery-zone-form')->close();
        $this->dispatch('delivery-zone-saved');
    }
}; ?>

<flux:modal name="delivery-zone-form" class="max-w-lg">
    <form wire:submit="save" class="space-y-6">
        <flux:heading size="lg">{{ $deliveryZone ? __('Edit kawasan penghantaran') : __('Tambah kawasan penghantaran') }}</flux:heading>

        <flux:input wire:model="name" :label="__('Nama kawasan')" placeholder="{{ __('cth: Dalam Bandar') }}" required autofocus />

        <flux:input wire:model="fee" :label="__('Caj penghantaran (RM)')" type="number" step="0.01" min="0" required />

        <flux:switch wire:model="isActive" :label="__('Aktif (boleh dipilih pelanggan)')" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Batal') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="primary" type="submit">{{ __('Simpan') }}</flux:button>
        </div>
    </form>
</flux:modal>
