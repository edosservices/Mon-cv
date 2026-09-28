@extends('layouts.app')
@section('heading', 'Ticket '.$voucher->username)
@section('content')
<div class="grid gap-4 lg:grid-cols-[320px_1fr]">
    @include('vouchers.ticket', ['public' => false])
    <div class="space-y-3">
        <a class="inline-block rounded-lg bg-electric px-4 py-2 text-sm text-white" href="{{ route('vouchers.pdf', $voucher) }}">Télécharger le PDF</a>
        <article class="rounded-2xl bg-white p-4 text-sm shadow-sm">
            @if($voucher->sync_status === 'synced')
                <p>Créé sur le MikroTik.</p>
            @else
                <p class="font-medium">Non synchronisé avec le MikroTik.</p>
                <p class="mt-1">Ticket créé, synchronisation MikroTik en attente.</p>
                <p class="mt-1 text-amber-800">{{ $voucher->sync_error ?: 'Le compte n’a pas été créé sur le routeur.' }}</p>
                <form method="POST" action="{{ route('vouchers.sync', $voucher) }}" class="mt-3">@csrf<button class="rounded-lg border px-3 py-2">Réessayer sur le MikroTik</button></form>
            @endif
        </article>
        <form method="POST" action="{{ route('vouchers.status', $voucher) }}" class="rounded-2xl bg-white p-4 shadow-sm">
            @csrf @method('PATCH')
            <label class="text-sm">Statut
                <select name="status" class="mt-1 w-full rounded-lg border px-3 py-2">
                    @foreach(['available' => 'Disponible', 'active' => 'Actif', 'disabled' => 'Désactivé'] as $value => $label)
                        <option value="{{ $value }}" @selected($voucher->status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button class="mt-3 rounded-lg border px-3 py-2 text-sm">Mettre à jour</button>
        </form>
        @if($voucher->status === 'available')
        <form method="POST" action="{{ route('vouchers.sell', $voucher) }}" class="space-y-2 rounded-2xl bg-white p-4 shadow-sm">
            @csrf
            <p class="font-medium">Vente au comptoir</p>
            <input class="w-full rounded-lg border px-3 py-2" name="name" placeholder="Nom du client">
            <input class="w-full rounded-lg border px-3 py-2" name="phone" placeholder="Téléphone">
            <button class="rounded-lg bg-navy px-4 py-2 text-sm text-white">Encaisser</button>
        </form>
        @endif
        <form method="POST" action="{{ route('vouchers.destroy', $voucher) }}">@csrf @method('DELETE')<button class="text-sm text-red-700">Archiver</button></form>
    </div>
</div>
@endsection
