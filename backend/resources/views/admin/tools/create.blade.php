@extends('layouts.app')

@section('title', 'New Tool')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">New Tool</h1>
            <p class="text-gray-600 mt-1">Register a tool or machine</p>
        </div>
        <a href="{{ route('admin.tools.index') }}" class="btn-touch btn-secondary">← Back</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.tools.store') }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Code <span class="text-red-500">*</span></label>
                    <input type="text" name="code" value="{{ old('code') }}"
                           class="form-input w-full" placeholder="e.g. TOOL-001" required maxlength="50">
                    @error('code') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}"
                           class="form-input w-full" placeholder="e.g. Lathe #3" required maxlength="200">
                    @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Workstation Type</label>
                    <select name="workstation_type_id" class="form-input w-full">
                        <option value="">— Not assigned —</option>
                        @foreach($workstationTypes as $wt)
                            <option value="{{ $wt->id }}" @selected(old('workstation_type_id') == $wt->id)>{{ $wt->name }}</option>
                        @endforeach
                    </select>
                    @error('workstation_type_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Status <span class="text-red-500">*</span></label>
                    <select name="status" class="form-input w-full" required>
                        <option value="available" @selected(old('status', 'available') === 'available')>Available</option>
                        <option value="in_use" @selected(old('status') === 'in_use')>In Use</option>
                        <option value="maintenance" @selected(old('status') === 'maintenance')>Maintenance</option>
                        <option value="retired" @selected(old('status') === 'retired')>Retired</option>
                    </select>
                    @error('status') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">Next Service Date</label>
                    <input type="date" name="next_service_at" value="{{ old('next_service_at') }}" class="form-input w-full">
                    @error('next_service_at') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="3" class="form-input w-full" maxlength="2000">{{ old('description') }}</textarea>
                    @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex gap-3 justify-end mt-6">
                <a href="{{ route('admin.tools.index') }}" class="btn-touch btn-secondary">Cancel</a>
                <button type="submit" class="btn-touch btn-primary">Create Tool</button>
            </div>
        </form>
    </div>
</div>
@endsection
