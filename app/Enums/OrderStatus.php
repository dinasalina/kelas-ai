<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case Delivering = 'delivering';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Confirmed => 'Disahkan',
            self::Preparing => 'Sedang Disediakan',
            self::Delivering => 'Dalam Penghantaran',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * Badge colour used across the UI for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Confirmed => 'amber',
            self::Preparing => 'blue',
            self::Delivering => 'purple',
            self::Completed => 'lime',
            self::Cancelled => 'red',
        };
    }

    /**
     * The next status in the forward-only order lifecycle, or null if already at a final status.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::Pending => self::Confirmed,
            self::Confirmed => self::Preparing,
            self::Preparing => self::Delivering,
            self::Delivering => self::Completed,
            self::Completed, self::Cancelled => null,
        };
    }

    /**
     * Whether the status is terminal (no further transitions allowed).
     */
    public function isFinal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    /**
     * The ordered list of statuses in the normal (non-cancelled) fulfilment flow.
     *
     * @return array<int, self>
     */
    public static function flow(): array
    {
        return [
            self::Pending,
            self::Confirmed,
            self::Preparing,
            self::Delivering,
            self::Completed,
        ];
    }

    /**
     * Zero-based position of this status within the normal flow, or null for Cancelled.
     */
    public function flowIndex(): ?int
    {
        $index = array_search($this, self::flow(), strict: true);

        return $index === false ? null : $index;
    }
}
