<article class="ticket model-classique" data-model="classique">
    <header class="head" style="background: {{ $ticket['color'] }};">
        @if($ticket['logo'])<img src="{{ $ticket['logo'] }}" alt="">@endif
        <p class="business">{{ $ticket['business'] }}</p>
        <p class="zone">{{ $ticket['zone'] }}</p>
    </header>
    @include('vouchers.templates.fields')
</article>
