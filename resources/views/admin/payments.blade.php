@extends('layouts.admin')
@section('content')
<h1 class="mb-4 text-2xl font-semibold">Paiements</h1>
<div class="overflow-x-auto rounded-2xl bg-white">
    <table class="w-full min-w-[720px] text-left text-sm">
        <thead><tr><th class="p-3">Référence</th><th>Montant</th><th>Fournisseur</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        @foreach($payments as $payment)
            <tr class="border-t">
                <td class="p-3">{{ $payment->transaction_reference }}</td>
                <td>{{ \App\Support\Money::format($payment->amount, $payment->currency) }}</td>
                <td>{{ $payment->provider }}</td>
                <td>{{ $payment->status }}</td>
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
