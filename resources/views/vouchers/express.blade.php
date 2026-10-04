@php
    $offers = [];
    $accounts = [];
    foreach ($quick['profiles'] as $profile) {
        $snap = $profile['snapshot'] ?? [];
        $price = $snap['price_amount'] ?? null;
        $selling = $snap['selling_price_amount'] ?? null;
        $rate = (string) ($snap['rate_limit'] ?? '');
        $rateParts = $rate !== '' ? explode('/', $rate, 2) : [];
        $row = [
            'name' => $profile['name'],
            'summary' => $profile['summary'] ?? '',
            'ready' => (bool) ($profile['ready'] ?? false),
            'validity' => $snap['validity'] ?? '',
            'validity_label' => $snap['validity_label'] ?? '',
            'time' => $snap['time_limit'] ?? '',
            'time_label' => $snap['time_label'] ?? '',
            'rate' => $rate,
            'upload' => $rateParts[0] ?? '',
            'download' => $rateParts[1] ?? ($rateParts[0] ?? ''),
            'amount' => $price,
            'currency' => $snap['price_currency'] ?? null,
            'price' => $price !== null ? \App\Support\Money::shop($price, $snap['price_currency'] ?? null) : '',
            'selling' => $selling !== null ? \App\Support\Money::shop($selling, $snap['selling_price_currency'] ?? null) : '',
            'shared' => $snap['shared_users'] ?? '',
            'pool' => $snap['address_pool'] ?? '',
            'lock' => $snap['lock_user'] ?? '',
            'queue' => $snap['parent_queue'] ?? '',
            'expired' => $snap['expired_mode'] ?? '',
            'data' => $snap['data_label'] ?? '',
        ];
        $accounts[] = $row;
        if ($row['ready']) {
            $offers[] = $row;
        }
    }
    $spans = [
        '1h' => '1 heure',
        '2h' => '2 heures',
        '6h' => '6 heures',
        '12h' => '12 heures',
        '1d' => '1 jour',
        '2d' => '2 jours',
        '4d' => '4 jours',
        '7d' => '7 jours',
        '15d' => '15 jours',
        '30d' => '30 jours',
    ];
    $selectedProfile = old('profile', $accounts[0]['name'] ?? '');
    $qtyShown = max(1, (int) old('qty', 1));
    $selectedAccount = collect($accounts)->firstWhere('name', $selectedProfile) ?? ($accounts[0] ?? null);
    $totalLabel = '—';
    if ($selectedAccount && $selectedAccount['amount'] !== null) {
        $totalLabel = \App\Support\Money::shop(((float) $selectedAccount['amount']) * $qtyShown, $selectedAccount['currency']);
    } elseif ($selectedAccount && $selectedAccount['price'] !== '') {
        $totalLabel = $selectedAccount['price'];
    }
@endphp

<section class="tg-card" data-user-compose>
    <h2 class="text-lg font-semibold">Tickets en un clic</h2>
    <p class="mt-1 text-sm text-slate-600">Le profil déjà créé complète la durée, le prix et le débit. Indiquez seulement le nombre de tickets, puis générez. Les comptes sont enregistrés sur le MikroTik.</p>
    @if($accounts === [])
        <p class="mt-3 text-sm text-slate-500">Aucun profil n’est encore lisible sur le MikroTik.</p>
        <p class="mt-3 text-sm text-slate-500">Aucun profil prêt. Associez un forfait dans les autres réglages, ou créez un utilisateur ci-dessous.</p>
    @else
        <form class="tg-form" method="POST" action="{{ route('vouchers.quick.express') }}" data-tg-generate-form>
            @csrf
            <input type="hidden" name="wifi_zone_id" value="{{ $quickZone->id }}">
            <div class="tg-main tg-grid">
                <fieldset class="tg-grid">
                    <legend class="text-lg font-semibold">2. Profile MikroTik</legend>
                    <label class="tg-label">Profile
                        <select name="profile" required data-user-profile>
                            @foreach($accounts as $account)
                                <option value="{{ $account['name'] }}" data-time="{{ $account['time'] ?: $account['validity'] }}" data-rate="{{ $account['rate'] }}" @selected($selectedProfile === $account['name'])>{{ $account['name'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    @foreach($accounts as $account)
                        @php
                            $previewDuration = $account['time_label'] ?: ($account['validity_label'] ?: ($account['time'] ?: ($account['validity'] ?: '')));
                        @endphp
                        <div class="tg-profile" data-profile-preview="{{ $account['name'] }}" @if($selectedProfile !== $account['name']) hidden @endif>
                            <div class="tg-chips">
                                @if($account['download'] !== '')
                                    <span class="tg-chip">↓ {{ $account['download'] }}</span>
                                @endif
                                @if($account['upload'] !== '')
                                    <span class="tg-chip">↑ {{ $account['upload'] }}</span>
                                @endif
                                @if($previewDuration !== '')
                                    <span class="tg-chip">{{ $previewDuration }}</span>
                                @endif
                            </div>
                            <p class="tg-hint">Pool : {{ $account['pool'] !== '' ? $account['pool'] : 'none' }} · Queue : {{ $account['queue'] !== '' ? $account['queue'] : 'none' }}</p>
                        </div>
                    @endforeach
                    @if($offers === [])
                        <p class="tg-hint">Aucun profil prêt. Associez un forfait dans les autres réglages, ou créez un utilisateur ci-dessous.</p>
                    @endif
                </fieldset>

                <fieldset class="tg-grid">
                    <legend class="text-lg font-semibold">3. Paramètres du ticket</legend>
                    <h3 class="text-base font-semibold">Créer un utilisateur HotSpot</h3>
                    <p class="tg-hint">Les champs reprennent l’écran MikroTik. La durée calcule le Time Limit, le débit du profil reste celui du routeur, et le commentaire se rédige à partir du nom, du profil et de la durée.</p>
                    <label class="tg-label">Server
                        <select name="server">
                            <option value="all" @selected(old('server', 'all') === 'all')>all</option>
                            @foreach($quick['servers'] as $server)
                                <option value="{{ $server }}" @selected(old('server') === $server)>{{ $server }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="tg-grid tg-split">
                        <label class="tg-label">Name
                            <input name="username" value="{{ old('username') }}" maxlength="32" autocapitalize="none" autocomplete="off" data-user-name placeholder="génération automatique">
                        </label>
                        <label class="tg-label">Password <span class="font-normal text-slate-500">facultatif, 3 à 8 chiffres</span>
                            <input name="password" value="{{ old('password') }}" inputmode="numeric" maxlength="8" autocomplete="new-password" placeholder="génération automatique">
                        </label>
                    </div>
                    <div data-time-calc>
                        <p class="text-sm font-semibold">Durée</p>
                        <p class="tg-hint">Automatiquement depuis le profil. Time Limit reste modifiable.</p>
                        <div class="tg-picks">
                            @foreach($spans as $code => $label)
                                <button type="button" data-time-pick="{{ $code }}">{{ $label }}</button>
                            @endforeach
                        </div>
                        <label class="tg-label">Time Limit
                            <input name="time_limit" value="{{ old('time_limit') }}" placeholder="4d" maxlength="32" data-time-limit>
                        </label>
                        <p class="tg-hint" data-time-readout></p>
                    </div>
                    <label class="tg-label">Data Limit MB
                        <input type="number" name="data_mb" min="1" max="100000" value="{{ old('data_mb') }}" inputmode="numeric" data-user-data>
                    </label>
                    <label class="tg-label">Comment
                        <input name="comment" value="{{ old('comment') }}" maxlength="120" data-user-comment>
                    </label>
                    <p class="tg-hint" data-user-rate></p>
                </fieldset>

                <fieldset class="tg-grid">
                    <legend class="text-lg font-semibold">4. Quantité</legend>
                    <div class="tg-picks" data-tg-presets>
                        @foreach([1, 5, 10, 20, 50] as $preset)
                            @if($preset <= max(1, $limit))
                                <button type="button" data-tg-preset="{{ $preset }}">{{ $preset }}</button>
                            @endif
                        @endforeach
                    </div>
                    <label class="tg-label">Nombre de tickets
                        <input type="number" name="qty" min="1" max="{{ max(1, $limit) }}" value="{{ old('qty', 1) }}" required inputmode="numeric" data-tg-qty>
                    </label>
                    <p class="tg-hint">Quantité limitée par l’abonnement : {{ max(0, $limit) }}.</p>
                </fieldset>
            </div>

            <aside class="tg-side">
                <h3>Résumé</h3>
                @foreach($accounts as $account)
                    @php
                        $duration = $account['time_label'] ?: ($account['validity_label'] ?: ($account['time'] ?: ($account['validity'] ?: '—')));
                    @endphp
                    <div data-summary-for="{{ $account['name'] }}" data-unit-price="{{ $account['amount'] !== null ? $account['amount'] : '' }}" data-price-label="{{ $account['price'] }}" @if($selectedProfile !== $account['name']) hidden @endif>
                        <div class="tg-chips">
                            @if($account['download'] !== '')
                                <span class="tg-chip">↓ {{ $account['download'] }}</span>
                            @endif
                            @if($account['upload'] !== '')
                                <span class="tg-chip">↑ {{ $account['upload'] }}</span>
                            @endif
                            @if($duration !== '—')
                                <span class="tg-chip">{{ $duration }}</span>
                            @endif
                        </div>
                        <dl class="tg-summary">
                            <div><dt>Profile</dt><dd>{{ $account['name'] }}</dd></div>
                            <div><dt>Débit</dt><dd>{{ $account['rate'] !== '' ? $account['rate'] : '—' }}</dd></div>
                            <div><dt>Shared Users</dt><dd>{{ $account['shared'] !== '' ? $account['shared'] : '—' }}</dd></div>
                            <div><dt>Address Pool</dt><dd>{{ $account['pool'] !== '' ? $account['pool'] : 'none' }}</dd></div>
                            <div><dt>Parent Queue</dt><dd>{{ $account['queue'] !== '' ? $account['queue'] : 'none' }}</dd></div>
                            <div><dt>Durée</dt><dd>{{ $duration }}</dd></div>
                            <div><dt>Data</dt><dd>{{ $account['data'] !== '' ? $account['data'] : 'Selon le ticket' }}</dd></div>
                            <div><dt>Prix</dt><dd>{{ $account['price'] !== '' ? $account['price'] : '—' }}</dd></div>
                            @if($account['selling'] !== '')
                                <div><dt>Selling Price</dt><dd>{{ $account['selling'] }}</dd></div>
                            @endif
                            @if($account['summary'] !== '')
                                <div><dt>Résumé</dt><dd>{{ $account['summary'] }}</dd></div>
                            @endif
                        </dl>
                    </div>
                @endforeach
                <div class="tg-total">
                    <span>Quantité <strong data-tg-count>{{ $qtyShown }}</strong></span>
                    <strong data-tg-total>{{ $totalLabel }}</strong>
                </div>
                <div class="tg-actions">
                    <p class="text-lg font-semibold">5. Génération</p>
                    <button class="tg-cta" type="submit" data-tg-generate @disabled($limit < 1)>
                        <span class="tg-cta-idle"><svg class="tg-bolt" viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7l1-8z" fill="currentColor"/></svg>GÉNÉRER LES TICKETS</span>
                        <span class="tg-cta-busy">Génération…</span>
                    </button>
                    <button class="tg-secondary" type="submit" formaction="{{ route('vouchers.quick.user') }}">Créer l’utilisateur</button>
                </div>
            </aside>
        </form>
    @endif
</section>
