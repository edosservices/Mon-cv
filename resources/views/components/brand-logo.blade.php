@props(['width' => null, 'height' => 40, 'alt' => 'LIMETE WIFI MANAGER'])
<img
    src="{{ asset('brand/logo-limete-wifi-manager.png') }}"
    alt="{{ $alt }}"
    height="{{ (int) $height }}"
    style="height: {{ (int) $height }}px; width: auto;"
    {{ $attributes->merge(['class' => 'lm-brand-logo']) }}
>
