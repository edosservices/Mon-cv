<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Commande</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50">
<main class="mx-auto max-w-lg px-4 py-8">
    <h1 class="text-2xl font-semibold">{{ $zone->name }}</h1>
    @if($sale->status === 'paid')
        <p class="mt-2 text-sm">Paiement confirmé. Voici votre ticket.</p>
        @php $voucher = $sale->items->first()->voucher; @endphp
        @if($voucher)
            <div class="mt-4">@include('vouchers.ticket', ['voucher' => $voucher, 'qr' => null, 'public' => true])</div>
            <a class="mt-4 block text-center text-electric" href="{{ route('tickets.public', $voucher->public_token) }}">Ouvrir le ticket</a>
        @endif
    @else
        <p class="mt-4 rounded-2xl bg-white p-5 shadow-sm">Paiement en attente. Référence {{ $sale->payment->transaction_reference ?? '' }}. Le ticket apparaîtra ici dès confirmation.</p>
    @endif
</main>
</body>
</html>
