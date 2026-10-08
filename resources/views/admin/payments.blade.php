@extends('layouts.admin')
@section('content')
<h1 class="mb-4 text-2xl font-semibold">Paiements</h1>
<section class="mb-6 grid gap-3 sm:grid-cols-2">
    @foreach($providers as $provider)
        <article class="rounded-2xl bg-white p-4 text-sm">
            <h2 class="font-semibold">{{ $provider['label'] }}</h2>
            <p class="mt-1">{{ $provider['configured'] ? 'Configuré' : 'Non configuré' }}</p>
            <p>{{ $provider['enabled'] ? 'Actif' : 'Désactivé' }}</p>
            <form method="POST" action="{{ route('admin.payments.providers', $provider['key']) }}" class="mt-3">
                @csrf
                <input type="hidden" name="enabled" value="{{ $provider['enabled'] ? '0' : '1' }}">
                <button class="rounded-lg border px-3 py-2">{{ $provider['enabled'] ? 'Désactiver' : 'Activer' }}</button>
            </form>
        </article>
    @endforeach
</section>
<div class="overflow-x-auto rounded-2xl bg-white">
    <table class="w-full min-w-[720px] text-left text-sm">
        <thead><tr><th class="p-3">Référence</th><th>Montant</th><th>Fournisseur</th><th>Opérateur</th><th>Bénéficiaire</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        @foreach($payments as $payment)
            <tr class="border-t">
                <td class="p-3">{{ $payment->internal_reference ?? $payment->transaction_reference }}<span class="block text-slate-500">{{ $payment->tenant->name ?? 'Plateforme' }}</span></td>
                <td>{{ \App\Support\Money::format($payment->amount, $payment->currency) }}</td>
                <td>{{ $payment->provider }}<span class="block text-slate-500">{{ $payment->provider_reference }}</span></td>
                <td>{{ $payment->metadata['operator'] ?? '—' }}</td>
                <td>{{ $payment->metadata['beneficiary_phone'] ?? '—' }}<span class="block text-slate-500">{{ $payment->metadata['payer_phone'] ?? '' }}</span></td>
                <td>{{ \App\Enums\PaymentStatus::tryFrom($payment->status)?->label() ?? $payment->status }}</td>
                <td>
                    @if($payment->status === 'pending' && $payment->payable_type === \App\Models\Subscription::class)
                        <form method="POST" action="{{ route('admin.payments.confirm', $payment->id) }}">@csrf<button class="text-electric">Confirmer</button></form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $payments->links() }}</div>
@endsection
