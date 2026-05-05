<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>OpenMES — Setup Wizard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen p-4">
    <div class="max-w-3xl mx-auto pt-8">
        {{-- Header --}}
        <div class="text-center mb-6">
            <img src="/logo_open_mes.png" alt="OpenMES" class="h-16 mx-auto mb-1">
            <p class="text-gray-500 text-sm">Setup Wizard</p>
        </div>

        {{-- Stepper --}}
        <div class="flex items-center justify-center mb-8">
            @php $currentStep = $currentStep ?? 1; @endphp
            @foreach(['Line', 'Product', 'Process', 'Work Order'] as $i => $label)
                @php $step = $i + 1; @endphp
                <div class="flex items-center">
                    <div class="flex flex-col items-center">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold
                            {{ $step < $currentStep ? 'bg-green-500 text-white' : ($step === $currentStep ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500') }}">
                            @if($step < $currentStep)
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            @else
                                {{ $step }}
                            @endif
                        </div>
                        <span class="text-xs mt-1 {{ $step === $currentStep ? 'text-blue-700 font-semibold' : 'text-gray-400' }}">{{ $label }}</span>
                    </div>
                    @if($step < 4)
                        <div class="w-12 h-0.5 mx-1 mt-[-12px] {{ $step < $currentStep ? 'bg-green-400' : 'bg-gray-200' }}"></div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Content --}}
        <div class="bg-white rounded-lg shadow-xl p-8">
            @yield('content')
        </div>

        {{-- Skip link --}}
        <div class="text-center mt-4">
            <form method="POST" action="{{ route('onboarding.skip') }}" class="inline">
                @csrf
                <button type="submit" class="text-sm text-gray-400 hover:text-gray-600 underline">
                    Skip wizard — I'll configure manually
                </button>
            </form>
        </div>
    </div>
</body>
</html>
