<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Installation Complete - OpenMES</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-2xl">
        <div class="text-center mb-8">
            <div class="inline-block p-4 bg-green-100 rounded-full mb-4">
                <svg class="w-16 h-16 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
            </div>
            <h1 class="text-4xl font-bold text-gray-800 mb-2">Installation Complete!</h1>
            <p class="text-xl text-gray-600">OpenMES is ready to use 🎉</p>
        </div>

        <div class="bg-white rounded-lg shadow-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">What's Next?</h2>

            <div class="space-y-4 mb-8">
                <div class="flex items-start gap-3 p-4 bg-blue-50 rounded-lg">
                    <span class="flex items-center justify-center w-8 h-8 bg-blue-600 text-white rounded-full font-bold">1</span>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-800">Login to OpenMES</h3>
                        <p class="text-gray-600 text-sm">Use the admin credentials you just created to log in</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-4 bg-blue-50 rounded-lg">
                    <span class="flex items-center justify-center w-8 h-8 bg-blue-600 text-white rounded-full font-bold">2</span>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-800">Create Production Lines</h3>
                        <p class="text-gray-600 text-sm">Set up your production lines and workstations</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-4 bg-blue-50 rounded-lg">
                    <span class="flex items-center justify-center w-8 h-8 bg-blue-600 text-white rounded-full font-bold">3</span>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-800">Add Users</h3>
                        <p class="text-gray-600 text-sm">Create operator and supervisor accounts</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-4 bg-blue-50 rounded-lg">
                    <span class="flex items-center justify-center w-8 h-8 bg-blue-600 text-white rounded-full font-bold">4</span>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-800">Import or Create Work Orders</h3>
                        <p class="text-gray-600 text-sm">Start tracking your production!</p>
                    </div>
                </div>
            </div>

            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-8">
                <h3 class="font-semibold text-green-900 mb-2">🎯 Quick Tips:</h3>
                <ul class="list-disc list-inside text-green-800 text-sm space-y-1">
                    <li>The application is now secured with your unique credentials</li>
                    <li>All data is stored in your PostgreSQL database</li>
                    <li>You can add more users from the admin panel</li>
                    <li>Check the documentation for advanced features</li>
                </ul>
            </div>

            <div class="text-center">
                <a href="{{ route('login') }}" class="inline-block btn-touch btn-primary text-lg px-8">
                    Go to Login Page →
                </a>
            </div>
        </div>

        <div class="text-center mt-6 text-sm text-gray-600">
            <p>Need help? Check the documentation or visit our GitHub repository</p>
        </div>
    </div>
</body>
</html>
