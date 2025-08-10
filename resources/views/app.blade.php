<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" type="image/png" href="/icons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/icons/favicon.svg" />
    <link rel="shortcut icon" href="/icons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="Musikfestapp" />
    <link rel="manifest" href="/icons/site.webmanifest" />

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    @routes
    @vite(['resources/js/app.ts'])
    @inertiaHead
</head>
<body class="font-sans antialiased">
@inertia
</body>
</html>
