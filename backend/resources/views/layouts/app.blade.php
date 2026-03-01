<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'OpenMES') }} — @yield('title', 'Manufacturing Execution System')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-100 overflow-hidden">

<div class="flex h-screen"
     x-data="{
         collapsed: localStorage.getItem('sb') === '1',
         mobileOpen: false,
         orders: false,
         production: false,
         linesGroup: false,
         structure: false,
         hr: false,
         maintenance: false,
         adminGroup: false,
         toggle() {
             this.collapsed = !this.collapsed;
             if (this.collapsed) {
                 this.orders = this.production = this.linesGroup = this.structure =
                 this.hr = this.maintenance = this.adminGroup = false;
             }
             localStorage.setItem('sb', this.collapsed ? '1' : '0');
         },
         expandGroup(g) {
             if (this.collapsed) {
                 this.collapsed = false;
                 localStorage.setItem('sb', '0');
             }
             this[g] = !this[g];
         }
     }"
     x-init="
         @auth @hasrole('Admin')
         if (!collapsed) {
             @if(request()->routeIs('admin.work-orders.*', 'admin.csv-import'))
                 orders = true;
             @endif
             @if(request()->routeIs('admin.product-types.*', 'admin.lines.*', 'admin.line-statuses.*', 'admin.issues.*', 'admin.companies.*', 'admin.anomaly-reasons.*'))
                 production = true;
             @endif
             @if(request()->routeIs('admin.lines.*', 'admin.line-statuses.*'))
                 linesGroup = true;
             @endif
             @if(request()->routeIs('admin.factories.*', 'admin.divisions.*', 'admin.workstation-types.*', 'admin.subassemblies.*'))
                 structure = true;
             @endif
             @if(request()->routeIs('admin.workers.*', 'admin.crews.*', 'admin.skills.*', 'admin.wage-groups.*'))
                 hr = true;
             @endif
             @if(request()->routeIs('admin.maintenance-events.*', 'admin.tools.*', 'admin.cost-sources.*', 'admin.production-anomalies.*'))
                 maintenance = true;
             @endif
             @if(request()->routeIs('admin.users.*', 'admin.reports', 'admin.audit-logs', 'admin.modules.*'))
                 adminGroup = true;
             @endif
         }
         @endhasrole @endauth
     "
>
    {{-- Sidebar --}}
    @include('layouts.components.sidebar')

    {{-- Mobile backdrop --}}
    <div x-show="mobileOpen" x-cloak
         @click="mobileOpen = false"
         x-transition:enter="transition-opacity duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50 z-30 lg:hidden">
    </div>

    {{-- Main column --}}
    <div class="flex flex-col flex-1 min-w-0 overflow-hidden">

        {{-- Mobile top bar --}}
        <header class="lg:hidden shrink-0 flex items-center gap-3 h-14 px-4 bg-white border-b border-gray-200 shadow-sm z-20">
            <button @click="mobileOpen = true"
                    class="p-2 rounded-md text-gray-600 hover:bg-gray-100 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <a href="@auth
                    @if(auth()->user()->hasRole('Admin')){{ route('admin.dashboard') }}
                    @elseif(auth()->user()->hasRole('Supervisor')){{ route('supervisor.dashboard') }}
                    @else{{ route('operator.select-line') }}
                    @endif
                @else{{ route('login') }}@endauth">
                <img src="/logo_open_mes.png" alt="OpenMES" class="h-7">
            </a>
        </header>

        {{-- Scrollable content --}}
        <main class="flex-1 overflow-auto p-4 md:p-6 lg:p-8">
            @if(session('success'))
                <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg" role="alert">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg" role="alert">
                    {{ session('error') }}
                </div>
            @endif
            @if($errors->any())
                <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg" role="alert">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<style>[x-cloak]{display:none!important}</style>
@livewireScripts
@stack('scripts')
<script>
(function(){
    var t=document.createElement('div');
    t.style.cssText='position:fixed;padding:3px 10px;background:#111827;color:#fff;font-size:11px;border-radius:5px;white-space:nowrap;z-index:9999;pointer-events:none;opacity:0;transition:opacity .15s;';
    document.body.appendChild(t);
    document.addEventListener('mouseover',function(e){
        var el=e.target.closest('[data-tip]');
        if(!el){t.style.opacity='0';return;}
        t.textContent=el.dataset.tip;
        var r=el.getBoundingClientRect();
        t.style.left=(r.left+r.width/2)+'px';
        t.style.top=(r.top-6)+'px';
        t.style.transform='translate(-50%,-100%)';
        t.style.opacity='1';
    });
    document.addEventListener('mouseout',function(e){
        if(e.target.closest('[data-tip]'))t.style.opacity='0';
    });
})();
</script>
</body>
</html>
