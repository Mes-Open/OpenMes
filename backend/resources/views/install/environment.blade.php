<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Setup - Install OpenMES</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-2xl">
        <div class="text-center mb-8">
            <img src="/logo_open_mes.png" alt="OpenMES" class="h-16 md:h-20 mx-auto mb-2">
            <h1 class="text-2xl font-bold text-gray-800">Welcome to OpenMES!</h1>
            <p class="text-gray-600 mt-2">Step 1 of 3: Basic Configuration</p>
        </div>

        <div class="bg-white rounded-lg shadow-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Basic Configuration</h2>

            @if($errors->any())
                <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('install.environment.setup') }}" x-data="{ loading: false }" @submit="loading = true">
                @csrf

                <div class="mb-4">
                    <label for="app_name" class="form-label">Site Name</label>
                    <input
                        type="text"
                        id="app_name"
                        name="app_name"
                        value="{{ old('app_name', 'OpenMES') }}"
                        class="form-input w-full"
                        required
                    >
                    <p class="text-sm text-gray-500 mt-1">The name of your manufacturing site</p>
                </div>

                <div class="mb-6">
                    <label for="app_url" class="form-label">Site URL</label>
                    <input
                        type="url"
                        id="app_url"
                        name="app_url"
                        value="{{ old('app_url', 'http://localhost') }}"
                        class="form-input w-full"
                        required
                    >
                    <p class="text-sm text-gray-500 mt-1">The URL where OpenMES will be accessed</p>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <p class="text-blue-800 text-sm">
                        <strong>Note:</strong> This will create the configuration file and prepare the system for database setup.
                    </p>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="btn-touch btn-primary"
                        :disabled="loading"
                        :class="{ 'opacity-50 cursor-not-allowed': loading }"
                    >
                        <span x-show="!loading">Continue →</span>
                        <span x-show="loading">Setting up...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
