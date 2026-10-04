@php
    $offers = [];
    $accounts = [];
    foreach ($quick['profiles'] as $profile) {
        $snap = $profile['snapshot'] ?? [];
        $price = $snap['price_amount'] ?? null;
        $selling = $snap['selling_price_amount'] ?? null;
        $row = [
            'name' => $profile['name'],
            'summary' => $profile['summary'] ?? '',
            'ready' => (bool) ($profile['ready'] ?? false),
            'validity' => $snap['validity'] ?? '',
            'validity_label' => $snap['validity_label'] ?? '',
            'time' => $snap['time_limit'] ?? '',
            'time_label' => $snap['time_label'] ?? '',
            'rate' => $snap['rate_limit'] ?? '',
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
@endphp

<section class="mb-8 rounded-2xl bg-white p-4 shadow-sm" data-user-compose>
    <h2 class="text-lg font-semibold">Tickets en un clic</h2>
    <p class="mt-1 text-sm text-slate-600">Le profil déjà créé complète la durée, le prix et le débit. Indiquez seulement le nombre de tickets, puis générez. Les comptes sont enregistrés sur le MikroTik.</p>
    @if($accounts === [])
        <p class="mt-3 text-sm text-slate-500">Aucun profil n’est encore lisible sur le MikroTik.</p>
        <p class="mt-3 text-sm text-slate-500">Aucun profil prêt. Associez un forfait dans les autres réglages, ou créez un utilisateur ci-dessous.</p>
    @else
        <form class="mt-4 grid gap-6" method="POST" action="{{ route('vouchers.quick.express') }}">
            @csrf
            <input type="hidden" name="wifi_zone_id" value="{{ $quickZone->id }}">

            <fieldset class="grid gap-3">
                <legend class="text-lg font-semibold">2. Profile MikroTik</legend>
                <label class="text-sm font-semibold">Profile
                    <select class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="profile" required data-user-profile>
                        @foreach($accounts as $account)
                            <option value="{{ $account['name'] }}" data-time="{{ $account['time'] ?: $account['validity'] }}" data-rate="{{ $account['rate'] }}" @selected($selectedProfile === $account['name'])>{{ $account['name'] }}</option>
                        @endforeach
                    </select>
                </label>
                @foreach($accounts as $account)
                    <dl class="grid gap-2 rounded-xl border bg-slate-50 p-3 text-sm" data-summary-for="{{ $account['name'] }}" @if($selectedProfile !== $account['name']) hidden @endif>
                        <div><dt class="text-slate-500">Profile</dt><dd class="font-semibold">{{ $account['name'] }}</dd></div>
                        <div><dt class="text-slate-500">Débit</dt><dd class="font-semibold">{{ $account['rate'] !== '' ? $account['rate'] : '—' }}</dd></div>
                        <div><dt class="text-slate-500">Shared Users</dt><dd class="font-semibold">{{ $account['shared'] !== '' ? $account['shared'] : '—' }}</dd></div>
                        <div><dt class="text-slate-500">Address Pool</dt><dd class="font-semibold">{{ $account['pool'] !== '' ? $account['pool'] : 'none' }}</dd></div>
                        <div><dt class="text-slate-500">Parent Queue</dt><dd class="font-semibold">{{ $account['queue'] !== '' ? $account['queue'] : 'none' }}</dd></div>
                        <div><dt class="text-slate-500">Durée</dt><dd class="font-semibold">{{ $account['time_label'] ?: ($account['validity_label'] ?: ($account['time'] ?: ($account['validity'] ?: '—'))) }}</dd></div>
                        <div><dt class="text-slate-500">Data</dt><dd class="font-semibold">{{ $account['data'] !== '' ? $account['data'] : 'Selon le ticket' }}</dd></div>
                        <div><dt class="text-slate-500">Prix</dt><dd class="font-semibold">{{ $account['price'] !== '' ? $account['price'] : '—' }}</dd></div>
                        @if($account['selling'] !== '')
                            <div><dt class="text-slate-500">Selling Price</dt><dd class="font-semibold">{{ $account['selling'] }}</dd></div>
                        @endif
                        @if($account['summary'] !== '')
                            <div><dt class="text-slate-500">Résumé</dt><dd class="font-semibold">{{ $account['summary'] }}</dd></div>
                        @endif
                    </dl>
                @endforeach
                @if($offers === [])
                    <p class="text-sm text-slate-500">Aucun profil prêt. Associez un forfait dans les autres réglages, ou créez un utilisateur ci-dessous.</p>
                @endif
            </fieldset>

            <fieldset class="grid gap-3">
                <legend class="text-lg font-semibold">3. Paramètres du ticket</legend>
                <h3 class="text-base font-semibold">Créer un utilisateur HotSpot</h3>
                <p class="text-sm text-slate-600">Les champs reprennent l’écran MikroTik. La durée calcule le Time Limit, le débit du profil reste celui du routeur, et le commentaire se rédige à partir du nom, du profil et de la durée.</p>
                <label class="text-sm font-semibold">Server
                    <select class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="server">
                        <option value="all" @selected(old('server', 'all') === 'all')>all</option>
                        @foreach($quick['servers'] as $server)
                            <option value="{{ $server }}" @selected(old('server') === $server)>{{ $server }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold">Name
                    <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="username" value="{{ old('username') }}" maxlength="32" autocapitalize="none" autocomplete="off" data-user-name placeholder="génération automatique">
                </label>
                <label class="text-sm font-semibold">Password <span class="font-normal text-slate-500">facultatif, 3 à 8 chiffres</span>
                    <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="password" value="{{ old('password') }}" inputmode="numeric" maxlength="8" autocomplete="new-password" placeholder="génération automatique">
                </label>
                <div data-time-calc>
                    <p class="text-sm font-semibold">Durée</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($spans as $code => $label)
                            <button class="rounded-lg border px-3 py-2 text-sm font-semibold" type="button" data-time-pick="{{ $code }}">{{ $label }}</button>
                        @endforeach
                    </div>
                    <label class="mt-3 block text-sm font-semibold">Time Limit
                        <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="time_limit" value="{{ old('time_limit') }}" placeholder="4d" maxlength="32" data-time-limit>
                    </label>
                    <p class="mt-2 text-sm text-slate-600" data-time-readout></p>
                </div>
                <label class="text-sm font-semibold">Data Limit MB
                    <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" type="number" name="data_mb" min="1" max="100000" value="{{ old('data_mb') }}" inputmode="numeric" data-user-data>
                </label>
                <label class="text-sm font-semibold">Comment
                    <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="comment" value="{{ old('comment') }}" maxlength="120" data-user-comment>
                </label>
                <p class="text-sm text-slate-500" data-user-rate></p>
            </fieldset>

            <fieldset class="grid gap-3">
                <legend class="text-lg font-semibold">4. Quantité</legend>
                <label class="text-sm font-semibold">Nombre de tickets
                    <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" type="number" name="qty" min="1" max="{{ max(1, $limit) }}" value="{{ old('qty', 1) }}" required inputmode="numeric">
                </label>
                <p class="text-sm text-slate-500">Quantité limitée par l’abonnement : {{ max(0, $limit) }}.</p>
            </fieldset>

            <fieldset class="grid gap-3">
                <legend class="text-lg font-semibold">5. Génération</legend>
                <button class="min-h-14 rounded-xl bg-electric px-4 py-3 font-semibold text-white" type="submit" @disabled($limit < 1)>GÉNÉRER LES TICKETS</button>
                <button class="min-h-14 rounded-xl border px-4 py-3 font-semibold" type="submit" formaction="{{ route('vouchers.quick.user') }}">Créer l’utilisateur</button>
            </fieldset>
        </form>
    @endif
</section>
