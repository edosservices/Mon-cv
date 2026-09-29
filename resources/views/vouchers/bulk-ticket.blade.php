<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Tickets</title>
    @vite(['resources/css/bulk-ticket.css'])
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Imprimer</button>
        <p>{{ $vouchers->count() }} tickets · {{ $perPage }} / page</p>
    </div>
    @foreach($vouchers->chunk($perPage) as $page)
        <section class="sheet sheet-{{ $perPage }}">
            @foreach($page as $voucher)
                @php $snapshot = is_array($voucher->profile_snapshot) ? $voucher->profile_snapshot : []; @endphp
                <article class="ticket">
                    <img src="{{ asset('brand/logo-limete-wifi-manager.png') }}" alt="LIMETE WIFI">
                    <span>Utilisateur</span>
                    <strong>{{ $voucher->username }}</strong>
                    <span>Mot de passe</span>
                    <strong>{{ $voucher->password }}</strong>
                    <small>{{ $snapshot['profile'] ?? ($voucher->plan->mikrotik_profile ?: $voucher->plan->name) }}</small>
                    <small>{{ $snapshot['validity_label'] ?? $voucher->plan->durationLabel() }} · {{ $snapshot['data_label'] ?? 'Data selon forfait' }}</small>
                    <small>{{ $snapshot['rate_limit'] ?? '' }}</small>
                    <div>{!! \App\Support\QrCodes::svg(route('tickets.public', $voucher->public_token)) !!}</div>
                    <small>{{ $voucher->public_token }}</small>
                </article>
            @endforeach
        </section>
    @endforeach
</body>
</html>
