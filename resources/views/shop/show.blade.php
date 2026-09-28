<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $zone->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-ink">
<main class="mx-auto w-full max-w-lg px-4 py-8">
    <p class="text-sm text-slate-500">{{ $zone->location }}</p>
    <h1 class="text-3xl font-semibold">{{ $zone->name }}</h1>
    <p class="mt-2 text-sm">{{ $zone->description }}</p>
    @if(session('warning'))<p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm">{{ session('warning') }}</p>@endif
    @if($errors->any())<div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form method="POST" action="{{ route('shop.checkout', $zone->slug) }}" class="mt-6 space-y-3">
        @csrf
        <fieldset class="space-y-2">
            @foreach($plans as $plan)
                <label class="flex items-center justify-between gap-3 rounded-2xl bg-white p-4 shadow-sm">
                    <span><input type="radio" name="plan_id" value="{{ $plan->id }}" required> {{ $plan->name }}</span>
                    <strong>{{ \App\Support\Money::format($plan->price, $plan->currency) }}</strong>
                </label>
            @endforeach
        </fieldset>
        <input class="w-full rounded-lg border px-3 py-2" name="name" placeholder="Votre nom" value="{{ old('name') }}">
        <input class="w-full rounded-lg border px-3 py-2" name="phone" placeholder="Téléphone" value="{{ old('phone') }}" required>
        <select class="w-full rounded-lg border px-3 py-2" name="provider">
            @foreach($providers as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
        </select>
        <input class="w-full rounded-lg border px-3 py-2" name="transaction_reference" placeholder="Référence Mobile Money si vous l’avez">
        <button class="w-full rounded-xl bg-electric px-4 py-3 text-white">Payer</button>
    </form>
    @if($zone->whatsapp)
        <a class="mt-4 block text-center text-sm text-electric" href="https://wa.me/{{ preg_replace('/\D+/', '', $zone->whatsapp) }}">WhatsApp {{ $zone->whatsapp }}</a>
    @endif
</main>
</body>
</html>
