<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #10233f; font-size: 12px; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #d5e0ee; padding: 6px 4px; text-align: left; }
        th { font-size: 10px; text-transform: uppercase; color: #5c6e86; }
    </style>
</head>
<body>
    <h1>Ventes</h1>
    <p>{{ $from->timezone(config('app.timezone'))->format('d/m/Y') }} – {{ $to->timezone(config('app.timezone'))->format('d/m/Y') }}</p>
    <p>Chiffre d’affaires confirmé : {{ \App\Support\Money::format($revenue) }}</p>
    <table>
        <thead>
            <tr><th>Date</th><th>Ticket</th><th>Client</th><th>Forfait</th><th>Montant</th><th>Paiement</th></tr>
        </thead>
        <tbody>
            @foreach($sales as $sale)
                @php $item = $sale->items->first(); @endphp
                <tr>
                    <td>{{ $sale->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                    <td>{{ $item?->voucher?->username }}</td>
                    <td>{{ $sale->customer->phone ?? $sale->customer->name }}</td>
                    <td>{{ $item?->plan?->name }}</td>
                    <td>{{ \App\Support\Money::format($sale->total_amount, $sale->currency) }}</td>
                    <td>{{ \App\Enums\PaymentStatus::tryFrom((string) $sale->payment?->status)?->label() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
