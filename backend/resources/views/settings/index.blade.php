@extends('layouts.app')

@section('title', __('Settings'))

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">{{ __('Settings') }}</h1>

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
                <h3 class="text-lg font-bold text-gray-800 mb-1">{{ __('System Settings') }}</h3>
                <p class="text-gray-600 text-sm">{{ __('Production period split, overproduction rules, step sequencing') }}</p>
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
                <h3 class="text-lg font-bold text-gray-800 mb-1">{{ __('API Tokens') }}</h3>
                <p class="text-gray-600 text-sm">{{ __('Manage tokens for external integrations (PrestaShop, ERP, etc.)') }}</p>
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
                    <h3 class="text-lg font-bold text-gray-800 mb-1">{{ __('Profile') }}</h3>
                    <p class="text-gray-600 text-sm">{{ __('Update your name and email address') }}</p>
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
                    <h3 class="text-lg font-bold text-gray-800 mb-1">{{ __('Change Password') }}</h3>
                    <p class="text-gray-600 text-sm">{{ __('Update your account password') }}</p>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </a>

        <!-- Language Card -->
        <div class="card" x-data="{ open: false }">
            <div class="flex items-start gap-4">
                <div class="bg-yellow-100 rounded-full p-3">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 11.37 9.198 15.53 5.347 18"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-gray-800 mb-1">{{ __('Language') }}</h3>
                    <div class="flex gap-2 mt-2">
                        <form action="{{ route('settings.set-language') }}" method="POST" class="inline">
                            @csrf
                            <button name="locale" value="en" type="submit"
                                    class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors {{ app()->getLocale() == 'en' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                                English
                            </button>
                        </form>
                        <form action="{{ route('settings.set-language') }}" method="POST" class="inline">
                            @csrf
                            <button name="locale" value="tr" type="submit"
                                    class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors {{ app()->getLocale() == 'tr' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                                Türkçe
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Info -->
    <div class="card mt-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">{{ __('Account Information') }}</h2>
        <div class="space-y-3">
            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                <span class="text-gray-600">{{ __('Name') }}</span>
                <span class="font-medium text-gray-800">{{ auth()->user()->name }}</span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                <span class="text-gray-600">{{ __('Username') }}</span>
                <span class="font-medium text-gray-800">{{ auth()->user()->username }}</span>
            </div>
            <div class="flex justify-between items-center py-2 border-b border-gray-200">
                <span class="text-gray-600">{{ __('Email') }}</span>
                <span class="font-medium text-gray-800">{{ auth()->user()->email }}</span>
            </div>
            <div class="flex justify-between items-center py-2">
                <span class="text-gray-600">{{ __('Role') }}</span>
                <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                    {{ auth()->user()->roles->first()->name ?? __('User') }}
                </span>
            </div>
        </div>
    </div>
</div>
@endsection
