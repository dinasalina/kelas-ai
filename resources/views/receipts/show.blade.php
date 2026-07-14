@php
    use App\Enums\OrderStatus;
    use Illuminate\Support\Number;

    $subtotal = $order->unit_price * $order->quantity;
    $orderNumber = $order->order_number ?? '#'.str_pad($order->id, 6, '0', STR_PAD_LEFT);
    $reachedAt = $order->statusHistories->groupBy(fn ($history) => $history->to_status->value)->map->first();
    $currentIndex = $order->status->flowIndex();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>{{ __('Resit') }} {{ $orderNumber }} - {{ config('app.name') }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-zinc-100 p-4 text-zinc-900 antialiased print:bg-white print:p-0 sm:p-10">
        <div class="mx-auto max-w-md space-y-4">
            <div class="flex justify-end gap-2 print:hidden">
                <a href="{{ route('home') }}" class="rounded-md border border-zinc-300 bg-white px-4 py-2 text-sm font-medium hover:bg-zinc-50">
                    {{ __('Kembali') }}
                </a>
                <button onclick="window.print()" class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700">
                    {{ __('Cetak Resit') }}
                </button>
            </div>

            <div class="space-y-5 rounded-lg bg-white p-8 shadow print:hidden">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">{{ __('Status Pesanan') }}</h2>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $order->status === OrderStatus::Cancelled ? 'bg-red-100 text-red-700' : 'bg-zinc-900 text-white' }}">
                        {{ $order->status->label() }}
                    </span>
                </div>

                @if ($order->status === OrderStatus::Cancelled)
                    <div class="space-y-4">
                        @foreach ($order->statusHistories as $history)
                            <div class="relative flex gap-3 pb-1">
                                @unless ($loop->last)
                                    <span class="absolute top-4 left-1.5 h-full w-px bg-zinc-200"></span>
                                @endunless

                                <span class="relative mt-1 block size-3 shrink-0 rounded-full {{ $loop->last ? 'bg-red-500' : 'bg-zinc-300' }}"></span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <p class="text-sm font-medium">{{ $history->to_status->label() }}</p>
                                        <p class="shrink-0 text-xs text-zinc-400">{{ $history->created_at->format('d/m h:i A') }}</p>
                                    </div>

                                    @if ($history->note)
                                        <p class="text-xs text-zinc-500">{{ $history->note }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="space-y-0">
                        @foreach (OrderStatus::flow() as $step)
                            @php
                                $reached = $currentIndex !== null && $step->flowIndex() <= $currentIndex;
                                $history = $reachedAt->get($step->value);
                            @endphp

                            <div class="relative flex gap-3 pb-5 last:pb-0">
                                @unless ($loop->last)
                                    <span class="absolute top-4 left-1.5 h-full w-px {{ $reached ? 'bg-emerald-400' : 'bg-zinc-200' }}"></span>
                                @endunless

                                <span class="relative mt-0.5 flex size-3 shrink-0 items-center justify-center rounded-full {{ $reached ? 'bg-emerald-500' : 'bg-zinc-200' }}">
                                    @if ($reached)
                                        <span class="size-1 rounded-full bg-white"></span>
                                    @endif
                                </span>

                                <div class="min-w-0 flex-1 -translate-y-0.5">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <p class="text-sm {{ $reached ? 'font-semibold text-zinc-900' : 'text-zinc-400' }}">{{ $step->label() }}</p>
                                        @if ($history)
                                            <p class="shrink-0 text-xs text-zinc-400">{{ $history->created_at->format('d/m h:i A') }}</p>
                                        @endif
                                    </div>

                                    @if ($history?->note)
                                        <p class="text-xs text-zinc-500">{{ $history->note }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <p class="border-t border-dashed border-zinc-200 pt-3 text-xs text-zinc-400">
                    {{ __('Simpan pautan halaman ini atau gunakan No. Pesanan :number di halaman Jejak Pesanan untuk semakan semula.', ['number' => $orderNumber]) }}
                </p>
            </div>

            <div class="space-y-6 rounded-lg bg-white p-8 shadow print:rounded-none print:shadow-none">
                <div class="space-y-1 text-center">
                    <h1 class="text-xl font-bold">{{ config('app.name') }}</h1>
                    <p class="text-sm text-zinc-500">{{ __('Resit Tempahan') }}</p>
                </div>

                <div class="space-y-1 border-y border-dashed border-zinc-300 py-4 text-sm">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">{{ __('No. Pesanan') }}</span>
                        <span class="font-medium">{{ $orderNumber }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">{{ __('Tarikh') }}</span>
                        <span>{{ $order->created_at->format('d/m/Y h:i A') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">{{ __('Status') }}</span>
                        <span>{{ $order->status->label() }}</span>
                    </div>
                </div>

                <div class="space-y-1 text-sm">
                    <p class="font-medium">{{ __('Maklumat Pelanggan') }}</p>
                    <p>{{ $order->customer_name }}</p>
                    <p>{{ $order->customer_phone }}</p>
                    <p class="whitespace-pre-line text-zinc-600">{{ $order->customer_address }}</p>
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-300 text-left">
                            <th class="py-2 font-medium">{{ __('Produk') }}</th>
                            <th class="py-2 text-center font-medium">{{ __('Kuantiti') }}</th>
                            <th class="py-2 text-right font-medium">{{ __('Harga') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b border-dashed border-zinc-200">
                            <td class="py-2">{{ $order->product->name }}</td>
                            <td class="py-2 text-center">{{ $order->quantity }}</td>
                            <td class="py-2 text-right">{{ Number::currency($order->unit_price, in: 'MYR', locale: 'ms') }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">{{ __('Subjumlah') }}</span>
                        <span>{{ Number::currency($subtotal, in: 'MYR', locale: 'ms') }}</span>
                    </div>

                    @if ((float) $order->discount_amount > 0)
                        <div class="flex justify-between">
                            <span class="text-zinc-500">
                                {{ __('Diskaun') }}
                                @if ($order->coupon)
                                    ({{ $order->coupon->code }})
                                @endif
                            </span>
                            <span>-{{ Number::currency($order->discount_amount, in: 'MYR', locale: 'ms') }}</span>
                        </div>
                    @endif

                    <div class="flex justify-between">
                        <span class="text-zinc-500">
                            {{ __('Penghantaran') }}
                            @if ($order->deliveryZone)
                                ({{ $order->deliveryZone->name }})
                            @else
                                ({{ __('Ambil Sendiri') }})
                            @endif
                        </span>
                        <span>{{ (float) $order->delivery_fee > 0 ? '+'.Number::currency($order->delivery_fee, in: 'MYR', locale: 'ms') : __('Percuma') }}</span>
                    </div>

                    <div class="flex justify-between border-t border-zinc-300 pt-2 text-base font-bold">
                        <span>{{ __('Jumlah') }}</span>
                        <span>{{ Number::currency($order->total_price, in: 'MYR', locale: 'ms') }}</span>
                    </div>
                </div>

                <div class="space-y-1 border-t border-dashed border-zinc-300 pt-4 text-center text-xs text-zinc-500">
                    <p>{{ __('Bayaran: Tunai Semasa Penghantaran (COD)') }}</p>
                    <p>{{ __('Terima kasih atas tempahan anda!') }}</p>
                </div>
            </div>
        </div>
    </body>
</html>
