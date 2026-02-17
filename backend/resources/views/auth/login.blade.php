@extends('layouts.auth')

@section('title', 'Login')

@section('content')
<div x-data="{
    username: '',
    password: '',
    remember: false,
    loading: false
}">
    <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Sign In</h2>

    <form method="POST" action="{{ route('login') }}" @submit="loading = true">
        @csrf

        <!-- Username -->
        <div class="mb-4">
            <label for="username" class="form-label">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                x-model="username"
                class="form-input w-full @error('username') border-red-500 @enderror"
                autocomplete="username"
                autofocus
                required
            >
            @error('username')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password -->
        <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                x-model="password"
                class="form-input w-full @error('password') border-red-500 @enderror"
                autocomplete="current-password"
                required
            >
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Remember Me -->
        <div class="mb-6 flex items-center">
            <input
                type="checkbox"
                id="remember"
                name="remember"
                x-model="remember"
                class="h-5 w-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
            >
            <label for="remember" class="ml-2 text-sm text-gray-700">
                Remember me
            </label>
        </div>

        <!-- Submit Button -->
        <button
            type="submit"
            class="w-full btn-touch btn-primary"
            :disabled="loading || !username || !password"
            :class="{ 'opacity-50 cursor-not-allowed': loading || !username || !password }"
        >
            <span x-show="!loading">Sign In</span>
            <span x-show="loading" class="flex items-center justify-center">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Signing in...
            </span>
        </button>
    </form>

    <!-- Info Box -->
    <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
        <p class="text-sm text-blue-800">
            <strong>Default Credentials:</strong><br>
            Admin: admin / admin123<br>
            Supervisor: supervisor / supervisor123<br>
            Operator: operator / operator123
        </p>
    </div>
</div>
@endsection
