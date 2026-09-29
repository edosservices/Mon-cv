@extends('layouts.admin')
@section('content')
<h1 class="mb-4 text-2xl font-semibold">Journal</h1>
<div class="space-y-2">
    @foreach($logs as $log)
        <article class="rounded-2xl bg-white p-4 text-sm">
            <p class="font-medium">{{ $log->action }}</p>
            <p class="text-slate-500">{{ $log->user->name ?? 'Système' }} · {{ $log->ip_address }} · {{ $log->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
        </article>
    @endforeach
</div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
