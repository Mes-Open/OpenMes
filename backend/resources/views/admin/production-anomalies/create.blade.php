@extends('layouts.app')

@section('title', 'Record Production Anomaly')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Record Production Anomaly</h1>
            <p class="text-gray-600 mt-1">Log a deviation from the production plan</p>
        </div>
        <a href="{{ route('admin.production-anomalies.index') }}" class="btn-touch btn-secondary">← Back</a>
    </div>

    <div
        class="card"
        x-data="{
            selectedWorkOrderId: '{{ old('work_order_id') }}',
            batches: @js($batches ?? []),
            get filteredBatches() {
                if (!this.selectedWorkOrderId) return [];
                return this.batches.filter(b => String(b.work_order_id) === String(this.selectedWorkOrderId));
            }
        }"
    >
        <form method="POST" action="{{ route('admin.production-anomalies.store') }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="form-label">Work Order <span class="text-red-500">*</span></label>
                    <select name="work_order_id" class="form-input w-full" required
                            x-model="selectedWorkOrderId">
                        <option value="">— Select work order —</option>
                        @foreach($workOrders as $wo)
                            <option value="{{ $wo->id }}">{{ $wo->order_no }} — {{ $wo->product_name }}</option>
                        @endforeach
                    </select>
                    @error('work_order_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">Batch <span class="text-gray-400 text-xs">(optional)</span></label>
                    <select name="batch_id" class="form-input w-full"
                            :disabled="filteredBatches.length === 0">
                        <option value="">— No specific batch —</option>
                        <template x-for="batch in filteredBatches" :key="batch.id">
                            <option :value="batch.id" x-text="batch.label"
                                    :selected="batch.id == '{{ old('batch_id') }}'"></option>
                        </template>
                    </select>
                    <template x-if="selectedWorkOrderId && filteredBatches.length === 0">
                        <p class="text-sm text-gray-400 mt-1">No batches available for this work order.</p>
                    </template>
                    @error('batch_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">Anomaly Reason <span class="text-red-500">*</span></label>
                    <select name="anomaly_reason_id" class="form-input w-full" required>
                        <option value="">— Select reason —</option>
                        @foreach($anomalyReasons as $reason)
                            <option value="{{ $reason->id }}" @selected(old('anomaly_reason_id') == $reason->id)>
                                {{ $reason->name }}{{ $reason->category ? ' (' . $reason->category . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('anomaly_reason_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">Product Name <span class="text-red-500">*</span></label>
                    <input type="text" name="product_name" value="{{ old('product_name') }}"
                           class="form-input w-full" placeholder="Product being produced" required maxlength="200">
                    @error('product_name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Planned Quantity <span class="text-red-500">*</span></label>
                    <input type="number" name="planned_qty" value="{{ old('planned_qty') }}"
                           class="form-input w-full" step="0.01" min="0" required placeholder="0.00">
                    @error('planned_qty') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Actual Quantity <span class="text-red-500">*</span></label>
                    <input type="number" name="actual_qty" value="{{ old('actual_qty') }}"
                           class="form-input w-full" step="0.01" min="0" required placeholder="0.00">
                    @error('actual_qty') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">Comment</label>
                    <textarea name="comment" rows="3" class="form-input w-full" maxlength="2000"
                              placeholder="Additional details about the anomaly...">{{ old('comment') }}</textarea>
                    @error('comment') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex gap-3 justify-end mt-6">
                <a href="{{ route('admin.production-anomalies.index') }}" class="btn-touch btn-secondary">Cancel</a>
                <button type="submit" class="btn-touch btn-primary">Record Anomaly</button>
            </div>
        </form>
    </div>
</div>
@endsection
