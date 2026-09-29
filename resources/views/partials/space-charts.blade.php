<section class="mt-6 rounded-2xl bg-white p-4 shadow-sm" aria-label="Graphiques">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <h2 class="font-semibold">Activité</h2>
        <form class="flex flex-wrap gap-2" method="GET">
            @foreach(request()->except(['chart', 'chart_from', 'chart_to', 'page']) as $key => $value)
                @if(is_string($value) || is_numeric($value))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <select class="rounded-xl border px-3 py-3 text-sm" name="chart" onchange="this.form.submit()">
                @foreach(['today' => 'Aujourd’hui', '7' => '7 jours', '30' => '30 jours', '90' => '90 jours', 'year' => 'Cette année', 'custom' => 'Dates'] as $value => $label)
                    <option value="{{ $value }}" @selected($charts['range']['preset'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @if($charts['range']['preset'] === 'custom')
                <input class="rounded-xl border px-3 py-3 text-sm" type="date" name="chart_from" value="{{ $charts['range']['from']->toDateString() }}">
                <input class="rounded-xl border px-3 py-3 text-sm" type="date" name="chart_to" value="{{ $charts['range']['to']->toDateString() }}">
                <button class="rounded-xl border px-3 py-3 text-sm font-semibold" type="submit">Appliquer</button>
            @endif
        </form>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($charts['kpis'] as $label => $value)
            <article class="rounded-2xl border p-3">
                <p class="text-sm text-slate-500">{{ $label }}</p>
                <p class="text-2xl font-semibold">{{ $value }}</p>
            </article>
        @endforeach
        @forelse($charts['revenue'] as $currency)
            <article class="rounded-2xl border p-3">
                <p class="text-sm text-slate-500">Revenus {{ $currency['currency'] }}</p>
                <p class="text-2xl font-semibold">{{ $currency['label'] }}</p>
            </article>
        @empty
            <article class="rounded-2xl border p-3">
                <p class="text-sm text-slate-500">Revenus</p>
                <p class="text-2xl font-semibold">—</p>
            </article>
        @endforelse
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <article>
            <h3 class="font-semibold">Ventes par devise</h3>
            @forelse($charts['revenue'] as $currency)
                <p class="mt-2 text-sm font-semibold">{{ $currency['label'] }} · {{ $currency['count'] }} vente(s)</p>
                @php $max = max(1, collect($currency['days'])->max('amount')); @endphp
                <div class="mt-2 flex h-24 items-end gap-1">
                    @foreach($currency['days'] as $day)
                        <div class="min-w-2 flex-1 rounded-t bg-electric" style="height: {{ max(4, ($day['amount'] / $max) * 96) }}px" title="{{ $day['day'] }}"></div>
                    @endforeach
                </div>
            @empty
                <p class="mt-2 text-sm text-slate-500">Pas encore de vente confirmée sur cette période.</p>
            @endforelse
        </article>
        <article>
            <h3 class="font-semibold">Tickets</h3>
            @php $statusMax = max(1, max($charts['statuses'])); @endphp
            <ul class="mt-2 space-y-2">
                @foreach($charts['statuses'] as $label => $value)
                    <li>
                        <div class="flex justify-between text-sm"><span>{{ $label }}</span><span>{{ $value }}</span></div>
                        <div class="mt-1 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-electric" style="width: {{ ($value / $statusMax) * 100 }}%"></div></div>
                    </li>
                @endforeach
            </ul>
        </article>
        <article>
            <h3 class="font-semibold">Profils utilisés</h3>
            @forelse($charts['profiles'] as $profile)
                <p class="mt-2 text-sm">{{ $profile['name'] }} · {{ $profile['total'] }}</p>
            @empty
                <p class="mt-2 text-sm text-slate-500">Aucun ticket sur cette période.</p>
            @endforelse
        </article>
        <article>
            <h3 class="font-semibold">Paiements</h3>
            @forelse($charts['payments'] as $payment)
                <p class="mt-2 text-sm">{{ $payment['provider'] }} · {{ $payment['label'] }} · {{ $payment['total'] }}</p>
            @empty
                <p class="mt-2 text-sm text-slate-500">Aucun paiement confirmé sur cette période.</p>
            @endforelse
            <h3 class="mt-4 font-semibold">Utilisateurs créés</h3>
            <p class="mt-2 text-sm">{{ array_sum(array_column($charts['created'], 'total')) }} sur la période</p>
        </article>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <article>
            <h3 class="font-semibold">Activité récente</h3>
            <ul class="mt-2 space-y-2 text-sm">
                @forelse($charts['activity'] as $item)
                    <li class="flex justify-between gap-3"><span>{{ $item['text'] }}</span><time class="text-slate-500">{{ $item['at'] }}</time></li>
                @empty
                    <li class="text-slate-500">Aucune activité récente.</li>
                @endforelse
            </ul>
        </article>
        <article>
            <h3 class="font-semibold">MikroTik</h3>
            @if($charts['router'])
                <p class="mt-2 text-sm font-semibold">{{ $charts['router']['online'] ? 'Connected' : 'Offline' }}</p>
                <dl class="mt-2 grid grid-cols-2 gap-2 text-sm">
                    <div><dt class="text-slate-500">Nom</dt><dd>{{ $charts['router']['name'] }}</dd></div>
                    <div><dt class="text-slate-500">Identity</dt><dd>{{ $charts['router']['identity'] ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">RouterOS</dt><dd>{{ $charts['router']['version'] ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Uptime</dt><dd>{{ $charts['router']['uptime'] ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">CPU</dt><dd>{{ $charts['router']['cpu'] ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Mémoire</dt><dd>{{ $charts['router']['memory'] ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Interfaces</dt><dd>{{ $charts['router']['interfaces'] ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">HotSpot Servers</dt><dd>{{ $charts['router']['servers'] }}</dd></div>
                    <div><dt class="text-slate-500">HotSpot Profiles</dt><dd>{{ $charts['router']['profiles'] }}</dd></div>
                    <div><dt class="text-slate-500">HotSpot Users</dt><dd>{{ $charts['router']['users'] ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Active Sessions</dt><dd>{{ $charts['router']['sessions'] ?? '—' }}</dd></div>
                </dl>
                @if(auth()->user()->hasPermission('mikrotiks.manage'))
                    <div class="mt-3 flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('mikrotiks.sync', $charts['router']['id']) }}">@csrf<button class="min-h-12 rounded-xl border px-3 py-2 text-sm font-semibold" type="submit">Synchroniser</button></form>
                        <a class="min-h-12 rounded-xl border px-3 py-2 text-sm font-semibold" href="{{ route('mikrotiks.show', $charts['router']['id']) }}">Voir MikroTik</a>
                        <form method="POST" action="{{ route('mikrotiks.sync', $charts['router']['id']) }}">@csrf<button class="min-h-12 rounded-xl border px-3 py-2 text-sm font-semibold" type="submit">Réessayer</button></form>
                    </div>
                @endif
            @else
                <p class="mt-2 text-sm text-slate-500">Aucun routeur associé.</p>
            @endif
        </article>
    </div>
</section>
