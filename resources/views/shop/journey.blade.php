<ol class="pay-steps no-print" aria-label="Parcours de paiement">
    @foreach(['Sélection du forfait', 'Paiement', 'Vérification', 'Succès'] as $index => $label)
        <li class="{{ $current === $index + 1 ? 'is-current' : ($current > $index + 1 ? 'is-done' : '') }}" @if($current === $index + 1) aria-current="step" @endif>{{ $label }}</li>
    @endforeach
</ol>
