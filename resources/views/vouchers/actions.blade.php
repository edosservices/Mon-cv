<div class="actions no-print">
    @if($voucher->status !== 'expired')
        <a class="btn btn-primary" href="#connexion">Utiliser</a>
        @if($portal = $zone->captiveLoginUrl())
            <a class="btn btn-primary" href="{{ $portal }}">Se connecter maintenant</a>
        @endif
        <a class="btn btn-ghost" href="#connexion">Se connecter au WiFi</a>
    @else
        <a class="btn btn-primary" href="{{ route('shop.show', $zone->slug) }}">Choisir un nouveau forfait</a>
    @endif
    <button class="btn btn-ghost" type="button" onclick="window.print()">Imprimer</button>
    <a class="btn btn-ghost" href="{{ route('tickets.pdf', $voucher->public_token) }}">Télécharger</a>
    <button class="btn btn-ghost" type="button" data-share="{{ route('tickets.public', $voucher->public_token) }}">Partager</button>
    @if($zone->whatsappDigits())
        <a class="btn btn-wa" href="https://wa.me/{{ $zone->whatsappDigits() }}?text={{ urlencode($voucher->shareText()) }}">Partager mon ticket sur WhatsApp</a>
    @endif
    <button class="btn btn-ghost" type="button" data-copy="{{ $voucher->username }}">Copier le code</button>
    @if(! $voucher->isSynced() && $voucher->status !== 'expired')
        <form method="POST" action="{{ route('tickets.sync', $voucher->public_token) }}" data-wait>
            @csrf
            <p class="help">Synchronisation en attente</p>
            <button class="btn btn-ghost" type="submit">Réessayer</button>
        </form>
    @endif
</div>
