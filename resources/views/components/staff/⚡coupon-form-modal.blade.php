<?php

use App\Enums\CouponType;
use App\Models\Coupon;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public ?Coupon $coupon = null;

    public string $code = '';

    public CouponType $type = CouponType::Percentage;

    public string $value = '';

    public string $minOrderAmount = '';

    public string $expiresAt = '';

    public bool $isActive = true;

    #[On('open-coupon-form')]
    public function open(?int $couponId = null): void
    {
        $this->resetValidation();
        $this->coupon = $couponId ? Coupon::findOrFail($couponId) : null;

        if ($this->coupon) {
            $this->authorize('update', $this->coupon);
            $this->code = $this->coupon->code;
            $this->type = $this->coupon->type;
            $this->value = (string) $this->coupon->value;
            $this->minOrderAmount = (string) ($this->coupon->min_order_amount ?? '');
            $this->expiresAt = $this->coupon->expires_at?->format('Y-m-d') ?? '';
            $this->isActive = $this->coupon->is_active;
        } else {
            $this->authorize('create', Coupon::class);
            $this->code = '';
            $this->type = CouponType::Percentage;
            $this->value = '';
            $this->minOrderAmount = '';
            $this->expiresAt = '';
            $this->isActive = true;
        }

        Flux::modal('coupon-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:50'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => $this->type === CouponType::Percentage
                ? ['required', 'numeric', 'min:0', 'max:100']
                : ['required', 'numeric', 'min:0'],
            'minOrderAmount' => ['nullable', 'numeric', 'min:0'],
            'expiresAt' => ['nullable', 'date'],
        ]);

        $code = strtoupper(trim($validated['code']));

        $codeRule = $this->coupon
            ? Rule::unique('coupons', 'code')->ignore($this->coupon->id)
            : Rule::unique('coupons', 'code');

        Validator::make(['code' => $code], ['code' => $codeRule], [
            'code.unique' => __('Kod kupon ini telah wujud.'),
        ])->validate();

        $attributes = [
            'code' => $code,
            'type' => $this->type,
            'value' => $validated['value'],
            'min_order_amount' => $validated['minOrderAmount'] !== '' ? $validated['minOrderAmount'] : null,
            'expires_at' => $validated['expiresAt'] !== '' ? Carbon::parse($validated['expiresAt'])->endOfDay() : null,
            'is_active' => $this->isActive,
        ];

        if ($this->coupon) {
            $this->authorize('update', $this->coupon);

            $this->coupon->update($attributes);

            Flux::toast(variant: 'success', text: __('Kupon dikemaskini.'));
        } else {
            $this->authorize('create', Coupon::class);

            Coupon::create($attributes);

            Flux::toast(variant: 'success', text: __('Kupon dicipta.'));
        }

        Flux::modal('coupon-form')->close();
        $this->dispatch('coupon-saved');
    }
}; ?>

<flux:modal name="coupon-form" class="max-w-lg">
    <form wire:submit="save" class="space-y-6">
        <flux:heading size="lg">{{ $coupon ? __('Edit kupon') : __('Tambah kupon') }}</flux:heading>

        <flux:input wire:model="code" :label="__('Kod')" required autofocus />

        <flux:select wire:model="type" :label="__('Jenis')">
            @foreach (CouponType::cases() as $couponType)
                <flux:select.option value="{{ $couponType->value }}">{{ $couponType->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input
            wire:model="value"
            :label="$type === App\Enums\CouponType::Percentage ? __('Nilai (%)') : __('Nilai (RM)')"
            type="number"
            step="0.01"
            min="0"
            required
        />

        <flux:input wire:model="minOrderAmount" :label="__('Jumlah pesanan minimum (RM, pilihan)')" type="number" step="0.01" min="0" />

        <flux:input wire:model="expiresAt" :label="__('Tarikh luput (pilihan)')" type="date" />

        <flux:switch wire:model="isActive" :label="__('Aktif')" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Batal') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="primary" type="submit">{{ __('Simpan') }}</flux:button>
        </div>
    </form>
</flux:modal>
