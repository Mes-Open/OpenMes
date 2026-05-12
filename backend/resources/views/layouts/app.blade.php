<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'OpenMES') }} — @yield('title', 'Manufacturing Execution System')</title>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1e40af">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="OpenMES">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/icon-512.png">
    <link rel="apple-touch-icon" href="/icon-192.png">
    <script>
        /* Apply dark class immediately to avoid flash */
        (function(){
            var t = localStorage.getItem('theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if(app()->environment('production'))
    <script type="text/javascript">
        (function(c,l,a,r,i,t,y){
            c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
            t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
            y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
        })(window, document, "clarity", "script", "w64eka00as");
    </script>
    @endif
</head>
<body class="bg-gray-100 overflow-hidden dark:bg-gray-900">

<div class="flex h-screen"
     x-data="{
         collapsed: localStorage.getItem('sb') === '1',
         mobileOpen: false,
         darkMode: document.documentElement.classList.contains('dark'),
         toggleDark() {
             this.darkMode = !this.darkMode;
             document.documentElement.classList.toggle('dark', this.darkMode);
             localStorage.setItem('theme', this.darkMode ? 'dark' : 'light');
         },
         orders: false,
         production: false,
         linesGroup: false,
         structure: false,
         hr: false,
         maintenance: false,
         connectivity: false,
         adminGroup: false,
         modulesGroup: false,
         toggle() {
             const sb = this.$refs.sidebar;
             if (sb) {
                 sb.style.transition = 'width 300ms ease-in-out, transform 300ms ease-in-out';
                 setTimeout(() => { sb.style.transition = ''; }, 350);
             }
             this.collapsed = !this.collapsed;
             if (this.collapsed) {
                 this.orders = this.production = this.linesGroup = this.structure =
                 this.hr = this.maintenance = this.connectivity = this.adminGroup = this.modulesGroup = false;
             }
             localStorage.setItem('sb', this.collapsed ? '1' : '0');
         },
         expandGroup(g) {
             if (this.collapsed) {
                 const sb = this.$refs.sidebar;
                 if (sb) {
                     sb.style.transition = 'width 300ms ease-in-out, transform 300ms ease-in-out';
                     setTimeout(() => { sb.style.transition = ''; }, 350);
                 }
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
             @if(request()->routeIs('admin.connectivity.*'))
                 connectivity = true;
             @endif
             @if(request()->routeIs('admin.users.*', 'admin.reports', 'admin.audit-logs', 'admin.modules.*'))
                 adminGroup = true;
             @endif
             @if(request()->routeIs('admin.modules.*'))
                 modulesGroup = true;
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
            <button @click="$refs.sidebar.style.transition = 'transform 300ms ease-in-out'; setTimeout(() => { $refs.sidebar.style.transition = '' }, 350); mobileOpen = true"
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
            @auth @if(auth()->user()->hasAnyRole(['Admin','Supervisor']))
            <a href="{{ route('admin.alerts') }}" class="relative ml-auto p-2 text-gray-600 hover:text-red-600 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                @php try { $__ac = \App\Http\Controllers\Web\Admin\AlertController::totalCount(); } catch(\Throwable $e) { $__ac = 0; } @endphp
                @if($__ac > 0)
                    <span class="absolute top-1 right-1 flex items-center justify-center w-4 h-4
                                 rounded-full bg-red-500 text-white text-[10px] font-bold">
                        {{ $__ac > 9 ? '9+' : $__ac }}
                    </span>
                @endif
            </a>
            @endif @endauth
        </header>

        {{-- Update banner --}}
        @include('layouts.components.update-banner')

        @if(!empty($demoExpiresAt))
        <div
            x-data="{
                expires: {{ $demoExpiresAt->timestamp * 1000 }},
                remaining: '',
                urgent: false,
                init() {
                    this.tick();
                    setInterval(() => this.tick(), 1000);
                },
                tick() {
                    const diff = this.expires - Date.now();
                    if (diff <= 0) {
                        this.remaining = 'Account expired — refresh to log out';
                        this.urgent = true;
                        return;
                    }
                    const h = Math.floor(diff / 3600000);
                    const m = Math.floor((diff % 3600000) / 60000);
                    const s = Math.floor((diff % 60000) / 1000);
                    this.remaining = (h > 0 ? h + 'h ' : '') + m + 'm ' + s + 's';
                    this.urgent = diff < 600000;
                }
            }"
            :class="urgent ? 'bg-red-600' : 'bg-amber-500'"
            class="flex items-center justify-center gap-2 px-4 py-1.5 text-sm font-medium text-white"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Demo account — will be deleted in: <strong x-text="remaining"></strong></span>
        </div>
        @endif

        {{-- Clock (Europe/Warsaw) --}}
        <div class="hidden lg:flex items-center justify-end px-4 py-1.5 shrink-0"
             x-data="{
                 time: '',
                 date: '',
                 init() {
                     this.tick();
                     setInterval(() => this.tick(), 1000);
                 },
                 tick() {
                     const now = new Date();
                     this.time = now.toLocaleTimeString('pl-PL', { timeZone: 'Europe/Warsaw', hour: '2-digit', minute: '2-digit', second: '2-digit' });
                     this.date = now.toLocaleDateString('pl-PL', { timeZone: 'Europe/Warsaw', weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
                 }
             }">
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span x-text="date"></span>
                <span class="font-mono font-semibold text-gray-700 dark:text-gray-200" x-text="time"></span>
            </div>
        </div>

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
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
</script>
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
@auth
@if(auth()->user()->hasRole('Admin') && \App\Http\Controllers\Web\OnboardingController::shouldShowWizard())
<div x-data="{ open: !sessionStorage.getItem('wizard_dismissed') }" x-show="open" x-cloak
     class="fixed inset-0 z-[100] flex items-center justify-center p-4"
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
    <div class="fixed inset-0 bg-black/50" @click="open = false; sessionStorage.setItem('wizard_dismissed','1')"></div>
    <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-md w-full p-8 text-center"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
        <div class="w-14 h-14 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
        </div>
        <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Welcome to OpenMES!</h3>
        <p class="text-gray-600 dark:text-gray-300 mb-6">Looks like this is a fresh installation. Would you like to run the setup wizard? It takes about 2 minutes.</p>
        <div class="flex flex-col gap-3">
            <a href="{{ route('onboarding.step1') }}" class="btn-touch btn-primary w-full">Start Setup Wizard</a>
            <button @click="open = false; sessionStorage.setItem('wizard_dismissed','1')" class="btn-touch btn-secondary w-full">I'll do it later</button>
        </div>
    </div>
</div>
@endif
@endauth
</body>
</html>
