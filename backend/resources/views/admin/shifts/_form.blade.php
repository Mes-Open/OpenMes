<form method="POST" action="{{ $action }}">
    @csrf
    @if($method === 'PUT') @method('PUT') @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="form-label">Name *</label>
            <input type="text" name="name" value="{{ old('name', $shift?->name) }}"
                   class="form-input" placeholder="e.g. Morning" required>
            @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">Line (leave empty = all lines)</label>
            <select name="line_id" class="form-input">
                <option value="">All lines</option>
                @foreach($lines as $line)
                    <option value="{{ $line->id }}" {{ old('line_id', $shift?->line_id) == $line->id ? 'selected' : '' }}>
                        {{ $line->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="form-label">Start Time *</label>
            <input type="time" name="start_time"
                   value="{{ old('start_time', $shift ? substr($shift->start_time, 0, 5) : '') }}"
                   class="form-input" required>
            @error('start_time') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">End Time *</label>
            <input type="time" name="end_time"
                   value="{{ old('end_time', $shift ? substr($shift->end_time, 0, 5) : '') }}"
                   class="form-input" required>
            @error('end_time') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="mt-4">
        <label class="form-label">Days of Week *</label>
        <div class="flex gap-2 flex-wrap">
            @php $selected = old('days_of_week', $shift?->days_of_week ?? [1,2,3,4,5]); @endphp
            @foreach([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $d => $label)
                <label class="flex flex-col items-center gap-1 cursor-pointer">
                    <input type="checkbox" name="days_of_week[]" value="{{ $d }}"
                           {{ in_array($d, $selected) ? 'checked' : '' }}
                           class="sr-only peer">
                    <span class="w-10 h-10 flex items-center justify-center rounded-lg text-sm font-medium border
                                 border-gray-200 bg-gray-50 text-gray-600
                                 peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600
                                 cursor-pointer transition-colors">
                        {{ $label }}
                    </span>
                </label>
            @endforeach
        </div>
        @error('days_of_week') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="mt-4">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1"
                   {{ old('is_active', $shift?->is_active ?? true) ? 'checked' : '' }}
                   class="rounded border-gray-300 text-blue-600">
            <span class="text-sm text-gray-700">Active</span>
        </label>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn-touch btn-primary">
            {{ $shift ? 'Save Changes' : 'Create Shift' }}
        </button>
    </div>
</form>
