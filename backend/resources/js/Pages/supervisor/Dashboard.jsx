import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';
import { __ } from '../../lib/i18n';

const ISSUE_STATUS_STYLES = {
    OPEN: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200',
    ACKNOWLEDGED: 'bg-yellow-100 text-yellow-800',
    RESOLVED: 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200',
    CLOSED: 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300',
};

export default function SupervisorDashboard() {
    const { lines = [], selectedLineId, stats = {}, throughput = {}, issueStats = {}, recentIssues = [] } = usePage().props;

    const changeLine = (id) => router.get('/supervisor/dashboard', id ? { line_id: id } : {}, { preserveState: false });

    return (
        <>
            <Head title={__('Supervisor Dashboard')} />
            <div className="max-w-7xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-3xl font-bold text-gray-800 dark:text-gray-100">{__('Supervisor Dashboard')}</h1>
                    <select value={selectedLineId ?? ''} onChange={(e) => changeLine(e.target.value)} className="form-input py-1.5 text-sm min-w-[180px]">
                        {lines.map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
                    </select>
                </div>

                <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
                    <Kpi label={__('Total WOs')} value={stats.total_work_orders} />
                    <Kpi label={__('Active')} value={stats.active_work_orders} accent="blue" />
                    <Kpi label={__('Completed')} value={stats.completed_work_orders} accent="green" />
                    <Kpi label={__('Blocked')} value={stats.blocked_work_orders} accent="red" />
                    <Kpi label={__('Open Issues')} value={stats.open_issues} accent="yellow" />
                    <Kpi label={__('Blocking')} value={stats.blocking_issues} accent="red" />
                </div>

                <div className="grid lg:grid-cols-2 gap-6">
                    <Card title={__('Throughput (30 days) · avg :avg', { avg: throughput.average ?? 0 })}>
                        <BarList labels={throughput.labels} values={throughput.values} unit="" />
                    </Card>
                    <Card title={__('Issues by type (30 days)')}>
                        <BarList labels={issueStats.by_type?.labels} values={issueStats.by_type?.values} unit="" color="bg-amber-400" />
                    </Card>
                </div>

                <Card title={__('Recent issues')}>
                    {recentIssues.length === 0 ? (
                        <p className="text-gray-400 dark:text-gray-500 text-sm">{__('No issues.')} 🎉</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-gray-500 dark:text-gray-400 border-b">
                                    <th className="py-2">{__('Issue')}</th><th className="py-2">{__('Type')}</th><th className="py-2">{__('WO')}</th>
                                    <th className="py-2">{__('Reported')}</th><th className="py-2">{__('Status')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentIssues.map((i) => (
                                    <tr key={i.id} className="border-b last:border-0">
                                        <td className="py-2 font-medium text-gray-800 dark:text-gray-100">{i.title}</td>
                                        <td className="py-2 text-gray-600 dark:text-gray-300">{i.type ?? '—'}</td>
                                        <td className="py-2 text-gray-600 dark:text-gray-300">{i.work_order ?? '—'}</td>
                                        <td className="py-2 text-gray-500 dark:text-gray-400">{i.reported_at ?? '—'}</td>
                                        <td className="py-2"><span className={`text-xs px-2 py-0.5 rounded font-medium ${ISSUE_STATUS_STYLES[i.status] ?? 'bg-gray-100 dark:bg-gray-700'}`}>{__(i.status)}</span></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </Card>
            </div>
        </>
    );
}

SupervisorDashboard.layout = (page) => <AppLayout>{page}</AppLayout>;

function Kpi({ label, value, accent }) {
    const colors = { blue: 'text-blue-600 dark:text-blue-300', green: 'text-green-600 dark:text-green-300', red: 'text-red-600 dark:text-red-300', yellow: 'text-yellow-600' };
    return (
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
            <p className="text-sm text-gray-500 dark:text-gray-400 mb-1">{label}</p>
            <p className={`text-3xl font-bold ${colors[accent] ?? 'text-gray-800 dark:text-gray-100'}`}>{value ?? 0}</p>
        </div>
    );
}

function Card({ title, children }) {
    return (
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5">
            <h2 className="text-lg font-bold text-gray-800 dark:text-gray-100 mb-3">{title}</h2>
            {children}
        </div>
    );
}

function BarList({ labels = [], values = [], unit = '', color = 'bg-blue-500' }) {
    if (!labels?.length) return <p className="text-gray-400 dark:text-gray-500 text-sm">{__('No data.')}</p>;
    const max = Math.max(...values, 1);
    return (
        <div className="space-y-1.5">
            {labels.map((label, i) => (
                <div key={i} className="flex items-center gap-2 text-xs">
                    <span className="w-20 shrink-0 text-gray-500 dark:text-gray-400 truncate text-right">{label}</span>
                    <div className="flex-1 bg-gray-100 dark:bg-gray-700 rounded h-4">
                        <div className={`${color} h-4 rounded`} style={{ width: `${(values[i] / max) * 100}%` }} />
                    </div>
                    <span className="w-12 shrink-0 text-gray-700 dark:text-gray-200 font-medium">{values[i]}{unit}</span>
                </div>
            ))}
        </div>
    );
}
