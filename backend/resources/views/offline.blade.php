<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offline — OpenMES</title>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1e40af">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="text-center max-w-sm">
        <img src="/logo_open_mes.png" alt="OpenMES" class="h-14 mx-auto mb-8 opacity-60">
        <div class="bg-white rounded-xl shadow-md p-8">
            <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M18.364 5.636a9 9 0 010 12.728M15.536 8.464a5 5 0 010 7.072M12 12h.01
                         M6.343 17.657a9 9 0 010-12.728M8.464 15.536a5 5 0 010-7.072"/>
            </svg>
            <h1 class="text-xl font-bold text-gray-800 mb-2">No connection</h1>
            <p class="text-gray-500 text-sm mb-6">Check your network and try again.</p>
            <button onclick="window.location.reload()"
                    class="w-full bg-blue-600 text-white font-medium py-2.5 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                Try again
            </button>
        </div>
    </div>
</body>
</html>
