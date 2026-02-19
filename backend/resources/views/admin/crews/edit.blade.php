@extends('layouts.app')

@section('title', 'Edit Crew')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Edit Crew</h1>
            <p class="text-gray-600 mt-1 font-mono">{{ $crew->code }}</p>
        </div>
        <a href="{{ route('admin.crews.index') }}" class="btn-touch btn-secondary">← Back</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.crews.update', $crew) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Code <span class="text-red-500">*</span></label>
                    <input type="text" name="code" value="{{ old('code', $crew->code) }}"
                           class="form-input w-full" required maxlength="50">
                    @error('code') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $crew->name) }}"
                           class="form-input w-full" required maxlength="200">
                    @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Leader</label>
                    <select name="leader_id" class="form-input w-full">
                        <option value="">— No leader —</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected(old('leader_id', $crew->leader_id) == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('leader_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Division</label>
                    <select name="division_id" class="form-input w-full">
                        <option value="">— No division —</option>
                        @foreach($divisions as $division)
                            <option value="{{ $division->id }}" @selected(old('division_id', $crew->division_id) == $division->id)>{{ $division->name }}</option>
                        @endforeach
                    </select>
                    @error('division_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="3" class="form-input w-full" maxlength="2000">{{ old('description', $crew->description) }}</textarea>
                    @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $crew->is_active) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="form-label mb-0">Active</span>
                    </label>
                </div>
            </div>

            <div class="flex gap-3 justify-end mt-6">
                <a href="{{ route('admin.crews.index') }}" class="btn-touch btn-secondary">Cancel</a>
                <button type="submit" class="btn-touch btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection
