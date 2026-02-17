<nav class="bg-white border-b border-gray-200 shadow-sm" x-data="{ mobileMenuOpen: false }">
    <div class="px-4 py-3 md:px-6 lg:px-8">
        <div class="flex items-center justify-between">
            <!-- Logo / Brand -->
            <div class="flex items-center space-x-4">
                <a href="{{ route('operator.select-line') }}" class="text-xl font-bold text-gray-800">
                    OpenMES
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
                <!-- User Info -->
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-600">{{ auth()->user()->name }}</span>
                    <span class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs font-medium">
                        {{ auth()->user()->roles->first()->name ?? 'User' }}
                    </span>
                </div>

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
                @endhasrole

                @hasrole('Admin')
                    <a href="{{ route('admin.csv-import') }}" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                        CSV Import
                    </a>
                    <a href="{{ route('admin.audit-logs') }}" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                        Audit Logs
                    </a>
                @endhasrole

                <!-- Change Password -->
                <a href="{{ route('change-password') }}" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                    Change Password
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
            <!-- User Info -->
            <div class="px-3 py-2 border-b border-gray-200">
                <p class="text-sm font-medium text-gray-800">{{ auth()->user()->name }}</p>
                <p class="text-xs text-gray-600">{{ auth()->user()->roles->first()->name ?? 'User' }}</p>
            </div>

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
            @endhasrole

            @hasrole('Admin')
                <a href="{{ route('admin.csv-import') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100">
                    CSV Import
                </a>
                <a href="{{ route('admin.audit-logs') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100">
                    Audit Logs
                </a>
            @endhasrole

            <a href="{{ route('change-password') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100">
                Change Password
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
