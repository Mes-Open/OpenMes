/**
 * Editable list of sub-rows for dynamic subforms (e.g. view-template columns,
 * inspection-plan criteria). `value` is an array of row objects; `fields`
 * describes the inputs per row.
 *
 * Props:
 *   value     — array of row objects
 *   onChange  — (nextArray) => void
 *   fields    — [{ name, label, type?: 'text'|'number'|'select', options?, placeholder?, width? }]
 *   addLabel  — text for the add button
 *   newRow    — () => object for a fresh row (defaults to empty strings per field)
 */
export default function RepeatableRows({ value, onChange, fields, addLabel = '+ Add row', newRow }) {
    const rows = value ?? [];

    const makeRow = newRow ?? (() => Object.fromEntries(fields.map((f) => [f.name, f.type === 'select' ? (f.options?.[0]?.value ?? '') : ''])));
    const update = (i, key, v) => onChange(rows.map((r, idx) => (idx === i ? { ...r, [key]: v } : r)));
    const add = () => onChange([...rows, makeRow()]);
    const remove = (i) => onChange(rows.filter((_, idx) => idx !== i));

    return (
        <div className="space-y-2">
            <div className="flex gap-2 px-1 text-xs font-medium text-gray-500">
                {fields.map((f) => <div key={f.name} className={f.width ?? 'flex-1'}>{f.label}</div>)}
                <div className="w-8" />
            </div>
            {rows.length === 0 && <p className="text-sm text-gray-400 px-1">No rows yet.</p>}
            {rows.map((row, i) => (
                <div key={i} className="flex items-center gap-2">
                    {fields.map((f) => (
                        <div key={f.name} className={`${f.width ?? 'flex-1'} ${f.type === 'checkbox' ? 'flex justify-center' : ''}`}>
                            {f.type === 'checkbox' ? (
                                <input type="checkbox" checked={!!row[f.name]} onChange={(e) => update(i, f.name, e.target.checked)} />
                            ) : f.type === 'select' ? (
                                <select value={row[f.name] ?? ''} onChange={(e) => update(i, f.name, e.target.value)} className="form-input w-full text-sm py-1">
                                    {(f.options ?? []).map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                                </select>
                            ) : (
                                <input
                                    type={f.type === 'number' ? 'number' : 'text'}
                                    value={row[f.name] ?? ''}
                                    onChange={(e) => update(i, f.name, e.target.value)}
                                    placeholder={f.placeholder}
                                    className="form-input w-full text-sm py-1"
                                />
                            )}
                        </div>
                    ))}
                    <button type="button" onClick={() => remove(i)} className="w-8 text-red-500 hover:text-red-700 text-lg leading-none" title="Remove">×</button>
                </div>
            ))}
            <button type="button" onClick={add} className="text-blue-600 hover:underline text-sm">{addLabel}</button>
        </div>
    );
}
