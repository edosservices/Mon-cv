@php
    $snapshot = is_array($ticket->profile_snapshot) ? $ticket->profile_snapshot : [];
    $public = route('tickets.public', $ticket->public_token);
    $share = 'https://wa.me/?text='.rawurlencode($ticket->clientShareText());
@endphp
<article class="client-card p-3 mb-3">
    @if($mark = $ticket->wifiZone?->logoUrl())
        <img class="client-ticket-logo" src="{{ $mark }}" alt="{{ $ticket->wifiZone->tenant?->name ?: $ticket->wifiZone->name }}">
    @endif
    <p class="mb-1"><strong>{{ $snapshot['profile'] ?? ($ticket->plan->mikrotik_profile ?: ($ticket->plan->name ?? 'Forfait')) }}</strong></p>
    <p class="mb-1">{{ $ticket->username }}@if($reveal ?? false) · {{ $ticket->password }}@endif</p>
    <p class="mb-1 small">Validité {{ $snapshot['validity'] ?? $ticket->plan?->durationLabel() }} · Temps {{ $snapshot['time_label'] ?? ($snapshot['time_limit'] ?? '—') }}</p>
    <p class="mb-1 small">Data {{ $snapshot['data_label'] ?? '—' }} · Débit {{ $snapshot['rate_limit'] ?? '—' }}</p>
    <p class="mb-1"><span class="badge {{ $ticket->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $ticket->statusLabel() }}</span></p>
    <p class="mb-1 small text-secondary">Créé {{ $ticket->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
    <p class="mb-2 small text-secondary">Expire {{ $ticket->expires_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'après activation' }}</p>
    <div class="ticket-qr">{!! \App\Support\QrCodes::svg($public) !!}</div>
    <div class="d-flex flex-wrap gap-2 mt-2">
        <a class="btn btn-sm client-btn" href="{{ $public }}#connexion">Se connecter</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ $public }}">QR code</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ $share }}">WhatsApp</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ $public }}">Voir le Ticket</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('tickets.pdf', $ticket->public_token) }}">PDF</a>
    </div>
</article>
