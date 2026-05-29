import { Link } from '@inertiajs/react';
import RepeatableRows from '../../../components/RepeatableRows';

const COLUMN_FIELDS = [
    { name: 'label', label: 'Label', placeholder: 'Quantity' },
    { name: 'key', label: 'Key', placeholder: 'planned_qty' },
    {
        name: 'source', label: 'Source', type: 'select', width: 'w-40',
        options: [{ value: 'field', label: 'Field' }, { value: 'extra_data', label: 'Extra data' }],
    },
];

/**
 * Shared create/edit form for view templates — name/description + a repeatable
 * list of column definitions ({ label, key, source }). Non-optimistic
 * write-through via Inertia useForm (passed in by the parent).
 */
export default function ViewTemplateForm({ form, submitLabel, onSubmit }) {
    const { data, setData, errors, processing } = form;

    return (
        <form onSubmit={onSubmit} className="bg-white rounded-lg shadow-sm p-6 max-w-3xl space-y-5">
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Name <span className="text-red-500">*</span></label>
                <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="form-input w-full" autoFocus />
                {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
            </div>
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea value={data.description ?? ''} onChange={(e) => setData('description', e.target.value)} rows={2} className="form-input w-full" />
                {errors.description && <p className="mt-1 text-xs text-red-600">{errors.description}</p>}
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Columns <span className="text-red-500">*</span></label>
                <RepeatableRows
                    value={data.columns}
                    onChange={(rows) => setData('columns', rows)}
                    fields={COLUMN_FIELDS}
                    addLabel="+ Add column"
                    newRow={() => ({ label: '', key: '', source: 'field' })}
                />
                {errors.columns && <p className="mt-1 text-xs text-red-600">{errors.columns}</p>}
            </div>

            <div className="flex items-center gap-3 pt-2">
                <button type="submit" disabled={processing} className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                    {processing ? 'Saving…' : submitLabel}
                </button>
                <Link href="/admin/view-templates" className="text-gray-500 hover:text-gray-800 text-sm">Cancel</Link>
            </div>
        </form>
    );
}
