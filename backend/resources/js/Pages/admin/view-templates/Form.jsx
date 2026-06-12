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
        <form onSubmit={onSubmit} className="bg-om-card rounded-om-sm shadow-sm p-6 max-w-3xl space-y-5">
            <div>
                <label className="block text-sm font-medium text-om-muted mb-1">Name <span className="text-om-blocked">*</span></label>
                <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="form-input w-full" autoFocus />
                {errors.name && <p className="mt-1 text-xs text-om-blocked">{errors.name}</p>}
            </div>
            <div>
                <label className="block text-sm font-medium text-om-muted mb-1">Description</label>
                <textarea value={data.description ?? ''} onChange={(e) => setData('description', e.target.value)} rows={2} className="form-input w-full" />
                {errors.description && <p className="mt-1 text-xs text-om-blocked">{errors.description}</p>}
            </div>

            <div>
                <label className="block text-sm font-medium text-om-muted mb-2">Columns <span className="text-om-blocked">*</span></label>
                <RepeatableRows
                    value={data.columns}
                    onChange={(rows) => setData('columns', rows)}
                    fields={COLUMN_FIELDS}
                    addLabel="+ Add column"
                    newRow={() => ({ label: '', key: '', source: 'field' })}
                />
                {errors.columns && <p className="mt-1 text-xs text-om-blocked">{errors.columns}</p>}
            </div>

            <div className="flex items-center gap-3 pt-2">
                <button type="submit" disabled={processing} className="bg-om-ink text-white px-4 py-2 rounded-om-sm text-sm font-medium hover:bg-black disabled:opacity-50">
                    {processing ? 'Saving…' : submitLabel}
                </button>
                <Link href="/admin/view-templates" className="text-om-muted hover:text-om-ink text-sm">Cancel</Link>
            </div>
        </form>
    );
}
