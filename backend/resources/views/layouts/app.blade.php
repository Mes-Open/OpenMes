<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'OpenMES') }} - @yield('title', 'Manufacturing Execution System')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex flex-col min-h-screen">
        <!-- Top Navigation -->
        @include('layouts.components.nav')

        <div class="flex flex-1">
            <!-- Sidebar Navigation (optional, can be toggled) -->
            @if(isset($showSidebar) && $showSidebar)
                @include('layouts.components.sidebar')
            @endif

            <!-- Main Content -->
            <main class="flex-1 p-4 md:p-6 lg:p-8">
                <!-- Flash Messages -->
                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg" role="alert">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg" role="alert">
                        {{ session('error') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg" role="alert">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Page Content -->
                @yield('content')
            </main>
        </div>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 py-4 px-6 text-center text-sm text-gray-600">
            <p>&copy; {{ date('Y') }} All rights reserved.</p>
        </footer>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
