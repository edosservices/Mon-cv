@extends('layouts.app')
@section('heading', $zone->exists ? 'Modifier la zone' : 'Nouvelle zone')
@section('content')
<form method="POST" action="{{ $zone->exists ? route('wifi-zones.update', $zone) : route('wifi-zones.store') }}" enctype="multipart/form-data" class="space-y-3 rounded-2xl bg-white p-5 shadow-sm">
    @csrf
    @if($zone->exists) @method('PUT') @endif
    <label class="block text-sm">Nom<input class="mt-1 w-full rounded-lg border px-3 py-2" name="name" value="{{ old('name', $zone->name) }}" required></label>
    <label class="block text-sm">Nom affiché<input class="mt-1 w-full rounded-lg border px-3 py-2" name="display_name" value="{{ old('display_name', $zone->display_name) }}"></label>
    <label class="block text-sm">Slogan<input class="mt-1 w-full rounded-lg border px-3 py-2" name="slogan" value="{{ old('slogan', $zone->slogan) }}"></label>
    <label class="block text-sm">Adresse<input class="mt-1 w-full rounded-lg border px-3 py-2" name="location" value="{{ old('location', $zone->location) }}"></label>
    <label class="block text-sm">Description<textarea class="mt-1 w-full rounded-lg border px-3 py-2" name="description">{{ old('description', $zone->description) }}</textarea></label>
    <label class="block text-sm">Téléphone<input class="mt-1 w-full rounded-lg border px-3 py-2" name="phone" value="{{ old('phone', $zone->phone) }}"></label>
    <label class="block text-sm">WhatsApp<input class="mt-1 w-full rounded-lg border px-3 py-2" name="whatsapp" value="{{ old('whatsapp', $zone->whatsapp) }}"></label>
    <label class="block text-sm">Email<input class="mt-1 w-full rounded-lg border px-3 py-2" type="email" name="email" value="{{ old('email', $zone->email) }}"></label>
    <label class="block text-sm">Couleur principale<input class="mt-1 w-full rounded-lg border px-3 py-2" name="primary_color" value="{{ old('primary_color', $zone->primary_color ?: '#0b5ed7') }}"></label>
    <label class="block text-sm">Couleur secondaire<input class="mt-1 w-full rounded-lg border px-3 py-2" name="secondary_color" value="{{ old('secondary_color', $zone->secondary_color ?: '#071e3d') }}"></label>
    <label class="block text-sm">Logo<input class="mt-1 w-full" type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/gif"></label>
    <label class="block text-sm">Bannière<input class="mt-1 w-full" type="file" name="banner" accept="image/jpeg,image/png,image/webp,image/gif"></label>
    <label class="block text-sm">Statut
        <select class="mt-1 w-full rounded-lg border px-3 py-2" name="status">
            <option value="active" @selected(old('status', $zone->status ?: 'active') === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $zone->status) === 'inactive')>Inactive</option>
        </select>
    </label>
    <button class="rounded-xl bg-electric px-4 py-3 text-white">Enregistrer</button>
</form>
@if($zone->exists)
<form method="POST" action="{{ route('wifi-zones.destroy', $zone) }}" class="mt-3">@csrf @method('DELETE')<button class="text-sm text-red-700">Archiver</button></form>
@endif
@endsection
