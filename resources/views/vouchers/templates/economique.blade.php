<table class="eco" data-layout="economique" data-access="{{ $ticket['access'] }}">
    <tr>
        <td class="eco-num">[{{ $ticket['number'] }}]</td>
    </tr>
    @if($ticket['logo'])
        <tr>
            <td><img class="eco-logo" src="{{ $ticket['logo'] }}" alt=""></td>
        </tr>
    @endif
    <tr>
        <td class="eco-business">{{ $ticket['business'] }}</td>
    </tr>
    <tr>
        <td class="eco-label">Username</td>
    </tr>
    <tr>
        <td class="eco-cred">{{ $ticket['username'] }}</td>
    </tr>
    <tr>
        <td class="eco-label">Password</td>
    </tr>
    <tr>
        <td class="eco-cred">{{ $ticket['password'] }}</td>
    </tr>
    @if($ticket['offer'] !== '')
        <tr>
            <td class="eco-offer">{{ $ticket['offer'] }}</td>
        </tr>
    @endif
    <tr>
        <td><div class="eco-qr" aria-label="QR de connexion">{!! $ticket['access_qr'] !!}</div></td>
    </tr>
    @if($ticket['login'])
        <tr>
            <td class="eco-login">Login: {{ $ticket['login'] }}</td>
        </tr>
    @endif
</table>
