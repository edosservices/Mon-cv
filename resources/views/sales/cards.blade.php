<div class="lm-table-wrap mt-3 rounded-2xl bg-white shadow-sm">
    <table class="lm-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Ticket</th>
                <th>Client</th>
                <th>Forfait</th>
                <th>Montant</th>
                <th>Paiement</th>
                <th>Sync</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($sales as $sale)
                @php
                    $item = $sale->items->first();
                    $payment = $sale->payment;
                @endphp
                <tr>
                    <td>{{ $sale->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                    <td>{{ $item?->voucher?->username ?: '—' }}</td>
                    <td>{{ $sale->customer->phone ?? $sale->customer->name ?? '—' }}</td>
                    <td>{{ $item?->plan?->name ?: '—' }}</td>
                    <td>{{ \App\Support\Money::format($sale->total_amount, $sale->currency) }}</td>
                    <td>{{ config('limete.payment_providers.'.$payment?->provider, $payment?->provider ?: '—') }} · {{ \App\Enums\PaymentStatus::tryFrom((string) $payment?->status)?->label() ?? ($sale->status === 'paid' ? 'Payé' : 'En attente') }}</td>
                    <td>{{ \App\Support\SyncLabel::for($item?->voucher?->sync_status) }}</td>
                    <td><a class="font-semibold text-electric" href="{{ route('sales.show', $sale) }}">Ouvrir</a></td>
                </tr>
            @empty
                <tr><td colspan="8">Aucune vente sur cette période.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if(method_exists($sales, 'links'))
    <div class="mt-4">{{ $sales->links() }}</div>
@endif
