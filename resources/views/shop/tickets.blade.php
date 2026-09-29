@extends('layouts.shop')
@section('title', 'Mes tickets — '.$zone->name)
@section('content')
<section class="panel">
    <h1>Mes tickets</h1>
    <p class="help">Retrouvez un ticket avec le numéro utilisé à l’achat et le code du ticket. Le numéro seul ne suffit pas.</p>
    <form method="POST" action="{{ route('shop.lookup', $zone->slug) }}" data-wait>
        @csrf
        <label class="field">Téléphone
            <input name="phone" type="tel" inputmode="tel" value="{{ old('phone') }}" required>
        </label>
        <label class="field">Code du ticket
            <input name="username" type="text" autocapitalize="characters" value="{{ old('username') }}" required>
        </label>
        <button class="btn btn-primary" type="submit">Retrouver mon ticket</button>
    </form>
</section>

@if($sales->isNotEmpty())
<section class="shop-section">
    <h2>Paiements en attente</h2>
    <div class="stack">
        @foreach($sales as $sale)
            <a class="ticket-row" href="{{ route('shop.order', [$zone->slug, $sale->public_token]) }}">
                <strong>{{ $sale->items->first()?->plan?->name ?? 'Forfait' }}</strong>
                <span>En attente · {{ \App\Support\Money::shop($sale->total_amount, $sale->currency) }}</span>
            </a>
        @endforeach
    </div>
</section>
@endif

<section class="shop-section">
    <h2>Historique</h2>
    @if($vouchers->isEmpty())
        <div class="empty">
            <p>Aucun ticket sur cet appareil pour le moment.</p>
            <a class="btn btn-ghost" href="{{ route('shop.show', $zone->slug) }}">Voir les forfaits</a>
        </div>
    @else
        <div class="stack">
            @foreach($vouchers as $voucher)
                <a class="ticket-row {{ $voucher->status === 'active' ? 'is-active' : '' }}" href="{{ route('tickets.public', $voucher->public_token) }}">
                    <strong>{{ $voucher->plan->name ?? 'Forfait' }}</strong>
                    <span>{{ $voucher->username }} · {{ $voucher->statusLabel() }}</span>
                    <span>Temps restant : {{ $voucher->remainingLabel() }}</span>
                </a>
            @endforeach
        </div>
    @endif
</section>
@endsection
