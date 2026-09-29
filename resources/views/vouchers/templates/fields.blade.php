<div class="fields">
    <p class="plan">{{ $ticket['plan'] }}</p>
    <p class="price">{{ $ticket['price'] }}</p>
    <p class="duration">Durée {{ $ticket['duration'] }}</p>
    <div class="codes">
        <p><span>Identifiant</span><strong class="code">{{ $ticket['username'] }}</strong></p>
        <p><span>Mot de passe</span><strong class="code">{{ $ticket['password'] }}</strong></p>
    </div>
    <div class="qr" aria-label="QR du ticket">{!! $ticket['qr'] !!}</div>
    <p class="meta">Créé le {{ $ticket['created'] }}</p>
    @if($ticket['expires'])
        <p class="meta">Expire le {{ $ticket['expires'] }}</p>
    @endif
    @if($ticket['phone'])
        <p class="meta">Tél. {{ $ticket['phone'] }}</p>
    @endif
    @if($ticket['whatsapp'])
        <p class="meta">WhatsApp {{ $ticket['whatsapp'] }}</p>
    @endif
    @if($ticket['address'])
        <p class="meta">{{ $ticket['address'] }}</p>
    @endif
</div>
