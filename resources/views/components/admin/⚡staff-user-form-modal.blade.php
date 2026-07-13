<?php

use App\Concerns\PasswordValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'staff';

    public string $password = '';

    public string $password_confirmation = '';

    #[On('open-staff-user-form')]
    public function open(?int $userId = null): void
    {
        $this->resetValidation();
        $this->user = $userId ? User::findOrFail($userId) : null;

        if ($this->user) {
            $this->authorize('update', $this->user);
            $this->name = $this->user->name;
            $this->email = $this->user->email;
            $this->role = $this->user->role->value;
        } else {
            $this->authorize('create', User::class);
            $this->name = '';
            $this->email = '';
            $this->role = UserRole::Staff->value;
        }

        $this->password = '';
        $this->password_confirmation = '';

        Flux::modal('staff-user-form')->show();
    }

    public function save(): void
    {
        $emailRule = $this->user
            ? Rule::unique('users', 'email')->ignore($this->user->id)
            : Rule::unique('users', 'email');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', $emailRule],
            'role' => ['required', Rule::enum(UserRole::class)],
        ];

        if (! $this->user) {
            $rules['password'] = $this->passwordRules();
        }

        $validated = $this->validate($rules);

        if ($this->user) {
            $this->authorize('update', $this->user);

            $this->user->name = $validated['name'];
            $this->user->email = $validated['email'];
            $this->user->role = UserRole::from($validated['role']);
            $this->user->save();

            Flux::toast(variant: 'success', text: __('Staff account updated.'));
        } else {
            $this->authorize('create', User::class);

            $newUser = new User([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);
            $newUser->role = UserRole::from($validated['role']);
            $newUser->is_active = true;
            $newUser->email_verified_at = now();
            $newUser->save();

            Flux::toast(variant: 'success', text: __('Staff account created.'));
        }

        Flux::modal('staff-user-form')->close();
        $this->dispatch('staff-user-saved');
    }
}; ?>

<flux:modal name="staff-user-form" class="max-w-lg">
    <form wire:submit="save" class="space-y-6">
        <flux:heading size="lg">{{ $user ? __('Edit staff account') : __('Add staff account') }}</flux:heading>

        <flux:input wire:model="name" :label="__('Name')" required autofocus />

        <flux:input wire:model="email" :label="__('Email')" type="email" required />

        <flux:select wire:model="role" :label="__('Role')">
            <flux:select.option value="staff">{{ __('Staff') }}</flux:select.option>
            <flux:select.option value="admin">{{ __('Admin') }}</flux:select.option>
        </flux:select>

        @unless ($user)
            <flux:input wire:model="password" :label="__('Password')" type="password" required viewable />

            <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required viewable />
        @endunless

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
        </div>
    </form>
</flux:modal>
