{{-- Shared form for create/edit view template --}}
<div x-data="{
    columns: @json($template->columns ?? []),
    newLabel: '',
    newKey: '',
    newSource: 'extra_data',
    add() {
        if (!this.newLabel || !this.newKey) return;
        this.columns.push({ label: this.newLabel, key: this.newKey, source: this.newSource });
        this.newLabel = '';
        this.newKey = '';
        this.newSource = 'extra_data';
    },
    remove(i) { this.columns.splice(i, 1); },
    moveUp(i) { if (i > 0) { [this.columns[i-1], this.columns[i]] = [this.columns[i], this.columns[i-1]]; } },
    moveDown(i) { if (i < this.columns.length - 1) { [this.columns[i], this.columns[i+1]] = [this.columns[i+1], this.columns[i]]; } }
}">

    <div class="mb-4">
        <label class="form-label">Template Name <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $template->name ?? '') }}"
               class="form-input w-full @error('name') border-red-500 @enderror" required maxlength="100">
        @error('name')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="mb-6">
        <label class="form-label">Description</label>
        <input type="text" name="description" value="{{ old('description', $template->description ?? '') }}"
               class="form-input w-full" maxlength="500" placeholder="Optional description...">
    </div>

    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">Columns</h3>

    {{-- Explanation panel --}}
    <div x-data="{ showHelp: false }" class="mb-4">
        <button type="button" @click="showHelp = !showHelp"
                class="text-sm text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span x-text="showHelp ? 'Hide help' : 'How do columns work?'"></span>
        </button>

        <div x-show="showHelp" x-transition class="mt-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 text-sm text-gray-700 dark:text-gray-300 space-y-3">
            <p>Columns define which data the operator sees in the Workstation production table. Each column has a <strong>Source</strong> and a <strong>Key</strong>.</p>

            <div class="grid sm:grid-cols-2 gap-4">
                {{-- extra_data source --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                    <h4 class="font-bold text-gray-800 dark:text-gray-200 mb-1">
                        <code class="text-xs bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200 px-1.5 py-0.5 rounded">extra_data</code>
                        — Imported CSV data
                    </h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                        When you import work orders from CSV/XLS, any column that doesn't map to a built-in field is stored in the <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">extra_data</code> JSON field. Use this source to display those custom values.
                    </p>
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Examples:</p>
                    <ul class="text-xs text-gray-500 dark:text-gray-400 space-y-0.5 list-disc list-inside">
                        <li>Key <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">material</code> → reads <code>extra_data.material</code></li>
                        <li>Key <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">color</code> → reads <code>extra_data.color</code></li>
                        <li>Key <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">client_name</code> → reads <code>extra_data.client_name</code></li>
                    </ul>
                </div>

                {{-- field source --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                    <h4 class="font-bold text-gray-800 dark:text-gray-200 mb-1">
                        <code class="text-xs bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 px-1.5 py-0.5 rounded">field</code>
                        — Built-in work order fields
                    </h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                        These are standard fields stored directly on the work order. Use this source when you need to show a system field that isn't already in the default table layout.
                    </p>
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Available keys:</p>
                    <ul class="text-xs text-gray-500 dark:text-gray-400 space-y-0.5 list-disc list-inside">
                        <li><code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">description</code> — work order description</li>
                        <li><code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">priority</code> — priority level (1–5)</li>
                        <li><code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">due_date</code> — deadline</li>
                        <li><code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">production_year</code> — production year</li>
                        <li><code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">month_number</code> — production month</li>
                    </ul>
                </div>
            </div>

            <p class="text-xs text-gray-400 dark:text-gray-500 italic">
                Tip: Columns like Order No, Product, Status, Week, Quantities and Progress are always shown — you don't need to add them here. Use this template to add <strong>additional</strong> data columns specific to your production line.
            </p>
        </div>
    </div>

    {{-- Column list --}}
    <div class="space-y-2 mb-4">
        <template x-for="(col, i) in columns" :key="i">
            <div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-800 rounded-lg px-3 py-2">
                <span class="text-gray-400 text-sm font-mono w-6 text-center" x-text="i + 1"></span>
                <input type="hidden" :name="'columns[' + i + '][label]'" :value="col.label">
                <input type="hidden" :name="'columns[' + i + '][key]'" :value="col.key">
                <input type="hidden" :name="'columns[' + i + '][source]'" :value="col.source">
                <span class="flex-1 text-sm font-medium text-gray-800 dark:text-gray-200" x-text="col.label"></span>
                <code class="text-xs text-gray-500 bg-gray-200 dark:bg-gray-700 px-1.5 py-0.5 rounded" x-text="col.source + '.' + col.key"></code>
                <button type="button" @click="moveUp(i)" class="p-1 text-gray-400 hover:text-gray-700"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg></button>
                <button type="button" @click="moveDown(i)" class="p-1 text-gray-400 hover:text-gray-700"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>
                <button type="button" @click="remove(i)" class="p-1 text-red-400 hover:text-red-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
        </template>
        <div x-show="columns.length === 0" class="text-sm text-gray-400 text-center py-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
            No columns added yet. Add at least one column.
        </div>
    </div>

    {{-- Add column --}}
    <div class="flex flex-wrap gap-2 items-end border-t border-gray-200 dark:border-gray-700 pt-4 mb-6">
        <div class="flex-1 min-w-[120px]">
            <label class="text-xs text-gray-500 block mb-1">Label</label>
            <input type="text" x-model="newLabel" placeholder="e.g. Material" class="form-input w-full text-sm">
        </div>
        <div class="flex-1 min-w-[120px]">
            <label class="text-xs text-gray-500 block mb-1">Key</label>
            <input type="text" x-model="newKey" placeholder="e.g. material" class="form-input w-full text-sm">
        </div>
        <div class="w-32">
            <label class="text-xs text-gray-500 block mb-1">Source</label>
            <select x-model="newSource" class="form-input w-full text-sm">
                <option value="extra_data">extra_data</option>
                <option value="field">field</option>
            </select>
        </div>
        <button type="button" @click="add()" class="btn-touch btn-secondary text-sm" :disabled="!newLabel || !newKey">+ Add</button>
    </div>

    @error('columns')<p class="text-red-600 text-sm mb-4">{{ $message }}</p>@enderror
</div>
