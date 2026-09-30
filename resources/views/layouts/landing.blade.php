<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LIMETE WIFI MANAGER — Achetez votre connexion WiFi rapidement</title>
    <meta name="description" content="Achetez votre forfait WiFi LIMETE en quelques secondes, sans créer de compte. Paiement sécurisé, ticket immédiat, connexion simple.">
    <link rel="canonical" href="{{ route('home') }}">
    <meta property="og:title" content="LIMETE WIFI MANAGER — Achetez votre connexion WiFi rapidement">
    <meta property="og:description" content="Achetez votre forfait WiFi LIMETE en quelques secondes, sans créer de compte.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ route('home') }}">
    <meta property="og:image" content="{{ asset('images/landing/hero.webp') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="LIMETE WIFI MANAGER">
    <meta name="twitter:description" content="Achetez votre forfait WiFi LIMETE en quelques secondes, sans créer de compte.">
    <meta name="twitter:image" content="{{ asset('images/landing/hero.webp') }}">
    <link rel="icon" href="{{ asset('brand/logo-limete-wifi-manager.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/landing.css', 'resources/js/landing.js'])
</head>
<body class="lp">
    <x-animated-background variant="network" />
    @yield('content')
</body>
</html>
