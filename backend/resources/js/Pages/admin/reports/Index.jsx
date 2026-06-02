import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';

const WO_STATUS_STYLES = {
    PENDING: 'bg-yellow-400', ACCEPTED: 'bg-blue-400', IN_PROGRESS: 'bg-emerald-400',
    PAUSED: 'bg-gray-400', BLOCKED: 'bg-red-400', DONE: 'bg-green-500', REJECTED: 'bg-red-500', CANCELLED: 'bg-gray-300',
};

export default function ReportsIndex() {
    const p = usePage().props;
    const { period, year, month, week, lineId, lines = [], availableYears = [],
        totalWorkOrders = 0, completedWorkOrders = 0, completionRate = 0, totalProducedQty = 0, avgCycleTime,
        byLine = [], byStatus = [], topIssues = [] } = p;

    const base = typeof window !== 'undefined' && window.location.pathname.startsWith('/supervisor') ? '/supervisor' : '/admin';
    const apply = (changes) => router.get(`${base}/reports`, { period, year, month, week, line_id: lineId || '', ...changes }, { preserveState: false });

    const maxStatus = Math.max(...byStatus.map((s) => s.count), 1);
    const maxIssue = Math.max(...topIssues.map((i) => i.count), 1);

    return (
        <>
            <Head title="Reports" />
            <div className="max-w-7xl mx-auto space-y-6">
                <h1 className="text-3xl font-bold text-gray-800">Production Reports</h1>

                {/* Filters */}
                <div className="bg-white rounded-lg shadow-sm p-4 flex flex-wrap items-end gap-4">
                    <Filter label="Period">
                        <select value={period} onChange={(e) => apply({ period: e.target.value })} className="form-input py-1.5 text-sm">
                            <option value="monthly">Monthly</option>
                            <option value="weekly">Weekly</option>
                        </select>
                    </Filter>
                    <Filter label="Year">
                        <select value={year} onChange={(e) => apply({ year: e.target.value })} className="form-input py-1.5 text-sm">
                            {availableYears.map((y) => <option key={y} value={y}>{y}</option>)}
                        </select>
                    </Filter>
                    {period === 'monthly' ? (
                        <Filter label="Month">
                            <select value={month} onChange={(e) => apply({ month: e.target.value })} className="form-input py-1.5 text-sm">
                                {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => <option key={m} value={m}>{m}</option>)}
                            </select>
                        </Filter>
                    ) : (
                        <Filter label="Week">
                            <input type="number" min="1" max="53" value={week} onChange={(e) => apply({ week: e.target.value })} className="form-input py-1.5 text-sm w-20" />
                        </Filter>
                    )}
                    <Filter label="Line">
                        <select value={lineId ?? ''} onChange={(e) => apply({ line_id: e.target.value })} className="form-input py-1.5 text-sm min-w-[160px]">
                            <option value="">All lines</option>
                            {lines.map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
                        </select>
                    </Filter>
                </div>

                {/* KPIs */}
                <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <Kpi label="Work Orders" value={totalWorkOrders} />
                    <Kpi label="Completed" value={completedWorkOrders} accent="green" />
                    <Kpi label="Completion" value={`${completionRate}%`} accent="blue" />
                    <Kpi label="Produced Qty" value={Number(totalProducedQty).toFixed(0)} />
                    <Kpi label="Avg Cycle (min)" value={avgCycleTime ?? '—'} />
                </div>

                <div className="grid lg:grid-cols-2 gap-6">
                    <Card title="By status">
                        {byStatus.length === 0 ? <Empty /> : (
                            <div className="space-y-1.5">
                                {byStatus.map((s) => (
                                    <div key={s.status} className="flex items-center gap-2 text-xs">
                                        <span className="w-24 shrink-0 text-gray-600 text-right">{s.status}</span>
                                        <div className="flex-1 bg-gray-100 rounded h-4">
                                            <div className={`${WO_STATUS_STYLES[s.status] ?? 'bg-gray-400'} h-4 rounded`} style={{ width: `${(s.count / maxStatus) * 100}%` }} />
                                        </div>
                                        <span className="w-16 shrink-0 text-gray-700 font-medium">{s.count} ({s.pct}%)</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>
                    <Card title="Top issues">
                        {topIssues.length === 0 ? <Empty /> : (
                            <div className="space-y-1.5">
                                {topIssues.map((i) => (
                                    <div key={i.type_name} className="flex items-center gap-2 text-xs">
                                        <span className="w-28 shrink-0 text-gray-600 text-right truncate">{i.type_name}</span>
                                        <div className="flex-1 bg-gray-100 rounded h-4"><div className="bg-amber-400 h-4 rounded" style={{ width: `${(i.count / maxIssue) * 100}%` }} /></div>
                                        <span className="w-8 shrink-0 text-gray-700 font-medium">{i.count}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>
                </div>

                <Card title="By line">
                    {byLine.length === 0 ? <Empty /> : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-gray-500 border-b">
                                    <th className="py-2">Line</th><th className="py-2 text-right">Orders</th><th className="py-2 text-right">Completed</th>
                                    <th className="py-2 text-right">Planned</th><th className="py-2 text-right">Produced</th><th className="py-2 text-right">Completion</th>
                                </tr>
                            </thead>
                            <tbody>
                                {byLine.map((r) => (
                                    <tr key={r.line_name} className="border-b last:border-0">
                                        <td className="py-2 font-medium text-gray-800">{r.line_name}</td>
                                        <td className="py-2 text-right text-gray-600">{r.total_orders}</td>
                                        <td className="py-2 text-right text-gray-600">{r.completed_orders}</td>
                                        <td className="py-2 text-right text-gray-600">{Number(r.planned_qty).toFixed(0)}</td>
                                        <td className="py-2 text-right text-gray-600">{Number(r.produced_qty).toFixed(0)}</td>
                                        <td className="py-2 text-right font-medium text-gray-800">{r.completion_pct}%</td>
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

ReportsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;

function Filter({ label, children }) {
    return <div><label className="block text-xs font-medium text-gray-500 mb-1">{label}</label>{children}</div>;
}
function Kpi({ label, value, accent }) {
    const c = { green: 'text-green-600', blue: 'text-blue-600' };
    return <div className="bg-white rounded-lg shadow-sm p-4"><p className="text-sm text-gray-500 mb-1">{label}</p><p className={`text-2xl font-bold ${c[accent] ?? 'text-gray-800'}`}>{value}</p></div>;
}
function Card({ title, children }) {
    return <div className="bg-white rounded-lg shadow-sm p-5"><h2 className="text-lg font-bold text-gray-800 mb-3">{title}</h2>{children}</div>;
}
function Empty() { return <p className="text-gray-400 text-sm">No data for this period.</p>; }
