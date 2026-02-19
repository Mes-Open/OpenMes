<nav class="bg-white border-b border-gray-200 shadow-sm" x-data="{ mobileMenuOpen: false }">
    <div class="px-4 py-3 md:px-4 lg:px-6">
        <div class="flex items-center justify-between gap-2">

            {{-- Logo --}}
            <div class="flex items-center gap-2 shrink-0">
                <a href="@auth
                    @if(auth()->user()->hasRole('Admin')){{ route('admin.dashboard') }}
                    @elseif(auth()->user()->hasRole('Supervisor')){{ route('supervisor.dashboard') }}
                    @else{{ route('operator.select-line') }}
                    @endif
                @else{{ route('login') }}
                @endauth">
                    <img src="/logo_open_mes.png" alt="OpenMES" class="h-8">
                </a>
                @if(session('selected_line_id'))
                    @php $selectedLine = \App\Models\Line::find(session('selected_line_id')); @endphp
                    @if($selectedLine)
                        <span class="hidden xl:inline-block px-2 py-0.5 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">{{ $selectedLine->name }}</span>
                    @endif
                @endif
            </div>

            {{-- Desktop nav --}}
            <div class="hidden md:flex items-center gap-0.5">
                @auth

                {{-- OPERATOR --}}
                @hasrole('Operator')
                    <a href="{{ route('operator.select-line') }}" class="nav-link">Select Line</a>
                    @if(session('selected_line_id'))
                        <a href="{{ route('operator.queue') }}" class="nav-link">Work Orders</a>
                    @endif
                @endhasrole

                {{-- SUPERVISOR --}}
                @hasrole('Supervisor')
                    <a href="{{ route('supervisor.dashboard') }}" class="nav-link">Dashboard</a>
                    <a href="{{ route('supervisor.work-orders.index') }}" class="nav-link">Work Orders</a>
                    <a href="{{ route('supervisor.issues.index') }}" class="nav-link">Issues</a>
                    <a href="{{ route('supervisor.reports') }}" class="nav-link">Reports</a>
                @endhasrole

                {{-- ADMIN --}}
                @hasrole('Admin')

                {{-- Orders ▼ --}}
                <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
                    <button @click="open = !open" @click.outside="open = false" class="nav-link flex items-center gap-1" :class="{ 'text-blue-600 bg-blue-50': open }">
                        Orders
                        <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak @click="open = false"
                         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                         class="absolute left-0 top-full mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1">
                        <a href="{{ route('admin.work-orders.index') }}" class="dd-item">Work Orders</a>
                        <a href="{{ route('admin.csv-import') }}" class="dd-item">Import CSV</a>
                    </div>
                </div>

                {{-- Production ▼ --}}
                <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
                    <button @click="open = !open" @click.outside="open = false" class="nav-link flex items-center gap-1" :class="{ 'text-blue-600 bg-blue-50': open }">
                        Production
                        <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak @click="open = false"
                         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                         class="absolute left-0 top-full mt-1 w-52 bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1">
                        <a href="{{ route('admin.product-types.index') }}" class="dd-item">Product Types</a>
                        <a href="{{ route('admin.lines.index') }}" class="dd-item">Lines</a>
                        <a href="{{ route('admin.issues.index') }}" class="dd-item">Issues</a>
                        <div class="my-1 border-t border-gray-100"></div>
                        <a href="{{ route('admin.companies.index') }}" class="dd-item">Companies</a>
                        <a href="{{ route('admin.anomaly-reasons.index') }}" class="dd-item">Anomaly Reasons</a>
                    </div>
                </div>

                {{-- Structure ▼ --}}
                <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
                    <button @click="open = !open" @click.outside="open = false" class="nav-link flex items-center gap-1" :class="{ 'text-blue-600 bg-blue-50': open }">
                        Structure
                        <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak @click="open = false"
                         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                         class="absolute left-0 top-full mt-1 w-52 bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1">
                        <a href="{{ route('admin.factories.index') }}" class="dd-item">Factories</a>
                        <a href="{{ route('admin.divisions.index') }}" class="dd-item">Divisions</a>
                        <a href="{{ route('admin.workstation-types.index') }}" class="dd-item">Workstation Types</a>
                        <a href="{{ route('admin.subassemblies.index') }}" class="dd-item">Subassemblies</a>
                    </div>
                </div>

                {{-- HR ▼ --}}
                <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
                    <button @click="open = !open" @click.outside="open = false" class="nav-link flex items-center gap-1" :class="{ 'text-blue-600 bg-blue-50': open }">
                        HR
                        <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak @click="open = false"
                         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 top-full mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1">
                        <a href="{{ route('admin.workers.index') }}" class="dd-item">Workers</a>
                        <a href="{{ route('admin.crews.index') }}" class="dd-item">Crews</a>
                        <a href="{{ route('admin.skills.index') }}" class="dd-item">Skills</a>
                        <a href="{{ route('admin.wage-groups.index') }}" class="dd-item">Wage Groups</a>
                    </div>
                </div>

                {{-- Maintenance ▼ --}}
                <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
                    <button @click="open = !open" @click.outside="open = false" class="nav-link flex items-center gap-1" :class="{ 'text-blue-600 bg-blue-50': open }">
                        Maint.
                        <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak @click="open = false"
                         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 top-full mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1">
                        <a href="{{ route('admin.maintenance-events.index') }}" class="dd-item">Events</a>
                        <a href="{{ route('admin.tools.index') }}" class="dd-item">Tools</a>
                        <a href="{{ route('admin.cost-sources.index') }}" class="dd-item">Cost Sources</a>
                        <a href="{{ route('admin.production-anomalies.index') }}" class="dd-item">Anomalies</a>
                    </div>
                </div>

                {{-- Admin ▼ --}}
                <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
                    <button @click="open = !open" @click.outside="open = false" class="nav-link flex items-center gap-1" :class="{ 'text-blue-600 bg-blue-50': open }">
                        Admin
                        <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak @click="open = false"
                         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 top-full mt-1 w-44 bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1">
                        <a href="{{ route('admin.users.index') }}" class="dd-item">Users</a>
                        <a href="{{ route('admin.reports') }}" class="dd-item">Reports</a>
                        <a href="{{ route('admin.audit-logs') }}" class="dd-item">Audit Logs</a>
                        <a href="{{ route('admin.modules.index') }}" class="dd-item">Modules</a>
                    </div>
                </div>

                @endhasrole

                {{-- Divider --}}
                <span class="mx-1.5 h-5 w-px bg-gray-200 shrink-0"></span>

                {{-- Settings icon --}}
                <a href="{{ route('settings.index') }}" class="nav-link p-2" title="Settings">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </a>

                {{-- Logout --}}
                <form action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="nav-link text-red-600 hover:text-red-700 hover:bg-red-50">Logout</button>
                </form>

                @endauth
            </div>

            {{-- Mobile hamburger --}}
            <button @click="mobileMenuOpen = !mobileMenuOpen"
                    class="md:hidden p-2 rounded-md text-gray-700 hover:bg-gray-100">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    <path x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Mobile menu --}}
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
                <a href="{{ route('supervisor.work-orders.index') }}" class="mobile-link">Work Orders</a>
                <a href="{{ route('supervisor.issues.index') }}" class="mobile-link">Issues</a>
                <a href="{{ route('supervisor.reports') }}" class="mobile-link">Reports</a>
            @endhasrole

            @hasrole('Admin')
                <p class="mobile-group-label">Orders</p>
                <a href="{{ route('admin.work-orders.index') }}" class="mobile-link pl-6">Work Orders</a>
                <a href="{{ route('admin.csv-import') }}" class="mobile-link pl-6">Import CSV</a>

                <p class="mobile-group-label">Production</p>
                <a href="{{ route('admin.product-types.index') }}" class="mobile-link pl-6">Product Types</a>
                <a href="{{ route('admin.lines.index') }}" class="mobile-link pl-6">Lines</a>
                <a href="{{ route('admin.issues.index') }}" class="mobile-link pl-6">Issues</a>
                <a href="{{ route('admin.companies.index') }}" class="mobile-link pl-6">Companies</a>
                <a href="{{ route('admin.anomaly-reasons.index') }}" class="mobile-link pl-6">Anomaly Reasons</a>

                <p class="mobile-group-label">Structure</p>
                <a href="{{ route('admin.factories.index') }}" class="mobile-link pl-6">Factories</a>
                <a href="{{ route('admin.divisions.index') }}" class="mobile-link pl-6">Divisions</a>
                <a href="{{ route('admin.workstation-types.index') }}" class="mobile-link pl-6">Workstation Types</a>
                <a href="{{ route('admin.subassemblies.index') }}" class="mobile-link pl-6">Subassemblies</a>

                <p class="mobile-group-label">HR</p>
                <a href="{{ route('admin.workers.index') }}" class="mobile-link pl-6">Workers</a>
                <a href="{{ route('admin.crews.index') }}" class="mobile-link pl-6">Crews</a>
                <a href="{{ route('admin.skills.index') }}" class="mobile-link pl-6">Skills</a>
                <a href="{{ route('admin.wage-groups.index') }}" class="mobile-link pl-6">Wage Groups</a>

                <p class="mobile-group-label">Maintenance</p>
                <a href="{{ route('admin.maintenance-events.index') }}" class="mobile-link pl-6">Events</a>
                <a href="{{ route('admin.tools.index') }}" class="mobile-link pl-6">Tools</a>
                <a href="{{ route('admin.cost-sources.index') }}" class="mobile-link pl-6">Cost Sources</a>
                <a href="{{ route('admin.production-anomalies.index') }}" class="mobile-link pl-6">Anomalies</a>

                <p class="mobile-group-label">Admin</p>
                <a href="{{ route('admin.users.index') }}" class="mobile-link pl-6">Users</a>
                <a href="{{ route('admin.reports') }}" class="mobile-link pl-6">Reports</a>
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
    @apply text-gray-700 hover:text-blue-600 hover:bg-gray-50 px-2.5 py-2 rounded-md text-sm font-medium transition-colors whitespace-nowrap;
}
.dd-item {
    @apply block px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors whitespace-nowrap;
}
.mobile-link {
    @apply block px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100;
}
.mobile-group-label {
    @apply px-3 pt-3 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider;
}
[x-cloak] { display: none !important; }
</style>
