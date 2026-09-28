<style>
.pass { max-width: 420px; margin: 0 auto; background: #fff; border-radius: 18px; overflow: hidden; color: #10233f; font-family: sans-serif; }
.pass-head { color: #fff; padding: 18px 16px; text-align: center; }
.pass-head img { width: 64px; height: auto; margin-bottom: 8px; }
.pass-head p { margin: 0; font-size: 18px; font-weight: 700; }
.pass-body { padding: 16px; }
.pass-grid, .pass-meta { display: table; width: 100%; }
.pass-grid > div, .pass-meta > div { display: table-cell; width: 50%; padding: 6px 8px 6px 0; vertical-align: top; }
.pass span, .pass dt { display: block; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #5c6e86; }
.pass strong, .pass dd { margin: 2px 0 0; font-size: 16px; font-weight: 700; }
.pass-code { margin-top: 12px; padding: 12px; border-radius: 12px; background: #f4f7fb; text-align: center; }
.pass-code strong { font-size: 26px; letter-spacing: .08em; }
.pass-meta { margin-top: 12px; }
.sync { margin: 12px 0 0; font-size: 13px; color: #8a5a00; }
.sync-ok { color: #0f6b3c; }
.qr { width: 180px; margin: 14px auto 0; }
.qr svg { width: 100%; height: auto; }
.fine { margin: 6px 0 0; text-align: center; font-size: 12px; color: #5c6e86; }
</style>
<article class="pass">
    <header class="pass-head" style="background: {{ $voucher->wifiZone->brandColor() }}">
        @if($voucher->wifiZone->logoUrl())
            <img src="{{ $voucher->wifiZone->logoUrl() }}" alt="">
        @endif
        <p>{{ $voucher->wifiZone->name }}</p>
    </header>
    <div class="pass-body">
        <div class="pass-grid">
            <div>
                <span>Forfait</span>
                <strong>{{ $voucher->plan->name }}</strong>
            </div>
            <div>
                <span>Internet</span>
                <strong>{{ $voucher->plan->unlimited_data ? 'Illimité' : 'Selon le forfait' }}</strong>
            </div>
        </div>
        <div class="pass-code">
            <span>Code</span>
            <strong>{{ $voucher->username }}</strong>
        </div>
        <div class="pass-code">
            <span>Mot de passe</span>
            <strong>{{ $voucher->password }}</strong>
        </div>
        <dl class="pass-meta">
            <div>
                <dt>Statut</dt>
                <dd>{{ $voucher->statusLabel() }}</dd>
            </div>
            <div>
                <dt>Activation</dt>
                <dd>{{ $voucher->activated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'À l’activation' }}</dd>
            </div>
            <div>
                <dt>Expiration</dt>
                <dd>{{ $voucher->expires_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Après activation' }}</dd>
            </div>
            <div>
                <dt>Temps restant</dt>
                <dd>{{ $voucher->remainingLabel() }}</dd>
            </div>
        </dl>
        @if($voucher->isSynced())
            <p class="sync sync-ok">Compte WiFi prêt</p>
        @else
            <p class="sync">Non synchronisé. Le ticket est enregistré. Il fonctionnera lorsque la zone aura relié le routeur.</p>
        @endif
        @if(!empty($qr))
            <div class="qr" aria-label="QR du ticket">{!! $qr !!}</div>
            <p class="fine">Ce QR ouvre le ticket. Il ne contient pas le mot de passe du routeur.</p>
        @endif
    </div>
</article>
