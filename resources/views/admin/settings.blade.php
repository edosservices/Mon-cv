@extends('layouts.admin')
@section('content')
<h1 class="mb-4 text-2xl font-semibold">Paramètres</h1>
<div class="space-y-3">
    <a class="block rounded-2xl bg-white p-4 font-semibold" href="{{ route('admin.plans') }}">Plans plateforme</a>
    <a class="block rounded-2xl bg-white p-4 font-semibold" href="{{ route('admin.payments') }}">Paiements</a>
    <a class="block rounded-2xl bg-white p-4 font-semibold" href="{{ route('admin.production-check') }}">Production</a>
    <a class="block rounded-2xl bg-white p-4 font-semibold" href="{{ route('admin.logs') }}">Journal</a>
</div>
@endsection
