@extends('layouts.business')
@section('heading', 'Vente rapide')
@section('content')
<h2 class="h4">Ticket prêt</h2>
<p class="text-secondary">Le client peut se connecter tout de suite. Le mot de passe reste sur cet écran et sur le ticket imprimé.</p>
<div class="d-flex flex-wrap gap-2 no-print mb-3">
    <form method="POST" action="{{ route('vouchers.print') }}">
        @csrf
        @foreach($vouchers as $voucher)
            <input type="hidden" name="ids[]" value="{{ $voucher->id }}">
        @endforeach
        <input type="hidden" name="template" value="{{ $template }}">
        <input type="hidden" name="per_page" value="6">
        <button class="btn biz-btn">Imprimer</button>
    </form>
    <form method="POST" action="{{ route('vouchers.sheet-pdf') }}">
        @csrf
        @foreach($vouchers as $voucher)
            <input type="hidden" name="ids[]" value="{{ $voucher->id }}">
        @endforeach
        <input type="hidden" name="template" value="{{ $template }}">
        <input type="hidden" name="per_page" value="6">
        <button class="btn btn-outline-secondary">PDF</button>
    </form>
    <a class="btn btn-outline-secondary" href="{{ route('sales.quick') }}">Nouvelle vente</a>
</div>
<div class="row g-3">
    @foreach($vouchers as $voucher)
        <div class="col-12 col-md-6">
            <article class="card border-0 shadow-sm lm-success">
                <div class="card-body">
                    <p class="mb-1">{{ $voucher->plan->name ?? 'Forfait' }}</p>
                    <p class="mb-1">Code <strong>{{ $voucher->username }}</strong></p>
                    <p class="mb-2">Mot de passe <strong class="code">{{ $voucher->password }}</strong></p>
                    <div class="qr">{!! \App\Support\QrCodes::svg(route('tickets.public', $voucher->public_token)) !!}</div>
                    <a class="btn btn-sm btn-outline-secondary mt-2" href="https://wa.me/{{ $voucher->customer?->phone ? preg_replace('/\D+/', '', $voucher->customer->phone) : '' }}?text={{ rawurlencode($voucher->shareText()) }}">Partager</a>
                </div>
            </article>
        </div>
    @endforeach
</div>
@endsection
