@extends('layouts.app')

@section('title', 'Edit Subassembly')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Edit Subassembly</h1>
            <p class="text-gray-600 mt-1 font-mono">{{ $subassembly->code }}</p>
        </div>
        <a href="{{ route('admin.subassemblies.index') }}" class="btn-touch btn-secondary">← Back</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.subassemblies.update', $subassembly) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Code <span class="text-red-500">*</span></label>
                    <input type="text" name="code" value="{{ old('code', $subassembly->code) }}"
                           class="form-input w-full" required maxlength="50">
                    @error('code') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $subassembly->name) }}"
                           class="form-input w-full" required maxlength="200">
                    @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">Product Type <span class="text-gray-400 text-xs">(optional)</span></label>
                    <select name="product_type_id" class="form-input w-full">
                        <option value="">— Not assigned —</option>
                        @foreach($productTypes as $pt)
                            <option value="{{ $pt->id }}" @selected(old('product_type_id', $subassembly->product_type_id) == $pt->id)>{{ $pt->name }}</option>
                        @endforeach
                    </select>
                    @error('product_type_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="3" class="form-input w-full" maxlength="2000">{{ old('description', $subassembly->description) }}</textarea>
                    @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $subassembly->is_active) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="form-label mb-0">Active</span>
                    </label>
                </div>
            </div>

            <div class="flex gap-3 justify-end mt-6">
                <a href="{{ route('admin.subassemblies.index') }}" class="btn-touch btn-secondary">Cancel</a>
                <button type="submit" class="btn-touch btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection
