<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>

    <tallstackui:script />
    @livewireStyles
    <link rel="stylesheet" href="{{ asset('vendor/langfy/app.css') }}">
</head>
    <body class="font-sans antialiased bg-gray-100">
        <div class="min-h-screen">
            <main>
                {{ $slot }}
            </main>

            @livewireScripts
        </div>
    </body>
</html>
