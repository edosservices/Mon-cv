<div {{ $attributes->merge(['class' => 'lm-empty']) }}>
    <svg class="lm-empty-art" viewBox="0 0 72 48" aria-hidden="true">
        <path d="M8 34c14-16 42-16 56 0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <path d="M20 36c8-8 24-8 32 0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <circle cx="36" cy="40" r="2.4" fill="currentColor"/>
        <circle cx="12" cy="18" r="2" fill="currentColor"/>
        <circle cx="60" cy="16" r="2" fill="#0f8a4b"/>
        <path d="M12 18 H36 M36 18 H60" fill="none" stroke="currentColor" stroke-width="1"/>
    </svg>
    <div class="lm-empty-copy">{{ $slot }}</div>
</div>
