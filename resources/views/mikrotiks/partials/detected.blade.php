@php
    $text = function ($value) {
        if ($value === null || $value === '' || $value === []) {
            return 'Non détecté';
        }

        return is_bool($value) ? ($value ? 'Oui' : 'Non') : $value;
    };
    $servers = collect($probe['hotspot_servers'] ?? [])->pluck('name')->filter()->implode(', ');
    $profiles = $probe['user_profiles'] ?? [];
    $addresses = collect($probe['addresses'] ?? [])->pluck('address')->filter()->implode(', ');
    $interfaces = collect($probe['interfaces'] ?? [])->pluck('name')->filter()->implode(', ');
    $pools = collect($probe['pools'] ?? [])->pluck('name')->filter()->implode(', ');
@endphp
<section class="mt-4 min-w-0 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm">
    <p class="font-semibold">✓ Connexion réussie</p>
    <p class="font-semibold">✓ MikroTik détecté</p>
    <p class="mt-1 font-semibold">Informations détectées</p>
    <dl class="mt-3 space-y-1">
        <div class="flex justify-between gap-3"><dt>Connexion</dt><dd>CONNECTÉ</dd></div>
        <div class="flex justify-between gap-3"><dt>Identity</dt><dd class="truncate">{{ $text($probe['identity'] ?? null) }}</dd></div>
        <div class="flex justify-between gap-3"><dt>RouterOS</dt><dd>{{ $text($probe['version'] ?? null) }}</dd></div>
        <div class="flex justify-between gap-3"><dt>Uptime</dt><dd>{{ $text($probe['uptime'] ?? null) }}</dd></div>
        <div class="flex justify-between gap-3"><dt>CPU</dt><dd>{{ $text($probe['cpu'] ?? null) }}</dd></div>
        <div class="flex justify-between gap-3"><dt>Mémoire</dt><dd>{{ $text($probe['memory'] ?? null) }}</dd></div>
        <div class="flex justify-between gap-3"><dt>Architecture</dt><dd>{{ $text($probe['architecture'] ?? null) }}</dd></div>
        <div class="flex justify-between gap-3"><dt>Interfaces</dt><dd class="truncate">{{ $interfaces !== '' ? $interfaces : 'Non détecté' }}</dd></div>
        <div class="flex justify-between gap-3"><dt>Adresse(s) IP</dt><dd class="truncate">{{ $addresses !== '' ? $addresses : 'Non détecté' }}</dd></div>
        <div class="flex justify-between gap-3"><dt>HotSpot détecté</dt><dd>{{ !empty($probe['hotspot']) ? 'Oui' : 'Non' }}</dd></div>
        <div class="flex justify-between gap-3"><dt>HotSpot</dt><dd class="truncate">{{ $servers !== '' ? $servers : 'Non détecté' }}</dd></div>
        <div class="flex justify-between gap-3"><dt>Pool d'adresses</dt><dd class="truncate">{{ $pools !== '' ? $pools : 'Non détecté' }}</dd></div>
        <div class="flex justify-between gap-3"><dt>DNS</dt><dd class="truncate">{{ filled($probe['dns_name'] ?? null) ? $probe['dns_name'] : 'DNS non configuré' }}</dd></div>
    </dl>
    <p class="mt-3">HotSpot détecté : {{ !empty($probe['hotspot']) ? 'Oui' : 'Non' }}</p>
    <p class="mt-2 font-semibold">Profils HotSpot</p>
    @if($profiles === [])
        <p>Aucun profil utilisateur lu sur le routeur.</p>
    @else
        <ul class="mt-1 list-disc pl-5">
            @foreach($profiles as $name)
                <li>{{ $name }}</li>
            @endforeach
        </ul>
    @endif
</section>
