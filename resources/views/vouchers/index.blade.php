@extends('layouts.app')
@section('heading', 'Vouchers')
@section('content')
<form method="POST" action="{{ route('vouchers.store') }}" class="mb-5 grid gap-3 rounded-2xl bg-white p-4 shadow-sm sm:grid-cols-4">
    @csrf
    <label class="text-sm">Zone
        <select class="mt-1 w-full rounded-lg border px-3 py-2" name="wifi_zone_id" required>
            @foreach($zones as $zone)<option value="{{ $zone->id }}">{{ $zone->name }}</option>@endforeach
        </select>
    </label>
    <label class="text-sm">Forfait
        <select class="mt-1 w-full rounded-lg border px-3 py-2" name="plan_id" required>
            @foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach
        </select>
    </label>
    <label class="text-sm">Quantité<input class="mt-1 w-full rounded-lg border px-3 py-2" type="number" name="count" min="1" max="100" value="1"></label>
    <button class="self-end rounded-xl bg-electric px-4 py-2 text-white">Générer</button>
</form>
<div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
    <table class="w-full min-w-[640px] text-left text-sm">
        <thead class="text-slate-500"><tr><th class="p-3">Code</th><th>Forfait</th><th>Statut</th><th>Expiration</th><th></th></tr></thead>
        <tbody>
        @foreach($vouchers as $voucher)
            <tr class="border-t">
                <td class="p-3 font-medium">{{ $voucher->username }}</td>
                <td>{{ $voucher->plan->name ?? '' }}</td>
                <td>{{ $voucher->status }}</td>
                <td>{{ $voucher->expires_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</td>
                <td class="pr-3 text-right"><a class="text-electric" href="{{ route('vouchers.show', $voucher) }}">Voir</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $vouchers->links() }}</div>
@endsection
