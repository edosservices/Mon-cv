@if(!empty($onboardingSteps) && collect($onboardingSteps)->contains(fn ($step) => ! $step['done']))
    @php
        $done = collect($onboardingSteps)->where('done', true)->count();
        $total = count($onboardingSteps);
        $percent = (int) round(($done / max(1, $total)) * 100);
        $business = ($onboardingTheme ?? 'app') === 'business';
    @endphp
    @if($business)
        <section class="card border-0 shadow-sm mb-3" aria-label="Configuration">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-2">
                    <strong>Configuration</strong>
                    <span class="text-secondary small">{{ $done }}/{{ $total }}</span>
                </div>
                <div class="progress mb-3" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar" style="width: {{ $percent }}%"></div>
                </div>
                <ol class="biz-steps">
                    @foreach($onboardingSteps as $step)
                        <li class="{{ $step['done'] ? 'is-done' : '' }}">{{ $step['label'] }} @if($step['done'])✓@endif</li>
                    @endforeach
                </ol>
                @if($onboardingNext)
                    <a class="btn biz-btn mt-3" href="{{ $onboardingNext }}">Continuer la configuration</a>
                @endif
            </div>
        </section>
    @else
        <section class="en-setup mb-3 rounded-2xl bg-white px-3 py-3 shadow-sm" aria-label="Configuration">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <p class="font-semibold">Configuration</p>
                <p class="text-sm text-slate-500">{{ $done }}/{{ $total }}</p>
                <div class="en-progress h-2 min-w-24 flex-1 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="en-progress-bar h-full bg-electric" style="width: {{ $percent }}%"></div>
                </div>
                @if($onboardingNext)
                    <a class="inline-flex rounded-xl bg-electric px-3 py-2 text-sm font-semibold text-white" href="{{ $onboardingNext }}">Continuer la configuration</a>
                @endif
            </div>
            <ol class="mt-2 flex flex-wrap gap-2 text-xs">
                @foreach($onboardingSteps as $step)
                    <li class="rounded-full px-2 py-1 {{ $step['done'] ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ $step['label'] }} @if($step['done'])✓@endif</li>
                @endforeach
            </ol>
        </section>
    @endif
@endif
