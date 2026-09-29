@php
    $cards = [];
    foreach ($quick['profiles'] as $profile) {
        $snap = $profile['snapshot'];
        $fields = [];
        $push = function (string $label, mixed $value) use (&$fields) {
            if ($value !== null && $value !== '') {
                $fields[] = ['label' => $label, 'value' => (string) $value];
            }
        };
        $push('Validité', trim(($snap['validity'] ?? '').($snap['validity_label'] ? ' · '.$snap['validity_label'] : '')));
        $push('Limite de temps', $snap['time_label'] ?: ($snap['time_limit'] ?? null));
        $push('Prix', $snap['price_amount'] !== null ? \App\Support\Money::shop($snap['price_amount'], $snap['price_currency']) : null);
        $push('Prix de vente', $snap['selling_price_amount'] !== null ? \App\Support\Money::shop($snap['selling_price_amount'], $snap['selling_price_currency']) : null);
        $push('Débit', $snap['rate_limit'] ?? null);
        $push('Utilisateurs partagés', $snap['shared_users'] ?? null);
        $push('Verrouillage', $snap['lock_user'] ?? null);
        $push('Pool d’adresses', $snap['address_pool'] ?? null);
        $push('Queue parente', $snap['parent_queue'] ?? null);
        $push('Mode d’expiration', $snap['expired_mode'] ?? null);
        $cards[] = [
            'name' => $profile['name'],
            'summary' => $profile['summary'],
            'ready' => $profile['ready'],
            'fields' => $fields,
            'plans' => $profile['plans'] ?? [],
        ];
    }
@endphp

<section class="mb-8 rounded-2xl bg-white p-4 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold">Générer depuis un profil</h2>
            <p class="mt-1 text-sm text-slate-600">Le profil remplit la durée, le prix et le débit. Il reste la data à indiquer.</p>
        </div>
        @if($quick['last'])
            <button class="min-h-14 rounded-xl border px-4 py-3 font-semibold" type="button" data-use-profile="{{ $quick['last'] }}">Utiliser {{ $quick['last'] }}</button>
        @endif
    </div>
    @if($quick['notice'])
        <p class="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $quick['notice'] }}</p>
    @endif
    @if($cards === [])
        <p class="mt-3 text-sm text-slate-500">Aucun profil nommé n’est encore lié à un forfait ou lu sur le MikroTik.</p>
    @else
        <form class="mt-4 grid gap-4" method="POST" action="{{ route('vouchers.quick.preview') }}" data-quick-form>
            @csrf
            <input type="hidden" name="wifi_zone_id" value="{{ $quickZone->id }}">
            <input type="hidden" name="draft" value="{{ (string) str()->uuid() }}">
            <input type="hidden" name="profile" value="">
            <div class="grid grid-cols-2 gap-2">
                <button class="min-h-14 rounded-xl border px-3 py-3 font-semibold" type="button" data-quick-mode="generate" aria-pressed="true">Générer</button>
                <button class="min-h-14 rounded-xl border px-3 py-3 font-semibold" type="button" data-quick-mode="add" aria-pressed="false">Ajouter</button>
            </div>
            <input type="hidden" name="mode" value="generate">

            <div class="relative" data-combo>
                <label class="text-sm font-semibold" for="quick-server">Serveur
                    <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" id="quick-server" type="search" placeholder="Rechercher..." autocomplete="off" role="combobox" aria-expanded="false" aria-controls="quick-server-list" data-combo-input>
                </label>
                <input type="hidden" name="server" value="all" data-combo-value>
                <ul class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-xl border bg-white shadow-lg" id="quick-server-list" role="listbox" hidden data-combo-list>
                    @foreach($quick['servers'] as $server)
                        <li class="cursor-pointer px-3 py-3" role="option">{{ $server }}</li>
                    @endforeach
                </ul>
            </div>

            <div class="relative" data-combo>
                <label class="text-sm font-semibold" for="quick-profile">Profil
                    <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" id="quick-profile" type="search" placeholder="Rechercher un profil..." autocomplete="off" role="combobox" aria-expanded="false" aria-controls="quick-profile-list" data-combo-input>
                </label>
                <ul class="absolute z-20 mt-1 max-h-72 w-full overflow-auto rounded-xl border bg-white shadow-lg" id="quick-profile-list" role="listbox" hidden data-combo-list>
                    @foreach($cards as $card)
                        <li class="cursor-pointer px-3 py-3" role="option">
                            <strong class="block">{{ $card['name'] }}</strong>
                            @if($card['summary'] !== '')
                                <small class="text-slate-500">{{ $card['summary'] }}</small>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="grid gap-3" data-quick-fields hidden></div>

            <div class="grid gap-2 sm:grid-cols-[1fr_8rem]">
                <label class="text-sm font-semibold">Data limit
                    <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" type="number" name="data_value" min="1" max="100000" value="{{ old('data_value', 5) }}" required inputmode="numeric">
                </label>
                <label class="text-sm font-semibold">Unité
                    <select class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="data_unit">
                        <option value="GB" @selected(old('data_unit', 'GB') === 'GB')>GB</option>
                        <option value="MB" @selected(old('data_unit') === 'MB')>MB</option>
                    </select>
                </label>
            </div>

            <div class="grid gap-3" data-quick-add hidden>
                <label class="text-sm font-semibold">Nom
                    <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="username" value="{{ old('username') }}" maxlength="32" autocapitalize="none" autocomplete="off" data-username>
                </label>
                <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900" data-username-warning hidden></p>
                <div class="flex flex-wrap gap-2" data-username-suggestions></div>
                <label class="text-sm font-semibold">Mot de passe <span class="font-normal text-slate-500">facultatif, 3 à 8 chiffres</span>
                    <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="password" value="{{ old('password') }}" inputmode="numeric" maxlength="8">
                </label>
            </div>

            <div class="grid gap-3 sm:grid-cols-2" data-quick-generate>
                <label class="text-sm font-semibold">Quantité
                    <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" type="number" name="qty" min="1" max="{{ max(1, $limit) }}" value="{{ old('qty', min(50, max(1, $limit))) }}" inputmode="numeric">
                </label>
                <label class="text-sm font-semibold">Préfixe
                    <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="prefix" value="{{ old('prefix', 'LM') }}" maxlength="12" autocapitalize="characters">
                </label>
                <label class="text-sm font-semibold">Longueur
                    <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" type="number" name="length" min="3" max="8" value="{{ old('length', 4) }}">
                </label>
                <label class="text-sm font-semibold">Caractères
                    <select class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="charset">
                        <option value="mixed">Aléatoire</option>
                        <option value="digits">Chiffres</option>
                        <option value="upper">Majuscules</option>
                        <option value="lower">Minuscules</option>
                    </select>
                </label>
            </div>

            <button class="min-h-14 rounded-xl bg-electric px-4 py-3 font-semibold text-white" type="submit" @disabled($limit < 1)>Aperçu</button>
        </form>
        <div class="mt-4 grid gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-4" data-plan-panel hidden>
            <p class="text-sm font-semibold text-amber-950" data-plan-notice>{{ \App\Services\TicketQuick::MISSING_PLAN }}</p>
            <div class="grid gap-3" data-plan-technical></div>
            <form class="grid gap-3" method="POST" action="{{ route('vouchers.quick.plan') }}" data-plan-create>
                @csrf
                <input type="hidden" name="wifi_zone_id" value="{{ $quickZone->id }}">
                <input type="hidden" name="profile" value="">
                <p class="text-sm text-slate-700">Complétez uniquement le forfait commercial. Les paramètres techniques restent ceux du profil MikroTik.</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-sm font-semibold">Validity
                        <input class="mt-1 w-full rounded-xl border bg-white px-3 py-3 text-base" name="validity" value="{{ old('validity') }}" placeholder="1d" maxlength="32" required>
                    </label>
                    <label class="text-sm font-semibold">Time Limit
                        <input class="mt-1 w-full rounded-xl border bg-white px-3 py-3 text-base" name="time_limit" value="{{ old('time_limit') }}" placeholder="24h" maxlength="32">
                    </label>
                    <label class="text-sm font-semibold">Price
                        <input class="mt-1 w-full rounded-xl border bg-white px-3 py-3 text-base" type="number" name="price_amount" min="0" step="0.01" value="{{ old('price_amount') }}" required>
                    </label>
                    <label class="text-sm font-semibold">Currency
                        <input class="mt-1 w-full rounded-xl border bg-white px-3 py-3 text-base" name="price_currency" value="{{ old('price_currency', 'CDF') }}" maxlength="8" required>
                    </label>
                    <label class="text-sm font-semibold">Selling Price
                        <input class="mt-1 w-full rounded-xl border bg-white px-3 py-3 text-base" type="number" name="selling_price_amount" min="0" step="0.01" value="{{ old('selling_price_amount') }}">
                    </label>
                    <label class="text-sm font-semibold">Selling Currency
                        <input class="mt-1 w-full rounded-xl border bg-white px-3 py-3 text-base" name="selling_price_currency" value="{{ old('selling_price_currency') }}" maxlength="8">
                    </label>
                </div>
                <button class="min-h-14 rounded-xl bg-electric px-4 py-3 font-semibold text-white" type="submit">Créer le forfait LIMETE</button>
            </form>
            <form class="grid gap-3" method="POST" action="{{ route('vouchers.quick.link') }}" data-plan-link>
                @csrf
                <input type="hidden" name="wifi_zone_id" value="{{ $quickZone->id }}">
                <input type="hidden" name="profile" value="">
                <label class="text-sm font-semibold">Forfait compatible
                    <select class="mt-1 w-full rounded-xl border bg-white px-3 py-3 text-base" name="plan_id" data-plan-select required></select>
                </label>
                <button class="min-h-14 rounded-xl border bg-white px-4 py-3 font-semibold" type="submit">Associer à un forfait existant</button>
            </form>
        </div>
        <script type="application/json" id="quick-profiles">@json($cards)</script>
        <script type="application/json" id="quick-servers">@json($quick['servers'])</script>
    @endif
</section>
@push('scripts')
<script>
(function () {
    const form = document.querySelector('[data-quick-form]');
    if (!form) return;
    const profiles = JSON.parse(document.getElementById('quick-profiles').textContent || '[]');
    const servers = JSON.parse(document.getElementById('quick-servers').textContent || '[]');
    const profileInput = document.getElementById('quick-profile');
    const serverInput = document.getElementById('quick-server');
    const hiddenProfile = form.querySelector('input[name=profile]');
    const fields = form.querySelector('[data-quick-fields]');
    const modeInput = form.querySelector('input[name=mode]');

    function combo(root, items, onPick) {
        const input = root.querySelector('[data-combo-input]');
        const list = root.querySelector('[data-combo-list]');
        const hidden = root.querySelector('[data-combo-value]');
        let active = -1;
        function render(query) {
            const needle = query.trim().toLowerCase();
            const rows = items.filter((item) => item.label.toLowerCase().includes(needle));
            list.innerHTML = '';
            active = -1;
            rows.forEach((item, index) => {
                const li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.className = 'cursor-pointer px-3 py-3';
                li.dataset.index = String(index);
                const title = document.createElement('strong');
                title.className = 'block';
                title.textContent = item.label;
                li.appendChild(title);
                if (item.detail) {
                    const small = document.createElement('small');
                    small.className = 'text-slate-500';
                    small.textContent = item.detail;
                    li.appendChild(small);
                }
                li.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    choose(item);
                });
                list.appendChild(li);
            });
            list.hidden = rows.length === 0;
            input.setAttribute('aria-expanded', rows.length ? 'true' : 'false');
            return rows;
        }
        function choose(item) {
            input.value = item.label;
            if (hidden) hidden.value = item.value;
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            onPick(item);
        }
        input.addEventListener('input', () => {
            if (hidden && input.value.trim() === '') hidden.value = '';
            render(input.value);
        });
        input.addEventListener('focus', () => render(input.value));
        input.addEventListener('keydown', (event) => {
            const options = [...list.querySelectorAll('[role=option]')];
            if (event.key === 'Escape') { list.hidden = true; return; }
            if (!options.length || (event.key !== 'ArrowDown' && event.key !== 'ArrowUp' && event.key !== 'Enter')) return;
            event.preventDefault();
            if (event.key === 'ArrowDown') active = Math.min(options.length - 1, active + 1);
            if (event.key === 'ArrowUp') active = Math.max(0, active - 1);
            options.forEach((option, index) => option.classList.toggle('bg-slate-100', index === active));
            if (event.key === 'Enter' && options[active]) options[active].dispatchEvent(new MouseEvent('mousedown'));
        });
        document.addEventListener('click', (event) => { if (!root.contains(event.target)) list.hidden = true; });
        return { choose, render };
    }

    function showProfile(name) {
        const profile = profiles.find((item) => item.name === name);
        hiddenProfile.value = profile ? profile.name : '';
        fields.innerHTML = '';
        fields.hidden = !profile;
        if (!profile) return;
        profile.fields.forEach((field) => {
            const row = document.createElement('p');
            row.className = 'rounded-xl border px-3 py-3 text-sm';
            const label = document.createElement('span');
            label.className = 'block text-slate-500';
            label.textContent = field.label;
            const value = document.createElement('strong');
            value.className = 'mt-1 block text-base';
            value.textContent = field.value;
            const note = document.createElement('span');
            note.className = 'mt-1 block text-xs text-slate-500';
            note.textContent = 'Automatique depuis le profil';
            row.append(label, value, note);
            fields.appendChild(row);
        });
        const panel = document.querySelector('[data-plan-panel]');
        if (panel) {
            panel.hidden = profile.ready;
            panel.querySelectorAll('input[name=profile]').forEach((input) => { input.value = profile.ready ? '' : profile.name; });
            const technical = panel.querySelector('[data-plan-technical]');
            technical.innerHTML = '';
            if (!profile.ready) {
                profile.fields.forEach((field) => {
                    const row = document.createElement('p');
                    row.className = 'rounded-xl border bg-white px-3 py-3 text-sm';
                    const label = document.createElement('span');
                    label.className = 'block text-slate-500';
                    label.textContent = field.label;
                    const value = document.createElement('strong');
                    value.className = 'mt-1 block text-base';
                    value.textContent = field.value;
                    const note = document.createElement('span');
                    note.className = 'mt-1 block text-xs text-slate-500';
                    note.textContent = 'Automatique depuis le profil';
                    row.append(label, value, note);
                    technical.appendChild(row);
                });
            }
            const select = panel.querySelector('[data-plan-select]');
            select.innerHTML = '';
            (profile.plans || []).forEach((plan) => {
                const option = document.createElement('option');
                option.value = String(plan.id);
                option.textContent = plan.summary ? plan.name + ' · ' + plan.summary : plan.name;
                select.appendChild(option);
            });
            panel.querySelector('[data-plan-link]').hidden = !(profile.plans || []).length;
        }
    }

    const profileCombo = combo(profileInput.closest('[data-combo]'), profiles.map((item) => ({
        label: item.name,
        value: item.name,
        detail: item.summary,
    })), (item) => showProfile(item.value));
    combo(serverInput.closest('[data-combo]'), servers.map((name) => ({ label: name, value: name, detail: '' })), () => {});
    if (servers.length) {
        serverInput.value = servers[0];
    }

    form.querySelectorAll('[data-quick-mode]').forEach((button) => {
        button.addEventListener('click', () => {
            modeInput.value = button.getAttribute('data-quick-mode');
            const generate = modeInput.value === 'generate';
            form.querySelector('[data-quick-generate]').hidden = !generate;
            form.querySelector('[data-quick-add]').hidden = generate;
            form.querySelectorAll('[data-quick-mode]').forEach((item) => item.setAttribute('aria-pressed', item === button ? 'true' : 'false'));
        });
    });

    form.addEventListener('submit', (event) => {
        const typedProfile = profiles.find((item) => item.name.toLowerCase() === profileInput.value.trim().toLowerCase());
        if (typedProfile) showProfile(typedProfile.name);
        const typedServer = servers.find((name) => name.toLowerCase() === serverInput.value.trim().toLowerCase());
        const serverHidden = form.querySelector('input[name=server]');
        if (typedServer && serverHidden) serverHidden.value = typedServer;
        if (typedProfile && !typedProfile.ready) {
            event.preventDefault();
            document.querySelector('[data-plan-panel]')?.scrollIntoView({ block: 'nearest' });
        }
    });

    const useLast = document.querySelector('[data-use-profile]');
    if (useLast) {
        useLast.addEventListener('click', () => {
            const name = useLast.getAttribute('data-use-profile');
            profileCombo.choose({ label: name, value: name, detail: '' });
        });
    }

    const username = form.querySelector('[data-username]');
    const warning = form.querySelector('[data-username-warning]');
    const suggestionBox = form.querySelector('[data-username-suggestions]');
    let timer = null;
    function paintSuggestions(payload) {
        suggestionBox.innerHTML = '';
        warning.hidden = !payload.taken;
        warning.textContent = payload.taken ? 'Nom déjà utilisé' : '';
        (payload.suggestions || []).forEach((name) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'min-h-12 rounded-xl border px-3 py-2 text-sm font-semibold';
            button.textContent = 'Générer un autre : ' + name;
            button.addEventListener('click', () => { username.value = name; username.dispatchEvent(new Event('input')); });
            suggestionBox.appendChild(button);
        });
    }
    if (username) {
        username.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                const query = new URLSearchParams({ wifi_zone_id: form.querySelector('input[name=wifi_zone_id]').value, q: username.value });
                fetch(@json(route('vouchers.quick.username')) + '?' + query.toString(), { headers: { 'Accept': 'application/json' } })
                    .then((response) => response.json())
                    .then(paintSuggestions)
                    .catch(() => {});
            }, 250);
        });
    }
})();
</script>
@endpush
