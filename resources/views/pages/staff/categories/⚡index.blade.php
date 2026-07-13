<?php

use App\Models\Category;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Categories')] class extends Component {
    public function mount(): void
    {
        $this->authorize('viewAny', Category::class);
    }

    #[Computed]
    public function categories()
    {
        return Category::withCount('products')->orderBy('name')->get();
    }

    public function delete(int $categoryId): void
    {
        $category = Category::findOrFail($categoryId);

        $this->authorize('delete', $category);

        $category->delete();
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Categories') }}</flux:heading>
            <flux:subheading>{{ __('Organise products into categories for the storefront') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="$dispatch('open-category-form')">
            {{ __('Add category') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Products') }}</flux:table.column>
            <flux:table.column>{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->categories as $category)
                <flux:table.row wire:key="category-{{ $category->id }}">
                    <flux:table.cell>{{ $category->name }}</flux:table.cell>
                    <flux:table.cell>{{ $category->products_count }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
                            <flux:button size="sm" wire:click="$dispatch('open-category-form', { categoryId: {{ $category->id }} })">
                                {{ __('Edit') }}
                            </flux:button>

                            @if ($category->products_count === 0)
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    wire:click="delete({{ $category->id }})"
                                    wire:confirm="{{ __('Delete this category?') }}"
                                >
                                    {{ __('Delete') }}
                                </flux:button>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <livewire:staff.category-form-modal />
</section>
