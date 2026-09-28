@extends('layouts.guest')
@section('content')
<section class="rounded-3xl bg-navy px-6 py-10 text-white">
    <p class="text-xs uppercase tracking-[0.2em] text-sky-200">SaaS multi-entrepreneur</p>
    <h1 class="mt-3 text-3xl font-semibold leading-tight">Gérez vos WiFi Zones et vos MikroTik depuis un seul espace.</h1>
    <p class="mt-4 text-sm text-sky-100">Chaque entrepreneur a ses zones, ses routeurs, ses clients, ses tickets et ses ventes. Les données ne sont jamais mélangées.</p>
    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('register') }}" class="rounded-xl bg-white px-4 py-3 text-sm font-semibold text-navy">Créer mon compte</a>
        <a href="{{ route('login') }}" class="rounded-xl border border-white/30 px-4 py-3 text-sm">Connexion</a>
    </div>
</section>
@endsection
