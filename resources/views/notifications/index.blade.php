@extends('layouts.app')
@section('heading', 'Notifications')
@section('content')
<ul class="space-y-2">
    @forelse($notifications as $notification)
        <li class="rounded-2xl bg-white p-4">
            <p class="font-medium">{{ $notification->data['title'] ?? 'Notification' }}</p>
            <p class="text-sm text-slate-600">{{ $notification->data['body'] ?? '' }}</p>
        </li>
    @empty
        <li class="text-sm text-slate-500">Aucune notification.</li>
    @endforelse
</ul>
<div class="mt-4">{{ $notifications->links() }}</div>
@endsection
