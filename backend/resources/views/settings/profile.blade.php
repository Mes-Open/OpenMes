@extends('layouts.app')

@section('title', 'Edit Profile')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('settings.index') }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Settings
        </a>
        <h1 class="text-3xl font-bold text-gray-800">Edit Profile</h1>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('settings.update-profile') }}">
            @csrf

            <!-- Profile Picture Placeholder -->
            <div class="mb-6 flex items-center gap-4">
                <div class="flex-shrink-0 h-20 w-20 bg-blue-100 rounded-full flex items-center justify-center">
                    <span class="text-blue-600 font-bold text-3xl">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </span>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800">{{ auth()->user()->username }}</h3>
                    <p class="text-sm text-gray-500">{{ auth()->user()->roles->first()->name ?? 'User' }}</p>
                </div>
            </div>

            <!-- Name -->
            <div class="mb-6">
                <label for="name" class="form-label">Full Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name', auth()->user()->name) }}"
                    class="form-input w-full @error('name') border-red-500 @enderror"
                    required
                    autofocus
                >
                @error('name')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Email -->
            <div class="mb-6">
                <label for="email" class="form-label">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email', auth()->user()->email) }}"
                    class="form-input w-full @error('email') border-red-500 @enderror"
                    required
                >
                @error('email')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Read-only fields -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Account Information (Read Only)</h3>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Username:</span>
                        <span class="font-medium text-gray-800">{{ auth()->user()->username }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Role:</span>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-medium">
                            {{ auth()->user()->roles->first()->name ?? 'User' }}
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Member Since:</span>
                        <span class="font-medium text-gray-800">{{ auth()->user()->created_at->format('F d, Y') }}</span>
                    </div>
                </div>
            </div>

            <!-- Info Note -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="flex gap-3">
                    <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="text-sm text-blue-800">
                        <p class="font-semibold mb-1">Note:</p>
                        <p>To change your username, password, or role, please contact an administrator.</p>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end gap-3">
                <a href="{{ route('settings.index') }}" class="btn-touch btn-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn-touch btn-primary">
                    Update Profile
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
