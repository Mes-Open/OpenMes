import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';

const ISSUE_STATUS_STYLES = {
    OPEN: 'bg-red-100 text-red-800',
    ACKNOWLEDGED: 'bg-yellow-100 text-yellow-800',
    RESOLVED: 'bg-green-100 text-green-800',
    CLOSED: 'bg-gray-200 text-gray-600',
};

export default function SupervisorDashboard() {
    const { lines = [], selectedLineId, stats = {}, throughput = {}, issueStats = {}, recentIssues = [] } = usePage().props;

    const changeLine = (id) => router.get('/supervisor/dashboard', id ? { line_id: id } : {}, { preserveState: false });

    return (
        <>
            <Head title="Supervisor Dashboard" />
            <div className="max-w-7xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-3xl font-bold text-gray-800">Supervisor Dashboard</h1>
                    <select value={selectedLineId ?? ''} onChange={(e) => changeLine(e.target.value)} className="form-input py-1.5 text-sm min-w-[180px]">
                        {lines.map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
                    </select>
                </div>

                <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
                    <Kpi label="Total WOs" value={stats.total_work_orders} />
                    <Kpi label="Active" value={stats.active_work_orders} accent="blue" />
                    <Kpi label="Completed" value={stats.completed_work_orders} accent="green" />
                    <Kpi label="Blocked" value={stats.blocked_work_orders} accent="red" />
                    <Kpi label="Open Issues" value={stats.open_issues} accent="yellow" />
                    <Kpi label="Blocking" value={stats.blocking_issues} accent="red" />
                </div>

                <div className="grid lg:grid-cols-2 gap-6">
                    <Card title={`Throughput (30 days) · avg ${throughput.average ?? 0}`}>
                        <BarList labels={throughput.labels} values={throughput.values} unit="" />
                    </Card>
                    <Card title="Issues by type (30 days)">
                        <BarList labels={issueStats.by_type?.labels} values={issueStats.by_type?.values} unit="" color="bg-amber-400" />
                    </Card>
                </div>

                <Card title="Recent issues">
                    {recentIssues.length === 0 ? (
                        <p className="text-gray-400 text-sm">No issues. 🎉</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-gray-500 border-b">
                                    <th className="py-2">Issue</th><th className="py-2">Type</th><th className="py-2">WO</th>
                                    <th className="py-2">Reported</th><th className="py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentIssues.map((i) => (
                                    <tr key={i.id} className="border-b last:border-0">
                                        <td className="py-2 font-medium text-gray-800">{i.title}</td>
                                        <td className="py-2 text-gray-600">{i.type ?? '—'}</td>
                                        <td className="py-2 text-gray-600">{i.work_order ?? '—'}</td>
                                        <td className="py-2 text-gray-500">{i.reported_at ?? '—'}</td>
                                        <td className="py-2"><span className={`text-xs px-2 py-0.5 rounded font-medium ${ISSUE_STATUS_STYLES[i.status] ?? 'bg-gray-100'}`}>{i.status}</span></td>
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
    const colors = { blue: 'text-blue-600', green: 'text-green-600', red: 'text-red-600', yellow: 'text-yellow-600' };
    return (
        <div className="bg-white rounded-lg shadow-sm p-4">
            <p className="text-sm text-gray-500 mb-1">{label}</p>
            <p className={`text-3xl font-bold ${colors[accent] ?? 'text-gray-800'}`}>{value ?? 0}</p>
        </div>
    );
}

function Card({ title, children }) {
    return (
        <div className="bg-white rounded-lg shadow-sm p-5">
            <h2 className="text-lg font-bold text-gray-800 mb-3">{title}</h2>
            {children}
        </div>
    );
}

function BarList({ labels = [], values = [], unit = '', color = 'bg-blue-500' }) {
    if (!labels?.length) return <p className="text-gray-400 text-sm">No data.</p>;
    const max = Math.max(...values, 1);
    return (
        <div className="space-y-1.5">
            {labels.map((label, i) => (
                <div key={i} className="flex items-center gap-2 text-xs">
                    <span className="w-20 shrink-0 text-gray-500 truncate text-right">{label}</span>
                    <div className="flex-1 bg-gray-100 rounded h-4">
                        <div className={`${color} h-4 rounded`} style={{ width: `${(values[i] / max) * 100}%` }} />
                    </div>
                    <span className="w-12 shrink-0 text-gray-700 font-medium">{values[i]}{unit}</span>
                </div>
            ))}
        </div>
    );
}
