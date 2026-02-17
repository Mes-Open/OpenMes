<aside class="w-64 bg-white border-r border-gray-200 shadow-sm hidden lg:block">
    <div class="p-4">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Navigation</h2>

        <nav class="space-y-2">
            @hasrole('Operator')
                <a href="{{ route('operator.select-line') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-md {{ request()->routeIs('operator.select-line') ? 'bg-blue-100 text-blue-700' : '' }}">
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Select Line
                    </span>
                </a>

                @if(session('selected_line_id'))
                    <a href="{{ route('operator.queue') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-md {{ request()->routeIs('operator.queue') ? 'bg-blue-100 text-blue-700' : '' }}">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                            Work Order Queue
                        </span>
                    </a>
                @endif
            @endhasrole

            @hasrole('Supervisor')
                <a href="{{ route('supervisor.dashboard') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-md {{ request()->routeIs('supervisor.dashboard') ? 'bg-blue-100 text-blue-700' : '' }}">
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Dashboard
                    </span>
                </a>
            @endhasrole

            @hasrole('Admin')
                <a href="{{ route('admin.csv-import') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-md {{ request()->routeIs('admin.csv-import') ? 'bg-blue-100 text-blue-700' : '' }}">
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        CSV Import
                    </span>
                </a>

                <a href="{{ route('admin.audit-logs') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-md {{ request()->routeIs('admin.audit-logs') ? 'bg-blue-100 text-blue-700' : '' }}">
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Audit Logs
                    </span>
                </a>
            @endhasrole

            <!-- Divider -->
            <div class="border-t border-gray-200 my-4"></div>

            <!-- Settings -->
            <a href="{{ route('change-password') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-md {{ request()->routeIs('change-password') ? 'bg-blue-100 text-blue-700' : '' }}">
                <span class="flex items-center">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                    Change Password
                </span>
            </a>
        </nav>
    </div>
</aside>
