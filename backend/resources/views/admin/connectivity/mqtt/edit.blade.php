@extends('layouts.app')
@section('title', __('Edit MQTT Connection') . ' — ' . $connection->name)

@section('content')
<div class="p-6 max-w-2xl">

    <div class="mb-6">
        <a href="{{ route('admin.connectivity.mqtt.show', $connection) }}"
           class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            {{ __('Back to') }} {{ $connection->name }}
        </a>
        <h1 class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">{{ __('Edit') }}: {{ $connection->name }}</h1>
    </div>

    @if($errors->any())
        <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg dark:bg-red-900/20 dark:border-red-700 dark:text-red-300">
            <ul class="list-disc list-inside space-y-1 text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.connectivity.mqtt.update', $connection) }}" class="space-y-6">
        @csrf @method('PUT')

        @include('admin.connectivity.mqtt.partials.form', ['mqtt' => $connection->mqttConnection])

        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                {{ __('Save Changes') }}
            </button>
            <a href="{{ route('admin.connectivity.mqtt.show', $connection) }}"
               class="px-5 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium
                      rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                {{ __('Cancel') }}
            </a>
            <form method="POST" action="{{ route('admin.connectivity.mqtt.destroy', $connection) }}"
                  class="ml-auto"
                  onsubmit="return confirm('{{ __('Delete this connection and all topics?') }}')">
                @csrf @method('DELETE')
                <button type="submit"
                        class="px-5 py-2 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 text-sm font-medium
                               rounded-lg hover:bg-red-100 transition-colors">
                    {{ __('Delete Connection') }}
                </button>
            </form>
        </div>
    </form>
</div>
@endsection
