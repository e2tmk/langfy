<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>

    <tallstackui:script />
    @livewireStyles
    <link href="{{ asset('vendor/langfy/assets/app.css') }}" rel="stylesheet">
</head>
    <body class="font-sans antialiased">
        <div class="min-h-screen">
            <main>
                {{ $slot }}
            </main>

            @livewireScripts
        </div>
    </body>
</html>
