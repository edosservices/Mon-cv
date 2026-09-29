@extends('layouts.client')
@section('heading', 'Mes tickets')
@section('content')
<h1 class="h4">Mes tickets</h1>
@forelse($tickets as $ticket)
    <article class="client-card p-3 mb-3">
        <p class="mb-1"><strong>{{ $ticket->wifiZone->name ?? 'WiFi Zone' }}</strong></p>
        <p class="mb-1">{{ $ticket->plan->name ?? 'Forfait' }} · {{ \App\Support\Money::format($ticket->price_amount ?? $ticket->plan?->price, $ticket->currency ?: $ticket->plan?->currency) }}</p>
        <p class="mb-1">Code {{ $ticket->username }}</p>
        <p class="mb-1"><span class="badge {{ $ticket->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $ticket->statusLabel() }}</span></p>
        <p class="mb-1 small text-secondary">Achat {{ ($ticket->saleItem?->sale?->created_at ?? $ticket->created_at)?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
        <p class="mb-2 small text-secondary">Expiration {{ $ticket->expires_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'après activation' }}</p>
        <div class="ticket-qr">{!! \App\Support\QrCodes::svg(route('tickets.public', $ticket->public_token)) !!}</div>
        <div class="d-flex gap-2 mt-2">
            <a class="btn btn-sm client-btn" href="{{ route('tickets.public', $ticket->public_token) }}">Ticket</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('tickets.pdf', $ticket->public_token) }}">PDF</a>
        </div>
    </article>
@empty
    <div class="client-card p-3">
        <p>Aucun ticket pour ce numéro.</p>
        <a class="btn client-btn" href="{{ route('client.buy') }}">Acheter un ticket</a>
    </div>
@endforelse
@endsection
