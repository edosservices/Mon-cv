@props(['payments' => []])
@php
    $marks = [];
    $names = [];
    foreach ($payments as $key => $label) {
        $file = null;
        foreach (['svg', 'png', 'webp'] as $ext) {
            if (is_file(public_path('brand/payments/'.$key.'.'.$ext))) {
                $file = 'brand/payments/'.$key.'.'.$ext;
                break;
            }
        }
        $alt = $key === 'card' ? 'Visa et Mastercard' : $label;
        if ($file) {
            $marks[$key] = ['file' => $file, 'alt' => $alt];
        } else {
            $names[$key] = $label;
        }
    }
@endphp
<div class="vh-pays">
    @forelse($marks as $mark)
        <div class="vh-pay">
            <img src="{{ asset($mark['file']) }}" alt="{{ $mark['alt'] }}" loading="lazy" decoding="async">
        </div>
    @empty
        @if($names === [])
            <p class="vh-muted">Les moyens de paiement s’affichent lorsqu’ils sont activés.</p>
        @endif
    @endforelse
    @foreach($names as $label)
        <div class="vh-pay"><span>{{ $label }}</span></div>
    @endforeach
</div>
