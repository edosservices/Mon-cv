@extends('layouts.admin')
@section('content')
<h1 class="mb-4 text-2xl font-semibold">Utilisateurs</h1>
<div class="space-y-3">
    @forelse($vouchers as $voucher)
        <article class="rounded-2xl bg-white p-4">
            <h2 class="font-semibold">{{ $voucher->username }}</h2>
            <p class="text-sm text-slate-500">{{ $voucher->tenant->name ?? 'Entreprise' }} · {{ $voucher->plan->mikrotik_profile ?: ($voucher->plan->name ?? 'Profil') }} · {{ $voucher->statusLabel() }}</p>
        </article>
    @empty
        <p class="rounded-2xl bg-white p-4 text-sm text-slate-500">Aucun utilisateur.</p>
    @endforelse
</div>
<div class="mt-4">{{ $vouchers->links() }}</div>
@endsection
