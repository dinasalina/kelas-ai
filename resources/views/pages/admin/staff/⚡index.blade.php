<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Staff Accounts')] class extends Component {
    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    #[Computed]
    public function users()
    {
        return User::orderBy('name')->get();
    }

    public function toggleActive(int $userId): void
    {
        $user = User::findOrFail($userId);

        $this->authorize('update', $user);

        $user->is_active = ! $user->is_active;
        $user->save();
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Staff Accounts') }}</flux:heading>
            <flux:subheading>{{ __('Manage admin and staff access to the backoffice') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="$dispatch('open-staff-user-form')">
            {{ __('Add staff') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->users as $user)
                <flux:table.row wire:key="user-{{ $user->id }}">
                    <flux:table.cell>{{ $user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$user->isAdmin() ? 'purple' : 'blue'">{{ $user->role->value }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$user->is_active ? 'lime' : 'zinc'">
                            {{ $user->is_active ? __('Active') : __('Deactivated') }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
                            <flux:button size="sm" wire:click="$dispatch('open-staff-user-form', { userId: {{ $user->id }} })">
                                {{ __('Edit') }}
                            </flux:button>

                            @unless ($user->is(Auth::user()))
                                <flux:button
                                    size="sm"
                                    variant="{{ $user->is_active ? 'danger' : 'primary' }}"
                                    wire:click="toggleActive({{ $user->id }})"
                                    wire:confirm="{{ $user->is_active ? __('Deactivate this account?') : __('Reactivate this account?') }}"
                                >
                                    {{ $user->is_active ? __('Deactivate') : __('Reactivate') }}
                                </flux:button>
                            @endunless
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <livewire:admin.staff-user-form-modal />
</section>
