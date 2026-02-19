@extends('layouts.app')

@section('title', 'Edit Work Order')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Edit Work Order</h1>
            <p class="text-gray-600 mt-1 font-mono">{{ $workOrder->order_no }}</p>
        </div>
        <a href="{{ route('admin.work-orders.show', $workOrder) }}" class="btn-touch btn-secondary">← Back</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.work-orders.update', $workOrder) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="form-label">Order Number <span class="text-red-500">*</span></label>
                    <input type="text" name="order_no" value="{{ old('order_no', $workOrder->order_no) }}"
                           class="form-input w-full" required maxlength="100">
                    @error('order_no') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Status <span class="text-red-500">*</span></label>
                    <select name="status" class="form-input w-full" required>
                        @foreach(['PENDING','ACCEPTED','IN_PROGRESS','PAUSED','BLOCKED','DONE','REJECTED','CANCELLED'] as $s)
                            <option value="{{ $s }}" @selected(old('status', $workOrder->status) === $s)>{{ str_replace('_', ' ', $s) }}</option>
                        @endforeach
                    </select>
                    @error('status') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Production Line</label>
                    <select name="line_id" class="form-input w-full">
                        <option value="">— Not assigned —</option>
                        @foreach($lines as $line)
                            <option value="{{ $line->id }}" @selected(old('line_id', $workOrder->line_id) == $line->id)>{{ $line->name }}</option>
                        @endforeach
                    </select>
                    @error('line_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Product Type</label>
                    <select name="product_type_id" class="form-input w-full">
                        <option value="">— Not assigned —</option>
                        @foreach($productTypes as $pt)
                            <option value="{{ $pt->id }}" @selected(old('product_type_id', $workOrder->product_type_id) == $pt->id)>{{ $pt->name }}</option>
                        @endforeach
                    </select>
                    @error('product_type_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Planned Quantity <span class="text-red-500">*</span></label>
                    <input type="number" name="planned_qty" value="{{ old('planned_qty', $workOrder->planned_qty) }}"
                           class="form-input w-full" step="0.01" min="0.01" required>
                    @error('planned_qty') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Priority</label>
                    <input type="number" name="priority" value="{{ old('priority', $workOrder->priority) }}"
                           class="form-input w-full" min="0" max="100">
                    @error('priority') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date"
                           value="{{ old('due_date', $workOrder->due_date?->format('Y-m-d')) }}"
                           class="form-input w-full">
                    @error('due_date') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="3" class="form-input w-full" maxlength="2000">{{ old('description', $workOrder->description) }}</textarea>
                    @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex gap-3 justify-end mt-6">
                <a href="{{ route('admin.work-orders.show', $workOrder) }}" class="btn-touch btn-secondary">Cancel</a>
                <button type="submit" class="btn-touch btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection
