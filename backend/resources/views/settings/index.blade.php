@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Settings</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Profile Card -->
        <a href="{{ route('settings.profile') }}" class="card hover:shadow-lg transition-shadow cursor-pointer">
            <div class="flex items-start gap-4">
                <div class="bg-blue-100 rounded-full p-3">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-gray-800 mb-1">Profile</h3>
                    <p class="text-gray-600 text-sm">Update your name and email address</p>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </a>

        <!-- Change Password Card -->
        <a href="{{ route('settings.change-password') }}" class="card hover:shadow-lg transition-shadow cursor-pointer">
            <div class="flex items-start gap-4">
                <div class="bg-green-100 rounded-full p-3">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-gray-800 mb-1">Change Password</h3>
                    <p class="text-gray-600 text-sm">Update your account password</p>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </a>
    </div>

    <!-- User Info -->
    <div class="card mt-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Account Information</h2>
        <div class="space-y-3">
            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                <span class="text-gray-600">Name</span>
                <span class="font-medium text-gray-800">{{ auth()->user()->name }}</span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                <span class="text-gray-600">Username</span>
                <span class="font-medium text-gray-800">{{ auth()->user()->username }}</span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                <span class="text-gray-600">Email</span>
                <span class="font-medium text-gray-800">{{ auth()->user()->email }}</span>
            </div>
            <div class="flex justify-between items-center py-2">
                <span class="text-gray-600">Role</span>
                <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                    {{ auth()->user()->roles->first()->name ?? 'User' }}
                </span>
            </div>
        </div>
    </div>
</div>
@endsection
