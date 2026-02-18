<nav class="bg-white border-b border-gray-200 shadow-sm" x-data="{ mobileMenuOpen: false }">
    <div class="px-4 py-3 md:px-6 lg:px-8">
        <div class="flex items-center justify-between">

            {{-- Logo / Brand --}}
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
                    @php $selectedLine = \App\Models\Line::find(session('selected_line_id')); @endphp
                    @if($selectedLine)
                        <span class="hidden md:inline-block px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                            {{ $selectedLine->name }}
                        </span>
                    @endif
                @endif
            </div>

            {{-- Desktop Navigation --}}
            <div class="hidden md:flex items-center space-x-1">
                @auth
                    {{-- User badge --}}
                    <div class="flex items-center space-x-2 mr-2">
                        <span class="text-sm text-gray-600">{{ auth()->user()->name }}</span>
                        <span class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs font-medium">
                            {{ auth()->user()->roles->first()->name ?? 'User' }}
                        </span>
                    </div>
                @endauth

                {{-- Operator links --}}
                @hasrole('Operator')
                    <a href="{{ route('operator.select-line') }}" class="nav-link">Select Line</a>
                    @if(session('selected_line_id'))
                        <a href="{{ route('operator.queue') }}" class="nav-link">Work Orders</a>
                    @endif
                @endhasrole

                {{-- Supervisor links --}}
                @hasrole('Supervisor')
                    <a href="{{ route('supervisor.dashboard') }}" class="nav-link">Dashboard</a>
                    <a href="{{ route('supervisor.issues.index') }}" class="nav-link">Issues</a>
                @endhasrole

                {{-- Admin links with dropdowns --}}
                @hasrole('Admin')

                    {{-- Orders dropdown --}}
                    <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
                        <button @click="open = !open" @click.outside="open = false"
                                class="nav-link flex items-center gap-1"
                                :class="{ 'text-blue-600 bg-blue-50': open }">
                            Orders
                            <svg class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': open }"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                             class="absolute left-0 top-full mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1"
                             x-cloak>
                            <a href="{{ route('admin.work-orders.index') }}" @click="open = false"
                               class="flex flex-row items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                <span>Work Orders</span>
                            </a>
                            <a href="{{ route('admin.csv-import') }}" @click="open = false"
                               class="flex flex-row items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                <span>Import</span>
                            </a>
                        </div>
                    </div>

                    {{-- Production dropdown --}}
                    <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
                        <button @click="open = !open" @click.outside="open = false"
                                class="nav-link flex items-center gap-1"
                                :class="{ 'text-blue-600 bg-blue-50': open }">
                            Production
                            <svg class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': open }"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                             class="absolute left-0 top-full mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1"
                             x-cloak>
                            <a href="{{ route('admin.product-types.index') }}" @click="open = false"
                               class="flex flex-row items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                <span>Product Types</span>
                            </a>
                            <a href="{{ route('admin.lines.index') }}" @click="open = false"
                               class="flex flex-row items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                                <span>Lines</span>
                            </a>
                            <a href="{{ route('admin.issues.index') }}" @click="open = false"
                               class="flex flex-row items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z"/></svg>
                                <span>Issues</span>
                            </a>
                        </div>
                    </div>

                    {{-- Flat links --}}
                    <a href="{{ route('admin.users.index') }}" class="nav-link">Users</a>
                    <a href="{{ route('admin.audit-logs') }}" class="nav-link">Audit Logs</a>
                    <a href="{{ route('admin.modules.index') }}" class="nav-link">Modules</a>

                @endhasrole

                {{-- Settings (all roles) --}}
                <a href="{{ route('settings.index') }}" class="nav-link">Settings</a>

                {{-- Logout --}}
                <form action="{{ route('logout') }}" method="POST" class="inline ml-1">
                    @csrf
                    <button type="submit" class="btn-touch btn-secondary text-sm">Logout</button>
                </form>
            </div>

            {{-- Mobile menu button --}}
            <button @click="mobileMenuOpen = !mobileMenuOpen"
                    class="md:hidden p-2 rounded-md text-gray-700 hover:bg-gray-100">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    <path x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Mobile Navigation --}}
        <div x-show="mobileMenuOpen" x-transition class="md:hidden mt-4 pb-2 space-y-1">
            @auth
                <div class="px-3 py-2 border-b border-gray-200 mb-2">
                    <p class="text-sm font-medium text-gray-800">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-gray-500">{{ auth()->user()->roles->first()->name ?? 'User' }}</p>
                </div>
            @endauth

            @hasrole('Operator')
                <a href="{{ route('operator.select-line') }}" class="mobile-link">Select Line</a>
                @if(session('selected_line_id'))
                    <a href="{{ route('operator.queue') }}" class="mobile-link">Work Orders</a>
                @endif
            @endhasrole

            @hasrole('Supervisor')
                <a href="{{ route('supervisor.dashboard') }}" class="mobile-link">Dashboard</a>
                <a href="{{ route('supervisor.issues.index') }}" class="mobile-link">Issues</a>
            @endhasrole

            @hasrole('Admin')
                {{-- Orders group --}}
                <p class="px-3 pt-2 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">Orders</p>
                <a href="{{ route('admin.work-orders.index') }}" class="mobile-link pl-6">Work Orders</a>
                <a href="{{ route('admin.csv-import') }}" class="mobile-link pl-6">Import</a>

                {{-- Production group --}}
                <p class="px-3 pt-2 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">Production</p>
                <a href="{{ route('admin.product-types.index') }}" class="mobile-link pl-6">Product Types</a>
                <a href="{{ route('admin.lines.index') }}" class="mobile-link pl-6">Lines</a>
                <a href="{{ route('admin.issues.index') }}" class="mobile-link pl-6">Issues</a>

                {{-- Other --}}
                <p class="px-3 pt-2 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">Admin</p>
                <a href="{{ route('admin.users.index') }}" class="mobile-link pl-6">Users</a>
                <a href="{{ route('admin.audit-logs') }}" class="mobile-link pl-6">Audit Logs</a>
                <a href="{{ route('admin.modules.index') }}" class="mobile-link pl-6">Modules</a>
            @endhasrole

            <div class="border-t border-gray-200 mt-2 pt-2">
                <a href="{{ route('settings.index') }}" class="mobile-link">Settings</a>
                <form action="{{ route('logout') }}" method="POST" class="px-3 py-2">
                    @csrf
                    <button type="submit" class="w-full btn-touch btn-secondary">Logout</button>
                </form>
            </div>
        </div>
    </div>
</nav>

<style>
.nav-link {
    @apply text-gray-700 hover:text-blue-600 hover:bg-gray-50 px-3 py-2 rounded-md text-sm font-medium transition-colors;
}
.dropdown-item {
    @apply flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors;
}
.mobile-link {
    @apply block px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100;
}
[x-cloak] { display: none !important; }
</style>
