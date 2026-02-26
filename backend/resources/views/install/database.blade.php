<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Database Setup - Install OpenMES</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-2xl">
        <div class="text-center mb-8">
            <img src="/logo_open_mes.png" alt="OpenMES" class="h-16 md:h-20 mx-auto mb-2">
            <h1 class="text-2xl font-bold text-gray-800">Installation Wizard</h1>
            <p class="text-gray-600 mt-2">Step 2 of 3: Database Configuration</p>
        </div>

        <!-- Progress Indicator -->
        <div class="mb-8">
            <div class="flex items-center justify-center space-x-2">
                <div class="flex items-center">
                    <a href="{{ route('install.environment') }}" class="w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center font-bold hover:bg-green-700 transition" title="Edit basic configuration">✓</a>
                    <a href="{{ route('install.environment') }}" class="ml-2 text-sm font-medium text-green-700 hover:text-green-800">Basic</a>
                </div>
                <div class="w-8 h-1 bg-green-600"></div>
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">2</div>
                    <span class="ml-2 text-sm font-medium text-gray-800">Database</span>
                </div>
                <div class="w-8 h-1 bg-gray-300"></div>
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center font-bold">3</div>
                    <span class="ml-2 text-sm font-medium text-gray-500">Admin</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Database Configuration</h2>

            @if($errors->any())
                <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('install.database.setup') }}"
                x-data="{
                    testing: false,
                    driver: '{{ old('db_driver', $dbConfig['db_driver']) }}',
                    get isSqlite() { return this.driver === 'sqlite'; },
                    get defaultPort() {
                        return { pgsql: '5432', mysql: '3306', mariadb: '3306' }[this.driver] ?? '';
                    }
                }"
                @submit="testing = true"
            >
                @csrf

                <!-- Database Driver -->
                <div class="mb-6">
                    <label for="db_driver" class="form-label">Database Driver</label>
                    <select
                        id="db_driver"
                        name="db_driver"
                        x-model="driver"
                        class="form-input w-full"
                    >
                        @foreach($dbDrivers as $key => $label)
                            <option value="{{ $key }}" {{ old('db_driver', $dbConfig['db_driver']) === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- SQLite path (only for SQLite) -->
                <div x-show="isSqlite" x-cloak class="mb-6">
                    <label for="db_database_sqlite" class="form-label">Database File Path</label>
                    <input
                        type="text"
                        id="db_database_sqlite"
                        name="db_database"
                        :value="isSqlite ? '{{ old('db_database', 'database/database.sqlite') }}' : ''"
                        x-bind:disabled="!isSqlite"
                        class="form-input w-full font-mono text-sm"
                        placeholder="database/database.sqlite"
                    >
                    <p class="text-sm text-gray-500 mt-1">
                        Relative to <code class="bg-gray-100 px-1 rounded">storage/</code>, or an absolute path.
                        The file will be created if it does not exist.
                    </p>
                </div>

                <!-- Server-based fields (hidden for SQLite) -->
                <div x-show="!isSqlite" x-cloak>
                    <div class="mb-4">
                        <label for="db_host" class="form-label">Database Host</label>
                        <input
                            type="text"
                            id="db_host"
                            name="db_host"
                            value="{{ old('db_host', $dbConfig['db_host']) }}"
                            x-bind:disabled="isSqlite"
                            class="form-input w-full"
                        >
                        <p class="text-sm text-gray-500 mt-1">Usually <code class="bg-gray-100 px-1 rounded">localhost</code> or the container service name</p>
                    </div>

                    <div class="mb-4">
                        <label for="db_port" class="form-label">Port</label>
                        <input
                            type="number"
                            id="db_port"
                            name="db_port"
                            :placeholder="defaultPort"
                            value="{{ old('db_port', $dbConfig['db_port']) }}"
                            x-bind:disabled="isSqlite"
                            class="form-input w-full"
                        >
                    </div>

                    <div class="mb-4">
                        <label for="db_database" class="form-label">Database Name</label>
                        <input
                            type="text"
                            id="db_database"
                            name="db_database"
                            value="{{ old('db_database', $dbConfig['db_database']) }}"
                            x-bind:disabled="isSqlite"
                            class="form-input w-full"
                        >
                        <p class="text-sm text-gray-500 mt-1">The database must already exist</p>
                    </div>

                    <div class="mb-4">
                        <label for="db_username" class="form-label">Username</label>
                        <input
                            type="text"
                            id="db_username"
                            name="db_username"
                            value="{{ old('db_username', $dbConfig['db_username']) }}"
                            x-bind:disabled="isSqlite"
                            class="form-input w-full"
                        >
                    </div>

                    <div class="mb-6">
                        <label for="db_password" class="form-label">Password</label>
                        <input
                            type="password"
                            id="db_password"
                            name="db_password"
                            value="{{ old('db_password', $dbConfig['db_password'] ?? '') }}"
                            x-bind:disabled="isSqlite"
                            class="form-input w-full"
                        >
                    </div>
                </div>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <p class="text-yellow-800 text-sm">
                        <strong>Note:</strong> Clicking "Continue" will test the connection and run all database migrations.
                    </p>
                </div>

                <div class="flex justify-between">
                    <a href="{{ route('install.environment') }}" class="btn-touch btn-secondary">
                        ← Back
                    </a>
                    <button
                        type="submit"
                        class="btn-touch btn-primary"
                        :disabled="testing"
                    >
                        <span x-show="!testing">Continue →</span>
                        <span x-show="testing">Testing connection...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
