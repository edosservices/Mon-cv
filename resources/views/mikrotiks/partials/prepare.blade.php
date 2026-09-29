<section class="space-y-4">
    <header class="rounded-2xl bg-white p-4 shadow-sm">
        <h2 class="text-lg font-semibold">Préparer mon MikroTik</h2>
        <p class="mt-1 text-sm text-slate-600">La configuration actuelle est lue avant toute proposition. Rien n’est envoyé au routeur sans confirmation.</p>
        <form method="POST" action="{{ route('mikrotiks.prepare.read', $router) }}" class="mt-3">
            @csrf
            <button class="rounded-xl border px-4 py-3 text-sm font-semibold">Lire la configuration</button>
        </form>
    </header>

    <ol class="grid gap-2 sm:grid-cols-2">
        @foreach($plan['steps'] as $step)
            <li class="rounded-2xl bg-white p-3 text-sm shadow-sm">
                <p class="font-semibold">{{ $step['ok'] ? '✓' : '✗' }} {{ $step['label'] }}</p>
                <p class="mt-1 break-words text-slate-600">{{ $step['detail'] }}</p>
            </li>
        @endforeach
    </ol>

    <article class="rounded-2xl bg-white p-4 text-sm shadow-sm">
        <h3 class="font-semibold">DNS</h3>
        <dl class="mt-3 space-y-1">
            <div><dt class="font-semibold">VALEUR ACTUELLE</dt><dd>{{ $plan['dns']['current'] }}</dd></div>
            <div><dt class="font-semibold">VALEUR PROPOSÉE</dt><dd>{{ $plan['dns']['proposed'] ?: 'DNS non configuré' }}</dd></div>
            <div><dt class="font-semibold">IMPACT</dt><dd>{{ $plan['dns']['impact'] }}</dd></div>
            <div><dt class="font-semibold">COMMANDE ROUTEROS</dt><dd class="font-mono text-xs">{{ $plan['dns']['command'] }}</dd></div>
        </dl>
        <p class="mt-2">DNS actuel : {{ $plan['dns']['current'] }}</p>
        <p>DNS proposé : {{ $plan['dns']['proposed'] ?: 'DNS non configuré' }}</p>
        <p>URL du portail : {{ $plan['dns']['portal_url'] ?: 'DNS non configuré' }}</p>
        <p>{{ $plan['dns']['resolution'] }}</p>
        <form method="POST" action="{{ route('mikrotiks.prepare.dns', $router) }}" class="mt-3 flex flex-col gap-2 sm:flex-row">
            @csrf
            <input class="w-full rounded-xl border px-3 py-3" name="dns" value="{{ $plan['dns']['proposed'] }}" placeholder="wifi.exemple.com">
            <button class="rounded-xl border px-4 py-3 font-semibold">Vérifier la résolution</button>
        </form>
        <form method="POST" action="{{ route('mikrotiks.prepare.apply', $router) }}" class="mt-3 space-y-2">
            @csrf
            <input type="hidden" name="group" value="dns">
            <input class="w-full rounded-xl border px-3 py-3" name="dns" value="{{ $plan['dns']['proposed'] }}" placeholder="DNS proposé">
            <label class="flex items-center gap-2"><input type="checkbox" name="confirm" value="1"> Je confirme ce groupe uniquement</label>
            <button class="rounded-xl bg-electric px-4 py-3 font-semibold text-white">Appliquer cette configuration</button>
        </form>
    </article>

    <article class="rounded-2xl bg-white p-4 text-sm shadow-sm">
        <h3 class="font-semibold">HotSpot</h3>
        @if($plan['hotspot']['state'] === 'missing')
            <p class="mt-2 font-semibold">HotSpot non configuré</p>
            <p class="mt-1">Choisissez une interface et un pool déjà présents sur le routeur. Le HotSpot n’est pas créé sans confirmation.</p>
        @endif
        <dl class="mt-3 space-y-1">
            <div><dt class="font-semibold">VALEUR ACTUELLE</dt><dd>{{ $plan['hotspot']['current'] }}</dd></div>
            <div><dt class="font-semibold">VALEUR PROPOSÉE</dt><dd>{{ $plan['hotspot']['proposed'] }}</dd></div>
            <div><dt class="font-semibold">IMPACT</dt><dd>{{ $plan['hotspot']['impact'] }}</dd></div>
            <div><dt class="font-semibold">COMMANDE ROUTEROS</dt><dd class="font-mono text-xs">{{ $plan['hotspot']['command'] }}</dd></div>
        </dl>
        @if($plan['hotspot']['state'] === 'missing')
            <form method="POST" action="{{ route('mikrotiks.prepare.apply', $router) }}" class="mt-3 space-y-2">
                @csrf
                <input type="hidden" name="group" value="hotspot">
                <input class="w-full rounded-xl border px-3 py-3" name="hotspot_name" placeholder="Nom du HotSpot">
                <label>Interface
                    <select class="mt-1 w-full rounded-xl border bg-white px-3 py-3" name="interface">
                        @foreach($router->detail('interfaces', []) as $interface)
                            <option value="{{ $interface['name'] ?? '' }}">{{ $interface['name'] ?? 'Non détecté' }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Address Pool
                    <select class="mt-1 w-full rounded-xl border bg-white px-3 py-3" name="address_pool">
                        @foreach($router->detail('pools', []) as $pool)
                            <option value="{{ $pool['name'] ?? '' }}">{{ $pool['name'] ?? 'Non détecté' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex items-center gap-2"><input type="checkbox" name="confirm" value="1"> Je confirme ce groupe uniquement</label>
                <button class="rounded-xl bg-electric px-4 py-3 font-semibold text-white">Appliquer cette configuration</button>
            </form>
        @endif
    </article>

    <article class="rounded-2xl bg-white p-4 text-sm shadow-sm">
        <h3 class="font-semibold">Walled Garden</h3>
        <dl class="mt-3 space-y-1">
            <div><dt class="font-semibold">VALEUR ACTUELLE</dt><dd class="break-words">{{ $plan['garden']['current'] }}</dd></div>
            <div><dt class="font-semibold">VALEUR PROPOSÉE</dt><dd class="break-words">{{ $plan['garden']['proposed'] }}</dd></div>
            <div><dt class="font-semibold">IMPACT</dt><dd>{{ $plan['garden']['impact'] }}</dd></div>
            <div><dt class="font-semibold">COMMANDE ROUTEROS</dt><dd class="whitespace-pre-wrap font-mono text-xs">{{ $plan['garden']['command'] }}</dd></div>
        </dl>
        <form method="POST" action="{{ route('mikrotiks.prepare.apply', $router) }}" class="mt-3 space-y-2">
            @csrf
            <input type="hidden" name="group" value="walled_garden">
            <label class="flex items-center gap-2"><input type="checkbox" name="confirm" value="1"> Je confirme ce groupe uniquement</label>
            <button class="rounded-xl bg-electric px-4 py-3 font-semibold text-white">Appliquer cette configuration</button>
        </form>
    </article>

    <article class="rounded-2xl bg-white p-4 text-sm shadow-sm">
        <h3 class="font-semibold">Portail captif</h3>
        <ul class="mt-3 space-y-1">
            @foreach($plan['files'] as $file)
                <li>{{ $file['present'] ? '✓ présent' : '✗ manquant' }} {{ $file['file'] }}@if($file['secret']) — secret détecté, ne pas copier @endif</li>
            @endforeach
        </ul>
        <p class="mt-2 text-slate-600">Copiez le dossier hotspot/ sur le routeur après vérification. Les fichiers ne contiennent pas le mot de passe du routeur.</p>
    </article>

    <article class="rounded-2xl bg-white p-4 text-sm shadow-sm">
        <h3 class="font-semibold">Profils HotSpot</h3>
        <p class="mt-1 text-slate-600">Le nom du forfait Laravel n’est pas le nom du profil RouterOS. Exemple : 24 HEURES peut être associé à VIP-24H.</p>
        @forelse($plan['profiles'] as $profile)
            @php $raw = $profile->raw ?? []; @endphp
            <article class="mt-3 rounded-xl bg-slate-50 p-3">
                <p class="font-semibold">{{ $profile->name }}</p>
                <p>session-timeout : {{ $raw['session-timeout'] ?? 'Non détecté' }}</p>
                <p>rate-limit : {{ $profile->rate_limit ?: 'Non détecté' }}</p>
                <p>idle-timeout : {{ $raw['idle-timeout'] ?? 'Non détecté' }}</p>
                <p>shared-users : {{ $profile->shared_users ?? 'Non détecté' }}</p>
                <p>keepalive-timeout : {{ $raw['keepalive-timeout'] ?? 'Non détecté' }}</p>
            </article>
        @empty
            <p class="mt-2">Non détecté</p>
        @endforelse
        <form method="POST" action="{{ route('mikrotiks.prepare.apply', $router) }}" class="mt-4 space-y-2">
            @csrf
            <input type="hidden" name="group" value="profile">
            <p class="font-semibold">Créer un profil manquant</p>
            <input class="w-full rounded-xl border px-3 py-3" name="profile_name" placeholder="Nom">
            <input class="w-full rounded-xl border px-3 py-3" name="session_timeout" placeholder="Session Timeout, ex. 1d">
            <input class="w-full rounded-xl border px-3 py-3" name="rate_limit" placeholder="Rate Limit, ex. 2M/2M">
            <input class="w-full rounded-xl border px-3 py-3" name="idle_timeout" placeholder="Idle Timeout, ex. 5m">
            <input class="w-full rounded-xl border px-3 py-3" name="shared_users" placeholder="Shared Users">
            <label class="flex items-center gap-2"><input type="checkbox" name="confirm" value="1"> Je confirme la création de ce profil</label>
            <button class="rounded-xl bg-electric px-4 py-3 font-semibold text-white">Appliquer cette configuration</button>
        </form>
        <div class="mt-4 space-y-2">
            <h4 class="font-semibold">Forfait Laravel → Profil RouterOS</h4>
            @foreach($plans as $item)
                @php $linked = $router->planLinks->firstWhere('plan_id', $item->id); @endphp
                <form method="POST" action="{{ route('mikrotiks.plan-profile', $router) }}" class="rounded-xl bg-slate-50 p-3">
                    @csrf
                    <input type="hidden" name="plan_id" value="{{ $item->id }}">
                    <p class="font-semibold">{{ $item->name }}</p>
                    @if(! $linked && ! $item->mikrotik_profile)
                        <p>Profil non associé</p>
                    @endif
                    <select class="mt-1 w-full rounded-xl border bg-white px-3 py-3" name="mikrotik_profile">
                        @foreach($router->profiles as $profile)
                            <option value="{{ $profile->name }}" @selected(($linked?->profile?->name ?? $item->mikrotik_profile) === $profile->name)>{{ $profile->name }}</option>
                        @endforeach
                    </select>
                    @if($router->profiles->isNotEmpty())
                        <button class="mt-2 rounded-xl border px-3 py-2">Associer</button>
                    @endif
                </form>
            @endforeach
        </div>
    </article>

    <article class="rounded-2xl bg-white p-4 text-sm shadow-sm">
        <h3 class="font-semibold">Vérification finale</h3>
        <p class="mt-1">Crée un compte HotSpot temporaire LIMETE_TEST_…, le relit, cherche une session, puis le supprime. Aucun ticket client n’est utilisé.</p>
        <form method="POST" action="{{ route('mikrotiks.prepare.verify', $router) }}" class="mt-3">
            @csrf
            <button class="rounded-xl border px-4 py-3 font-semibold">Vérifier ma configuration</button>
        </form>
        @if(!empty($verifyReport))
            <ul class="mt-3 space-y-1">
                @foreach($verifyReport as $step)
                    <li>{{ $step['ok'] ? '✓' : '✗' }} {{ $step['label'] }} — {{ $step['detail'] }}</li>
                @endforeach
            </ul>
        @endif
    </article>

    <article class="rounded-2xl bg-white p-4 text-sm shadow-sm">
        <h3 class="font-semibold">Snapshots</h3>
        <ul class="mt-3 space-y-2">
            @forelse($plan['snapshots'] as $snapshot)
                <li class="rounded-xl bg-slate-50 p-3">
                    <p class="font-semibold">{{ $snapshot->group }} — {{ $snapshot->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
                    @if(in_array($snapshot->group, ['dns', 'walled_garden', 'profile'], true) && ! $snapshot->restored_at)
                        <form method="POST" action="{{ route('mikrotiks.snapshots.restore', [$router, $snapshot]) }}" class="mt-2">
                            @csrf
                            <button class="rounded-xl border px-3 py-2">Restaurer</button>
                        </form>
                    @else
                        <p class="mt-1">{{ $snapshot->restored_at ? 'Déjà restauré' : 'Restauration non disponible' }}</p>
                    @endif
                </li>
            @empty
                <li>Aucun snapshot. Une sauvegarde est créée juste avant une modification confirmée.</li>
            @endforelse
        </ul>
    </article>

    <form method="POST" action="{{ route('mikrotiks.pending', $router) }}">
        @csrf
        <button class="rounded-xl border bg-white px-4 py-3 text-sm font-semibold">Synchroniser les tickets en attente</button>
    </form>
</section>
