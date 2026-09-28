<article class="ticket-sheet rounded-2xl bg-white p-5 text-center shadow-sm" style="border-top: 8px solid {{ preg_match('/^#[0-9A-Fa-f]{6}$/', $voucher->wifiZone->primary_color ?? '') ? $voucher->wifiZone->primary_color : '#0b5ed7' }}">
    <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Ticket internet</p>
    <h2 class="mt-1 text-xl font-semibold">{{ $voucher->wifiZone->name }}</h2>
    <p class="mt-3 text-sm">Forfait</p>
    <p class="text-lg font-semibold">{{ $voucher->plan->name }}</p>
    <p class="mt-3 text-sm">Identifiant</p>
    <p class="text-2xl font-semibold tracking-wide">{{ $voucher->username }}</p>
    <p class="mt-3 text-sm">Mot de passe</p>
    <p class="text-2xl font-semibold">{{ $voucher->password }}</p>
    <p class="mt-3 text-sm">{{ $voucher->plan->unlimited_data ? 'Internet illimité' : 'Volume selon le forfait' }}</p>
    <p class="mt-2 text-sm">Début : {{ $voucher->activated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'À la première utilisation' }}</p>
    <p class="text-sm">Expiration : {{ $voucher->expires_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Après activation' }}</p>
    @if($voucher->wifiZone->whatsapp)
        <p class="mt-3 text-sm">WhatsApp {{ $voucher->wifiZone->whatsapp }}</p>
    @endif
    @if(!empty($qr))
        <div class="mx-auto mt-4 max-w-[160px]">{!! $qr !!}</div>
    @endif
</article>
@if(!empty($public))
<p class="no-print mt-4 text-center"><button onclick="window.print()" class="rounded-lg bg-electric px-4 py-2 text-sm text-white">Imprimer</button></p>
@endif
