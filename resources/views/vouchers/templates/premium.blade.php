<article class="ticket model-premium" data-model="premium">
    <header class="head">
        @if($ticket['logo'])<img src="{{ $ticket['logo'] }}" alt="">@endif
        <p class="business">{{ $ticket['business'] }}</p>
        <p class="zone">{{ $ticket['zone'] }}</p>
    </header>
    @include('vouchers.templates.fields')
</article>
