import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import { formatDateTime } from '../../../lib/i18n';

const DIMENSIONS = [
    ['appearance_score', '外形', '条索、匀整、净度'],
    ['color_score', '汤色', '深浅、明亮、清浊'],
    ['aroma_score', '香气', '纯度、高低、持久'],
    ['taste_score', '滋味', '鲜爽、厚薄、回甘'],
    ['leaf_score', '叶底', '嫩度、匀度、色泽'],
];

const EMPTY_SCORES = {
    appearance_score: null,
    color_score: null,
    aroma_score: null,
    taste_score: null,
    leaf_score: null,
};

export default function SensoryIndex() {
    const { state, progress = {}, sample = null, summary = [] } = usePage().props;

    return (
        <>
            <Head title="感官盲评" />
            <div className="mx-auto w-full max-w-[900px] px-4 pb-10 sm:px-6">
                <header className="flex flex-wrap items-start justify-between gap-3 pt-1">
                    <div className="min-w-0">
                        <p className="text-[11px] font-bold tracking-[0.08em] text-om-faint">感官盲评</p>
                        {state === 'completed' ? (
                            <h1 className="mt-1 text-2xl font-bold tracking-tight text-om-ink">
                                共 <span className="font-mono">{progress.total ?? 0}</span> 份，已全部评完
                            </h1>
                        ) : state === 'empty' ? (
                            <h1 className="mt-1 text-2xl font-bold tracking-tight text-om-ink">今日盲评</h1>
                        ) : (
                            <h1 className="mt-1 text-2xl font-bold tracking-tight text-om-ink">
                                第 <span className="font-mono">{progress.current ?? 0}</span> 份
                                <span className="ml-1 text-sm font-normal text-om-faint">/ 共 {progress.total ?? 0} 份</span>
                            </h1>
                        )}
                        <p className="mt-1 text-xs leading-5 text-om-muted">
                            {state === 'completed'
                                ? '全部盲样已锁定；现在才显示与标准样的对比。'
                                : '盲码、分数对你一个人可见；产品与客户在本页隐藏。'}
                        </p>
                    </div>
                    <Link href="/admin/blend-formulas" className="btn-touch btn-secondary text-xs">退出盲评</Link>
                </header>

                {state === 'empty' && <EmptyState />}
                {state === 'sample' && sample && <SampleForm sample={sample} progress={progress} />}
                {state === 'completed' && <CompletedSummary summary={summary} progress={progress} />}

                <p className="mt-6 text-[11px] leading-5 text-om-faint">
                    同一评茶师只看到自己的盲码和自己的分数；其他人的队列和评分不进入本页。
                </p>
            </div>
        </>
    );
}

function SampleForm({ sample, progress }) {
    const form = useForm({ ...EMPTY_SCORES, comment: '' });

    useEffect(() => {
        form.reset();
        form.clearErrors();
        window.scrollTo({ top: 0, behavior: 'instant' });
    }, [sample.id]);

    const allScored = DIMENSIONS.every(([key]) => form.data[key] !== null);
    const total = DIMENSIONS.reduce((sum, [key]) => sum + (form.data[key] ?? 0), 0);
    const lastSample = progress.current === progress.total;

    const submit = (event) => {
        event.preventDefault();
        form.post(`/admin/sensory/${sample.id}`, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="mt-4 space-y-4">
            <section className="overflow-hidden rounded-om-sm border border-om-line bg-om-card">
                <div className="border-b border-om-line2 px-4 py-5 text-center">
                    <div className="text-[11px] font-bold tracking-[0.12em] text-om-faint">盲码</div>
                    <div className="mt-1 break-all font-mono text-4xl font-bold tracking-tight text-om-ink sm:text-5xl">
                        {sample.blind_code}
                    </div>
                    <div className="mt-2 text-[11px] text-om-faint">先评样，不看产品、客户或批号</div>
                </div>

                {DIMENSIONS.map(([key, label, hint], index) => (
                    <div key={key} className="border-b border-om-line2 last:border-0">
                        <div className="flex items-end justify-between gap-3 px-4 pt-3.5">
                            <div className="flex items-baseline gap-2">
                                <span className="font-mono text-[10px] text-om-faint">{String(index + 1).padStart(2, '0')}</span>
                                <h2 className="text-base font-bold text-om-ink">{label}</h2>
                                <span className="hidden text-[11px] text-om-faint sm:inline">{hint}</span>
                            </div>
                            <div className="shrink-0 text-right">
                                <span className={`font-mono text-2xl font-bold ${form.data[key] === null ? 'text-om-faintest' : 'text-om-ink'}`}>
                                    {form.data[key] ?? '—'}
                                </span>
                                <span className="ml-0.5 text-[11px] text-om-faint">/ 10</span>
                            </div>
                        </div>

                        <div className="grid grid-cols-6 gap-1.5 px-4 pb-4 pt-2 sm:grid-cols-11">
                            {Array.from({ length: 11 }, (_, score) => {
                                const selected = form.data[key] === score;
                                return (
                                    <button
                                        key={score}
                                        type="button"
                                        aria-pressed={selected}
                                        aria-label={`${label} ${score} 分`}
                                        onClick={() => form.setData(key, score)}
                                        className={`h-11 rounded-om-sm border text-sm font-bold transition-colors ${
                                            selected
                                                ? 'border-om-accent bg-om-accent text-white'
                                                : 'border-om-line bg-om-card text-om-muted hover:bg-om-chip'
                                        }`}
                                    >
                                        {score}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                ))}

                <div className="px-4 py-4">
                    <label htmlFor="sensory-comment" className="text-xs font-bold text-om-ink">评语</label>
                    <textarea
                        id="sensory-comment"
                        value={form.data.comment}
                        onChange={(event) => form.setData('comment', event.target.value)}
                        rows={3}
                        placeholder="记下最明显的特征或缺陷；可留空。"
                        className="mt-1.5 w-full rounded-om-sm border border-om-line bg-om-bg px-3 py-2.5 text-sm leading-6 text-om-ink outline-none focus:border-om-accent"
                    />
                </div>
            </section>

            {Object.keys(form.errors).length > 0 && (
                <div className="rounded-om-sm border border-om-line bg-om-blocked-bg/45 px-3 py-2.5 text-xs leading-5 text-om-blocked">
                    {form.errors.appearance_score ?? form.errors.color_score ?? form.errors.aroma_score ?? form.errors.taste_score ?? form.errors.leaf_score ?? form.errors.scores ?? '提交没有通过，请检查五项分数。'}
                </div>
            )}

            <div className="sticky bottom-0 z-10 -mx-4 border-t border-om-line bg-om-page/95 px-4 py-3 backdrop-blur sm:static sm:mx-0 sm:rounded-om-sm sm:border sm:px-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div className="text-[11px] font-bold tracking-wide text-om-faint">总分</div>
                        <div className="mt-0.5 font-mono text-lg font-bold text-om-ink">
                            {allScored ? total : '—'}
                            <span className="ml-0.5 text-[11px] font-normal text-om-faint">/ 50</span>
                        </div>
                    </div>
                    <button
                        type="submit"
                        disabled={!allScored || form.processing}
                        className="btn-touch btn-primary min-h-11 min-w-[190px] flex-1 text-sm disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none"
                    >
                        {form.processing ? '锁定中…' : lastSample ? '提交并完成盲评' : '提交并下一份'}
                    </button>
                </div>
                <p className="mt-1 text-[11px] text-om-faint">
                    {allScored ? '提交后这一份锁定，不能回改。' : '五项都打分后才能提交。'}
                </p>
            </div>
        </form>
    );
}

function CompletedSummary({ summary, progress }) {
    return (
        <div className="mt-4 space-y-4">
            <div className="rounded-om-sm border border-om-line bg-om-running-bg/35 px-4 py-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-sm font-bold text-om-ink">盲评完成</h2>
                    <span className="font-mono text-xs text-om-running">{progress.submitted} / {progress.total} 已锁定</span>
                </div>
                <p className="mt-1 text-xs leading-5 text-om-muted">全部提交后才解锁标准样对照；下面只显示你的评分。</p>
            </div>

            <section className="hidden overflow-hidden rounded-om-sm border border-om-line bg-om-card lg:block">
                <table className="w-full table-fixed text-xs">
                    <thead>
                        <tr className="border-b border-om-line2 bg-om-chip/40 text-left text-[10px] font-bold tracking-wide text-om-muted">
                            <th className="w-[16%] px-4 py-2.5">盲码</th>
                            <th className="w-[8%] px-2 py-2.5 text-right">外形</th>
                            <th className="w-[8%] px-2 py-2.5 text-right">汤色</th>
                            <th className="w-[8%] px-2 py-2.5 text-right">香气</th>
                            <th className="w-[8%] px-2 py-2.5 text-right">滋味</th>
                            <th className="w-[8%] px-2 py-2.5 text-right">叶底</th>
                            <th className="w-[10%] px-2 py-2.5 text-right">总分</th>
                            <th className="w-[14%] px-2 py-2.5 text-right">标准样</th>
                            <th className="w-[10%] px-3 py-2.5 text-right">偏差</th>
                        </tr>
                    </thead>
                    <tbody>
                        {summary.map((row) => (
                            <tr key={row.id} className="border-b border-om-line2 last:border-0">
                                <td className="px-4 py-2.5 font-mono font-bold text-om-ink">{row.blind_code}</td>
                                {DIMENSIONS.map(([key]) => <td key={key} className="px-2 py-2.5 text-right font-mono text-om-ink">{row.scores[key]}</td>)}
                                <td className="px-2 py-2.5 text-right font-mono font-bold text-om-ink">{row.total ?? '—'}<span className="text-[10px] font-normal text-om-faint">/50</span></td>
                                <td className="px-2 py-2.5 text-right font-mono text-om-muted">{row.standard_total == null ? '无' : row.standard_total}</td>
                                <td className={`px-3 py-2.5 text-right font-mono font-bold ${deltaTone(row.delta)}`}>{row.delta == null ? '—' : signed(row.delta)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>

            <div className="space-y-3 lg:hidden">
                {summary.map((row) => <SummaryCard key={row.id} row={row} />)}
            </div>

            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs text-om-muted">没有排行榜，也不显示其他评茶师的分数。</p>
                <Link href="/admin/blend-formulas" className="btn-touch btn-secondary text-xs">返回拼配版本</Link>
            </div>
        </div>
    );
}

function SummaryCard({ row }) {
    return (
        <div className="rounded-om-sm border border-om-line bg-om-card p-4">
            <div className="flex items-start justify-between gap-2">
                <div className="font-mono text-lg font-bold text-om-ink">{row.blind_code}</div>
                <div className="text-right">
                    <div className="font-mono text-lg font-bold text-om-ink">{row.total ?? '—'}<span className="text-[10px] font-normal text-om-faint">/50</span></div>
                    <div className="mt-0.5 max-w-[280px] text-[10px] leading-4 text-om-faint">
                        {row.delta == null ? '无标准样' : (
                            <>
                                <span className="font-mono">{row.standard_sample_code}</span>
                                <span className="mx-1.5 text-om-faintest">·</span>
                                <span className={`font-bold ${deltaTone(row.delta)}`}>偏差 {signed(row.delta)}</span>
                            </>
                        )}
                    </div>
                </div>
            </div>
            <div className="mt-3 grid grid-cols-5 gap-1.5">
                {DIMENSIONS.map(([key, label]) => (
                    <div key={key} className="rounded-om-sm bg-om-chip/35 px-1.5 py-2 text-center">
                        <div className="text-[9px] font-bold text-om-faint">{label}</div>
                        <div className="mt-0.5 font-mono text-sm font-bold text-om-ink">{row.scores[key]}</div>
                    </div>
                ))}
            </div>
            {row.standard_scores && (
                <div className="mt-1 grid grid-cols-5 gap-1.5 text-center text-[10px] text-om-faint">
                    {DIMENSIONS.map(([key]) => (
                        <div key={key}>标准 <span className="font-mono text-om-muted">{row.standard_scores[key]}</span></div>
                    ))}
                </div>
            )}
            {row.comment && <p className="mt-3 whitespace-pre-line text-xs leading-5 text-om-muted">{row.comment}</p>}
            {row.submitted_at && <div className="mt-2 font-mono text-[10px] text-om-faint">锁定 {formatDateTime(row.submitted_at)}</div>}
        </div>
    );
}

function EmptyState() {
    return (
        <div className="mt-4 rounded-om-sm border border-om-line bg-om-card px-4 py-14 text-center">
            <p className="text-base font-bold text-om-ink">今天没有待评样品</p>
            <p className="mt-1 text-xs leading-5 text-om-muted">先回到拼配版本确认小样；有盲样时再进来评。</p>
            <Link href="/admin/blend-formulas" className="btn-touch btn-secondary mt-4 inline-flex text-xs">返回拼配版本</Link>
        </div>
    );
}

function signed(value) {
    if (value == null) return '—';
    return `${value > 0 ? '+' : ''}${value}`;
}

function deltaTone(delta) {
    if (delta == null) return 'text-om-faint';
    if (delta > 0) return 'text-om-running';
    if (delta < 0) return 'text-om-blocked';
    return 'text-om-muted';
}
