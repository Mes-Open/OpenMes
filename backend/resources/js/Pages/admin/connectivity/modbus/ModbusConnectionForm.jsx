import { Link, useForm } from '@inertiajs/react';
import { Button, Checkbox, Dropdown } from '@openmes/ui';
import { Section, Field } from '../ui';
import { __ } from '../../../../lib/i18n';

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
            <Section title={__('General')}>
                <Field label={__('Name')} required error={errors.name}>
                    <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} required className="form-input w-full" />
                </Field>
                <Field label={__('Description')} error={errors.description}>
                    <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={2} className="form-input w-full" />
                </Field>
                <Checkbox checked={data.is_active} onChange={(next) => setData('is_active', next)} label={__('Active (start polling on daemon start)')} />
            </Section>

            <Section title={__('Device')}>
                <div className="grid grid-cols-3 gap-4">
                    <div className="col-span-2">
                        <Field label={__('Host')} required error={errors.host}>
                            <input type="text" value={data.host} onChange={(e) => setData('host', e.target.value)} placeholder="192.168.1.50" required className="form-input w-full font-mono" />
                        </Field>
                    </div>
                    <Field label={__('Port')} required error={errors.port}>
                        <input type="number" value={data.port} onChange={(e) => setData('port', e.target.value)} min="1" max="65535" required className="form-input w-full font-mono" />
                    </Field>
                </div>
                <Field label={__('Unit ID (slave address)')} required error={errors.unit_id}>
                    <input type="number" value={data.unit_id} onChange={(e) => setData('unit_id', e.target.value)} min="0" max="255" required className="form-input w-full font-mono" />
                </Field>
            </Section>

            <Section title={__('Polling & encoding')}>
                <div className="grid grid-cols-2 gap-4">
                    <Field label={__('Poll interval (ms)')} required error={errors.poll_interval_ms}>
                        <input type="number" value={data.poll_interval_ms} onChange={(e) => setData('poll_interval_ms', e.target.value)} min="100" max="60000" required className="form-input w-full" />
                    </Field>
                    <Field label={__('Timeout (seconds)')} required error={errors.timeout_seconds}>
                        <input type="number" value={data.timeout_seconds} onChange={(e) => setData('timeout_seconds', e.target.value)} min="1" max="60" required className="form-input w-full" />
                    </Field>
                    <Field label={__('Byte order')} required error={errors.byte_order}>
                        <Dropdown
                            options={[
                                { value: 'big', label: __('Big-endian') },
                                { value: 'little', label: __('Little-endian') },
                            ]}
                            value={data.byte_order == null ? '' : String(data.byte_order)}
                            onChange={(v) => setData('byte_order', v)}
                            className="w-full"
                        />
                    </Field>
                    <Field label={__('Word order')} required error={errors.word_order}>
                        <Dropdown
                            options={[
                                { value: 'big', label: __('Big-endian (high word first)') },
                                { value: 'little', label: __('Little-endian (low word first)') },
                            ]}
                            value={data.word_order == null ? '' : String(data.word_order)}
                            onChange={(v) => setData('word_order', v)}
                            className="w-full"
                        />
                    </Field>
                </div>
            </Section>

            <div className="flex gap-3 pt-2">
                <Button variant="primary" type="submit" loading={processing}>
                    {processing ? __('Saving…') : submitLabel}
                </Button>
                <Link href={cancelHref} className="px-5 py-2 bg-om-chip text-om-muted text-sm font-medium rounded-om-sm hover:bg-om-line2 transition-colors">
                    {__('Cancel')}
                </Link>
                {onDelete && (
                    <Button variant="danger" type="button" onClick={onDelete} className="ml-auto">
                        {__('Delete Connection')}
                    </Button>
                )}
            </div>
        </form>
    );
}
