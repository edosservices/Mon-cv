@extends('layouts.app')
@section('heading', 'Ticket créé')
@section('content')
@include('vouchers.assist-steps', ['step' => 6])
@php
    $gigabytes = null;
@endphp

<section class="rounded-2xl bg-white p-4 shadow-sm">
    <p class="font-semibold text-emerald-700">✓ Ticket créé</p>
    <h2 class="mt-1 text-lg font-semibold">Votre ticket est prêt.</h2>
    <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
        <div><dt class="text-slate-500">Utilisateur</dt><dd class="font-semibold">{{ $voucher->username }}</dd></div>
        <div><dt class="text-slate-500">Mot de passe</dt><dd class="font-semibold">{{ $voucher->password }}</dd></div>
        <div><dt class="text-slate-500">Profil</dt><dd class="font-semibold">{{ $voucher->profile_snapshot['profile'] ?? ($handover['profile'] ?? ($voucher->plan->mikrotik_profile ?: '—')) }}</dd></div>
        <div><dt class="text-slate-500">Durée</dt><dd class="font-semibold">{{ $voucher->profile_snapshot['validity_label'] ?? ($handover['duration'] ?? $voucher->plan->durationLabel()) }}</dd></div>
        <div><dt class="text-slate-500">Data</dt><dd class="font-semibold">{{ $voucher->profile_snapshot['data_label'] ?? ($handover['data'] ?? '—') }}</dd></div>
        <div><dt class="text-slate-500">Débit</dt><dd class="font-semibold">{{ $voucher->profile_snapshot['rate_limit'] ?? ($handover['rate'] ?? '—') }}</dd></div>
    </dl>
    @if($voucher->isSynced())
        <p class="mt-4 font-semibold text-emerald-700">✓ Synchronisé</p>
    @else
        <p class="mt-4 font-semibold text-amber-800">Synchronisation en attente</p>
        <form class="mt-2" method="POST" action="{{ route('vouchers.retry', $voucher) }}">
            @csrf
            <button class="min-h-14 rounded-xl border px-4 py-3 font-semibold" type="submit">Réessayer</button>
        </form>
    @endif
</section>

<div class="mt-4">
    @include('vouchers.ticket')
</div>
@endsection
