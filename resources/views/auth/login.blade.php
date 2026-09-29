@extends('layouts.guest')
@section('title', 'Connexion')
@section('content')
<form method="POST" action="{{ route('login') }}" class="lm-card space-y-4 p-6">
    @csrf
    <h1 class="text-xl font-semibold">Connexion</h1>
    <label class="block text-sm">Email<input class="mt-1 w-full rounded-lg border px-3 py-2" type="email" name="email" value="{{ old('email') }}" required></label>
    <label class="block text-sm">Mot de passe<input class="mt-1 w-full rounded-lg border px-3 py-2" type="password" name="password" required></label>
    <button class="w-full rounded-xl bg-electric px-4 py-3 text-white">Entrer</button>
    <a class="block text-center text-sm text-electric" href="{{ route('register') }}">Créer une entreprise</a>
</form>
@endsection
