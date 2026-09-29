<ol class="mb-4 grid grid-cols-3 gap-2 text-xs font-semibold sm:grid-cols-6" aria-label="Création du ticket">
    @foreach(['Durée', 'Configuration', 'Vérification', 'Identifiants', 'Confirmation', 'Création'] as $index => $label)
        <li class="min-w-0 rounded-xl px-2 py-2 text-center {{ $step === $index + 1 ? 'bg-white text-electric' : ($step > $index + 1 ? 'text-emerald-700' : 'text-slate-400') }}">
            <span class="block">{{ $index + 1 }}</span>
            {{ $label }}
        </li>
    @endforeach
</ol>
