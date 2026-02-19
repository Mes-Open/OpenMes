@php
$classes = match($status) {
    'PENDING'     => 'bg-gray-100 text-gray-700',
    'ACCEPTED'    => 'bg-indigo-100 text-indigo-700',
    'IN_PROGRESS' => 'bg-blue-100 text-blue-700',
    'PAUSED'      => 'bg-yellow-100 text-yellow-800',
    'BLOCKED'     => 'bg-red-100 text-red-700',
    'DONE'        => 'bg-green-100 text-green-700',
    'REJECTED'    => 'bg-pink-100 text-pink-700',
    'CANCELLED'   => 'bg-gray-100 text-gray-400',
    default       => 'bg-gray-100 text-gray-600',
};
@endphp
<span class="px-2 py-1 rounded-full text-xs font-medium {{ $classes }}">
    {{ str_replace('_', ' ', $status) }}
</span>
