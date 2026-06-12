import { useState, useEffect, useRef } from 'react';
import { Head, router, usePage, useForm } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';
import { formatNumber, formatTime } from '../../../../lib/i18n';

const STATUS_DOT = {
    green:  'bg-om-running',
    yellow: 'bg-om-downtime',
    red:    'bg-om-blocked',
    slate:  'bg-slate-400',
};

const ACTION_COLORS = {
    update_batch_step:     'bg-om-chip text-purple-700',
    update_work_order_qty: 'bg-om-chip text-om-accent',
    create_issue:          'bg-om-blocked-bg text-om-blocked',
    update_line_status:    'bg-om-downtime-bg text-orange-700',
    set_work_order_status: 'bg-om-chip text-indigo-700',
    log_event:             'bg-om-chip text-om-muted',
    webhook_forward:       'bg-teal-100 text-teal-700',
};

const ACTION_LABELS = {
    update_batch_step:     'Update Batch Step',
    update_work_order_qty: 'Update Work Order Qty',
    create_issue:          'Create Issue',
    update_line_status:    'Update Line Status',
    set_work_order_status: 'Set Work Order Status',
    log_event:             'Log Event',
    webhook_forward:       'Webhook Forward',
};

export default function MqttShow() {
    const { connection, recentMessages = [], messagesUrl } = usePage().props;
    const mqtt = connection.mqtt;
    const dot = STATUS_DOT[connection.status_color] ?? 'bg-slate-400';

    const handleToggle = () => {
        router.post(`/admin/connectivity/mqtt/${connection.id}/toggle-active`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={`${connection.name} — MQTT`} />

            <div className="p-6 space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <a
                            href="/admin/connectivity/mqtt"
                            className="text-sm text-om-muted hover:text-om-ink flex items-center gap-1 mb-2"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            MQTT Connections
                        </a>
                        <div className="flex items-center gap-3">
                            <span className={`w-3 h-3 rounded-full ${dot} ${connection.status === 'connected' ? 'animate-pulse' : ''}`} />
                            <h1 className="text-2xl font-bold text-om-ink">{connection.name}</h1>
                            {!connection.is_active && (
                                <span className="text-xs px-2 py-0.5 bg-om-chip text-om-muted rounded-full">
                                    Inactive
                                </span>
                            )}
                        </div>
                        {mqtt && (
                            <p className="mt-1 text-sm text-om-muted font-mono">
                                {mqtt.broker_host}:{mqtt.broker_port}
                                {mqtt.use_tls && <span className="ml-2 text-om-running">TLS</span>}
                                {' · '}QoS {mqtt.qos_default}
                            </p>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <a
                            href={`/admin/connectivity/mqtt/${connection.id}/edit`}
                            className="px-4 py-2 text-sm font-medium bg-om-chip text-om-muted rounded-om-sm hover:bg-om-line2 transition-colors"
                        >
                            Edit
                        </a>
                        <button
                            type="button"
                            onClick={handleToggle}
                            className={`px-4 py-2 text-sm font-medium rounded-om-sm transition-colors ${
                                connection.is_active
                                    ? 'bg-om-downtime-bg text-om-downtime hover:bg-om-downtime-bg'
                                    : 'bg-om-running-bg text-om-running hover:bg-om-running-bg'
                            }`}
                        >
                            {connection.is_active ? 'Disable' : 'Enable'}
                        </button>
                    </div>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-3 gap-4">
                    <StatCard value={connection.topics.length} label="Topics" />
                    <StatCard value={formatNumber(Number(connection.messages_received))} label="Messages received" />
                    <StatCard value={connection.status} label={connection.last_connected_at ?? 'Never'} capitalize />
                </div>

                {/* Topics & Mappings */}
                <div>
                    <div className="flex items-center justify-between mb-3">
                        <h2 className="text-lg font-semibold text-om-ink">Topics &amp; Mappings</h2>
                    </div>

                    <div className="space-y-4">
                        {connection.topics.length === 0 ? (
                            <div className="bg-om-card rounded-om border border-dashed border-om-line p-8 text-center text-om-faint">
                                <svg className="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                                </svg>
                                <p className="text-sm">No topics subscribed yet.</p>
                            </div>
                        ) : (
                            connection.topics.map((topic) => (
                                <TopicCard
                                    key={topic.id}
                                    topic={topic}
                                    connectionId={connection.id}
                                />
                            ))
                        )}

                        {/* Add topic form */}
                        <AddTopicForm connectionId={connection.id} />
                    </div>
                </div>

                {/* Live Message Log */}
                <LiveMessageLog
                    initialMessages={recentMessages}
                    initialLastId={recentMessages.length > 0 ? Math.max(...recentMessages.map((m) => m.id)) : 0}
                    messagesUrl={messagesUrl}
                />
            </div>
        </>
    );
}

MqttShow.layout = (page) => <AppLayout>{page}</AppLayout>;

/* ------------------------------------------------------------------ */
/* Sub-components                                                        */
/* ------------------------------------------------------------------ */

function StatCard({ value, label, capitalize }) {
    return (
        <div className="bg-om-card rounded-om border border-om-line2 p-4 text-center">
            <p className={`text-2xl font-bold text-om-ink ${capitalize ? 'capitalize' : ''}`}>
                {value}
            </p>
            <p className="text-xs text-om-muted mt-1">{label}</p>
        </div>
    );
}

function TopicCard({ topic, connectionId }) {
    const [editOpen, setEditOpen] = useState(false);
    const [addMappingOpen, setAddMappingOpen] = useState(false);

    const handleDeleteTopic = () => {
        if (confirm('Delete this topic and all its mappings?')) {
            router.delete(`/admin/connectivity/mqtt/${connectionId}/topics/${topic.id}`, { preserveScroll: true });
        }
    };

    return (
        <div className="bg-om-card rounded-om border border-om-line2 overflow-hidden">
            {/* Topic header */}
            <div className="flex items-center gap-3 px-4 py-3 bg-om-panel border-b border-om-line2">
                <span className={`w-2 h-2 rounded-full shrink-0 ${topic.is_active ? 'bg-om-running' : 'bg-slate-400'}`} />
                <span className="font-mono text-sm font-medium text-om-ink flex-1">{topic.topic_pattern}</span>
                <span className="text-xs px-2 py-0.5 bg-om-line2 text-om-muted rounded-full uppercase">
                    {topic.payload_format}
                </span>
                {topic.description && (
                    <span className="text-xs text-om-faint max-w-xs truncate">{topic.description}</span>
                )}
                <div className="flex items-center gap-1 shrink-0">
                    <button
                        type="button"
                        onClick={() => setEditOpen((o) => !o)}
                        className="p-1.5 text-om-faint hover:text-om-ink rounded-md hover:bg-om-chip transition-colors"
                    >
                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        onClick={handleDeleteTopic}
                        className="p-1.5 text-om-faint hover:text-om-blocked rounded-md hover:bg-om-chip transition-colors"
                    >
                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>

            {/* Edit topic form */}
            {editOpen && (
                <EditTopicForm
                    topic={topic}
                    connectionId={connectionId}
                    onClose={() => setEditOpen(false)}
                />
            )}

            {/* Mappings list */}
            <div className="divide-y divide-om-line2">
                {topic.mappings.length === 0 ? (
                    <p className="px-4 py-3 text-xs text-om-faint italic">
                        No mappings defined — messages will be logged only.
                    </p>
                ) : (
                    topic.mappings.map((mapping) => (
                        <MappingRow
                            key={mapping.id}
                            mapping={mapping}
                            topic={topic}
                            connectionId={connectionId}
                        />
                    ))
                )}
            </div>

            {/* Add mapping */}
            <div className="px-4 py-3 border-t border-om-line2">
                <button
                    type="button"
                    onClick={() => setAddMappingOpen((o) => !o)}
                    className="flex items-center gap-1.5 text-xs font-medium text-om-accent hover:text-om-accent"
                >
                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add mapping rule
                </button>
                {addMappingOpen && (
                    <AddMappingForm
                        connectionId={connectionId}
                        topicId={topic.id}
                        onClose={() => setAddMappingOpen(false)}
                    />
                )}
            </div>
        </div>
    );
}

function EditTopicForm({ topic, connectionId, onClose }) {
    const form = useForm({
        topic_pattern:  topic.topic_pattern,
        payload_format: topic.payload_format,
        description:    topic.description ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.put(`/admin/connectivity/mqtt/${connectionId}/topics/${topic.id}`, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <div className="px-4 py-3 border-b border-om-line2 bg-om-chip/40">
            <form onSubmit={submit} className="flex gap-3 items-end flex-wrap">
                <div className="flex-1 min-w-48">
                    <label className="block text-xs font-medium text-om-muted mb-1">Pattern</label>
                    <input
                        type="text"
                        value={form.data.topic_pattern}
                        onChange={(e) => form.setData('topic_pattern', e.target.value)}
                        required
                        className="w-full px-2 py-1.5 text-sm font-mono border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent"
                    />
                </div>
                <div>
                    <label className="block text-xs font-medium text-om-muted mb-1">Format</label>
                    <select
                        value={form.data.payload_format}
                        onChange={(e) => form.setData('payload_format', e.target.value)}
                        className="px-2 py-1.5 text-sm border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent"
                    >
                        {['json', 'plain', 'csv', 'hex'].map((f) => (
                            <option key={f} value={f}>{f.toUpperCase()}</option>
                        ))}
                    </select>
                </div>
                <div className="flex-1 min-w-36">
                    <label className="block text-xs font-medium text-om-muted mb-1">Description</label>
                    <input
                        type="text"
                        value={form.data.description}
                        onChange={(e) => form.setData('description', e.target.value)}
                        className="w-full px-2 py-1.5 text-sm border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent"
                    />
                </div>
                <div className="flex gap-2">
                    <button type="submit" disabled={form.processing} className="px-3 py-1.5 bg-om-ink text-white text-xs rounded-om-sm hover:bg-black transition-colors disabled:opacity-50">
                        Save
                    </button>
                    <button type="button" onClick={onClose} className="px-3 py-1.5 bg-om-chip text-om-muted text-xs rounded-om-sm hover:bg-om-line2 transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    );
}

function MappingRow({ mapping, topic, connectionId }) {
    const [editOpen, setEditOpen] = useState(false);
    const color = ACTION_COLORS[mapping.action_type] ?? 'bg-om-chip text-om-muted';
    const label = ACTION_LABELS[mapping.action_type] ?? mapping.action_type;
    const priority = String(mapping.priority).padStart(3, '0');

    const handleDelete = () => {
        if (confirm('Delete this mapping?')) {
            router.delete(
                `/admin/connectivity/mqtt/${connectionId}/topics/${topic.id}/mappings/${mapping.id}`,
                { preserveScroll: true },
            );
        }
    };

    return (
        <div className={`px-4 py-3 flex items-start gap-3 text-xs ${!mapping.is_active ? 'opacity-50' : ''}`}>
            <span className="shrink-0 text-om-faint tabular-nums mt-0.5">{priority}</span>

            <div className="flex-1 min-w-0 space-y-1">
                <div className="flex items-center gap-2 flex-wrap">
                    {mapping.field_path && (
                        <>
                            <span className="font-mono text-om-muted bg-om-chip px-1.5 py-0.5 rounded">
                                {mapping.field_path}
                            </span>
                            <span className="text-om-faint">→</span>
                        </>
                    )}
                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${color}`}>{label}</span>
                    {mapping.condition_expr && (
                        <span className="font-mono text-om-faint text-xs">if: {mapping.condition_expr}</span>
                    )}
                </div>
                {mapping.description && <p className="text-om-faint">{mapping.description}</p>}
                {mapping.action_params && (
                    <p className="font-mono text-om-faint break-all">
                        {JSON.stringify(mapping.action_params).substring(0, 120)}
                    </p>
                )}

                {editOpen && (
                    <EditMappingForm
                        mapping={mapping}
                        topic={topic}
                        connectionId={connectionId}
                        onClose={() => setEditOpen(false)}
                    />
                )}
            </div>

            <div className="flex items-center gap-1 shrink-0">
                <button
                    type="button"
                    onClick={() => setEditOpen((o) => !o)}
                    className="p-1 text-om-faint hover:text-om-ink rounded hover:bg-om-chip transition-colors"
                >
                    <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </button>
                <button
                    type="button"
                    onClick={handleDelete}
                    className="p-1 text-om-faint hover:text-om-blocked rounded hover:bg-om-chip transition-colors"
                >
                    <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    );
}

function EditMappingForm({ mapping, topic, connectionId, onClose }) {
    const form = useForm({
        field_path:     mapping.field_path ?? '',
        action_type:    mapping.action_type,
        condition_expr: mapping.condition_expr ?? '',
        priority:       String(mapping.priority),
        action_params:  mapping.action_params ? JSON.stringify(mapping.action_params, null, 2) : '',
        description:    mapping.description ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.put(`/admin/connectivity/mqtt/${connectionId}/topics/${topic.id}/mappings/${mapping.id}`, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <div className="mt-2 pt-2 border-t border-om-line2">
            <form onSubmit={submit} className="space-y-2">
                <div className="grid grid-cols-2 gap-2">
                    <MiniField label="Field path">
                        <input type="text" value={form.data.field_path} onChange={(e) => form.setData('field_path', e.target.value)} className="w-full px-2 py-1 text-xs font-mono border border-om-line rounded bg-om-card text-om-ink focus:ring-1 focus:ring-om-accent" />
                    </MiniField>
                    <MiniField label="Action type">
                        <select value={form.data.action_type} onChange={(e) => form.setData('action_type', e.target.value)} className="w-full px-2 py-1 text-xs border border-om-line rounded bg-om-card text-om-ink focus:ring-1 focus:ring-om-accent">
                            {Object.entries(ACTION_LABELS).map(([val, lbl]) => (
                                <option key={val} value={val}>{lbl}</option>
                            ))}
                        </select>
                    </MiniField>
                    <MiniField label="Condition">
                        <input type="text" value={form.data.condition_expr} onChange={(e) => form.setData('condition_expr', e.target.value)} className="w-full px-2 py-1 text-xs font-mono border border-om-line rounded bg-om-card text-om-ink focus:ring-1 focus:ring-om-accent" />
                    </MiniField>
                    <MiniField label="Priority">
                        <input type="number" value={form.data.priority} onChange={(e) => form.setData('priority', e.target.value)} min="1" max="9999" className="w-full px-2 py-1 text-xs border border-om-line rounded bg-om-card text-om-ink focus:ring-1 focus:ring-om-accent" />
                    </MiniField>
                </div>
                <MiniField label="Action params (JSON)">
                    <textarea value={form.data.action_params} onChange={(e) => form.setData('action_params', e.target.value)} rows={2} className="w-full px-2 py-1 text-xs font-mono border border-om-line rounded bg-om-card text-om-ink focus:ring-1 focus:ring-om-accent" />
                </MiniField>
                <MiniField label="Description">
                    <input type="text" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} className="w-full px-2 py-1 text-xs border border-om-line rounded bg-om-card text-om-ink focus:ring-1 focus:ring-om-accent" />
                </MiniField>
                <div className="flex gap-2">
                    <button type="submit" disabled={form.processing} className="px-2 py-1 bg-om-ink text-white text-xs rounded hover:bg-black transition-colors disabled:opacity-50">Save</button>
                    <button type="button" onClick={onClose} className="px-2 py-1 bg-om-chip text-om-muted text-xs rounded hover:bg-om-line2 transition-colors">Cancel</button>
                </div>
            </form>
        </div>
    );
}

function AddTopicForm({ connectionId }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ topic_pattern: '', payload_format: 'json', description: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(`/admin/connectivity/mqtt/${connectionId}/topics`, {
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
                Add topic
            </button>
            {open && (
                <form onSubmit={submit} className="mt-4 space-y-3">
                    <div className="grid grid-cols-3 gap-3">
                        <div className="col-span-2">
                            <label className="block text-xs font-medium text-om-muted mb-1">
                                Topic pattern <span className="text-om-faint font-normal">(supports + and # wildcards)</span>
                            </label>
                            <input
                                type="text"
                                value={form.data.topic_pattern}
                                onChange={(e) => form.setData('topic_pattern', e.target.value)}
                                placeholder="factory/line1/+/status"
                                required
                                className="w-full px-3 py-2 text-sm font-mono border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-om-muted mb-1">Payload format</label>
                            <select
                                value={form.data.payload_format}
                                onChange={(e) => form.setData('payload_format', e.target.value)}
                                className="w-full px-3 py-2 text-sm border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent"
                            >
                                <option value="json">JSON</option>
                                <option value="plain">Plain text</option>
                                <option value="csv">CSV</option>
                                <option value="hex">Hex</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-om-muted mb-1">Description (optional)</label>
                        <input
                            type="text"
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            placeholder="e.g. Production count from Line 1"
                            className="w-full px-3 py-2 text-sm border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent"
                        />
                    </div>
                    <div className="flex gap-2">
                        <button type="submit" disabled={form.processing} className="px-4 py-1.5 bg-om-ink text-white text-sm rounded-om-sm hover:bg-black transition-colors disabled:opacity-50">
                            Add Topic
                        </button>
                        <button type="button" onClick={() => setOpen(false)} className="px-4 py-1.5 bg-om-chip text-om-muted text-sm rounded-om-sm hover:bg-om-line2 transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}

function AddMappingForm({ connectionId, topicId, onClose }) {
    const form = useForm({
        field_path:     '',
        action_type:    Object.keys(ACTION_LABELS)[0],
        condition_expr: '',
        priority:       '100',
        action_params:  '',
        description:    '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(`/admin/connectivity/mqtt/${connectionId}/topics/${topicId}/mappings`, {
            preserveScroll: true,
            onSuccess: () => { form.reset(); onClose(); },
        });
    };

    return (
        <form onSubmit={submit} className="mt-3 space-y-3">
            <div className="grid grid-cols-2 gap-3">
                <MiniField label="Field path — e.g. $.qty or $.data.value">
                    <input type="text" value={form.data.field_path} onChange={(e) => form.setData('field_path', e.target.value)} placeholder="$.value" className="w-full px-2 py-1.5 text-xs font-mono border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent" />
                </MiniField>
                <MiniField label="Action type *">
                    <select value={form.data.action_type} onChange={(e) => form.setData('action_type', e.target.value)} required className="w-full px-2 py-1.5 text-xs border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent">
                        {Object.entries(ACTION_LABELS).map(([val, lbl]) => (
                            <option key={val} value={val}>{lbl}</option>
                        ))}
                    </select>
                </MiniField>
                <MiniField label="Condition — e.g. value > 0">
                    <input type="text" value={form.data.condition_expr} onChange={(e) => form.setData('condition_expr', e.target.value)} placeholder="value > 0" className="w-full px-2 py-1.5 text-xs font-mono border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent" />
                </MiniField>
                <MiniField label="Priority">
                    <input type="number" value={form.data.priority} onChange={(e) => form.setData('priority', e.target.value)} min="1" max="9999" className="w-full px-2 py-1.5 text-xs border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent" />
                </MiniField>
            </div>
            <MiniField label='Action params (JSON) — e.g. {"order_no_path":"$.order_no"}'>
                <textarea value={form.data.action_params} onChange={(e) => form.setData('action_params', e.target.value)} rows={3} placeholder='{"order_no_path": "$.order_no", "qty_path": "$.qty"}' className="w-full px-2 py-1.5 text-xs font-mono border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent" />
            </MiniField>
            <MiniField label="Description">
                <input type="text" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="e.g. Update produced qty from machine counter" className="w-full px-2 py-1.5 text-xs border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent" />
            </MiniField>
            <div className="flex gap-2">
                <button type="submit" disabled={form.processing} className="px-3 py-1.5 bg-om-ink text-white text-xs rounded-om-sm hover:bg-black transition-colors disabled:opacity-50">Add Mapping</button>
                <button type="button" onClick={onClose} className="px-3 py-1.5 bg-om-chip text-om-muted text-xs rounded-om-sm hover:bg-om-line2 transition-colors">Cancel</button>
            </div>
        </form>
    );
}

function MiniField({ label, children }) {
    return (
        <div>
            <label className="block text-xs text-om-muted mb-0.5">{label}</label>
            {children}
        </div>
    );
}

function LiveMessageLog({ initialMessages, initialLastId, messagesUrl }) {
    const [messages, setMessages] = useState([]);
    const [autoScroll, setAutoScroll] = useState(true);
    const lastIdRef = useRef(initialLastId);
    const logRef = useRef(null);

    useEffect(() => {
        const interval = setInterval(async () => {
            try {
                const res = await fetch(`${messagesUrl}?after_id=${lastIdRef.current}`);
                if (!res.ok) return;
                const data = await res.json();
                if (data.length === 0) return;
                const sorted = [...data].reverse();
                setMessages((prev) => {
                    const next = [...prev, ...sorted];
                    return next.length > 500 ? next.slice(next.length - 500) : next;
                });
                lastIdRef.current = Math.max(...data.map((m) => m.id ?? 0), lastIdRef.current);
            } catch (_) {}
        }, 3000);
        return () => clearInterval(interval);
    }, [messagesUrl]);

    useEffect(() => {
        if (autoScroll && logRef.current) {
            logRef.current.scrollTop = logRef.current.scrollHeight;
        }
    }, [messages, autoScroll]);

    const formatTime = (iso) => {
        if (!iso) return '';
        try { return formatTime(new Date(iso), { hour12: false }); } catch { return iso; }
    };

    const statusDot = (status) => {
        if (status === 'ok') return 'bg-om-running';
        if (status === 'error') return 'bg-om-blocked';
        return 'bg-om-downtime';
    };

    return (
        <div>
            <h2 className="text-lg font-semibold text-om-ink mb-3">Live Message Log</h2>
            <div className="bg-om-ink rounded-om border border-gray-700 overflow-hidden">
                {/* Toolbar */}
                <div className="flex items-center justify-between px-4 py-2.5 border-b border-gray-700 bg-om-ink/60">
                    <div className="flex items-center gap-3">
                        <div className="flex items-center gap-1.5">
                            <span className="w-2 h-2 rounded-full bg-om-running animate-pulse" />
                            <span className="text-xs text-om-faint">Live (polling)</span>
                        </div>
                        <span className="text-xs text-om-muted">{messages.length} new messages</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <label className="flex items-center gap-1.5 text-xs text-om-faint cursor-pointer">
                            <input
                                type="checkbox"
                                checked={autoScroll}
                                onChange={(e) => setAutoScroll(e.target.checked)}
                                className="rounded border-gray-600 text-om-accent"
                            />
                            Auto-scroll
                        </label>
                        <button onClick={() => setMessages([])} className="text-xs text-om-muted hover:text-om-faintest transition-colors">
                            Clear
                        </button>
                    </div>
                </div>

                {/* Log entries */}
                <div ref={logRef} className="h-96 overflow-y-auto font-mono text-xs p-4 space-y-2">
                    {/* Historic messages (server-side, dimmed) */}
                    {[...initialMessages].reverse().map((msg) => (
                        <div key={`init-${msg.id}`} className="flex gap-3 items-start opacity-60">
                            <span className="text-om-muted shrink-0 tabular-nums">{formatTime(msg.received_at)}</span>
                            <span className={`w-1.5 h-1.5 rounded-full mt-1 shrink-0 ${statusDot(msg.processing_status)}`} />
                            <span className="text-blue-300 shrink-0 max-w-xs truncate">{msg.topic}</span>
                            <span className="text-om-faintest break-all">{String(msg.raw_payload ?? '').substring(0, 200)}</span>
                        </div>
                    ))}

                    {/* Live messages */}
                    {messages.map((msg) => (
                        <div key={msg.id} className="flex gap-3 items-start">
                            <span className="text-om-muted shrink-0 tabular-nums">{formatTime(msg.received_at)}</span>
                            <span className={`w-1.5 h-1.5 rounded-full mt-1 shrink-0 ${statusDot(msg.processing_status)}`} />
                            <span className="text-blue-300 shrink-0 max-w-xs truncate">{msg.topic}</span>
                            <span className="text-om-faintest break-all">{String(msg.raw_payload ?? '').substring(0, 200)}</span>
                            {msg.processing_error && (
                                <span className="text-red-400 ml-1">⚠ {msg.processing_error}</span>
                            )}
                        </div>
                    ))}

                    {initialMessages.length === 0 && messages.length === 0 && (
                        <div className="text-om-muted text-center py-8">Waiting for messages...</div>
                    )}
                </div>
            </div>
        </div>
    );
}
