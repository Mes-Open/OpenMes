import { Link, useForm } from '@inertiajs/react';
import { Section, Field } from '../ui';

/**
 * Shared create/edit form for MachineConnection (protocol=modbus) + ModbusConnection config.
 *
 * Props:
 *   action, method ('post'|'put'), submitLabel, cancelHref
 *   connection — existing (edit mode); null for create
 *   onDelete   — optional delete callback (edit mode)
 */
export default function ModbusConnectionForm({ action, method, submitLabel, cancelHref, connection = null, onDelete }) {
    const modbus = connection?.modbus ?? null;

    const form = useForm({
        name:             connection?.name ?? '',
        description:      connection?.description ?? '',
        is_active:        connection?.is_active ?? false,
        host:             modbus?.host ?? '',
        port:             String(modbus?.port ?? 502),
        unit_id:          String(modbus?.unit_id ?? 1),
        poll_interval_ms: String(modbus?.poll_interval_ms ?? 1000),
        timeout_seconds:  String(modbus?.timeout_seconds ?? 5),
        byte_order:       modbus?.byte_order ?? 'big',
        word_order:       modbus?.word_order ?? 'big',
    });

    const { data, setData, errors, processing } = form;

    const submit = (e) => {
        e.preventDefault();
        form.submit(method, action);
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <Section title="General">
                <Field label="Name" required error={errors.name}>
                    <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} required className="form-input w-full" />
                </Field>
                <Field label="Description" error={errors.description}>
                    <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={2} className="form-input w-full" />
                </Field>
                <label className="flex items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                    <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                    Active (start polling on daemon start)
                </label>
            </Section>

            <Section title="Device">
                <div className="grid grid-cols-3 gap-4">
                    <div className="col-span-2">
                        <Field label="Host" required error={errors.host}>
                            <input type="text" value={data.host} onChange={(e) => setData('host', e.target.value)} placeholder="192.168.1.50" required className="form-input w-full font-mono" />
                        </Field>
                    </div>
                    <Field label="Port" required error={errors.port}>
                        <input type="number" value={data.port} onChange={(e) => setData('port', e.target.value)} min="1" max="65535" required className="form-input w-full font-mono" />
                    </Field>
                </div>
                <Field label="Unit ID (slave address)" required error={errors.unit_id}>
                    <input type="number" value={data.unit_id} onChange={(e) => setData('unit_id', e.target.value)} min="0" max="255" required className="form-input w-full font-mono" />
                </Field>
            </Section>

            <Section title="Polling & encoding">
                <div className="grid grid-cols-2 gap-4">
                    <Field label="Poll interval (ms)" required error={errors.poll_interval_ms}>
                        <input type="number" value={data.poll_interval_ms} onChange={(e) => setData('poll_interval_ms', e.target.value)} min="100" max="60000" required className="form-input w-full" />
                    </Field>
                    <Field label="Timeout (seconds)" required error={errors.timeout_seconds}>
                        <input type="number" value={data.timeout_seconds} onChange={(e) => setData('timeout_seconds', e.target.value)} min="1" max="60" required className="form-input w-full" />
                    </Field>
                    <Field label="Byte order" required error={errors.byte_order}>
                        <select value={data.byte_order} onChange={(e) => setData('byte_order', e.target.value)} className="form-input w-full">
                            <option value="big">Big-endian</option>
                            <option value="little">Little-endian</option>
                        </select>
                    </Field>
                    <Field label="Word order" required error={errors.word_order}>
                        <select value={data.word_order} onChange={(e) => setData('word_order', e.target.value)} className="form-input w-full">
                            <option value="big">Big-endian (high word first)</option>
                            <option value="little">Little-endian (low word first)</option>
                        </select>
                    </Field>
                </div>
            </Section>

            <div className="flex gap-3 pt-2">
                <button type="submit" disabled={processing} className="px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
                    {processing ? 'Saving…' : submitLabel}
                </button>
                <Link href={cancelHref} className="px-5 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    Cancel
                </Link>
                {onDelete && (
                    <button type="button" onClick={onDelete} className="ml-auto px-5 py-2 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 text-sm font-medium rounded-lg hover:bg-red-100 transition-colors">
                        Delete Connection
                    </button>
                )}
            </div>
        </form>
    );
}
