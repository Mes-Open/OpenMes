@extends('layouts.app')

@section('title', 'CSV Import')

@section('content')
<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">CSV Import</h1>
        <p class="text-gray-600 mt-2">Import work orders and other data from CSV files</p>
    </div>

    <!-- Info Card -->
    <div class="card bg-blue-50 border border-blue-200 mb-6">
        <div class="flex items-start gap-4">
            <div class="bg-blue-100 rounded-full p-3">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-lg font-bold text-blue-900 mb-2">CSV Import Feature</h3>
                <p class="text-blue-800 mb-3">
                    The CSV import functionality allows you to bulk import work orders, products, and other entities into the system.
                </p>
                <div class="text-sm text-blue-700">
                    <p><strong>Supported Import Types:</strong></p>
                    <ul class="list-disc list-inside ml-4 mt-2">
                        <li>Work Orders</li>
                        <li>Product Types</li>
                        <li>Lines and Workstations</li>
                        <li>Users and Assignments</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Placeholder Upload Section -->
    <div class="card">
        <h2 class="text-xl font-bold text-gray-800 mb-6">Upload CSV File</h2>

        <div class="border-2 border-dashed border-gray-300 rounded-lg p-12 text-center bg-gray-50">
            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">CSV Import (Coming Soon)</h3>
            <p class="text-gray-600 mb-6">
                The CSV import wizard is currently under development. For now, you can continue using the API endpoints or manually enter data.
            </p>

            <div class="max-w-md mx-auto text-left bg-white p-4 rounded-lg border border-gray-200">
                <p class="text-sm font-medium text-gray-800 mb-2">Alternative Methods:</p>
                <ul class="text-sm text-gray-600 space-y-2">
                    <li class="flex items-center">
                        <svg class="w-4 h-4 mr-2 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        Use the API endpoint: <code class="bg-gray-100 px-2 py-1 rounded">/api/v1/csv-import/upload</code>
                    </li>
                    <li class="flex items-center">
                        <svg class="w-4 h-4 mr-2 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        Contact administrator for manual import assistance
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- CSV Format Guide -->
    <div class="card mt-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">CSV Format Guide</h2>

        <div class="prose max-w-none">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Work Orders CSV Format</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left">Column</th>
                            <th class="px-4 py-2 text-left">Required</th>
                            <th class="px-4 py-2 text-left">Description</th>
                            <th class="px-4 py-2 text-left">Example</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td class="px-4 py-2 font-mono">order_no</td>
                            <td class="px-4 py-2">Yes</td>
                            <td class="px-4 py-2">Unique order number</td>
                            <td class="px-4 py-2 font-mono">WO-2024-001</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 font-mono">line_code</td>
                            <td class="px-4 py-2">Yes</td>
                            <td class="px-4 py-2">Production line code</td>
                            <td class="px-4 py-2 font-mono">LINE-A</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 font-mono">product_code</td>
                            <td class="px-4 py-2">Yes</td>
                            <td class="px-4 py-2">Product type code</td>
                            <td class="px-4 py-2 font-mono">PROD-001</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 font-mono">planned_qty</td>
                            <td class="px-4 py-2">Yes</td>
                            <td class="px-4 py-2">Planned quantity</td>
                            <td class="px-4 py-2 font-mono">1000</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 font-mono">priority</td>
                            <td class="px-4 py-2">No</td>
                            <td class="px-4 py-2">Priority (1-10)</td>
                            <td class="px-4 py-2 font-mono">5</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 font-mono">due_date</td>
                            <td class="px-4 py-2">No</td>
                            <td class="px-4 py-2">Due date (YYYY-MM-DD)</td>
                            <td class="px-4 py-2 font-mono">2024-12-31</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-700">
                    <strong>Example CSV:</strong>
                </p>
                <pre class="mt-2 p-3 bg-gray-800 text-green-400 rounded text-xs overflow-x-auto">order_no,line_code,product_code,planned_qty,priority,due_date
WO-2024-001,LINE-A,PROD-001,1000,5,2024-12-31
WO-2024-002,LINE-B,PROD-002,500,7,2024-12-25</pre>
            </div>
        </div>
    </div>
</div>
@endsection
