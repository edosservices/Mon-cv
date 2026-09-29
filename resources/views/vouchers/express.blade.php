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
@endphp

<section class="mb-8 rounded-2xl bg-white p-4 shadow-sm">
    <h2 class="text-lg font-semibold">Tickets en un clic</h2>
    <p class="mt-1 text-sm text-slate-600">Le profil déjà créé complète la durée, le prix et le débit. Indiquez seulement le nombre de tickets, puis générez. Les comptes sont enregistrés sur le MikroTik.</p>
    @if($offers === [])
        <p class="mt-3 text-sm text-slate-500">Aucun profil prêt. Associez un forfait dans les autres réglages, ou créez un utilisateur ci-dessous.</p>
    @else
        <form class="mt-4 grid gap-4" method="POST" action="{{ route('vouchers.quick.express') }}">
            @csrf
            <input type="hidden" name="wifi_zone_id" value="{{ $quickZone->id }}">
            <input type="hidden" name="server" value="all">
            <fieldset class="grid gap-2">
                <legend class="text-sm font-semibold">Profil</legend>
                @foreach($offers as $offer)
                    <label class="lm-express-choice rounded-xl border px-3 py-3">
                        <span class="flex items-start gap-3">
                            <input type="radio" name="profile" value="{{ $offer['name'] }}" @checked($loop->first || old('profile') === $offer['name']) required>
                            <span>
                                <strong class="block">{{ $offer['name'] }}</strong>
                                @if($offer['summary'] !== '')
                                    <small class="text-slate-500">{{ $offer['summary'] }}</small>
                                @endif
                            </span>
                        </span>
                        <dl class="lm-express-detail mt-3 grid gap-1 text-sm text-slate-700">
                            @if($offer['validity'] !== '' || $offer['validity_label'] !== '')
                                <div><dt class="inline text-slate-500">Validité</dt> <dd class="inline font-semibold">{{ trim($offer['validity'].' '.$offer['validity_label']) }}</dd></div>
                            @endif
                            @if($offer['time'] !== '' || $offer['time_label'] !== '')
                                <div><dt class="inline text-slate-500">Time Limit</dt> <dd class="inline font-semibold">{{ $offer['time_label'] ?: $offer['time'] }}</dd></div>
                            @endif
                            @if($offer['rate'] !== '')
                                <div><dt class="inline text-slate-500">Rate limit</dt> <dd class="inline font-semibold">{{ $offer['rate'] }}</dd></div>
                            @endif
                            @if($offer['price'] !== '')
                                <div><dt class="inline text-slate-500">Price</dt> <dd class="inline font-semibold">{{ $offer['price'] }}</dd></div>
                            @endif
                            @if($offer['selling'] !== '')
                                <div><dt class="inline text-slate-500">Selling Price</dt> <dd class="inline font-semibold">{{ $offer['selling'] }}</dd></div>
                            @endif
                            @if($offer['shared'] !== '')
                                <div><dt class="inline text-slate-500">Shared Users</dt> <dd class="inline font-semibold">{{ $offer['shared'] }}</dd></div>
                            @endif
                            @if($offer['pool'] !== '')
                                <div><dt class="inline text-slate-500">Address Pool</dt> <dd class="inline font-semibold">{{ $offer['pool'] }}</dd></div>
                            @endif
                            @if($offer['lock'] !== '')
                                <div><dt class="inline text-slate-500">Lock User</dt> <dd class="inline font-semibold">{{ $offer['lock'] }}</dd></div>
                            @endif
                            @if($offer['queue'] !== '')
                                <div><dt class="inline text-slate-500">Parent Queue</dt> <dd class="inline font-semibold">{{ $offer['queue'] }}</dd></div>
                            @endif
                            @if($offer['expired'] !== '')
                                <div><dt class="inline text-slate-500">Expired Mode</dt> <dd class="inline font-semibold">{{ $offer['expired'] }}</dd></div>
                            @endif
                        </dl>
                    </label>
                @endforeach
            </fieldset>
            <label class="text-sm font-semibold">Nombre de tickets
                <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" type="number" name="qty" min="1" max="{{ max(1, $limit) }}" value="{{ old('qty', 1) }}" required inputmode="numeric">
            </label>
            <button class="min-h-14 rounded-xl bg-electric px-4 py-3 font-semibold text-white" type="submit" @disabled($limit < 1)>Générer</button>
        </form>
    @endif
</section>

<section class="mb-8 rounded-2xl bg-white p-4 shadow-sm" data-user-compose>
    <h2 class="text-lg font-semibold">Créer un utilisateur HotSpot</h2>
    <p class="mt-1 text-sm text-slate-600">Les champs reprennent l’écran MikroTik. La durée calcule le Time Limit, le débit du profil reste celui du routeur, et le commentaire se rédige à partir du nom, du profil et de la durée.</p>
    @if($accounts === [])
        <p class="mt-3 text-sm text-slate-500">Aucun profil n’est encore lisible sur le MikroTik.</p>
    @else
        <form class="mt-4 grid gap-4" method="POST" action="{{ route('vouchers.quick.user') }}">
            @csrf
            <input type="hidden" name="wifi_zone_id" value="{{ $quickZone->id }}">
            <label class="text-sm font-semibold">Server
                <select class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="server">
                    <option value="all" @selected(old('server', 'all') === 'all')>all</option>
                    @foreach($quick['servers'] as $server)
                        <option value="{{ $server }}" @selected(old('server') === $server)>{{ $server }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-semibold">Name
                <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="username" value="{{ old('username') }}" maxlength="32" autocapitalize="none" autocomplete="off" data-user-name>
            </label>
            <label class="text-sm font-semibold">Password <span class="font-normal text-slate-500">facultatif, 3 à 8 chiffres</span>
                <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="password" value="{{ old('password') }}" inputmode="numeric" maxlength="8" autocomplete="new-password">
            </label>
            <label class="text-sm font-semibold">Profile
                <select class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="profile" required data-user-profile>
                    @foreach($accounts as $account)
                        <option value="{{ $account['name'] }}" data-time="{{ $account['time'] ?: $account['validity'] }}" data-rate="{{ $account['rate'] }}" @selected(old('profile', $accounts[0]['name']) === $account['name'])>{{ $account['name'] }}</option>
                    @endforeach
                </select>
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
            <button class="min-h-14 rounded-xl bg-electric px-4 py-3 font-semibold text-white" type="submit">Créer l’utilisateur</button>
        </form>
    @endif
</section>
