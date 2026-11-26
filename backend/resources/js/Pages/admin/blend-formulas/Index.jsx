import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { formatNumber } from '../../../lib/i18n';

const STATUS = {
    DRAFT: { label: '草稿', className: 'bg-om-chip text-om-muted' },
    SAMPLE: { label: '小样', className: 'bg-om-downtime-bg text-om-downtime' },
    LARGE_SAMPLE: { label: '大样', className: 'bg-om-downtime-bg text-om-downtime' },
    APPROVED: { label: '批准', className: 'bg-om-running-bg text-om-running' },
    FROZEN: { label: '冻结', className: 'border border-om-line bg-om-card text-om-ink' },
};

const RESULT = {
    PASS: { label: '合格', className: 'text-om-running' },
    FAIL: { label: '超差', className: 'text-om-blocked' },
    PENDING: { label: '待样', className: 'text-om-faint' },
    null: { label: '—', className: 'text-om-faint' },
};

export default function BlendFormulaIndex() {
    const { formulas = [], summary = {}, filters = {}, sourceTeaBase = null } = usePage().props;
    const activeStatus = filters.status ?? null;

    const query = (status) => {
        const params = new URLSearchParams();
        if (status) params.set('status', status);
        if (sourceTeaBase) params.set('tea_base', sourceTeaBase.id);
        const suffix = params.toString();
        return `/admin/blend-formulas${suffix ? `?${suffix}` : ''}`;
    };

    const filterItems = [
        { key: null, label: '全部', count: summary.total ?? 0 },
        { key: 'DRAFT', label: '草稿', count: summary.draft ?? 0 },
        { key: 'IN_REVIEW', label: '在评', count: summary.pending ?? 0 },
        { key: 'APPROVED', label: '批准', count: summary.approved ?? 0 },
        { key: 'FROZEN', label: '冻结', count: summary.frozen ?? 0 },
    ];

    return (
        <>
            <Head title="拼配版本" />
            <div className="mx-auto w-full max-w-[1180px] space-y-4 px-4 pb-10 sm:px-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="text-2xl font-bold tracking-tight text-om-ink">拼配版本</h1>
                        <p className="mt-1 max-w-[720px] text-sm leading-6 text-om-muted">
                            版本工作台：配方、标准样和审批时间线放在一起。先看版本差异和冻结状态，再决定这一版能不能放大。
                        </p>
                        <p className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                            <span className="text-om-muted">共 <span className="font-mono font-bold text-om-ink">{summary.total ?? formulas.length}</span> 版</span>
                            <span className="text-om-running">批准 {summary.approved ?? 0}</span>
                            <span className="text-om-downtime">在评 {summary.pending ?? 0}</span>
                            <span className="text-om-ink">冻结 {summary.frozen ?? 0}</span>
                            <span className="text-om-muted">草稿 {summary.draft ?? 0}</span>
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Link href="/admin/tea-bases" className="btn-touch btn-secondary text-sm">茶底档案</Link>
                        <Link href="/admin/quality" className="btn-touch btn-secondary text-sm">质量指挥台</Link>
                    </div>
                </div>

                {sourceTeaBase && (
                    <div className="flex flex-wrap items-center justify-between gap-2 rounded-om-sm border border-om-line bg-om-chip/40 px-4 py-2.5">
                        <p className="text-xs text-om-muted">
                            从茶底 <span className="font-mono font-bold text-om-ink">{sourceTeaBase.code}</span> · {sourceTeaBase.name} 进入，只显示使用这支茶底的版本。
                        </p>
                        <Link href="/admin/blend-formulas" className="text-xs font-bold text-om-accent hover:underline">查看全部版本 →</Link>
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-2">
                    {filterItems.map((item) => {
                        const active = activeStatus === item.key;
                        return (
                            <Link
                                key={item.key ?? 'all'}
                                href={query(item.key)}
                                className={`btn-touch text-xs ${active ? 'border border-om-ink bg-om-ink text-white' : 'border border-om-line bg-om-card text-om-muted hover:bg-om-chip'}`}
                            >
                                {item.label}
                                <span className={`ml-1.5 font-mono ${active ? 'text-white/70' : 'text-om-faint'}`}>{item.count}</span>
                                {item.note && <span className={`ml-1 font-normal ${active ? 'text-white/60' : 'text-om-faint'}`}>{item.note}</span>}
                            </Link>
                        );
                    })}
                </div>

                <div className="hidden overflow-hidden rounded-om-sm border border-om-line bg-om-card lg:block">
                    <table className="w-full table-fixed text-sm">
                        <thead>
                            <tr className="border-b border-om-line2 bg-om-chip/40 text-left text-[11px] font-bold tracking-wide text-om-muted">
                                <th className="w-[13%] px-4 py-3">版本</th>
                                <th className="w-[20%] px-3 py-3">客户 / 产品</th>
                                <th className="w-[10%] px-3 py-3">状态</th>
                                <th className="w-[14%] px-3 py-3">标准样</th>
                                <th className="w-[9%] px-3 py-3">小样</th>
                                <th className="w-[9%] px-3 py-3">大样</th>
                                <th className="w-[13%] px-3 py-3">配方</th>
                                <th className="w-[12%] px-3 py-3">生效 / 批准</th>
                            </tr>
                        </thead>
                        <tbody>
                            {formulas.map((formula) => (
                                <tr key={formula.id} className="border-b border-om-line2 transition-colors last:border-0 hover:bg-om-chip/20">
                                    <td className="px-4 py-3">
                                        <Link href={`/admin/blend-formulas/${formula.id}`} className="font-mono text-base font-bold text-om-ink hover:text-om-accent">{formula.version}</Link>
                                        <div className="mt-0.5 truncate font-mono text-[10px] text-om-faint" title={formula.code}>{formula.code}</div>
                                    </td>
                                    <td className="px-3 py-3">
                                        <div className="truncate font-semibold text-om-ink" title={formula.customer}>{formula.customer ?? '未指定客户'}</div>
                                        <div className="mt-0.5 truncate text-xs text-om-muted" title={formula.product}>{formula.product ?? '未指定产品'}</div>
                                    </td>
                                    <td className="px-3 py-3"><StatusPill status={formula.status} /></td>
                                    <td className="px-3 py-3">
                                        {formula.standard_sample_code ? (
                                            <>
                                                <div className="truncate font-mono text-xs font-bold text-om-ink" title={formula.standard_sample_code}>{formula.standard_sample_code}</div>
                                                <div className={`mt-0.5 text-[10px] ${formula.has_standard_sample ? 'text-om-running' : 'text-om-downtime'}`}>{formula.has_standard_sample ? '已批准' : '未批准'}</div>
                                            </>
                                        ) : <span className="text-xs text-om-faint">未指定</span>}
                                    </td>
                                    <td className="px-3 py-3"><ResultText result={formula.small_sample_result} /></td>
                                    <td className="px-3 py-3"><ResultText result={formula.large_sample_result} /></td>
                                    <td className="px-3 py-3">
                                        <div className="font-mono text-xs text-om-ink">{formula.component_count} 组分 · {formatNumber(formula.formula_total, 1)}%</div>
                                        {formula.out_of_tolerance_count > 0 ? (
                                            <div className="mt-0.5 text-[10px] font-bold text-om-blocked">{formula.out_of_tolerance_count} 行超差</div>
                                        ) : (
                                            <div className="mt-0.5 text-[10px] text-om-faint">{formula.formula_complete ? '合计 100%' : '合计未满 100%'}</div>
                                        )}
                                    </td>
                                    <td className="px-3 py-3">
                                        <div className="font-mono text-xs text-om-ink">{formula.effective_date ?? '—'}</div>
                                        <div className="mt-0.5 truncate text-[10px] text-om-faint" title={formula.approved_by}>{formula.approved_by ? `批准 ${formula.approved_by}` : '未批准'}</div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="space-y-3 lg:hidden">
                    {formulas.map((formula) => <FormulaCard key={formula.id} formula={formula} />)}
                </div>

                {formulas.length === 0 && (
                    <div className="rounded-om-sm border border-om-line bg-om-card px-4 py-12 text-center">
                        <p className="text-sm text-om-faint">这个筛选下没有拼配版本。</p>
                        <Link href={query(null)} className="btn-touch btn-secondary mt-4 inline-flex text-xs">看全部版本</Link>
                    </div>
                )}

                <p className="text-[11px] leading-5 text-om-faint">
                    冻结后比例只读；要改配方只能从现有版本复制新版本。小样/大样提交与批准在本页下一阶段接入。
                </p>
            </div>
        </>
    );
}

function FormulaCard({ formula }) {
    const resultFailed = formula.out_of_tolerance_count > 0;

    return (
        <div className="rounded-om-sm border border-om-line bg-om-card p-4">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <Link href={`/admin/blend-formulas/${formula.id}`} className="font-mono text-xl font-bold text-om-ink hover:text-om-accent">{formula.version}</Link>
                        <StatusPill status={formula.status} />
                    </div>
                    <div className="mt-1 truncate font-semibold text-om-ink">{formula.product ?? '未指定产品'}</div>
                    <div className="mt-0.5 truncate text-[11px] text-om-muted">{formula.customer ?? '未指定客户'} · <span className="font-mono">{formula.code}</span></div>
                </div>
                <Link href={`/admin/blend-formulas/${formula.id}`} className="shrink-0 text-xs font-bold text-om-accent hover:underline">打开 →</Link>
            </div>

            <div className="mt-3 grid grid-cols-2 gap-2">
                <MiniCell label="标准样" value={formula.standard_sample_code ?? '未指定'} note={formula.has_standard_sample ? '已批准' : '不能批准'} tone={formula.has_standard_sample ? 'text-om-ink' : 'text-om-downtime'} mono />
                <MiniCell label="配方" value={`${formula.component_count} 组分 · ${formatNumber(formula.formula_total, 1)}%`} note={resultFailed ? `${formula.out_of_tolerance_count} 行超差` : (formula.formula_complete ? '合计 100%' : '合计未满 100%')} tone={resultFailed ? 'text-om-blocked' : 'text-om-ink'} />
                <MiniCell label="小样" value={(RESULT[formula.small_sample_result] ?? RESULT.PENDING).label} note={formula.small_sample_result === 'PASS' ? '已过' : '看详情'} tone={(RESULT[formula.small_sample_result] ?? RESULT.PENDING).className} />
                <MiniCell label="大样" value={(RESULT[formula.large_sample_result] ?? RESULT.PENDING).label} note={formula.large_sample_result === 'PENDING' ? '待大样' : '看详情'} tone={(RESULT[formula.large_sample_result] ?? RESULT.PENDING).className} />
            </div>

            <div className="mt-3 flex items-center justify-between gap-2 border-t border-om-line2 pt-2.5 text-[11px] text-om-faint">
                <span>生效 {formula.effective_date ?? '—'}</span>
                <span className="truncate">{formula.approved_by ? `批准 ${formula.approved_by}` : '未批准'}</span>
            </div>
        </div>
    );
}

function StatusPill({ status }) {
    const tone = STATUS[status] ?? STATUS.DRAFT;
    return (
        <span className={`inline-flex items-center gap-1 rounded px-2 py-0.5 text-[11px] font-bold ${tone.className}`}>
            <span className="h-1.5 w-1.5 rounded-full bg-current opacity-70" />
            {tone.label}
        </span>
    );
}

function ResultText({ result }) {
    const tone = RESULT[result ?? 'null'] ?? RESULT.PENDING;
    return <span className={`text-xs font-bold ${tone.className}`}>{tone.label}</span>;
}

function MiniCell({ label, value, note, tone = 'text-om-ink', mono = false }) {
    return (
        <div className="rounded-om-sm bg-om-chip/35 px-3 py-2">
            <div className="text-[10px] font-bold tracking-wide text-om-faint">{label}</div>
            <div className={`mt-0.5 truncate text-xs font-semibold ${tone} ${mono ? 'font-mono' : ''}`} title={value}>{value}</div>
            {note && <div className="mt-0.5 truncate text-[10px] text-om-faint">{note}</div>}
        </div>
    );
}
