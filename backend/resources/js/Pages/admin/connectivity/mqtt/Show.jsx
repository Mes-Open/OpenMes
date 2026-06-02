import { useState, useEffect, useRef } from 'react';
import { Head, router, usePage, useForm } from '@inertiajs/react';
import AppLayout from '../../../../layouts/AppLayout';

const STATUS_DOT = {
    green:  'bg-green-500',
    yellow: 'bg-yellow-400',
    red:    'bg-red-500',
    slate:  'bg-slate-400',
};

const ACTION_COLORS = {
    update_batch_step:     'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300',
    update_work_order_qty: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
    create_issue:          'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300',
    update_line_status:    'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300',
    set_work_order_status: 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300',
    log_event:             'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300',
    webhook_forward:       'bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300',
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
                            className="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex items-center gap-1 mb-2"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            MQTT Connections
                        </a>
                        <div className="flex items-center gap-3">
                            <span className={`w-3 h-3 rounded-full ${dot} ${connection.status === 'connected' ? 'animate-pulse' : ''}`} />
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{connection.name}</h1>
                            {!connection.is_active && (
                                <span className="text-xs px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-full">
                                    Inactive
                                </span>
                            )}
                        </div>
                        {mqtt && (
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400 font-mono">
                                {mqtt.broker_host}:{mqtt.broker_port}
                                {mqtt.use_tls && <span className="ml-2 text-green-600 dark:text-green-400">TLS</span>}
                                {' · '}QoS {mqtt.qos_default}
                            </p>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <a
                            href={`/admin/connectivity/mqtt/${connection.id}/edit`}
                            className="px-4 py-2 text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                        >
                            Edit
                        </a>
                        <button
                            type="button"
                            onClick={handleToggle}
                            className={`px-4 py-2 text-sm font-medium rounded-lg transition-colors ${
                                connection.is_active
                                    ? 'bg-yellow-50 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300 hover:bg-yellow-100'
                                    : 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 hover:bg-green-100'
                            }`}
                        >
                            {connection.is_active ? 'Disable' : 'Enable'}
                        </button>
                    </div>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-3 gap-4">
                    <StatCard value={connection.topics.length} label="Topics" />
                    <StatCard value={Number(connection.messages_received).toLocaleString()} label="Messages received" />
                    <StatCard value={connection.status} label={connection.last_connected_at ?? 'Never'} capitalize />
                </div>

                {/* Topics & Mappings */}
                <div>
                    <div className="flex items-center justify-between mb-3">
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Topics &amp; Mappings</h2>
                    </div>

                    <div className="space-y-4">
                        {connection.topics.length === 0 ? (
                            <div className="bg-white dark:bg-gray-800 rounded-xl border border-dashed border-gray-300 dark:border-gray-600 p-8 text-center text-gray-400 dark:text-gray-500">
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
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
            <p className={`text-2xl font-bold text-gray-900 dark:text-white ${capitalize ? 'capitalize' : ''}`}>
                {value}
            </p>
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">{label}</p>
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
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            {/* Topic header */}
            <div className="flex items-center gap-3 px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                <span className={`w-2 h-2 rounded-full shrink-0 ${topic.is_active ? 'bg-green-500' : 'bg-slate-400'}`} />
                <span className="font-mono text-sm font-medium text-gray-900 dark:text-white flex-1">{topic.topic_pattern}</span>
                <span className="text-xs px-2 py-0.5 bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300 rounded-full uppercase">
                    {topic.payload_format}
                </span>
                {topic.description && (
                    <span className="text-xs text-gray-400 dark:text-gray-500 max-w-xs truncate">{topic.description}</span>
                )}
                <div className="flex items-center gap-1 shrink-0">
                    <button
                        type="button"
                        onClick={() => setEditOpen((o) => !o)}
                        className="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-md hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                    >
                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        onClick={handleDeleteTopic}
                        className="p-1.5 text-gray-400 hover:text-red-500 rounded-md hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
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
            <div className="divide-y divide-gray-100 dark:divide-gray-700">
                {topic.mappings.length === 0 ? (
                    <p className="px-4 py-3 text-xs text-gray-400 dark:text-gray-500 italic">
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
            <div className="px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                <button
                    type="button"
                    onClick={() => setAddMappingOpen((o) => !o)}
                    className="flex items-center gap-1.5 text-xs font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700"
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
        <div className="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-blue-50/40 dark:bg-blue-900/10">
            <form onSubmit={submit} className="flex gap-3 items-end flex-wrap">
                <div className="flex-1 min-w-48">
                    <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Pattern</label>
                    <input
                        type="text"
                        value={form.data.topic_pattern}
                        onChange={(e) => form.setData('topic_pattern', e.target.value)}
                        required
                        className="w-full px-2 py-1.5 text-sm font-mono border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Format</label>
                    <select
                        value={form.data.payload_format}
                        onChange={(e) => form.setData('payload_format', e.target.value)}
                        className="px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        {['json', 'plain', 'csv', 'hex'].map((f) => (
                            <option key={f} value={f}>{f.toUpperCase()}</option>
                        ))}
                    </select>
                </div>
                <div className="flex-1 min-w-36">
                    <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Description</label>
                    <input
                        type="text"
                        value={form.data.description}
                        onChange={(e) => form.setData('description', e.target.value)}
                        className="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    />
                </div>
                <div className="flex gap-2">
                    <button type="submit" disabled={form.processing} className="px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
                        Save
                    </button>
                    <button type="button" onClick={onClose} className="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs rounded-lg hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    );
}

function MappingRow({ mapping, topic, connectionId }) {
    const [editOpen, setEditOpen] = useState(false);
    const color = ACTION_COLORS[mapping.action_type] ?? 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300';
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
            <span className="shrink-0 text-gray-400 dark:text-gray-500 tabular-nums mt-0.5">{priority}</span>

            <div className="flex-1 min-w-0 space-y-1">
                <div className="flex items-center gap-2 flex-wrap">
                    {mapping.field_path && (
                        <>
                            <span className="font-mono text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">
                                {mapping.field_path}
                            </span>
                            <span className="text-gray-400">→</span>
                        </>
                    )}
                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${color}`}>{label}</span>
                    {mapping.condition_expr && (
                        <span className="font-mono text-gray-400 dark:text-gray-500 text-xs">if: {mapping.condition_expr}</span>
                    )}
                </div>
                {mapping.description && <p className="text-gray-400 dark:text-gray-500">{mapping.description}</p>}
                {mapping.action_params && (
                    <p className="font-mono text-gray-400 dark:text-gray-500 break-all">
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
                    className="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                >
                    <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </button>
                <button
                    type="button"
                    onClick={handleDelete}
                    className="p-1 text-gray-400 hover:text-red-500 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
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
        <div className="mt-2 pt-2 border-t border-gray-100 dark:border-gray-700">
            <form onSubmit={submit} className="space-y-2">
                <div className="grid grid-cols-2 gap-2">
                    <MiniField label="Field path">
                        <input type="text" value={form.data.field_path} onChange={(e) => form.setData('field_path', e.target.value)} className="w-full px-2 py-1 text-xs font-mono border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-blue-500" />
                    </MiniField>
                    <MiniField label="Action type">
                        <select value={form.data.action_type} onChange={(e) => form.setData('action_type', e.target.value)} className="w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-blue-500">
                            {Object.entries(ACTION_LABELS).map(([val, lbl]) => (
                                <option key={val} value={val}>{lbl}</option>
                            ))}
                        </select>
                    </MiniField>
                    <MiniField label="Condition">
                        <input type="text" value={form.data.condition_expr} onChange={(e) => form.setData('condition_expr', e.target.value)} className="w-full px-2 py-1 text-xs font-mono border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-blue-500" />
                    </MiniField>
                    <MiniField label="Priority">
                        <input type="number" value={form.data.priority} onChange={(e) => form.setData('priority', e.target.value)} min="1" max="9999" className="w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-blue-500" />
                    </MiniField>
                </div>
                <MiniField label="Action params (JSON)">
                    <textarea value={form.data.action_params} onChange={(e) => form.setData('action_params', e.target.value)} rows={2} className="w-full px-2 py-1 text-xs font-mono border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-blue-500" />
                </MiniField>
                <MiniField label="Description">
                    <input type="text" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} className="w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-blue-500" />
                </MiniField>
                <div className="flex gap-2">
                    <button type="submit" disabled={form.processing} className="px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition-colors disabled:opacity-50">Save</button>
                    <button type="button" onClick={onClose} className="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs rounded hover:bg-gray-200 transition-colors">Cancel</button>
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
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className="flex items-center gap-2 text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700"
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
                            <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                Topic pattern <span className="text-gray-400 font-normal">(supports + and # wildcards)</span>
                            </label>
                            <input
                                type="text"
                                value={form.data.topic_pattern}
                                onChange={(e) => form.setData('topic_pattern', e.target.value)}
                                placeholder="factory/line1/+/status"
                                required
                                className="w-full px-3 py-2 text-sm font-mono border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Payload format</label>
                            <select
                                value={form.data.payload_format}
                                onChange={(e) => form.setData('payload_format', e.target.value)}
                                className="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="json">JSON</option>
                                <option value="plain">Plain text</option>
                                <option value="csv">CSV</option>
                                <option value="hex">Hex</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Description (optional)</label>
                        <input
                            type="text"
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            placeholder="e.g. Production count from Line 1"
                            className="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                    </div>
                    <div className="flex gap-2">
                        <button type="submit" disabled={form.processing} className="px-4 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
                            Add Topic
                        </button>
                        <button type="button" onClick={() => setOpen(false)} className="px-4 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm rounded-lg hover:bg-gray-200 transition-colors">
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
                    <input type="text" value={form.data.field_path} onChange={(e) => form.setData('field_path', e.target.value)} placeholder="$.value" className="w-full px-2 py-1.5 text-xs font-mono border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                </MiniField>
                <MiniField label="Action type *">
                    <select value={form.data.action_type} onChange={(e) => form.setData('action_type', e.target.value)} required className="w-full px-2 py-1.5 text-xs border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        {Object.entries(ACTION_LABELS).map(([val, lbl]) => (
                            <option key={val} value={val}>{lbl}</option>
                        ))}
                    </select>
                </MiniField>
                <MiniField label="Condition — e.g. value > 0">
                    <input type="text" value={form.data.condition_expr} onChange={(e) => form.setData('condition_expr', e.target.value)} placeholder="value > 0" className="w-full px-2 py-1.5 text-xs font-mono border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                </MiniField>
                <MiniField label="Priority">
                    <input type="number" value={form.data.priority} onChange={(e) => form.setData('priority', e.target.value)} min="1" max="9999" className="w-full px-2 py-1.5 text-xs border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                </MiniField>
            </div>
            <MiniField label='Action params (JSON) — e.g. {"order_no_path":"$.order_no"}'>
                <textarea value={form.data.action_params} onChange={(e) => form.setData('action_params', e.target.value)} rows={3} placeholder='{"order_no_path": "$.order_no", "qty_path": "$.qty"}' className="w-full px-2 py-1.5 text-xs font-mono border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            </MiniField>
            <MiniField label="Description">
                <input type="text" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="e.g. Update produced qty from machine counter" className="w-full px-2 py-1.5 text-xs border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            </MiniField>
            <div className="flex gap-2">
                <button type="submit" disabled={form.processing} className="px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">Add Mapping</button>
                <button type="button" onClick={onClose} className="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs rounded-lg hover:bg-gray-200 transition-colors">Cancel</button>
            </div>
        </form>
    );
}

function MiniField({ label, children }) {
    return (
        <div>
            <label className="block text-xs text-gray-500 dark:text-gray-400 mb-0.5">{label}</label>
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
        try { return new Date(iso).toLocaleTimeString('pl-PL', { hour12: false }); } catch { return iso; }
    };

    const statusDot = (status) => {
        if (status === 'ok') return 'bg-green-500';
        if (status === 'error') return 'bg-red-500';
        return 'bg-yellow-500';
    };

    return (
        <div>
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-3">Live Message Log</h2>
            <div className="bg-gray-900 dark:bg-gray-950 rounded-xl border border-gray-700 overflow-hidden">
                {/* Toolbar */}
                <div className="flex items-center justify-between px-4 py-2.5 border-b border-gray-700 bg-gray-800/60">
                    <div className="flex items-center gap-3">
                        <div className="flex items-center gap-1.5">
                            <span className="w-2 h-2 rounded-full bg-green-500 animate-pulse" />
                            <span className="text-xs text-gray-400">Live (polling)</span>
                        </div>
                        <span className="text-xs text-gray-500">{messages.length} new messages</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <label className="flex items-center gap-1.5 text-xs text-gray-400 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={autoScroll}
                                onChange={(e) => setAutoScroll(e.target.checked)}
                                className="rounded border-gray-600 text-blue-500"
                            />
                            Auto-scroll
                        </label>
                        <button onClick={() => setMessages([])} className="text-xs text-gray-500 hover:text-gray-300 transition-colors">
                            Clear
                        </button>
                    </div>
                </div>

                {/* Log entries */}
                <div ref={logRef} className="h-96 overflow-y-auto font-mono text-xs p-4 space-y-2">
                    {/* Historic messages (server-side, dimmed) */}
                    {[...initialMessages].reverse().map((msg) => (
                        <div key={`init-${msg.id}`} className="flex gap-3 items-start opacity-60">
                            <span className="text-gray-500 shrink-0 tabular-nums">{formatTime(msg.received_at)}</span>
                            <span className={`w-1.5 h-1.5 rounded-full mt-1 shrink-0 ${statusDot(msg.processing_status)}`} />
                            <span className="text-blue-300 shrink-0 max-w-xs truncate">{msg.topic}</span>
                            <span className="text-gray-300 break-all">{String(msg.raw_payload ?? '').substring(0, 200)}</span>
                        </div>
                    ))}

                    {/* Live messages */}
                    {messages.map((msg) => (
                        <div key={msg.id} className="flex gap-3 items-start">
                            <span className="text-gray-500 shrink-0 tabular-nums">{formatTime(msg.received_at)}</span>
                            <span className={`w-1.5 h-1.5 rounded-full mt-1 shrink-0 ${statusDot(msg.processing_status)}`} />
                            <span className="text-blue-300 shrink-0 max-w-xs truncate">{msg.topic}</span>
                            <span className="text-gray-300 break-all">{String(msg.raw_payload ?? '').substring(0, 200)}</span>
                            {msg.processing_error && (
                                <span className="text-red-400 ml-1">⚠ {msg.processing_error}</span>
                            )}
                        </div>
                    ))}

                    {initialMessages.length === 0 && messages.length === 0 && (
                        <div className="text-gray-600 text-center py-8">Waiting for messages...</div>
                    )}
                </div>
            </div>
        </div>
    );
}
