@extends('layouts.app')
@section('heading', 'Journal')
@section('content')
<div class="space-y-2">
    @forelse($logs as $log)
        <article class="rounded-2xl bg-white p-4 text-sm">
            <p class="font-medium">{{ $log->action }}</p>
            <p class="text-slate-500">{{ $log->user->name ?? 'Système' }} · {{ $log->ip_address }} · {{ $log->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
        </article>
    @empty
        <p class="text-sm text-slate-500">Aucune action enregistrée.</p>
    @endforelse
</div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
