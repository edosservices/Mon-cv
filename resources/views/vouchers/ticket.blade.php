<style>
.pass { max-width: 420px; margin: 0 auto; background: #fff; border-radius: 22px; overflow: hidden; color: #10233f; font-family: sans-serif; }
.pass-head { color: #fff; padding: 20px 16px 16px; text-align: center; }
.pass-head img { width: 64px; height: auto; margin-bottom: 8px; }
.pass-head p { margin: 0; font-size: 18px; font-weight: 800; }
.pass-body { padding: 8px 16px 18px; }
.pass-plan { margin: 12px 0 0; text-align: center; font-size: 22px; font-weight: 800; }
.pass-price { margin: 4px 0 0; text-align: center; font-size: 28px; font-weight: 800; letter-spacing: -0.03em; }
.pass span, .pass dt { display: block; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #5c6e86; }
.pass strong, .pass dd { margin: 2px 0 0; font-size: 16px; font-weight: 800; }
.pass-cut { height: 0; margin: 14px 0; border-top: 2px dashed #d5e0ee; }
.pass-code { padding: 12px; border-radius: 14px; background: #f4f7fb; text-align: center; }
.pass-code strong { font-size: 26px; letter-spacing: .08em; }
.pass-meta { display: table; width: 100%; margin-top: 12px; }
.pass-meta > div { display: table-cell; width: 50%; padding: 6px 8px 6px 0; vertical-align: top; }
.pass-remain { margin-top: 12px; padding: 12px; border-radius: 14px; background: #e7f4ff; text-align: center; }
.pass-remain strong { font-size: 22px; }
.sync { margin: 12px 0 0; font-size: 14px; line-height: 1.45; color: #8a5a00; }
.sync-ok { color: #0f6b3c; }
.qr { width: 180px; margin: 14px auto 0; }
.qr svg { width: 100%; height: auto; }
.fine { margin: 6px 0 0; text-align: center; font-size: 12px; color: #5c6e86; }
.st-ok { color: #0f6b3c; }
.st-bad { color: #a11d1d; }
</style>
<article class="pass">
    <header class="pass-head" style="background: {{ $voucher->wifiZone->brandColor() }}">
        @if($voucher->wifiZone->logoUrl())
            <img src="{{ $voucher->wifiZone->logoUrl() }}" alt="">
        @endif
        <p>{{ $voucher->wifiZone->displayLabel() }}</p>
        <p class="fine">{{ $voucher->wifiZone->name }}</p>
    </header>
    <div class="pass-body">
        <p class="pass-plan">{{ $voucher->plan->name }}</p>
        <p class="pass-price">{{ \App\Support\Money::shop($voucher->price_amount ?? $voucher->plan->price, $voucher->currency ?: $voucher->plan->currency) }}</p>
        <div class="pass-cut"></div>
        <div class="pass-code">
            <span>Code</span>
            <strong>{{ $voucher->username }}</strong>
        </div>
        <div class="pass-code" style="margin-top: 8px;">
            <span>Mot de passe</span>
            <strong>{{ $voucher->password }}</strong>
        </div>
        <dl class="pass-meta">
            <div>
                <dt>Internet</dt>
                <dd>{{ $voucher->plan->unlimited_data ? 'Illimité' : 'Selon le forfait' }}</dd>
            </div>
            <div>
                <dt>Statut</dt>
                <dd class="{{ $voucher->status === 'expired' ? 'st-bad' : 'st-ok' }}">{{ $voucher->statusLabel() }}</dd>
            </div>
            <div>
                <dt>Début</dt>
                <dd>{{ $voucher->activated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'À l’activation' }}</dd>
            </div>
            <div>
                <dt>Expire</dt>
                <dd>{{ $voucher->expires_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Après activation' }}</dd>
            </div>
        </dl>
        <div class="pass-remain">
            <span>Temps restant</span>
            <strong>{{ $voucher->remainingLabel() }}</strong>
        </div>
        @if($voucher->isSynced())
            <p class="sync sync-ok">Compte WiFi prêt. Synchronisé.</p>
        @else
            <p class="sync">
                @if($voucher->saleItem?->sale?->status === 'paid')
                    Votre paiement a bien été confirmé. Votre ticket est créé. Le WiFi est temporairement en cours de synchronisation.
                @else
                    Votre ticket est créé. Le WiFi est temporairement en cours de synchronisation.
                @endif
                Non synchronisé. Ticket créé, synchronisation MikroTik en attente.
            </p>
        @endif
        @if(!empty($qr))
            <div class="qr" aria-label="QR du ticket">{!! $qr !!}</div>
            <p class="fine">Ce QR ouvre le ticket. Il ne contient pas le mot de passe.</p>
        @endif
    </div>
</article>
