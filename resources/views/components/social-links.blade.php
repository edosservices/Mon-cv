@php
    $links = collect(config('limete.social', []))
        ->filter(fn ($url) => is_string($url) && filter_var($url, FILTER_VALIDATE_URL))
        ->all();
    $labels = [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'whatsapp' => 'WhatsApp',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube',
    ];
@endphp
@if($links !== [])
    <div class="vh-social">
        @foreach($links as $key => $url)
            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $labels[$key] ?? $key }}" title="{{ $labels[$key] ?? $key }}">
                <x-icon :name="$key" :size="18" />
            </a>
        @endforeach
    </div>
@else
    <p class="vh-muted">Les comptes officiels seront publiés ici.</p>
@endif
