<nav class="bg-white border-b border-gray-200 shadow-sm" x-data="{ mobileMenuOpen: false }">
    <div class="px-4 py-3 md:px-6 lg:px-8">
        <div class="flex items-center justify-between">
            <!-- Logo / Brand -->
            <div class="flex items-center space-x-4">
                <a href="@auth
                    @if(auth()->user()->hasRole('Admin'))
                        {{ route('admin.dashboard') }}
                    @elseif(auth()->user()->hasRole('Supervisor'))
                        {{ route('supervisor.dashboard') }}
                    @else
                        {{ route('operator.select-line') }}
                    @endif
                @else
                    {{ route('login') }}
                @endauth" class="flex items-center">
                    <img src="/logo_open_mes.png" alt="OpenMES" class="h-8 md:h-10">
                </a>

                @if(session('selected_line_id'))
                    @php
                        $selectedLine = \App\Models\Line::find(session('selected_line_id'));
                    @endphp
                    @if($selectedLine)
                        <span class="hidden md:inline-block px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                            {{ $selectedLine->name }}
                        </span>
                    @endif
                @endif
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center space-x-4">
                @auth
                <!-- User Info -->
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-600">{{ auth()->user()->name }}</span>
                    <span class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs font-medium">
                        {{ auth()->user()->roles->first()->name ?? 'User' }}
                    </span>
                </div>
                @endauth

                <!-- Navigation Links based on role -->
                @hasrole('Operator')
                    <a href="{{ route('operator.select-line') }}" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                        Select Line
                    </a>
                    @if(session('selected_line_id'))
                        <a href="{{ route('operator.queue') }}" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                            Work Orders
                        </a>
                    @endif
                @endhasrole

                @hasrole('Supervisor')
                    <a href="{{ route('supervisor.dashboard') }}" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                        Dashboard
                    </a>
                    <a href="{{ route('supervisor.issues.index') }}" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                        Issues
                    </a>
                @endhasrole

                @hasrole('Admin')
                    <a href="{{ route('admin.work-orders.index') }}" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                        Work Orders
                    </a>
                    <a href="{{ route('admin.issues.index') }}" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                        Issues
                    </a>
                    <a href="{{ route('admin.lines.index') }}" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                        Lines
                    </a>
                    <a href="{{ route('admin.product-types.index') }}" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                        Product Types
                    </a>
                    <a href="{{ route('admin.users.index') }}" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                        Users
                    </a>
                    <a href="{{ route('admin.csv-import') }}" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                        CSV Import
                    </a>
                    <a href="{{ route('admin.audit-logs') }}" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                        Audit Logs
                    </a>
                @endhasrole

                <!-- Settings -->
                <a href="{{ route('settings.index') }}" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                    Settings
                </a>

                <!-- Logout -->
                <form action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="btn-touch btn-secondary text-sm">
                        Logout
                    </button>
                </form>
            </div>

            <!-- Mobile Menu Button -->
            <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 rounded-md text-gray-700 hover:bg-gray-100">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    <path x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Mobile Navigation Menu -->
        <div x-show="mobileMenuOpen" x-transition class="md:hidden mt-4 pb-2 space-y-2">
            @auth
            <!-- User Info -->
            <div class="px-3 py-2 border-b border-gray-200">
                <p class="text-sm font-medium text-gray-800">{{ auth()->user()->name }}</p>
                <p class="text-xs text-gray-600">{{ auth()->user()->roles->first()->name ?? 'User' }}</p>
            </div>
            @endauth

            <!-- Navigation Links -->
            @hasrole('Operator')
                <a href="{{ route('operator.select-line') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100">
                    Select Line
                </a>
                @if(session('selected_line_id'))
                    <a href="{{ route('operator.queue') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100">
                        Work Orders
                    </a>
                @endif
            @endhasrole

            @hasrole('Supervisor')
                <a href="{{ route('supervisor.dashboard') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100">
                    Dashboard
                </a>
                <a href="{{ route('supervisor.issues.index') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100">
                    Issues
                </a>
            @endhasrole

            @hasrole('Admin')
                <a href="{{ route('admin.work-orders.index') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100">
                    Work Orders
                </a>
                <a href="{{ route('admin.issues.index') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100">
                    Issues
                </a>
                <a href="{{ route('admin.lines.index') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100">
                    Lines
                </a>
                <a href="{{ route('admin.product-types.index') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100">
                    Product Types
                </a>
                <a href="{{ route('admin.users.index') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100">
                    Users
                </a>
                <a href="{{ route('admin.csv-import') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100">
                    CSV Import
                </a>
                <a href="{{ route('admin.audit-logs') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100">
                    Audit Logs
                </a>
            @endhasrole

            <a href="{{ route('settings.index') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100">
                Settings
            </a>

            <!-- Logout -->
            <form action="{{ route('logout') }}" method="POST" class="px-3 py-2">
                @csrf
                <button type="submit" class="w-full btn-touch btn-secondary">
                    Logout
                </button>
            </form>
        </div>
    </div>
</nav>
