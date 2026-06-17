import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button, Checkbox, Dropdown } from '@openmes/ui';

/**
 * Shared create/edit form for MachineConnection (protocol=mqtt) + MqttConnection config.
 *
 * Props:
 *   action       — POST/PUT URL
 *   method       — 'post' | 'put'
 *   submitLabel  — button text
 *   cancelHref   — cancel link URL
 *   connection   — existing MachineConnection (edit mode); null for create
 *   onDelete     — optional callback for delete button (edit mode only)
 */
export default function MqttConnectionForm({ action, method, submitLabel, cancelHref, connection = null, onDelete }) {
    const mqtt = connection?.mqtt ?? null;

    const form = useForm({
        name:                    connection?.name ?? '',
        description:             connection?.description ?? '',
        is_active:               connection?.is_active ?? false,
        broker_host:             mqtt?.broker_host ?? '',
        broker_port:             String(mqtt?.broker_port ?? 1883),
        client_id:               mqtt?.client_id ?? '',
        username:                mqtt?.username ?? '',
        password:                '',
        use_tls:                 mqtt?.use_tls ?? false,
        ca_cert:                 mqtt?.ca_cert ?? '',
        qos_default:             String(mqtt?.qos_default ?? 0),
        keep_alive_seconds:      String(mqtt?.keep_alive_seconds ?? 60),
        connect_timeout:         String(mqtt?.connect_timeout ?? 10),
        reconnect_delay_seconds: String(mqtt?.reconnect_delay_seconds ?? 5),
        clean_session:           mqtt?.clean_session ?? true,
    });

    const { data, setData, errors, processing } = form;

    const submit = (e) => {
        e.preventDefault();
        form.submit(method, action);
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            {/* General */}
            <Section title="General">
                <Field label="Name" required error={errors.name}>
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        className="form-input w-full"
                    />
                </Field>
                <Field label="Description" error={errors.description}>
                    <textarea
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        rows={2}
                        className="form-input w-full"
                    />
                </Field>
                <Checkbox
                    checked={data.is_active}
                    onChange={(next) => setData('is_active', next)}
                    label="Active (start listening on daemon start)"
                />
            </Section>

            {/* Broker */}
            <Section title="Broker">
                <div className="grid grid-cols-3 gap-4">
                    <div className="col-span-2">
                        <Field label="Host" required error={errors.broker_host}>
                            <input
                                type="text"
                                value={data.broker_host}
                                onChange={(e) => setData('broker_host', e.target.value)}
                                placeholder="broker.example.com"
                                required
                                className="form-input w-full font-mono"
                            />
                        </Field>
                    </div>
                    <div>
                        <Field label="Port" required error={errors.broker_port}>
                            <input
                                type="number"
                                value={data.broker_port}
                                onChange={(e) => setData('broker_port', e.target.value)}
                                min="1"
                                max="65535"
                                required
                                className="form-input w-full font-mono"
                            />
                        </Field>
                    </div>
                </div>
                <Field label="Client ID" error={errors.client_id}>
                    <input
                        type="text"
                        value={data.client_id}
                        onChange={(e) => setData('client_id', e.target.value)}
                        placeholder="Auto-generated if empty"
                        className="form-input w-full font-mono"
                    />
                </Field>
            </Section>

            {/* Authentication */}
            <Section title="Authentication">
                <div className="grid grid-cols-2 gap-4">
                    <Field label="Username" error={errors.username}>
                        <input
                            type="text"
                            value={data.username}
                            onChange={(e) => setData('username', e.target.value)}
                            autoComplete="off"
                            className="form-input w-full"
                        />
                    </Field>
                    <Field
                        label={
                            <>
                                Password
                                {mqtt?.has_password && (
                                    <span className="text-xs text-om-faint font-normal ml-1">(leave blank to keep current)</span>
                                )}
                            </>
                        }
                        error={errors.password}
                    >
                        <input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="new-password"
                            className="form-input w-full"
                        />
                    </Field>
                </div>
            </Section>

            {/* TLS */}
            <Section title="TLS / Security">
                <Checkbox
                    checked={data.use_tls}
                    onChange={(next) => setData('use_tls', next)}
                    label="Enable TLS (port 8883)"
                />
                {data.use_tls && (
                    <Field label="CA Certificate (PEM)" error={errors.ca_cert}>
                        <textarea
                            value={data.ca_cert}
                            onChange={(e) => setData('ca_cert', e.target.value)}
                            rows={4}
                            placeholder="-----BEGIN CERTIFICATE-----"
                            className="form-input w-full text-xs font-mono"
                        />
                    </Field>
                )}
            </Section>

            {/* Advanced */}
            <Section title="Advanced">
                <div className="grid grid-cols-2 gap-4">
                    <Field label="QoS default" error={errors.qos_default}>
                        <Dropdown
                            value={data.qos_default == null ? '' : String(data.qos_default)}
                            onChange={(v) => setData('qos_default', v)}
                            options={[
                                { value: '0', label: 'QoS 0 — At most once' },
                                { value: '1', label: 'QoS 1 — At least once' },
                                { value: '2', label: 'QoS 2 — Exactly once' },
                            ]}
                            className="w-full"
                        />
                    </Field>
                    <Field label="Keep-alive (seconds)" error={errors.keep_alive_seconds}>
                        <input
                            type="number"
                            value={data.keep_alive_seconds}
                            onChange={(e) => setData('keep_alive_seconds', e.target.value)}
                            min="5"
                            max="3600"
                            className="form-input w-full"
                        />
                    </Field>
                    <Field label="Connect timeout (seconds)" error={errors.connect_timeout}>
                        <input
                            type="number"
                            value={data.connect_timeout}
                            onChange={(e) => setData('connect_timeout', e.target.value)}
                            min="1"
                            max="120"
                            className="form-input w-full"
                        />
                    </Field>
                    <Field label="Reconnect delay (seconds)" error={errors.reconnect_delay_seconds}>
                        <input
                            type="number"
                            value={data.reconnect_delay_seconds}
                            onChange={(e) => setData('reconnect_delay_seconds', e.target.value)}
                            min="1"
                            max="300"
                            className="form-input w-full"
                        />
                    </Field>
                </div>
                <Checkbox
                    checked={data.clean_session}
                    onChange={(next) => setData('clean_session', next)}
                    label="Clean session (recommended for stateless connections)"
                />
            </Section>

            {/* Actions */}
            <div className="flex gap-3 pt-2">
                <Button type="submit" variant="primary" loading={processing}>
                    {processing ? 'Saving…' : submitLabel}
                </Button>
                <a
                    href={cancelHref}
                    className="px-5 py-2 bg-om-chip text-om-muted text-sm font-medium rounded-om-sm hover:bg-om-line2 transition-colors"
                >
                    Cancel
                </a>
                {onDelete && (
                    <Button
                        type="button"
                        variant="danger"
                        onClick={onDelete}
                        className="ml-auto"
                    >
                        Delete Connection
                    </Button>
                )}
            </div>
        </form>
    );
}

function Section({ title, children }) {
    return (
        <div className="bg-om-card rounded-om border border-om-line2 p-5 space-y-4">
            <h2 className="text-sm font-semibold text-om-muted uppercase tracking-wider">{title}</h2>
            {children}
        </div>
    );
}

function Field({ label, required, error, children }) {
    return (
        <div>
            <label className="block text-sm font-medium text-om-muted mb-1">
                {label} {required && <span className="text-om-blocked">*</span>}
            </label>
            {children}
            {error && <p className="mt-1 text-xs text-om-blocked">{error}</p>}
        </div>
    );
}
