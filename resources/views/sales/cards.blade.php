<div class="mt-3 space-y-3">
    @forelse($sales as $sale)
        @php
            $item = $sale->items->first();
            $payment = $sale->payment;
        @endphp
        <article class="min-w-0 rounded-2xl bg-white p-4 text-sm shadow-sm">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <p class="font-semibold">{{ $sale->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
                <p class="font-semibold">{{ \App\Support\Money::format($sale->total_amount, $sale->currency) }}</p>
            </div>
            <p class="mt-1 break-all">Ticket {{ $item?->voucher?->username ?: '—' }}</p>
            <p>Client {{ $sale->customer->phone ?? $sale->customer->name ?? '—' }}</p>
            <p>Forfait {{ $item?->plan?->name ?: '—' }}</p>
            <p>Méthode {{ config('limete.payment_providers.'.$payment?->provider, $payment?->provider ?: '—') }}</p>
            <p>Paiement {{ \App\Enums\PaymentStatus::tryFrom((string) $payment?->status)?->label() ?? ($sale->status === 'paid' ? 'Payé' : 'En attente') }}</p>
            <p>MikroTik {{ \App\Support\SyncLabel::for($item?->voucher?->sync_status) }}</p>
            <a class="mt-2 inline-block font-semibold text-electric" href="{{ route('sales.show', $sale) }}">Ouvrir</a>
        </article>
    @empty
        <p class="text-sm text-slate-500">Aucune vente sur cette période.</p>
    @endforelse
</div>
@if(method_exists($sales, 'links'))
    <div class="mt-4">{{ $sales->links() }}</div>
@endif
