@extends('layouts.app')

@section('title', 'New Worker')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Workers', 'url' => route('admin.workers.index')],
    ['label' => 'New Worker', 'url' => null],
]" />

<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">New Worker</h1>
            <p class="text-gray-600 mt-1">Register a production worker</p>
        </div>
        <a href="{{ route('admin.workers.index') }}" class="btn-touch btn-secondary">← Back</a>
    </div>

    <div
        x-data="{
            skillRows: @js(
                $skills->map(fn($s) => [
                    'id'      => $s->id,
                    'code'    => $s->code,
                    'name'    => $s->name,
                    'enabled' => false,
                    'level'   => 1,
                ])
            )
        }"
    >
        <form method="POST" action="{{ route('admin.workers.store') }}">
            @csrf

            <!-- Basic info -->
            <div class="card mb-4">
                <h2 class="text-lg font-semibold text-gray-700 mb-4">Basic Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Code <span class="text-red-500">*</span></label>
                        <input type="text" name="code" value="{{ old('code') }}"
                               class="form-input w-full" placeholder="e.g. WRK-001" required maxlength="50">
                        @error('code') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}"
                               class="form-input w-full" placeholder="Full name" required maxlength="200">
                        @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                               class="form-input w-full" placeholder="worker@company.com" maxlength="200">
                        @error('email') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone') }}"
                               class="form-input w-full" placeholder="+48 123 456 789" maxlength="50">
                        @error('phone') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Crew</label>
                        <select name="crew_id" class="form-input w-full">
                            <option value="">— No crew —</option>
                            @foreach($crews as $crew)
                                <option value="{{ $crew->id }}" @selected(old('crew_id') == $crew->id)>{{ $crew->name }}</option>
                            @endforeach
                        </select>
                        @error('crew_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Wage Group</label>
                        <select name="wage_group_id" class="form-input w-full">
                            <option value="">— No wage group —</option>
                            @foreach($wageGroups as $wg)
                                <option value="{{ $wg->id }}" @selected(old('wage_group_id') == $wg->id)>{{ $wg->name }}</option>
                            @endforeach
                        </select>
                        @error('wage_group_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="form-label mb-0">Active</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Skills section -->
            <div class="card mb-4">
                <h2 class="text-lg font-semibold text-gray-700 mb-1">Skills</h2>
                <p class="text-sm text-gray-500 mb-4">Toggle skills and set the proficiency level for each.</p>

                <template x-if="skillRows.length === 0">
                    <p class="text-sm text-gray-500 italic">No skills defined yet. <a href="{{ route('admin.skills.create') }}" class="text-blue-600 hover:underline">Add skills</a> first.</p>
                </template>

                <div class="divide-y divide-gray-100">
                    <template x-for="(row, index) in skillRows" :key="row.id">
                        <div class="flex items-center gap-3 py-2">
                            <label class="flex items-center gap-2 flex-1 cursor-pointer">
                                <input type="checkbox"
                                       :name="row.enabled ? 'skills[' + index + '][id]' : ''"
                                       :value="row.id"
                                       x-model="row.enabled"
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-sm font-medium text-gray-800" x-text="row.name"></span>
                                <span class="text-xs text-gray-400 font-mono" x-text="row.code"></span>
                            </label>
                            <template x-if="row.enabled">
                                <div class="flex items-center gap-2">
                                    <input type="hidden" :name="'skills[' + index + '][id]'" :value="row.id">
                                    <select :name="'skills[' + index + '][level]'" x-model="row.level"
                                            class="form-input py-1 text-sm">
                                        <option value="1">Basic</option>
                                        <option value="2">Intermediate</option>
                                        <option value="3">Expert</option>
                                    </select>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            <div class="flex gap-3 justify-end">
                <a href="{{ route('admin.workers.index') }}" class="btn-touch btn-secondary">Cancel</a>
                <button type="submit" class="btn-touch btn-primary">Create Worker</button>
            </div>
        </form>
    </div>
</div>
@endsection
