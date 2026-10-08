<section class="card border-0 en-chart-card" aria-label="Graphiques">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
            <h2>Activité</h2>
            <form class="d-flex flex-wrap gap-2" method="GET">
                @foreach(request()->except(['chart', 'chart_from', 'chart_to', 'page']) as $key => $value)
                    @if(is_string($value) || is_numeric($value))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <select class="form-select form-select-sm" name="chart" onchange="this.form.submit()" aria-label="Période des graphiques">
                    @foreach(['today' => 'Aujourd’hui', '7' => '7 jours', '30' => '30 jours', '90' => '90 jours', 'year' => 'Cette année', 'custom' => 'Dates'] as $value => $label)
                        <option value="{{ $value }}" @selected($charts['range']['preset'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @if($charts['range']['preset'] === 'custom')
                    <input class="form-control form-control-sm" type="date" name="chart_from" value="{{ $charts['range']['from']->toDateString() }}">
                    <input class="form-control form-control-sm" type="date" name="chart_to" value="{{ $charts['range']['to']->toDateString() }}">
                    <button class="btn btn-sm btn-primary" type="submit">Appliquer</button>
                @endif
            </form>
        </div>

        <ul class="en-legend">
            @foreach($charts['kpis'] as $label => $value)
                <li>{{ $label }} · {{ $value }}</li>
            @endforeach
            @forelse($charts['revenue'] as $currency)
                <li>Revenus {{ $currency['currency'] }} · {{ $currency['label'] }} · {{ $currency['count'] }} vente(s)</li>
            @empty
                <li>Revenus · —</li>
            @endforelse
        </ul>

        <div class="row g-3 mt-1">
            <div class="col-lg-4">
                <h3 class="h6 mt-2">Tickets</h3>
                <div class="en-chart is-short">
                    <canvas data-en-chart="statuses" data-en-source="en-statuses" aria-label="Répartition des tickets"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <h3 class="h6 mt-2">Profils utilisés</h3>
                @if($charts['profiles'] === [])
                    <p class="text-secondary small mt-2 mb-0">Aucun ticket sur cette période.</p>
                @else
                    <div class="en-chart is-short">
                        <canvas data-en-chart="profiles" data-en-source="en-profiles" aria-label="Profils utilisés"></canvas>
                    </div>
                    <ul class="en-legend">
                        @foreach($charts['profiles'] as $profile)
                            <li>{{ $profile['name'] }} · {{ $profile['total'] }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="col-lg-4">
                <h3 class="h6 mt-2">Paiements</h3>
                @if($charts['payments'] === [])
                    <p class="text-secondary small mt-2 mb-0">Aucun paiement confirmé sur cette période.</p>
                @else
                    <div class="en-chart is-short">
                        <canvas data-en-chart="providers" data-en-source="en-providers" aria-label="Paiements par moyen"></canvas>
                    </div>
                    <ul class="en-legend">
                        @foreach($charts['payments'] as $payment)
                            <li>{{ $payment['provider'] }} · {{ $payment['label'] }} · {{ $payment['total'] }}</li>
                        @endforeach
                    </ul>
                @endif
                <p class="small text-secondary mt-2 mb-0">Utilisateurs créés · {{ array_sum(array_column($charts['created'], 'total')) }} sur la période</p>
            </div>
        </div>

        <h3 class="h6 mt-3">Activité récente</h3>
        <ul class="list-unstyled mb-0 small">
            @forelse($charts['activity'] as $item)
                <li class="d-flex justify-content-between gap-3 py-1">
                    <span class="min-w-0">{{ $item['text'] }}</span>
                    <time class="text-secondary">{{ $item['at'] }}</time>
                </li>
            @empty
                <li class="text-secondary">Aucune activité récente.</li>
            @endforelse
        </ul>
    </div>
</section>
<script type="application/json" id="en-statuses">@json($charts['statuses'])</script>
<script type="application/json" id="en-profiles">@json($charts['profiles'])</script>
<script type="application/json" id="en-providers">@json($charts['payments'])</script>
