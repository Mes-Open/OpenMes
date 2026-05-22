@extends('layouts.app')

@section('title', __('Personnel Class') . ' — ' . $personnelClass->name)

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Personnel Classes'), 'url' => route('admin.personnel-classes.index')],
    ['label' => $personnelClass->name, 'url' => null],
]" />

<div class="max-w-5xl mx-auto">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
        <div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-mono text-sm text-gray-500">{{ $personnelClass->code }}</span>
                @if($personnelClass->is_active)
                    <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">{{ __('Active') }}</span>
                @else
                    <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ __('Inactive') }}</span>
                @endif
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mt-1">{{ $personnelClass->name }}</h1>
            @if($personnelClass->description)
                <p class="text-gray-600 mt-1">{{ $personnelClass->description }}</p>
            @endif
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.personnel-classes.edit', $personnelClass) }}" class="btn-touch btn-secondary">
                {{ __('Edit') }}
            </a>
            <form method="POST" action="{{ route('admin.personnel-classes.destroy', $personnelClass) }}" class="inline"
                  onsubmit="return confirm('{{ __('Delete this personnel class?') }}');">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="btn-touch bg-red-50 text-red-700 hover:bg-red-100">
                    {{ __('Delete') }}
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="card mb-4 border-l-4 border-green-400 bg-green-50">
            <p class="text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif
    @if(session('error'))
        <div class="card mb-4 border-l-4 border-red-400 bg-red-50">
            <p class="text-sm text-red-700">{{ session('error') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-4">
            <section class="card">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('Required skills') }}</h2>
                @if($requiredSkills->isEmpty())
                    <p class="text-sm text-gray-400">{{ __('No required skills configured.') }}</p>
                @else
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500 border-b">
                                <th class="py-2 pr-3">{{ __('Skill') }}</th>
                                <th class="py-2 pr-3">{{ __('Code') }}</th>
                                <th class="py-2 pr-3">{{ __('Minimum cert level') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($requiredSkills as $skill)
                                <tr>
                                    <td class="py-2 pr-3 text-gray-800">{{ $skill->name }}</td>
                                    <td class="py-2 pr-3 font-mono text-xs text-gray-600">{{ $skill->code }}</td>
                                    <td class="py-2 pr-3">
                                        <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 text-xs font-medium">
                                            {{ ucfirst($reqLevels[$skill->id] ?? 'operator') }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            <section class="card">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('Workers in this class') }}</h2>
                @if($workers->isEmpty())
                    <p class="text-sm text-gray-400 italic">{{ __('No workers assigned yet.') }}</p>
                @else
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500 border-b">
                                <th class="py-2 pr-3">{{ __('Code') }}</th>
                                <th class="py-2 pr-3">{{ __('Name') }}</th>
                                <th class="py-2 pr-3">{{ __('Qualified') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($workers as $worker)
                                @php $ok = $personnelClass->workerMeetsRequirements($worker); @endphp
                                <tr>
                                    <td class="py-2 pr-3 font-mono text-xs text-gray-600">{{ $worker->code }}</td>
                                    <td class="py-2 pr-3">
                                        <a href="{{ route('admin.workers.show', $worker) }}" class="text-blue-600 hover:underline">{{ $worker->name }}</a>
                                    </td>
                                    <td class="py-2 pr-3">
                                        @if($ok)
                                            <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">{{ __('Yes') }}</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-800">{{ __('Gap') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        </div>

        <div class="space-y-4">
            <section class="card">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('Metadata') }}</h2>
                <ul class="space-y-2 text-sm">
                    <li class="flex justify-between gap-2">
                        <span class="text-gray-500">{{ __('Workers') }}</span>
                        <span class="font-medium text-gray-800">{{ $workers->count() }}</span>
                    </li>
                    <li class="flex justify-between gap-2">
                        <span class="text-gray-500">{{ __('Required skills') }}</span>
                        <span class="font-medium text-gray-800">{{ $requiredSkills->count() }}</span>
                    </li>
                    <li class="flex justify-between gap-2">
                        <span class="text-gray-500">{{ __('Created') }}</span>
                        <span class="text-gray-700">{{ $personnelClass->created_at?->translatedFormat('d M Y') }}</span>
                    </li>
                    <li class="flex justify-between gap-2">
                        <span class="text-gray-500">{{ __('Updated') }}</span>
                        <span class="text-gray-700">{{ $personnelClass->updated_at?->diffForHumans() }}</span>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</div>
@endsection
