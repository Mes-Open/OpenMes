import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Dropdown } from '@openmes/ui';
import { __ } from '../../../lib/i18n';

/**
 * Shared tag → signal manager for Modbus / OPC UA connections.
 * Both protocols use the MachineTag model; Modbus additionally needs a
 * register_type, OPC UA does not (showRegisterType toggles it).
 *
 * Props:
 *   connectionId      — MachineConnection id
 *   tags              — array of mapped tags (see *ConnectionController::mapTag)
 *   workstations      — [{id, name, line}]
 *   basePath          — e.g. '/admin/connectivity/modbus'
 *   showRegisterType  — bool (Modbus only)
 *   addressLabel      — field label for the address
 *   addressPlaceholder
 */

const SIGNAL_TYPES = [
    { value: 'state',          label: __('State') },
    { value: 'good_count',     label: __('Good count') },
    { value: 'reject_count',   label: __('Reject count') },
    { value: 'cycle_complete', label: __('Cycle complete') },
    { value: 'telemetry',      label: __('Telemetry') },
    { value: 'alarm',          label: __('Alarm') },
];

const SIGNAL_LABELS = Object.fromEntries(SIGNAL_TYPES.map((s) => [s.value, s.label]));

const DATA_TYPES = ['bool', 'int16', 'uint16', 'int32', 'uint32', 'float32', 'float64', 'string'];

// Values MUST match ModbusReader::buildRequest()'s match() arms:
// 'coil' → ReadCoils, 'discrete' → ReadInputDiscretes, 'input' → ReadInputRegisters,
// default → ReadHoldingRegisters.
const REGISTER_TYPES = [
    { value: 'coil',     label: __('Coil (0x)') },
    { value: 'discrete', label: __('Discrete input (1x)') },
    { value: 'input',    label: __('Input register (3x)') },
    { value: 'holding',  label: __('Holding register (4x)') },
];

export default function TagManager({
    connectionId,
    tags = [],
    workstations = [],
    basePath,
    showRegisterType = false,
    addressLabel = __('Address'),
    addressPlaceholder = '',
}) {
    return (
        <div>
            <div className="flex items-center justify-between mb-3">
                <h2 className="text-lg font-semibold text-om-ink">{__('Tags & Signals')}</h2>
                <span className="text-xs text-om-faint">
                    {tags.length} {tags.length === 1 ? __('tag') : __('tags')}
                </span>
            </div>

            <div className="space-y-3">
                {tags.length === 0 ? (
                    <div className="bg-om-card rounded-om border border-dashed border-om-line p-8 text-center text-om-faint">
                        <p className="text-sm">{__('No tags defined yet — the poller has nothing to read.')}</p>
                    </div>
                ) : (
                    <div className="bg-om-card rounded-om border border-om-line2 overflow-hidden divide-y divide-om-line2">
                        {tags.map((tag) => (
                            <TagRow key={tag.id} tag={tag} connectionId={connectionId} basePath={basePath} />
                        ))}
                    </div>
                )}

                <AddTagForm
                    connectionId={connectionId}
                    workstations={workstations}
                    basePath={basePath}
                    showRegisterType={showRegisterType}
                    addressLabel={addressLabel}
                    addressPlaceholder={addressPlaceholder}
                />
            </div>
        </div>
    );
}

function TagRow({ tag, connectionId, basePath }) {
    const handleDelete = () => {
        if (confirm(__('Delete tag ":name"?', { name: tag.name }))) {
            router.delete(`${basePath}/${connectionId}/tags/${tag.id}`, { preserveScroll: true });
        }
    };

    const valueMap = tag.transform?.value_map;
    const scale = tag.transform?.scale;

    return (
        <div className={`px-4 py-3 flex items-start gap-3 ${!tag.is_active ? 'opacity-50' : ''}`}>
            <div className="flex-1 min-w-0 space-y-1">
                <div className="flex items-center gap-2 flex-wrap text-sm">
                    <span className="font-medium text-om-ink">{tag.name}</span>
                    <span className="font-mono text-xs text-om-muted bg-om-chip px-1.5 py-0.5 rounded">
                        {tag.address}
                    </span>
                    <span className="text-om-faint">→</span>
                    <span className="text-xs px-2 py-0.5 rounded-full bg-om-chip text-om-accent">
                        {SIGNAL_LABELS[tag.signal_type] ?? tag.signal_type}
                    </span>
                </div>
                <div className="flex items-center gap-2 flex-wrap text-xs text-om-faint">
                    <span className="uppercase">{tag.data_type}</span>
                    {tag.register_type && <span>· {tag.register_type}</span>}
                    {tag.workstation && <span>· {tag.workstation}</span>}
                    {scale != null && <span>· scale ×{scale}</span>}
                    {valueMap && (
                        <span className="font-mono">
                            · {Object.entries(valueMap).map(([k, v]) => `${k}=${v}`).join(', ')}
                        </span>
                    )}
                </div>
            </div>
            <button
                type="button"
                onClick={handleDelete}
                className="p-1.5 text-om-faint hover:text-om-blocked rounded-md hover:bg-om-chip transition-colors shrink-0"
                title={__('Delete tag')}
            >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </button>
        </div>
    );
}

function AddTagForm({ connectionId, workstations, basePath, showRegisterType, addressLabel, addressPlaceholder }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: '',
        address: '',
        signal_type: 'state',
        data_type: 'int16',
        register_type: showRegisterType ? 'holding' : '',
        workstation_id: '',
        value_map: '',
        scale: '',
    });
    const { data, setData, errors, processing } = form;

    const submit = (e) => {
        e.preventDefault();
        form.post(`${basePath}/${connectionId}/tags`, {
            preserveScroll: true,
            onSuccess: () => { form.reset(); setOpen(false); },
        });
    };

    return (
        <div className="bg-om-card rounded-om border border-om-line2 p-4">
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className="flex items-center gap-2 text-sm font-medium text-om-accent hover:text-om-accent"
            >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                </svg>
                {__('Add tag')}
            </button>

            {open && (
                <form onSubmit={submit} className="mt-4 space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <MiniField label={__('Name *')} error={errors.name}>
                            <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} required className="form-input w-full text-sm" placeholder={__('e.g. Line 1 state')} />
                        </MiniField>
                        <MiniField label={`${addressLabel} *`} error={errors.address}>
                            <input type="text" value={data.address} onChange={(e) => setData('address', e.target.value)} required className="form-input w-full text-sm font-mono" placeholder={addressPlaceholder} />
                        </MiniField>
                        <MiniField label={__('Signal type *')} error={errors.signal_type}>
                            <Dropdown
                                options={SIGNAL_TYPES.map((s) => ({ value: String(s.value), label: s.label }))}
                                value={data.signal_type == null ? '' : String(data.signal_type)}
                                onChange={(v) => setData('signal_type', v)}
                                className="w-full"
                            />
                        </MiniField>
                        <MiniField label={__('Data type *')} error={errors.data_type}>
                            <Dropdown
                                options={DATA_TYPES.map((d) => ({ value: String(d), label: d }))}
                                value={data.data_type == null ? '' : String(data.data_type)}
                                onChange={(v) => setData('data_type', v)}
                                className="w-full"
                            />
                        </MiniField>
                        {showRegisterType && (
                            <MiniField label={__('Register type *')} error={errors.register_type}>
                                <Dropdown
                                    options={REGISTER_TYPES.map((r) => ({ value: String(r.value), label: r.label }))}
                                    value={data.register_type == null ? '' : String(data.register_type)}
                                    onChange={(v) => setData('register_type', v)}
                                    className="w-full"
                                />
                            </MiniField>
                        )}
                        <MiniField label={__('Workstation')} error={errors.workstation_id}>
                            <Dropdown
                                options={workstations.map((w) => ({ value: String(w.id), label: w.line ? `${w.line} / ${w.name}` : w.name }))}
                                value={data.workstation_id == null ? '' : String(data.workstation_id)}
                                onChange={(v) => setData('workstation_id', v)}
                                placeholder={__('— none —')}
                                className="w-full"
                            />
                        </MiniField>
                        <MiniField label={__('Value map — e.g. 1=RUNNING,2=IDLE,3=FAULT')} error={errors.value_map}>
                            <input type="text" value={data.value_map} onChange={(e) => setData('value_map', e.target.value)} className="form-input w-full text-sm font-mono" placeholder="1=RUNNING,2=IDLE" />
                        </MiniField>
                        <MiniField label={__('Scale — multiply raw reading')} error={errors.scale}>
                            <input type="number" step="any" value={data.scale} onChange={(e) => setData('scale', e.target.value)} className="form-input w-full text-sm" placeholder="1.0" />
                        </MiniField>
                    </div>
                    <div className="flex gap-2">
                        <button type="submit" disabled={processing} className="px-4 py-1.5 bg-om-ink text-om-on-ink text-sm rounded-om-sm hover:bg-om-ink-hover transition-colors disabled:opacity-50">
                            {processing ? __('Adding…') : __('Add Tag')}
                        </button>
                        <button type="button" onClick={() => setOpen(false)} className="px-4 py-1.5 bg-om-chip text-om-muted text-sm rounded-om-sm hover:bg-om-line2 transition-colors">
                            {__('Cancel')}
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}

function MiniField({ label, error, children }) {
    return (
        <div>
            <label className="block text-xs text-om-muted mb-0.5">{label}</label>
            {children}
            {error && <p className="mt-0.5 text-xs text-om-blocked">{error}</p>}
        </div>
    );
}
