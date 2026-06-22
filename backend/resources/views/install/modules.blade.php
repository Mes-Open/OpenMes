<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Select Modules - Install OpenMES</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-2xl">
        <div class="text-center mb-8">
            <img src="/logo_open_mes.png" alt="OpenMES" class="h-16 md:h-20 mx-auto mb-2">
            <h1 class="text-2xl font-bold text-gray-800">Installation Wizard</h1>
            <p class="text-gray-600 mt-2">Step 3 of 4: Select Modules</p>
        </div>

        <!-- Progress Indicator -->
        <div class="mb-8">
            <div class="flex items-center justify-center space-x-2">
                <div class="flex items-center">
                    <a href="{{ route('install.environment') }}" class="w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center font-bold hover:bg-green-700 transition" title="Edit basic configuration">✓</a>
                    <a href="{{ route('install.environment') }}" class="ml-2 text-sm font-medium text-green-700 hover:text-green-800">Basic</a>
                </div>
                <div class="w-8 h-1 bg-green-600"></div>
                <div class="flex items-center">
                    <a href="{{ route('install.database') }}" class="w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center font-bold hover:bg-green-700 transition" title="Edit database configuration">✓</a>
                    <a href="{{ route('install.database') }}" class="ml-2 text-sm font-medium text-green-700 hover:text-green-800">Database</a>
                </div>
                <div class="w-8 h-1 bg-green-600"></div>
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">3</div>
                    <span class="ml-2 text-sm font-medium text-gray-800">Modules</span>
                </div>
                <div class="w-8 h-1 bg-gray-300"></div>
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center font-bold">4</div>
                    <span class="ml-2 text-sm font-medium text-gray-500">Admin</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Choose the modules you need</h2>
            <p class="text-gray-600 mb-6">Enable only the feature areas your team will use — you can change this later in Settings → System. The core areas (Dashboard, Orders, Production, Admin) are always on.</p>

            @if($errors->any())
                <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('install.modules.select') }}">
                @csrf

                <div class="space-y-3">
                    @foreach($modules as $module)
                        <label class="flex items-start gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition">
                            <input type="checkbox" name="modules[]" value="{{ $module['key'] }}"
                                   class="mt-1 h-5 w-5 text-blue-600 rounded"
                                   @checked($module['enabled'])>
                            <span>
                                <span class="block font-semibold text-gray-800">{{ $module['label'] }}</span>
                                <span class="block text-sm text-gray-600">{{ $module['description'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="flex justify-between mt-8">
                    <a href="{{ route('install.database') }}" class="px-6 py-2 text-gray-600 hover:text-gray-800 font-medium">← Back</a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition">Continue →</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
