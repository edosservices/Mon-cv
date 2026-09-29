@props(['label', 'count' => null])
<article {{ $attributes->merge(['class' => 'lm-kpi lm-reveal']) }}>
    <p class="lm-kpi-label">{{ $label }}</p>
    <p class="lm-kpi-value" @if(isset($count)) data-count="{{ $count }}" @endif>{{ $slot }}</p>
</article>
