@extends('layouts.app')
@section('heading', 'Équipe')
@section('content')
<form method="POST" action="{{ route('staff.store') }}" class="mb-5 space-y-3 rounded-2xl bg-white p-5 shadow-sm">
    @csrf
    <h2 class="font-semibold">Ajouter un collaborateur</h2>
    <input class="w-full rounded-lg border px-3 py-2" name="name" placeholder="Nom" required>
    <input class="w-full rounded-lg border px-3 py-2" type="email" name="email" placeholder="Email" required>
    <input class="w-full rounded-lg border px-3 py-2" type="password" name="password" placeholder="Mot de passe" required>
    <div class="grid gap-2 sm:grid-cols-2">
        @foreach($permissions as $permission)
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}"> {{ $permission->name }}</label>
        @endforeach
    </div>
    <button class="rounded-xl bg-electric px-4 py-3 text-white">Ajouter</button>
</form>
<ul class="space-y-2">
    @foreach($staff as $member)
        <li class="rounded-2xl bg-white p-4 text-sm">{{ $member->name }} · {{ $member->email }} · {{ $member->permissions->pluck('name')->join(', ') }}</li>
    @endforeach
</ul>
@endsection
