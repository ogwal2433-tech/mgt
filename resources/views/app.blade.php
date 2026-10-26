<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#16a34a">

        <title inertia>{{ config('app.name', 'Biiteek') }}</title>

        <!-- Typography: Plus Jakarta Sans (display) · Inter (UI) · JetBrains Mono (references) -->
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link
            href="https://fonts.bunny.net/css?family=inter:400,450,500,600,700|jetbrains-mono:400,500|plus-jakarta-sans:500,600,700,800"
            rel="stylesheet"
        />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
