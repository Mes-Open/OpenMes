@extends('layouts.app')

@section('title', __('Settings'))

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">{{ __("Settings") }}</h1>

    @hasrole('Admin')
    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <a href="{{ route('settings.system') }}" class="card hover:shadow-lg transition-shadow cursor-pointer flex items-start gap-4 border-l-4 border-blue-400">
            <div class="bg-blue-100 rounded-full p-3 flex-shrink-0">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-lg font-bold text-gray-800 mb-1">{{ __("{{ __("System Settings") }}") }}</h3>
                <p class="text-gray-600 text-sm">Production period split, overproduction rules, step sequencing</p>
            </div>
            <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </a>
        <a href="{{ route('admin.dashboard-widgets.index') }}" class="card hover:shadow-lg transition-shadow cursor-pointer flex items-start gap-4 border-l-4 border-amber-400">
            <div class="bg-amber-100 rounded-full p-3 flex-shrink-0">
                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-lg font-bold text-gray-800 mb-1">{{ __('Dashboard Setup') }}</h3>
                <p class="text-gray-600 text-sm">{{ __('Enable, disable, and reorder dashboard widgets') }}</p>
            </div>
            <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </a>
        <a href="{{ route('onboarding.index') }}" class="card hover:shadow-lg transition-shadow cursor-pointer flex items-start gap-4 border-l-4 border-green-400">
            <div class="bg-green-100 rounded-full p-3 flex-shrink-0">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-lg font-bold text-gray-800 mb-1">{{ __("Setup Wizard") }}</h3>
                <p class="text-gray-600 text-sm">{{ __("Re-launch the onboarding wizard") }}</p>
            </div>
            <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </a>
        <a href="{{ route('settings.api-tokens') }}" class="card hover:shadow-lg transition-shadow cursor-pointer flex items-start gap-4 border-l-4 border-purple-400">
            <div class="bg-purple-100 rounded-full p-3 flex-shrink-0">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-lg font-bold text-gray-800 mb-1">{{ __("API Tokens") }}</h3>
                <p class="text-gray-600 text-sm">{{ __("Manage tokens for external integrations") }}</p>
            </div>
            <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </a>
    </div>
    @endhasrole

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
                    <h3 class="text-lg font-bold text-gray-800 mb-1">{{ __("Profile") }}</h3>
                    <p class="text-gray-600 text-sm">{{ __("Update your profile and account info") }}</p>
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
                    <h3 class="text-lg font-bold text-gray-800 mb-1">{{ __("Change Password") }}</h3>
                    <p class="text-gray-600 text-sm">{{ __("Change your account password") }}</p>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </a>

        <!-- PIN Login Card -->
        @php
            $pinSetting = \Illuminate\Support\Facades\DB::table('system_settings')->where('key','pin_login_enabled')->first();
            $pinLoginEnabled = json_decode($pinSetting->value ?? 'false', true) === true;
        @endphp
        @if($pinLoginEnabled)
        <a href="{{ route('settings.pin') }}" class="card hover:shadow-lg transition-shadow cursor-pointer">
            <div class="flex items-start gap-4">
                <div class="bg-amber-100 rounded-full p-3">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-gray-800 mb-1">Quick PIN</h3>
                    <p class="text-gray-600 text-sm">
                        {{ auth()->user()->pin ? 'PIN active — change or remove' : 'Set a 4–6 digit PIN for fast login' }}
                    </p>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </a>
        @endif
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
