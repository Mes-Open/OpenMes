<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Account - Install OpenMES</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-2xl">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-800">🏭 OpenMES Installation</h1>
            <p class="text-gray-600 mt-2">Step 2 of 2: Create Admin Account</p>
        </div>

        <div class="bg-white rounded-lg shadow-xl p-8">
            <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                ✓ Database configured successfully!
            </div>

            <h2 class="text-2xl font-bold text-gray-800 mb-6">Create Administrator Account</h2>

            @if($errors->any())
                <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('install.admin.create') }}" x-data="{
                password: '',
                passwordConfirmation: '',
                showPassword: false,
                showPasswordConfirmation: false
            }">
                @csrf

                <h3 class="text-lg font-semibold text-gray-800 mb-4">Site Information</h3>

                <div class="mb-4">
                    <label for="site_name" class="form-label">Site Name</label>
                    <input
                        type="text"
                        id="site_name"
                        name="site_name"
                        value="{{ old('site_name', 'OpenMES') }}"
                        class="form-input w-full"
                        required
                    >
                    <p class="text-sm text-gray-500 mt-1">The name of your manufacturing site</p>
                </div>

                <div class="mb-6">
                    <label for="site_url" class="form-label">Site URL</label>
                    <input
                        type="url"
                        id="site_url"
                        name="site_url"
                        value="{{ old('site_url', 'http://localhost') }}"
                        class="form-input w-full"
                        required
                    >
                    <p class="text-sm text-gray-500 mt-1">The URL where OpenMES will be accessed</p>
                </div>

                <h3 class="text-lg font-semibold text-gray-800 mb-4 mt-8">Administrator Account</h3>

                <div class="mb-4">
                    <label for="admin_username" class="form-label">Username</label>
                    <input
                        type="text"
                        id="admin_username"
                        name="admin_username"
                        value="{{ old('admin_username') }}"
                        class="form-input w-full"
                        required
                    >
                    <p class="text-sm text-gray-500 mt-1">Your admin login username</p>
                </div>

                <div class="mb-4">
                    <label for="admin_email" class="form-label">Email Address</label>
                    <input
                        type="email"
                        id="admin_email"
                        name="admin_email"
                        value="{{ old('admin_email') }}"
                        class="form-input w-full"
                        required
                    >
                </div>

                <div class="mb-4">
                    <label for="admin_password" class="form-label">Password</label>
                    <div class="relative">
                        <input
                            :type="showPassword ? 'text' : 'password'"
                            id="admin_password"
                            name="admin_password"
                            x-model="password"
                            class="form-input w-full pr-12"
                            minlength="8"
                            required
                        >
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                        >
                            <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">Minimum 8 characters</p>
                </div>

                <div class="mb-6">
                    <label for="admin_password_confirmation" class="form-label">Confirm Password</label>
                    <div class="relative">
                        <input
                            :type="showPasswordConfirmation ? 'text' : 'password'"
                            id="admin_password_confirmation"
                            name="admin_password_confirmation"
                            x-model="passwordConfirmation"
                            class="form-input w-full pr-12"
                            minlength="8"
                            required
                        >
                        <button
                            type="button"
                            @click="showPasswordConfirmation = !showPasswordConfirmation"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                        >
                            <svg x-show="!showPasswordConfirmation" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="showPasswordConfirmation" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    <p class="text-sm mt-1" :class="password && passwordConfirmation && password === passwordConfirmation ? 'text-green-600' : 'text-gray-500'">
                        <span x-show="!passwordConfirmation">Re-enter your password</span>
                        <span x-show="passwordConfirmation && password !== passwordConfirmation" class="text-red-600">Passwords do not match</span>
                        <span x-show="password && passwordConfirmation && password === passwordConfirmation">✓ Passwords match</span>
                    </p>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <p class="text-blue-800 text-sm">
                        <strong>Important:</strong> Save these credentials securely! You will need them to access OpenMES.
                    </p>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="btn-touch btn-primary"
                        :disabled="!password || !passwordConfirmation || password !== passwordConfirmation"
                        :class="{ 'opacity-50 cursor-not-allowed': !password || !passwordConfirmation || password !== passwordConfirmation }"
                    >
                        Complete Installation →
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
