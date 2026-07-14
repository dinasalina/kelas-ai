<?php

use App\Models\Category;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public ?Product $product = null;

    public int $categoryId = 0;

    public string $name = '';

    public string $description = '';

    public string $price = '';

    public string $costPrice = '';

    public int $stock = 0;

    public bool $isActive = true;

    public $image = null;

    #[On('open-product-form')]
    public function open(?int $productId = null): void
    {
        $this->resetValidation();
        $this->image = null;
        $this->product = $productId ? Product::findOrFail($productId) : null;

        if ($this->product) {
            $this->authorize('update', $this->product);
            $this->categoryId = $this->product->category_id;
            $this->name = $this->product->name;
            $this->description = (string) $this->product->description;
            $this->price = (string) $this->product->price;
            $this->costPrice = (string) $this->product->cost_price;
            $this->stock = $this->product->stock;
            $this->isActive = $this->product->is_active;
        } else {
            $this->authorize('create', Product::class);
            $this->categoryId = (int) (Category::query()->value('id') ?? 0);
            $this->name = '';
            $this->description = '';
            $this->price = '';
            $this->costPrice = '';
            $this->stock = 0;
            $this->isActive = true;
        }

        Flux::modal('product-form')->show();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Category>
     */
    #[Computed]
    public function categories(): \Illuminate\Database\Eloquent\Collection
    {
        return Category::orderBy('name')->get();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'categoryId' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'costPrice' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        $imagePath = $this->product?->image_path;

        if ($this->image) {
            $imagePath = $this->image->store('products', 'public');
        }

        $attributes = [
            'category_id' => $validated['categoryId'],
            'name' => $validated['name'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'cost_price' => $validated['costPrice'] !== null && $validated['costPrice'] !== '' ? $validated['costPrice'] : 0,
            'image_path' => $imagePath,
            'stock' => $validated['stock'],
            'is_active' => $this->isActive,
        ];

        if ($this->product) {
            $this->authorize('update', $this->product);

            $attributes['slug'] = Str::slug($validated['name']).'-'.$this->product->id;
            $this->product->update($attributes);

            Flux::toast(variant: 'success', text: __('Product updated.'));
        } else {
            $this->authorize('create', Product::class);

            $attributes['slug'] = Str::slug($validated['name']).'-'.Str::random(6);
            Product::create($attributes);

            Flux::toast(variant: 'success', text: __('Product created.'));
        }

        Flux::modal('product-form')->close();
        $this->dispatch('product-saved');
    }
}; ?>

<flux:modal name="product-form" class="max-w-lg">
    <form wire:submit="save" class="space-y-6">
        <flux:heading size="lg">{{ $product ? __('Edit product') : __('Add product') }}</flux:heading>

        <flux:select wire:model="categoryId" :label="__('Category')">
            @foreach ($this->categories as $category)
                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="name" :label="__('Name')" required autofocus />

        <flux:textarea wire:model="description" :label="__('Description')" />

        <div class="grid grid-cols-2 gap-4">
            <flux:input wire:model="price" :label="__('Harga Jual (RM)')" type="number" step="0.01" min="0" required />
            <flux:input wire:model="costPrice" :label="__('Harga Modal (RM)')" type="number" step="0.01" min="0" :description="__('Untuk kiraan untung')" />
        </div>

        <flux:input wire:model="stock" :label="__('Stock')" type="number" min="0" required />

        <flux:switch wire:model="isActive" :label="__('Active (visible on storefront)')" />

        <flux:field>
            <flux:label>{{ __('Image') }}</flux:label>
            <input type="file" wire:model="image" accept="image/*" class="block w-full text-sm text-zinc-600 dark:text-zinc-400" />
            <flux:error name="image" />
        </flux:field>

        @if ($image)
            <img src="{{ $image->temporaryUrl() }}" class="h-24 w-24 rounded object-cover" alt="" />
        @elseif ($product?->image_path)
            <img src="{{ asset('storage/'.$product->image_path) }}" class="h-24 w-24 rounded object-cover" alt="" />
        @endif

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
        </div>
    </form>
</flux:modal>
