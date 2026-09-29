@extends('layouts.app')
@section('heading', 'Créer un ticket')
@section('content')
@include('vouchers.assist-steps', ['step' => 2])
@php $calc = app(\App\Services\TicketAssist::class)->parameters($plan, 5, 0); @endphp

<section class="rounded-2xl bg-white p-4 shadow-sm">
    <h2 class="text-lg font-semibold">Configurer le ticket</h2>
    <p class="mt-1 text-sm text-slate-600">{{ $plan->name }} · {{ $calc['duration_label'] }} · time limit {{ $calc['time_limit'] }} · profil {{ $calc['profile'] }}</p>
    <form class="mt-4 grid gap-4" method="POST" action="{{ route('vouchers.assist.preview') }}">
        @csrf
        <input type="hidden" name="wifi_zone_id" value="{{ $zone->id }}">
        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
        <label class="text-sm font-semibold">Débit
            <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" type="number" name="mbps" min="1" max="1000" value="{{ old('mbps', 5) }}" required inputmode="numeric">
            <span class="mt-1 block font-normal text-slate-500">En Mbps. 5 devient 5M sur le profil.</span>
        </label>
        <label class="text-sm font-semibold">Data limit
            <select class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="data_gb" required>
                @foreach([2 => '2 GB', 5 => '5 GB', 10 => '10 GB', 20 => '20 GB', 0 => 'Sans limite'] as $value => $label)
                    <option value="{{ $value }}" @selected((int) old('data_gb', 10) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm font-semibold">Nom utilisateur <span class="font-normal text-slate-500">facultatif</span>
            <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="username" value="{{ old('username') }}" maxlength="32" autocapitalize="none" placeholder="enh">
        </label>
        <label class="text-sm font-semibold">Mot de passe <span class="font-normal text-slate-500">facultatif, 3 à 8 chiffres</span>
            <input class="mt-1 w-full rounded-xl border px-3 py-3 text-base" name="password" value="{{ old('password') }}" inputmode="numeric" maxlength="8" placeholder="432">
        </label>
        <button class="min-h-14 rounded-xl bg-electric px-4 py-3 font-semibold text-white" type="submit">Vérifier sur le MikroTik</button>
    </form>
</section>
@endsection
