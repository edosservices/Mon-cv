<div class="actions">
    @if($voucher->status !== 'expired')
        <a class="btn btn-primary" href="#connexion">Se connecter au WiFi</a>
    @else
        <a class="btn btn-primary" href="{{ route('shop.show', $zone->slug) }}">Choisir un nouveau forfait</a>
    @endif
    <a class="btn btn-ghost" href="{{ route('tickets.pdf', $voucher->public_token) }}">Télécharger</a>
    @if($zone->whatsappDigits())
        <a class="btn btn-wa" href="https://wa.me/{{ $zone->whatsappDigits() }}?text={{ urlencode($voucher->shareText()) }}">Envoyer sur WhatsApp</a>
    @endif
    <button class="btn btn-ghost" type="button" data-copy="{{ $voucher->username }}">Copier le code</button>
</div>
