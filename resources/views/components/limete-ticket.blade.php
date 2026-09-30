@props([
    'username',
    'password',
    'plan',
    'price',
    'qr' => null,
    'number' => null,
    'login' => null,
    'ticketUrl' => null,
    'logo' => null,
])
<style>
.lt {
    position: relative; max-width: 680px; margin: 18px auto 0; color: #12315c;
    border-radius: 32px; overflow: hidden;
    background: linear-gradient(180deg, #f7fbff 0%, #fff 42%);
    box-shadow: 0 28px 56px rgba(11, 63, 134, .22), inset 0 1px 0 rgba(255,255,255,.8);
    font-family: "Instrument Sans", "Segoe UI", system-ui, sans-serif;
    mask-image: radial-gradient(circle 14px at 0 58%, transparent 13px, #000 14px), radial-gradient(circle 14px at 100% 58%, transparent 13px, #000 14px);
    mask-composite: intersect;
    -webkit-mask-image: radial-gradient(circle 14px at 0 58%, transparent 13px, #000 14px), radial-gradient(circle 14px at 100% 58%, transparent 13px, #000 14px);
    -webkit-mask-composite: source-in;
}
.lt-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; min-height: 124px; padding: 18px 18px 28px; background: linear-gradient(100deg, rgba(255,255,255,.96) 0 34%, rgba(232,244,255,.55) 52%, rgba(11, 78, 162, .28) 100%), url("{{ asset('images/landing/skyline.webp') }}") right center / cover no-repeat; }
.lt-brand { display: flex; align-items: center; gap: 8px; min-width: 0; }
.lt-brand .lm-brand-logo, .lt-zone-logo { height: 52px; width: auto; max-width: 190px; object-fit: contain; }
.lt-no { margin: 0; text-align: right; background: rgba(255,255,255,.94); border: 1px solid #d5e2f2; border-radius: 14px; padding: 8px 10px; box-shadow: 0 8px 18px rgba(11, 63, 134, .08); }
.lt-no strong { display: block; font-size: 18px; letter-spacing: .04em; }
.lt-no span { font-size: 10px; letter-spacing: .12em; color: #5c6e86; }
.lt-body { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(148px, .72fr); gap: 14px; padding: 0 16px 16px; margin-top: -18px; }
.lt-fields { display: grid; gap: 8px; }
.lt-field { display: grid; grid-template-columns: 36px 1fr; grid-template-rows: auto auto; column-gap: 8px; align-items: center; background: #eef6ff; border: 1px solid #d7e6f8; border-radius: 16px; padding: 10px 12px; }
.lt-field .vh-ico { grid-column: 1; grid-row: 1 / span 2; color: #1463f3; }
.lt-field span { font-size: 12px; color: #5c6e86; }
.lt-field strong { font-size: 22px; letter-spacing: .04em; }
.lt-qr { align-self: end; background: #fff; border: 1px solid #e1ebf6; border-radius: 22px; padding: 10px; text-align: center; box-shadow: 0 16px 32px rgba(11, 63, 134, .14); }
.lt-qr svg { width: 100%; height: auto; display: block; }
.lt-qr p { margin: 6px 0 0; font-size: 11px; color: #3d516b; }
.lt-bar { display: flex; align-items: stretch; margin: 0 14px 14px; border-radius: 18px; overflow: hidden; background: linear-gradient(90deg, #0b4ea2, #1463f3); color: #fff; box-shadow: 0 10px 24px rgba(20, 99, 243, .22); }
.lt-bar div { flex: 1; padding: 12px 16px; }
.lt-bar div + div { border-left: 1px solid rgba(255,255,255,.35); }
.lt-bar span { display: block; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; opacity: .9; }
.lt-bar strong { font-size: clamp(1.15rem, 2vw, 1.7rem); }
.lt-foot { display: flex; flex-wrap: wrap; gap: 8px 14px; padding: 0 18px 16px; color: #3d516b; font-size: 12px; }
.lt-foot span { display: inline-flex; align-items: center; gap: 6px; }
.lt-foot .vh-ico { color: #1463f3; }
@media (max-width: 640px) {
    .lt-body { grid-template-columns: 1fr; margin-top: 0; }
    .lt-qr { max-width: 210px; }
    .lt-field strong { font-size: 18px; }
}
</style>
<article {{ $attributes->merge(['class' => 'lt']) }} aria-label="Ticket LIMETE WIFI">
    <header class="lt-top">
        <div class="lt-brand">
            @if($logo)
                <img class="lt-zone-logo" src="{{ $logo }}" alt="">
            @else
                <x-brand-logo height="46" alt="LIMETE WIFI MANAGER" />
            @endif
        </div>
        @if($number)
            <p class="lt-no"><strong>[{{ $number }}]</strong><span>TICKET N°</span></p>
        @endif
    </header>
    <div class="lt-body">
        <div class="lt-fields">
            <div class="lt-field">
                <x-icon name="person" :size="22" />
                <span>Username</span>
                <strong>{{ $username }}</strong>
            </div>
            <div class="lt-field">
                <x-icon name="shield-lock" :size="22" />
                <span>Password</span>
                <strong>{{ $password }}</strong>
            </div>
        </div>
        @if($qr)
            <div class="lt-qr" @if($ticketUrl) data-ticket-url="{{ $ticketUrl }}" @endif aria-label="QR du ticket">
                {!! $qr !!}
                <p><x-icon name="phone" :size="14" /> Scannez pour vous connecter</p>
            </div>
        @endif
    </div>
    <div class="lt-bar">
        <div>
            <span><x-icon name="clock" :size="14" /> Forfait</span>
            <strong>{{ $plan }}</strong>
        </div>
        <div>
            <span>Prix</span>
            <strong>{{ $price }}</strong>
        </div>
    </div>
    <footer class="lt-foot">
        @if($login)
            <span><x-icon name="wifi" :size="14" /> Login: {{ $login }}</span>
        @endif
        <span><x-icon name="lightning-charge" :size="14" /> Connexion rapide</span>
        <span><x-icon name="shield-lock" :size="14" /> Sécurisé et fiable</span>
        <span><x-icon name="phone" :size="14" /> Utilisable sur tous vos appareils</span>
    </footer>
</article>
