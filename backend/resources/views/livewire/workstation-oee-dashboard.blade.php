<div wire:poll.10s="refreshOee" class="p-4 bg-white rounded-lg shadow-md border border-gray-200">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-bold text-gray-800">Shop-Floor Real-Time OEE</h3>
        <span class="px-2 py-1 text-xs font-semibold text-green-700 bg-green-100 rounded-full">LIVE</span>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="p-3 bg-blue-50 rounded-lg text-center">
            <p class="text-sm text-blue-600 font-medium">Availability</p>
            <p class="text-2xl font-black text-blue-900">{{ number_format($oeeData['availability'] * 100, 1) }}%</p>
        </div>
        <div class="p-3 bg-purple-50 rounded-lg text-center">
            <p class="text-sm text-purple-600 font-medium">Performance</p>
            <p class="text-2xl font-black text-purple-900">{{ number_format($oeeData['performance'] * 100, 1) }}%</p>
        </div>
        <div class="p-3 bg-indigo-50 rounded-lg text-center">
            <p class="text-sm text-indigo-600 font-medium">Quality</p>
            <p class="text-2xl font-black text-indigo-900">{{ number_format($oeeData['quality'] * 100, 1) }}%</p>
        </div>
        <div class="p-3 bg-green-50 rounded-lg text-center border-2 border-green-200">
            <p class="text-sm text-green-600 font-medium">OEE</p>
            <p class="text-2xl font-black text-green-900">{{ number_format($oeeData['oee'] * 100, 1) }}%</p>
        </div>
    </div>

    <div class="mt-4 flex justify-between items-center text-xs text-gray-500">
        <span>Last calculated: {{ \Carbon\Carbon::parse($oeeData['calculated_at'])->format('H:i:s') }}</span>
        <span>Auto-refreshing every 10s</span>
    </div>
</div>
