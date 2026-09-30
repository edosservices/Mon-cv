@props(['payments' => []])
<div class="vh-pays">
    @forelse($payments as $key => $label)
        @php
            $file = null;
            foreach (['svg', 'png', 'webp'] as $ext) {
                if (is_file(public_path('brand/payments/'.$key.'.'.$ext))) {
                    $file = 'brand/payments/'.$key.'.'.$ext;
                    break;
                }
            }
        @endphp
        <div class="vh-pay">
            @if($file)
                <img src="{{ asset($file) }}" alt="{{ $label }}" width="120" height="48" loading="lazy">
            @else
                <span>{{ $label }}</span>
            @endif
        </div>
    @empty
        <p class="vh-muted">Les moyens de paiement s’affichent lorsqu’ils sont configurés.</p>
    @endforelse
</div>
