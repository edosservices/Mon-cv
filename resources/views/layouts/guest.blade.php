<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'LIMETE WIFI MANAGER')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="lm-guest min-h-screen">
    <main class="lm-guest-wrap">
        <a class="lm-guest-brand" href="{{ route('home') }}">
            <span class="lm-logo" aria-hidden="true">L</span>
            <span>LIMETE WIFI MANAGER</span>
        </a>
        @if($errors->any())
            <div class="mb-4 rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
