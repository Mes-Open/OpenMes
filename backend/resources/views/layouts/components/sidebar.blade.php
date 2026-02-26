{{--
    Collapsible sidebar navigation component.
    Alpine state (x-data) is defined in layouts/app.blade.php:
        collapsed, mobileOpen, orders, production, structure, hr, maintenance, adminGroup
        toggle(), expandGroup(g)

    Collapsed-mode condition: collapsed && !mobileOpen
    Label visibility:         !collapsed || mobileOpen
--}}
<aside
    class="fixed inset-y-0 left-0 z-40 flex flex-col shrink-0 bg-slate-900 text-slate-100
           -translate-x-full lg:translate-x-0 w-64
           lg:relative lg:inset-auto lg:z-auto
           transition-[width,transform] duration-300 ease-in-out overflow-hidden"
    :class="{
        '-translate-x-full': !mobileOpen,
        'translate-x-0':     mobileOpen,
        'lg:w-16':           collapsed,
        'lg:w-64':           !collapsed
    }"
>

    {{-- ── Logo / Header ────────────────────────────────────────── --}}
    <div class="flex items-center h-16 px-3 shrink-0 border-b border-slate-700/60">
        <a href="@auth
                @if(auth()->user()->hasRole('Admin')){{ route('admin.dashboard') }}
                @elseif(auth()->user()->hasRole('Supervisor')){{ route('supervisor.dashboard') }}
                @else{{ route('operator.select-line') }}
                @endif
            @else{{ route('login') }}@endauth"
           class="flex items-center gap-2.5 min-w-0 overflow-hidden">
            <img src="/logo_open_mes.png" alt="OpenMES" class="h-8 w-8 shrink-0 object-contain">
            <span x-show="!collapsed || mobileOpen" x-cloak
                  class="text-white font-bold text-sm tracking-tight truncate">
                OpenMES
            </span>
        </a>
        {{-- Mobile close button --}}
        <button @click="mobileOpen = false"
                class="lg:hidden ml-auto p-1.5 rounded-md text-slate-400 hover:text-white
                       hover:bg-slate-700 transition-colors shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- ── Navigation ───────────────────────────────────────────── --}}
    <nav class="flex-1 overflow-y-auto overflow-x-hidden py-3 space-y-0.5">
        @auth

        {{-- ══════════════════════════════════════ OPERATOR ══════ --}}
        @hasrole('Operator')

            {{-- Select Line --}}
            @php $a = request()->routeIs('operator.select-line'); @endphp
            <div class="relative group px-2">
                <a href="{{ route('operator.select-line') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ $a ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}"
                   :class="{'justify-center !px-0': collapsed && !mobileOpen}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                    <span x-show="!collapsed || mobileOpen" x-cloak>Select Line</span>
                </a>
                <span x-show="collapsed && !mobileOpen" x-cloak
                      class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-slate-700
                             text-white text-xs rounded-md whitespace-nowrap z-50 opacity-0
                             group-hover:opacity-100 transition-opacity shadow-lg pointer-events-none">
                    Select Line
                </span>
            </div>

            @if(session('selected_line_id'))
                @php $a = request()->routeIs('operator.queue', 'operator.work-order.*'); @endphp
                <div class="relative group px-2">
                    <a href="{{ route('operator.queue') }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                              {{ $a ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}"
                       :class="{'justify-center !px-0': collapsed && !mobileOpen}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                        </svg>
                        <span x-show="!collapsed || mobileOpen" x-cloak>Work Orders</span>
                    </a>
                    <span x-show="collapsed && !mobileOpen" x-cloak
                          class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-slate-700
                                 text-white text-xs rounded-md whitespace-nowrap z-50 opacity-0
                                 group-hover:opacity-100 transition-opacity shadow-lg pointer-events-none">
                        Work Orders
                    </span>
                </div>
            @endif

        @endhasrole

        {{-- ══════════════════════════════════════ SUPERVISOR ════ --}}
        @hasrole('Supervisor')

            @php
                $links = [
                    ['route' => 'supervisor.dashboard',         'label' => 'Dashboard',    'icon' => 'home'],
                    ['route' => 'supervisor.work-orders.index', 'label' => 'Work Orders',  'icon' => 'clipboard'],
                    ['route' => 'supervisor.issues.index',      'label' => 'Issues',       'icon' => 'warning'],
                    ['route' => 'supervisor.reports',           'label' => 'Reports',      'icon' => 'chart'],
                ];
            @endphp

            {{-- Dashboard --}}
            @php $a = request()->routeIs('supervisor.dashboard'); @endphp
            <div class="relative group px-2">
                <a href="{{ route('supervisor.dashboard') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ $a ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}"
                   :class="{'justify-center !px-0': collapsed && !mobileOpen}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span x-show="!collapsed || mobileOpen" x-cloak>Dashboard</span>
                </a>
                <span x-show="collapsed && !mobileOpen" x-cloak
                      class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-slate-700
                             text-white text-xs rounded-md whitespace-nowrap z-50 opacity-0
                             group-hover:opacity-100 transition-opacity shadow-lg pointer-events-none">
                    Dashboard
                </span>
            </div>

            {{-- Work Orders --}}
            @php $a = request()->routeIs('supervisor.work-orders.*'); @endphp
            <div class="relative group px-2">
                <a href="{{ route('supervisor.work-orders.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ $a ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}"
                   :class="{'justify-center !px-0': collapsed && !mobileOpen}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    <span x-show="!collapsed || mobileOpen" x-cloak>Work Orders</span>
                </a>
                <span x-show="collapsed && !mobileOpen" x-cloak
                      class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-slate-700
                             text-white text-xs rounded-md whitespace-nowrap z-50 opacity-0
                             group-hover:opacity-100 transition-opacity shadow-lg pointer-events-none">
                    Work Orders
                </span>
            </div>

            {{-- Issues --}}
            @php $a = request()->routeIs('supervisor.issues.*'); @endphp
            <div class="relative group px-2">
                <a href="{{ route('supervisor.issues.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ $a ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}"
                   :class="{'justify-center !px-0': collapsed && !mobileOpen}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span x-show="!collapsed || mobileOpen" x-cloak>Issues</span>
                </a>
                <span x-show="collapsed && !mobileOpen" x-cloak
                      class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-slate-700
                             text-white text-xs rounded-md whitespace-nowrap z-50 opacity-0
                             group-hover:opacity-100 transition-opacity shadow-lg pointer-events-none">
                    Issues
                </span>
            </div>

            {{-- Reports --}}
            @php $a = request()->routeIs('supervisor.reports'); @endphp
            <div class="relative group px-2">
                <a href="{{ route('supervisor.reports') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ $a ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}"
                   :class="{'justify-center !px-0': collapsed && !mobileOpen}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <span x-show="!collapsed || mobileOpen" x-cloak>Reports</span>
                </a>
                <span x-show="collapsed && !mobileOpen" x-cloak
                      class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-slate-700
                             text-white text-xs rounded-md whitespace-nowrap z-50 opacity-0
                             group-hover:opacity-100 transition-opacity shadow-lg pointer-events-none">
                    Reports
                </span>
            </div>

        @endhasrole

        {{-- ══════════════════════════════════════ ADMIN ══════════ --}}
        @hasrole('Admin')

            {{-- Dashboard --}}
            @php $a = request()->routeIs('admin.dashboard'); @endphp
            <div class="relative group px-2">
                <a href="{{ route('admin.dashboard') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ $a ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}"
                   :class="{'justify-center !px-0': collapsed && !mobileOpen}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span x-show="!collapsed || mobileOpen" x-cloak>Dashboard</span>
                </a>
                <span x-show="collapsed && !mobileOpen" x-cloak
                      class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-slate-700
                             text-white text-xs rounded-md whitespace-nowrap z-50 opacity-0
                             group-hover:opacity-100 transition-opacity shadow-lg pointer-events-none">
                    Dashboard
                </span>
            </div>

            {{-- Separator --}}
            <div x-show="!collapsed || mobileOpen" x-cloak class="mx-4 my-2 border-t border-slate-700/60"></div>

            {{-- ── ORDERS ─── --}}
            <div class="px-2">
                <div class="relative group">
                    <button @click="expandGroup('orders')"
                            class="flex items-center gap-3 w-full px-3 py-2.5 rounded-lg text-sm font-medium
                                   transition-colors text-slate-300 hover:bg-slate-700 hover:text-white"
                            :class="{
                                'justify-center !px-0':    collapsed && !mobileOpen,
                                'bg-slate-700/50 text-white': orders && (!collapsed || mobileOpen)
                            }">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        <span x-show="!collapsed || mobileOpen" x-cloak class="flex-1 text-left">Orders</span>
                        <svg x-show="!collapsed || mobileOpen" x-cloak
                             class="w-4 h-4 shrink-0 transition-transform" :class="{'rotate-180': orders}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <span x-show="collapsed && !mobileOpen" x-cloak
                          class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-slate-700
                                 text-white text-xs rounded-md whitespace-nowrap z-50 opacity-0
                                 group-hover:opacity-100 transition-opacity shadow-lg pointer-events-none">
                        Orders
                    </span>
                </div>
                <div x-show="orders && (!collapsed || mobileOpen)" x-cloak
                     x-transition:enter="transition-opacity ease-out duration-150"
                     x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity ease-in duration-100"
                     x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="mt-0.5 ml-4 space-y-0.5 border-l border-slate-700/60 pl-3">
                    <a href="{{ route('admin.work-orders.index') }}"
                       class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors
                              {{ request()->routeIs('admin.work-orders.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Work Orders
                    </a>
                    <a href="{{ route('admin.csv-import') }}"
                       class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors
                              {{ request()->routeIs('admin.csv-import') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Import CSV
                    </a>
                    @foreach($menuRegistry->getItems('orders') as $item)
                        <a href="{{ $item['url'] }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-slate-400 hover:text-white hover:bg-slate-700 transition-colors">
                            <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>{{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- ── PRODUCTION ─── --}}
            <div class="px-2">
                <div class="relative group">
                    <button @click="expandGroup('production')"
                            class="flex items-center gap-3 w-full px-3 py-2.5 rounded-lg text-sm font-medium
                                   transition-colors text-slate-300 hover:bg-slate-700 hover:text-white"
                            :class="{
                                'justify-center !px-0':    collapsed && !mobileOpen,
                                'bg-slate-700/50 text-white': production && (!collapsed || mobileOpen)
                            }">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                        </svg>
                        <span x-show="!collapsed || mobileOpen" x-cloak class="flex-1 text-left">Production</span>
                        <svg x-show="!collapsed || mobileOpen" x-cloak
                             class="w-4 h-4 shrink-0 transition-transform" :class="{'rotate-180': production}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <span x-show="collapsed && !mobileOpen" x-cloak
                          class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-slate-700
                                 text-white text-xs rounded-md whitespace-nowrap z-50 opacity-0
                                 group-hover:opacity-100 transition-opacity shadow-lg pointer-events-none">
                        Production
                    </span>
                </div>
                <div x-show="production && (!collapsed || mobileOpen)" x-cloak
                     x-transition:enter="transition-opacity ease-out duration-150"
                     x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity ease-in duration-100"
                     x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="mt-0.5 ml-4 space-y-0.5 border-l border-slate-700/60 pl-3">
                    <a href="{{ route('admin.product-types.index') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.product-types.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Product Types
                    </a>
                    <a href="{{ route('admin.lines.index') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.lines.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Lines
                    </a>
                    <a href="{{ route('admin.line-statuses.index') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.line-statuses.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Line Statuses
                    </a>
                    <a href="{{ route('admin.issues.index') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.issues.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Issues
                    </a>
                    <a href="{{ route('admin.companies.index') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.companies.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Companies
                    </a>
                    <a href="{{ route('admin.anomaly-reasons.index') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.anomaly-reasons.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Anomaly Reasons
                    </a>
                    @foreach($menuRegistry->getItems('production') as $item)
                        <a href="{{ $item['url'] }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-slate-400 hover:text-white hover:bg-slate-700 transition-colors">
                            <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>{{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- ── STRUCTURE ─── --}}
            <div class="px-2">
                <div class="relative group">
                    <button @click="expandGroup('structure')"
                            class="flex items-center gap-3 w-full px-3 py-2.5 rounded-lg text-sm font-medium
                                   transition-colors text-slate-300 hover:bg-slate-700 hover:text-white"
                            :class="{
                                'justify-center !px-0':    collapsed && !mobileOpen,
                                'bg-slate-700/50 text-white': structure && (!collapsed || mobileOpen)
                            }">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        <span x-show="!collapsed || mobileOpen" x-cloak class="flex-1 text-left">Structure</span>
                        <svg x-show="!collapsed || mobileOpen" x-cloak
                             class="w-4 h-4 shrink-0 transition-transform" :class="{'rotate-180': structure}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <span x-show="collapsed && !mobileOpen" x-cloak
                          class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-slate-700
                                 text-white text-xs rounded-md whitespace-nowrap z-50 opacity-0
                                 group-hover:opacity-100 transition-opacity shadow-lg pointer-events-none">
                        Structure
                    </span>
                </div>
                <div x-show="structure && (!collapsed || mobileOpen)" x-cloak
                     x-transition:enter="transition-opacity ease-out duration-150"
                     x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity ease-in duration-100"
                     x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="mt-0.5 ml-4 space-y-0.5 border-l border-slate-700/60 pl-3">
                    <a href="{{ route('admin.factories.index') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.factories.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Factories
                    </a>
                    <a href="{{ route('admin.divisions.index') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.divisions.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Divisions
                    </a>
                    <a href="{{ route('admin.workstation-types.index') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.workstation-types.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Workstation Types
                    </a>
                    <a href="{{ route('admin.subassemblies.index') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.subassemblies.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Subassemblies
                    </a>
                    @foreach($menuRegistry->getItems('structure') as $item)
                        <a href="{{ $item['url'] }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-slate-400 hover:text-white hover:bg-slate-700 transition-colors">
                            <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>{{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- ── HR ─── --}}
            <div class="px-2">
                <div class="relative group">
                    <button @click="expandGroup('hr')"
                            class="flex items-center gap-3 w-full px-3 py-2.5 rounded-lg text-sm font-medium
                                   transition-colors text-slate-300 hover:bg-slate-700 hover:text-white"
                            :class="{
                                'justify-center !px-0':    collapsed && !mobileOpen,
                                'bg-slate-700/50 text-white': hr && (!collapsed || mobileOpen)
                            }">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span x-show="!collapsed || mobileOpen" x-cloak class="flex-1 text-left">HR</span>
                        <svg x-show="!collapsed || mobileOpen" x-cloak
                             class="w-4 h-4 shrink-0 transition-transform" :class="{'rotate-180': hr}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <span x-show="collapsed && !mobileOpen" x-cloak
                          class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-slate-700
                                 text-white text-xs rounded-md whitespace-nowrap z-50 opacity-0
                                 group-hover:opacity-100 transition-opacity shadow-lg pointer-events-none">
                        HR
                    </span>
                </div>
                <div x-show="hr && (!collapsed || mobileOpen)" x-cloak
                     x-transition:enter="transition-opacity ease-out duration-150"
                     x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity ease-in duration-100"
                     x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="mt-0.5 ml-4 space-y-0.5 border-l border-slate-700/60 pl-3">
                    <a href="{{ route('admin.workers.index') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.workers.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Workers
                    </a>
                    <a href="{{ route('admin.crews.index') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.crews.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Crews
                    </a>
                    <a href="{{ route('admin.skills.index') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.skills.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Skills
                    </a>
                    <a href="{{ route('admin.wage-groups.index') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.wage-groups.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Wage Groups
                    </a>
                    @foreach($menuRegistry->getItems('hr') as $item)
                        <a href="{{ $item['url'] }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-slate-400 hover:text-white hover:bg-slate-700 transition-colors">
                            <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>{{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- ── MAINTENANCE ─── --}}
            <div class="px-2">
                <div class="relative group">
                    <button @click="expandGroup('maintenance')"
                            class="flex items-center gap-3 w-full px-3 py-2.5 rounded-lg text-sm font-medium
                                   transition-colors text-slate-300 hover:bg-slate-700 hover:text-white"
                            :class="{
                                'justify-center !px-0':    collapsed && !mobileOpen,
                                'bg-slate-700/50 text-white': maintenance && (!collapsed || mobileOpen)
                            }">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span x-show="!collapsed || mobileOpen" x-cloak class="flex-1 text-left">Maintenance</span>
                        <svg x-show="!collapsed || mobileOpen" x-cloak
                             class="w-4 h-4 shrink-0 transition-transform" :class="{'rotate-180': maintenance}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <span x-show="collapsed && !mobileOpen" x-cloak
                          class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-slate-700
                                 text-white text-xs rounded-md whitespace-nowrap z-50 opacity-0
                                 group-hover:opacity-100 transition-opacity shadow-lg pointer-events-none">
                        Maintenance
                    </span>
                </div>
                <div x-show="maintenance && (!collapsed || mobileOpen)" x-cloak
                     x-transition:enter="transition-opacity ease-out duration-150"
                     x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity ease-in duration-100"
                     x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="mt-0.5 ml-4 space-y-0.5 border-l border-slate-700/60 pl-3">
                    <a href="{{ route('admin.maintenance-events.index') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.maintenance-events.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Events
                    </a>
                    <a href="{{ route('admin.tools.index') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.tools.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Tools
                    </a>
                    <a href="{{ route('admin.cost-sources.index') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.cost-sources.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Cost Sources
                    </a>
                    <a href="{{ route('admin.production-anomalies.index') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.production-anomalies.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Anomalies
                    </a>
                    @foreach($menuRegistry->getItems('maintenance') as $item)
                        <a href="{{ $item['url'] }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-slate-400 hover:text-white hover:bg-slate-700 transition-colors">
                            <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>{{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- ── ADMIN GROUP ─── --}}
            <div class="px-2">
                <div class="relative group">
                    <button @click="expandGroup('adminGroup')"
                            class="flex items-center gap-3 w-full px-3 py-2.5 rounded-lg text-sm font-medium
                                   transition-colors text-slate-300 hover:bg-slate-700 hover:text-white"
                            :class="{
                                'justify-center !px-0':    collapsed && !mobileOpen,
                                'bg-slate-700/50 text-white': adminGroup && (!collapsed || mobileOpen)
                            }">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        <span x-show="!collapsed || mobileOpen" x-cloak class="flex-1 text-left">Admin</span>
                        <svg x-show="!collapsed || mobileOpen" x-cloak
                             class="w-4 h-4 shrink-0 transition-transform" :class="{'rotate-180': adminGroup}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <span x-show="collapsed && !mobileOpen" x-cloak
                          class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-slate-700
                                 text-white text-xs rounded-md whitespace-nowrap z-50 opacity-0
                                 group-hover:opacity-100 transition-opacity shadow-lg pointer-events-none">
                        Admin
                    </span>
                </div>
                <div x-show="adminGroup && (!collapsed || mobileOpen)" x-cloak
                     x-transition:enter="transition-opacity ease-out duration-150"
                     x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity ease-in duration-100"
                     x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="mt-0.5 ml-4 space-y-0.5 border-l border-slate-700/60 pl-3">
                    <a href="{{ route('admin.users.index') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.users.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Users
                    </a>
                    <a href="{{ route('admin.reports') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.reports') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Reports
                    </a>
                    <a href="{{ route('admin.audit-logs') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.audit-logs') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Audit Logs
                    </a>
                    <a href="{{ route('admin.modules.index') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-colors {{ request()->routeIs('admin.modules.*') ? 'text-blue-400 font-medium' : 'text-slate-400 hover:text-white hover:bg-slate-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>Modules
                    </a>
                    @foreach($menuRegistry->getItems('admin') as $item)
                        <a href="{{ $item['url'] }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-slate-400 hover:text-white hover:bg-slate-700 transition-colors">
                            <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>{{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Module-registered extra groups --}}
            @foreach($menuRegistry->getGroups() as $group)
                @php $groupKey = 'mod_' . \Illuminate\Support\Str::slug($group['label']); @endphp
                <div class="px-2">
                    <button @click="$data[{{ json_encode($groupKey) }}] = !($data[{{ json_encode($groupKey) }}] ?? false)"
                            class="flex items-center gap-3 w-full px-3 py-2.5 rounded-lg text-sm font-medium
                                   transition-colors text-slate-300 hover:bg-slate-700 hover:text-white"
                            :class="{'justify-center !px-0': collapsed && !mobileOpen}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                        <span x-show="!collapsed || mobileOpen" x-cloak class="flex-1 text-left">{{ $group['label'] }}</span>
                    </button>
                    <div class="mt-0.5 ml-4 space-y-0.5 border-l border-slate-700/60 pl-3">
                        @foreach($group['items'] as $item)
                            <a href="{{ $item['url'] }}" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-slate-400 hover:text-white hover:bg-slate-700 transition-colors">
                                <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0 opacity-60"></span>{{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach

        @endhasrole

        @endauth
    </nav>

    {{-- ── Footer ────────────────────────────────────────────────── --}}
    <div class="border-t border-slate-700/60 shrink-0">
        {{-- Settings --}}
        @auth
        @php $a = request()->routeIs('settings.*'); @endphp
        <div class="relative group px-2 pt-2">
            <a href="{{ route('settings.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ $a ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}"
               :class="{'justify-center !px-0': collapsed && !mobileOpen}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span x-show="!collapsed || mobileOpen" x-cloak>Settings</span>
            </a>
            <span x-show="collapsed && !mobileOpen" x-cloak
                  class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-slate-700
                         text-white text-xs rounded-md whitespace-nowrap z-50 opacity-0
                         group-hover:opacity-100 transition-opacity shadow-lg pointer-events-none">
                Settings
            </span>
        </div>

        {{-- User info + logout --}}
        <div class="px-2 py-2">
            <div class="flex items-center gap-3 px-3 py-2 rounded-lg"
                 :class="{'justify-center': collapsed && !mobileOpen}">
                {{-- Avatar --}}
                <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center shrink-0 text-white text-sm font-bold">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                {{-- Name + role --}}
                <div x-show="!collapsed || mobileOpen" x-cloak class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-slate-400 truncate">{{ auth()->user()->roles->first()->name ?? 'User' }}</p>
                </div>
                {{-- Logout button --}}
                <form x-show="!collapsed || mobileOpen" x-cloak action="{{ route('logout') }}" method="POST" class="shrink-0">
                    @csrf
                    <button type="submit" title="Logout"
                            class="p-1.5 rounded-md text-slate-400 hover:text-red-400 hover:bg-slate-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
            </div>
            {{-- Collapsed logout --}}
            <div x-show="collapsed && !mobileOpen" x-cloak class="relative group px-0 mt-0.5">
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="flex items-center justify-center w-full py-2 rounded-lg text-slate-400
                                   hover:text-red-400 hover:bg-slate-700 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
                <span class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-slate-700
                             text-white text-xs rounded-md whitespace-nowrap z-50 opacity-0
                             group-hover:opacity-100 transition-opacity shadow-lg pointer-events-none">
                    Logout
                </span>
            </div>
        </div>
        @endauth

        {{-- Collapse toggle (desktop only) --}}
        <div class="hidden lg:flex border-t border-slate-700/60 px-2 py-2">
            <button @click="toggle()"
                    class="flex items-center justify-center w-full py-2 rounded-lg text-slate-400
                           hover:text-white hover:bg-slate-700 transition-colors"
                    :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                {{-- Left arrow when expanded → collapse; right arrow when collapsed → expand --}}
                <svg class="w-5 h-5 transition-transform" :class="{'rotate-180': collapsed}"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                </svg>
                <span x-show="!collapsed" x-cloak class="ml-2 text-sm">Collapse</span>
            </button>
        </div>
    </div>
</aside>
