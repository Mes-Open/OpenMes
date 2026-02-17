<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install OpenMES</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-2xl">
        <div class="text-center mb-8">
            <h1 class="text-5xl font-bold text-gray-800 mb-2">🏭 OpenMES</h1>
            <p class="text-xl text-gray-600">Manufacturing Execution System</p>
        </div>

        <div class="bg-white rounded-lg shadow-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Welcome to OpenMES Installation</h2>

            <div class="space-y-4 mb-8">
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-green-600 mt-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h3 class="font-semibold text-gray-800">Simple Installation</h3>
                        <p class="text-gray-600 text-sm">Just a few steps to get your manufacturing system running</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-green-600 mt-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h3 class="font-semibold text-gray-800">Secure Setup</h3>
                        <p class="text-gray-600 text-sm">You'll create your own unique credentials - no defaults!</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-green-600 mt-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h3 class="font-semibold text-gray-800">Ready in Minutes</h3>
                        <p class="text-gray-600 text-sm">Configure database, create admin account, and you're done!</p>
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-8">
                <h3 class="font-semibold text-blue-900 mb-2">Before you begin, make sure you have:</h3>
                <ul class="list-disc list-inside text-blue-800 text-sm space-y-1">
                    <li>PostgreSQL database created and accessible</li>
                    <li>Database credentials (host, port, name, username, password)</li>
                    <li>A strong password for your admin account</li>
                </ul>
            </div>

            <div class="flex justify-end">
                <a href="{{ route('install.database') }}" class="btn-touch btn-primary">
                    Let's Begin →
                </a>
            </div>
        </div>

        <div class="text-center mt-6 text-sm text-gray-600">
            <p>OpenMES v1.0 - Open Source Manufacturing Execution System</p>
        </div>
    </div>
</body>
</html>
