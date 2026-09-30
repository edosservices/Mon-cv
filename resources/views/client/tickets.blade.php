@extends('layouts.client')
@section('heading', 'Mes tickets')
@section('content')
<h1 class="h4">Mes tickets</h1>
@forelse($tickets as $ticket)
    @include('client.ticket-card', ['reveal' => false])
@empty
    <div class="client-card p-3">
        <p>Aucun ticket pour ce numéro.</p>
        <a class="btn client-btn" href="{{ route('client.buy') }}">Acheter un ticket</a>
    </div>
@endforelse
@endsection
