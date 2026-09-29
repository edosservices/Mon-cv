@props(['width' => 36, 'height' => 36, 'alt' => 'LIMETE WIFI'])
<img
    src="{{ asset('brand/logo-limete-wifi.svg') }}"
    alt="{{ $alt }}"
    width="{{ $width }}"
    height="{{ $height }}"
    {{ $attributes->merge(['class' => 'lm-brand-logo']) }}
>
