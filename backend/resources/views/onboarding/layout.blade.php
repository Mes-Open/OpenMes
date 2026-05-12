<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpenMES — Setup Wizard</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center justify-center p-4">
    <div class="w-full max-w-2xl">
        <!-- Logo -->
        <div class="text-center mb-8">
            <img src="/logo_open_mes.png" alt="OpenMES" class="h-10 mx-auto mb-2">
            <p class="text-sm text-gray-500">Setup Wizard</p>
        </div>

        <!-- Stepper -->
        <div class="flex items-center justify-center mb-8">
            @php $currentStep = $step ?? 1; @endphp
            @foreach(['Line', 'Product', 'Process', 'Work Order'] as $i => $label)
                <div class="flex items-center">
                    <div class="flex flex-col items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
                            {{ $i + 1 < $currentStep ? 'bg-green-500 text-white' :
                               ($i + 1 === $currentStep ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-600') }}">
                            @if($i + 1 < $currentStep)
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            @else
                                {{ $i + 1 }}
                            @endif
                        </div>
                        <span class="text-xs mt-1 {{ $i + 1 === $currentStep ? 'text-blue-600 font-medium' : 'text-gray-500' }}">{{ $label }}</span>
                    </div>
                    @if($i < 3)
                        <div class="w-12 h-0.5 mx-1 {{ $i + 1 < $currentStep ? 'bg-green-500' : 'bg-gray-300' }}"></div>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- Content -->
        <div class="bg-white rounded-xl shadow-lg p-8">
            @yield('content')
        </div>

        <!-- Skip -->
        <div class="text-center mt-4">
            <form action="{{ route('onboarding.skip') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="text-sm text-gray-400 hover:text-gray-600">Skip wizard →</button>
            </form>
        </div>
    </div>
</body>
</html>
