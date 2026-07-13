<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="flex min-h-screen flex-col bg-zinc-50 antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <flux:header class="sticky top-0 z-20 border-b border-zinc-200 bg-white/80 backdrop-blur-md dark:border-zinc-700 dark:bg-zinc-900/80">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-semibold" wire:navigate>
                <span class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
                    <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
                </span>
                <span class="text-lg">{{ config('app.name') }}</span>
            </a>

            <flux:spacer />

            <flux:button size="sm" variant="subtle" icon="magnifying-glass" :href="route('track')" wire:navigate>
                {{ __('Jejak Pesanan') }}
            </flux:button>

            @auth
                <flux:button size="sm" variant="subtle" icon="squares-2x2" :href="route('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:button>
            @else
                <flux:button size="sm" variant="subtle" icon="arrow-right-end-on-rectangle" :href="route('login')" wire:navigate>
                    {{ __('Log Masuk Staf') }}
                </flux:button>
            @endauth
        </flux:header>

        <flux:main container class="flex-1">
            {{ $slot }}
        </flux:main>

        <footer class="border-t border-zinc-200 py-6 dark:border-zinc-700">
            <div class="mx-auto max-w-7xl px-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                © {{ date('Y') }} {{ config('app.name') }} · {{ __('Bayaran secara Tunai Semasa Penghantaran (COD)') }}
            </div>
        </footer>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
