<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <!-- Billing data must never reach a search index. robots.txt only
             deters crawlers that fetch it; this covers crawlers arriving
             from a shared or bookmarked link. -->
        <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
        <meta name="googlebot" content="noindex, nofollow">
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon-180.png">

        <!-- PWA: without these Chrome/Safari will not offer to install the app. -->
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#0f172a">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Invoice CRM') }}">

        <title inertia>{{ config('app.name', 'Jewelry Invoice') }}</title>

        @viteReactRefresh
        @vite(['resources/js/app.tsx', 'resources/css/app.css'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
