@props(['width' => 36, 'height' => 36, 'alt' => 'LIMETE WIFI'])
<img
    src="{{ asset('brand/logo-limete-wifi.svg') }}"
    alt="{{ $alt }}"
    width="{{ (int) $width }}"
    height="{{ (int) $height }}"
    style="width: {{ (int) $width }}px; height: {{ (int) $height }}px;"
    {{ $attributes->merge(['class' => 'lm-brand-logo']) }}
>
