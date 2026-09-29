<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'LIMETE WIFI MANAGER')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-foam text-ink">
    <main class="mx-auto w-full max-w-lg px-4 py-10">
        <a href="{{ route('home') }}" class="mb-6 block text-sm font-semibold text-electric">LIMETE WIFI MANAGER</a>
        @if($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
