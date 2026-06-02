<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name', 'OpenMES') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
    <script>
        /* Apply dark class before first paint to avoid a flash (mirrors layouts/app.blade.php) */
        (function(){
            if (localStorage.getItem('theme') === 'dark') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    @inertia
</body>
</html>
