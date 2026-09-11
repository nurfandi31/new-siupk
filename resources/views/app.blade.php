<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="classic">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0284c7">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="siupk Next">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/build/assets/icon-192.png">
    
    <title inertia>{{ config('app.name', 'siupk Next') }}</title>
    <script>
        (function () {
            try {
                var t = localStorage.getItem('siupk-theme');
                var ok = { classic:1, forest:1, amber:1, violet:1, ocean:1, rose:1, midnight:1 };
                if (t && ok[t]) document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js').then(function(reg) {
                    console.log('siupk PWA ServiceWorker registered with scope:', reg.scope);
                }).catch(function(err) {
                    console.warn('siupk PWA ServiceWorker registration failed:', err);
                });
            });
        }
    </script>
    @routes
    @vite('resources/js/app.js')
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
