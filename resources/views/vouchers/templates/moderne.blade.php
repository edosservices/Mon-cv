<article class="ticket model-moderne" data-model="moderne" style="border-left: 6px solid {{ $ticket['color'] }};">
    <header class="head">
        @if($ticket['logo'])<img src="{{ $ticket['logo'] }}" alt="">@endif
        <div>
            <p class="business">{{ $ticket['business'] }}</p>
            <p class="zone">{{ $ticket['zone'] }}</p>
        </div>
    </header>
    @include('vouchers.templates.fields')
</article>
