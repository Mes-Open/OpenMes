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
                    <a href="{{ route('supervisor.work-orders.index') }}" class="nav-link">Work Orders</a>
                    <a href="{{ route('supervisor.issues.index') }}" class="nav-link">Issues</a>
                    <a href="{{ route('supervisor.reports') }}" class="nav-link">Reports</a>
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

                    {{-- Company Structure dropdown --}}
                    <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
                        <button @click="open = !open" @click.outside="open = false"
                                class="nav-link flex items-center gap-1"
                                :class="{ 'text-blue-600 bg-blue-50': open }">
                            Structure
                            <svg class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                             class="absolute left-0 top-full mt-1 w-52 bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1" x-cloak>
                            <a href="{{ route('admin.factories.index') }}" @click="open = false" class="flex flex-row items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                <span>Factories</span>
                            </a>
                            <a href="{{ route('admin.divisions.index') }}" @click="open = false" class="flex flex-row items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                                <span>Divisions</span>
                            </a>
                            <a href="{{ route('admin.workstation-types.index') }}" @click="open = false" class="flex flex-row items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>
                                <span>Workstation Types</span>
                            </a>
                            <a href="{{ route('admin.subassemblies.index') }}" @click="open = false" class="flex flex-row items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 11-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/></svg>
                                <span>Subassemblies</span>
                            </a>
                        </div>
                    </div>

                    {{-- HR dropdown --}}
                    <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
                        <button @click="open = !open" @click.outside="open = false"
                                class="nav-link flex items-center gap-1"
                                :class="{ 'text-blue-600 bg-blue-50': open }">
                            HR
                            <svg class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                             class="absolute left-0 top-full mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1" x-cloak>
                            <a href="{{ route('admin.workers.index') }}" @click="open = false" class="flex flex-row items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span>Workers</span>
                            </a>
                            <a href="{{ route('admin.crews.index') }}" @click="open = false" class="flex flex-row items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                <span>Crews</span>
                            </a>
                            <a href="{{ route('admin.skills.index') }}" @click="open = false" class="flex flex-row items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                                <span>Skills</span>
                            </a>
                            <a href="{{ route('admin.wage-groups.index') }}" @click="open = false" class="flex flex-row items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Wage Groups</span>
                            </a>
                        </div>
                    </div>

                    {{-- Maintenance dropdown --}}
                    <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
                        <button @click="open = !open" @click.outside="open = false"
                                class="nav-link flex items-center gap-1"
                                :class="{ 'text-blue-600 bg-blue-50': open }">
                            Maintenance
                            <svg class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                             class="absolute left-0 top-full mt-1 w-52 bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1" x-cloak>
                            <a href="{{ route('admin.maintenance-events.index') }}" @click="open = false" class="flex flex-row items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>Events</span>
                            </a>
                            <a href="{{ route('admin.tools.index') }}" @click="open = false" class="flex flex-row items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span>Tools</span>
                            </a>
                            <a href="{{ route('admin.cost-sources.index') }}" @click="open = false" class="flex flex-row items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                <span>Cost Sources</span>
                            </a>
                            <a href="{{ route('admin.production-anomalies.index') }}" @click="open = false" class="flex flex-row items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <span>Anomalies</span>
                            </a>
                        </div>
                    </div>

                    {{-- Dictionaries dropdown --}}
                    <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
                        <button @click="open = !open" @click.outside="open = false"
                                class="nav-link flex items-center gap-1"
                                :class="{ 'text-blue-600 bg-blue-50': open }">
                            Dictionaries
                            <svg class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                             class="absolute left-0 top-full mt-1 w-52 bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1" x-cloak>
                            <a href="{{ route('admin.companies.index') }}" @click="open = false" class="flex flex-row items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <span>Companies</span>
                            </a>
                            <a href="{{ route('admin.anomaly-reasons.index') }}" @click="open = false" class="flex flex-row items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                                <span>Anomaly Reasons</span>
                            </a>
                        </div>
                    </div>

                    {{-- Flat links --}}
                    <a href="{{ route('admin.reports') }}" class="nav-link">Reports</a>
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
                <a href="{{ route('supervisor.work-orders.index') }}" class="mobile-link">Work Orders</a>
                <a href="{{ route('supervisor.issues.index') }}" class="mobile-link">Issues</a>
                <a href="{{ route('supervisor.reports') }}" class="mobile-link">Reports</a>
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

                {{-- Structure group --}}
                <p class="px-3 pt-2 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">Structure</p>
                <a href="{{ route('admin.factories.index') }}" class="mobile-link pl-6">Factories</a>
                <a href="{{ route('admin.divisions.index') }}" class="mobile-link pl-6">Divisions</a>
                <a href="{{ route('admin.workstation-types.index') }}" class="mobile-link pl-6">Workstation Types</a>
                <a href="{{ route('admin.subassemblies.index') }}" class="mobile-link pl-6">Subassemblies</a>

                {{-- HR group --}}
                <p class="px-3 pt-2 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">HR</p>
                <a href="{{ route('admin.workers.index') }}" class="mobile-link pl-6">Workers</a>
                <a href="{{ route('admin.crews.index') }}" class="mobile-link pl-6">Crews</a>
                <a href="{{ route('admin.skills.index') }}" class="mobile-link pl-6">Skills</a>
                <a href="{{ route('admin.wage-groups.index') }}" class="mobile-link pl-6">Wage Groups</a>

                {{-- Dictionaries group --}}
                <p class="px-3 pt-2 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">Dictionaries</p>
                <a href="{{ route('admin.companies.index') }}" class="mobile-link pl-6">Companies</a>
                <a href="{{ route('admin.anomaly-reasons.index') }}" class="mobile-link pl-6">Anomaly Reasons</a>

                {{-- Maintenance group --}}
                <p class="px-3 pt-2 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">Maintenance</p>
                <a href="{{ route('admin.maintenance-events.index') }}" class="mobile-link pl-6">Events</a>
                <a href="{{ route('admin.tools.index') }}" class="mobile-link pl-6">Tools</a>
                <a href="{{ route('admin.cost-sources.index') }}" class="mobile-link pl-6">Cost Sources</a>
                <a href="{{ route('admin.production-anomalies.index') }}" class="mobile-link pl-6">Anomalies</a>

                {{-- Other --}}
                <a href="{{ route('admin.reports') }}" class="mobile-link pl-6">Reports</a>
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
