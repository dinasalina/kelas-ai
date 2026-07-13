<?php

use App\Models\Category;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public ?Category $category = null;

    public string $name = '';

    public string $description = '';

    #[On('open-category-form')]
    public function open(?int $categoryId = null): void
    {
        $this->resetValidation();
        $this->category = $categoryId ? Category::findOrFail($categoryId) : null;

        if ($this->category) {
            $this->authorize('update', $this->category);
            $this->name = $this->category->name;
            $this->description = (string) $this->category->description;
        } else {
            $this->authorize('create', Category::class);
            $this->name = '';
            $this->description = '';
        }

        Flux::modal('category-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $slug = Str::slug($validated['name']);

        $slugRule = $this->category
            ? Rule::unique('categories', 'slug')->ignore($this->category->id)
            : Rule::unique('categories', 'slug');

        Validator::make(['slug' => $slug], ['slug' => $slugRule], [
            'slug.unique' => __('A category with this name already exists.'),
        ])->validate();

        if ($this->category) {
            $this->authorize('update', $this->category);

            $this->category->update([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'],
            ]);

            Flux::toast(variant: 'success', text: __('Category updated.'));
        } else {
            $this->authorize('create', Category::class);

            Category::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'],
            ]);

            Flux::toast(variant: 'success', text: __('Category created.'));
        }

        Flux::modal('category-form')->close();
        $this->dispatch('category-saved');
    }
}; ?>

<flux:modal name="category-form" class="max-w-lg">
    <form wire:submit="save" class="space-y-6">
        <flux:heading size="lg">{{ $category ? __('Edit category') : __('Add category') }}</flux:heading>

        <flux:input wire:model="name" :label="__('Name')" required autofocus />

        <flux:textarea wire:model="description" :label="__('Description')" />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
        </div>
    </form>
</flux:modal>
