@php
    $network = $network ?? [
        'state' => 'session non disponible',
        'ip' => null,
        'mac' => null,
        'session_time_left' => null,
    ];
@endphp
<section class="panel" id="statut">
    <h2>Statut</h2>
    @if($voucher->status === 'expired')
        <p class="status-pill is-bad">Expiré</p>
    @endif
    <dl class="summary">
        <div>
            <dt>Nom d’utilisateur</dt>
            <dd>{{ $voucher->username }}</dd>
        </div>
        <div>
            <dt>État de connexion</dt>
            <dd>{{ $network['state'] }}</dd>
        </div>
        <div>
            <dt>Forfait</dt>
            <dd>{{ $voucher->plan->name }}</dd>
        </div>
        <div>
            <dt>Internet</dt>
            <dd>{{ $voucher->plan->unlimited_data ? 'Internet illimité' : 'Selon le forfait' }}</dd>
        </div>
        <div>
            <dt>Activation</dt>
            <dd>{{ $voucher->activated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'À l’activation' }}</dd>
        </div>
        <div>
            <dt>Expiration commerciale</dt>
            <dd>{{ $voucher->expires_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Après activation' }}</dd>
        </div>
        <div>
            <dt>Temps restant</dt>
            <dd>{{ $voucher->remainingLabel() }}</dd>
        </div>
        <div>
            <dt>IP</dt>
            <dd>{{ $network['ip'] ?: 'non disponible' }}</dd>
        </div>
        <div>
            <dt>MAC</dt>
            <dd>{{ $network['mac'] ?: 'non disponible' }}</dd>
        </div>
        <div>
            <dt>Session routeur</dt>
            <dd>{{ $network['session_time_left'] ?: 'non disponible' }}</dd>
        </div>
    </dl>
    <p class="help">La session routeur décrit l’état du réseau. La durée commerciale reste entre l’activation et l’expiration. Une reconnexion ne la recommence pas.</p>
    <p class="help">Déconnexion : utilisez le bouton Déconnexion du portail WiFi de cette zone.</p>
</section>
