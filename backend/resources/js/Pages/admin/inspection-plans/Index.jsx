import { Head, router, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import useConfirm from '../../../components/useConfirm';
import { formatDateTime } from '../../../lib/i18n';

const CRITERIA_EXAMPLE = '水分|测量|%|5|8|必填\n感官-香气|感官|—|||必填\n无异物|合格|—|||必填';

const INSPECTION_LABELS = {
    pending: '待检验', pass: '合格', fail: '不合格', conditional_pass: '让步接收',
};
const DISPOSITION_LABELS = {
    pending: '待处置', accept: '接收', accept_with_deviation: '让步接收', rework: '返工',
    quarantine: '隔离', scrap: '报废', return_to_supplier: '退货', reject: '拒收',
};
const LOT_LABELS = {
    received: '已收货', quarantine: '隔离', released: '已放行',
    consumed: '已耗用', expired: '过期', rejected: '拒收',
};


function criteriaInitial(record) {
    return {
        name: record?.name ?? '',
        description: record?.description ?? '',
        material_id: record?.material_name ? String(record.material_id ?? '') : '',
        material_type_id: record?.material_type_name ? String(record.material_type_id ?? '') : '',
        criteria_text: record?.criteria_text ?? CRITERIA_EXAMPLE,
    };
}

export default function InspectionPlansIndex() {
    const { rows = [], materials = [], materialTypes = [], arrivals = [], pendingArrivals = 0, basePath, filters = {} } = usePage().props;
    const drawer = useResourceDrawer();
    const { confirm, dialog } = useConfirm();

    const stats = useMemo(() => ({
        total: rows.length,
        active: rows.filter((row) => row.is_active).length,
        drafts: rows.filter((row) => row.is_draft).length,
        covered: new Set(rows.filter((row) => row.is_active).map((row) => row.scope)).size,
        arrivals: pendingArrivals,
    }), [rows]);

    const fields = useMemo(() => [
        { name: 'name', label: '计划名称', required: true, placeholder: '例如：贵州绿茶毛茶来料检验' },
        { name: 'description', label: '说明', type: 'textarea', placeholder: '检验依据、抽样要求、异常处理' },
        {
            name: 'material_id',
            label: '限定到具体原料',
            type: 'select',
            options: [{ value: '', label: '不限原料（按类别或通用）' }, ...materials],
            help: '与“限定到原料类别”二选一；具体原料优先匹配。',
        },
        {
            name: 'material_type_id',
            label: '限定到原料类别',
            type: 'select',
            options: [{ value: '', label: '不限类别' }, ...materialTypes],
            help: '例如“茶叶原料/包装材料”；具体原料为空时才按类别匹配。',
        },
        {
            name: 'criteria_text',
            label: '检验项目',
            type: 'textarea',
            required: true,
            help: '每行一条：项目|类型|单位|下限|上限|必填。类型可用 感官 / 测量 / 功能 / 合格。',
            placeholder: CRITERIA_EXAMPLE,
        },
    ], [materials, materialTypes]);

    const publish = (row) => confirm(
        {
            title: `发布“${row.name}” V${row.version}？`,
            body: '发布后该版本立即生效，旧版本自动停用；已产生的检验记录保持不变。',
            confirmLabel: '发布',
            destructive: false,
        },
        () => router.post(`${basePath}/${row.id}/publish`, {}, { preserveScroll: true }),
    );

    const remove = (row) => confirm(
        {
            title: `删除“${row.name}” V${row.version}？`,
            body: row.inspections_count > 0
                ? '该版本已有检验记录，系统会拒绝删除；请改为派生新版本。'
                : '删除后不可恢复。',
            confirmLabel: '删除',
        },
        () => router.delete(`${basePath}/${row.id}`, { preserveScroll: true }),
    );

    const search = (value) => router.get(basePath, { q: value }, { preserveState: true, replace: true });

    const dispatchArrivals = () => confirm(
        {
            title: '同步已入库到货批次？',
            body: '系统会为最近 30 天内已过账的原料到货生成待检任务；没有适用计划的原料不会派工，同一条到货不会重复生成。',
            confirmLabel: '同步并派工',
            destructive: false,
        },
        () => router.post(`${basePath}/dispatch`, {}, { preserveScroll: true }),
    );

    return (
        <>
            <Head title="来料检验计划" />
            {dialog}
            <div className="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-5">
                {[
                    { label: '计划版本', value: stats.total, hint: '含草稿与历史版本' },
                    { label: '当前生效', value: stats.active, hint: '来料检验可直接选用' },
                    { label: '待发布草稿', value: stats.drafts, hint: '编辑后需发布才生效' },
                    { label: '覆盖范围', value: stats.covered, hint: '生效计划覆盖的原料/类别' },
                    { label: '待派工到货', value: stats.arrivals, hint: '已入库、有计划、尚未建检验' },
                ].map((card) => (
                    <div key={card.label} className="rounded-om-md border border-om-line bg-om-bg px-4 py-3">
                        <div className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">{card.label}</div>
                        <div className="mt-1 text-[24px] font-semibold tracking-[-0.02em] text-om-ink">{card.value}</div>
                        <div className="mt-1 text-xs text-om-muted">{card.hint}</div>
                    </div>
                ))}
            </div>

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-om-ink">来料检验计划</h1>
                    <p className="mt-1 text-sm text-om-muted">原料入厂时按计划抽样、记录、判定；发布后的版本不可改，只能派生新草稿。</p>
                </div>
                <div className="flex gap-2">
                    <input
                        defaultValue={filters.q ?? ''}
                        onKeyDown={(event) => { if (event.key === 'Enter') search(event.currentTarget.value); }}
                        placeholder="搜索计划名称…"
                        className="w-48 rounded border border-om-line bg-om-card px-3 py-2 text-sm text-om-ink outline-none focus:border-om-accent"
                    />
                    <button type="button" onClick={drawer.create} className="btn-touch btn-primary text-sm">新建计划</button>
                </div>
            </div>

            <div className="mb-5 rounded-om-md border border-om-line bg-om-card">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-om-line px-4 py-3">
                    <div>
                        <h2 className="text-sm font-bold text-om-ink">到货批次待检</h2>
                        <p className="mt-1 text-xs text-om-muted">取最近 30 天已过账的原料入库单；有已发布计划的行可一键派工，生成操作工端待检任务。</p>
                    </div>
                    <div className="flex items-center gap-3">
                        {pendingArrivals > 0 && <span className="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-800">{pendingArrivals} 条待派工</span>}
                        <button type="button" onClick={dispatchArrivals} className="btn-touch btn-secondary text-xs">同步到货批次</button>
                    </div>
                </div>
                {arrivals.length > 0 ? (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[980px] text-sm">
                            <thead>
                                <tr className="border-b border-om-line bg-om-bg text-left text-xs text-om-muted">
                                    <th className="whitespace-nowrap px-4 py-2.5 font-semibold">到货单</th>
                                    <th className="whitespace-nowrap px-3 py-2.5 font-semibold">原料</th>
                                    <th className="whitespace-nowrap px-3 py-2.5 font-semibold">批号</th>
                                    <th className="whitespace-nowrap px-3 py-2.5 font-semibold">数量</th>
                                    <th className="whitespace-nowrap px-3 py-2.5 font-semibold">适用计划</th>
                                    <th className="whitespace-nowrap px-4 py-2.5 font-semibold">检验 / 处置</th>
                                    <th className="whitespace-nowrap px-4 py-2.5 font-semibold">物料批</th>
                                </tr>
                            </thead>
                            <tbody>
                                {arrivals.map((arrival) => (
                                    <tr key={arrival.id} className="border-b border-om-line2/60">
                                        <td className="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-om-ink">{arrival.document_no ?? '—'}</td>
                                        <td className="px-3 py-2.5">
                                            <div className="font-semibold text-om-ink">{arrival.material_name ?? '—'}</div>
                                            {arrival.material_code && <div className="font-mono text-[10px] text-om-faint">{arrival.material_code}</div>}
                                        </td>
                                        <td className="whitespace-nowrap px-3 py-2.5 font-mono text-xs text-om-muted">{arrival.lot_number ?? '—'}</td>
                                        <td className="whitespace-nowrap px-3 py-2.5 text-xs text-om-ink">{arrival.quantity} {arrival.unit ?? ''}</td>
                                        <td className="px-3 py-2.5 text-xs text-om-muted">{arrival.plan_label ?? <span className="text-om-blocked">无适用计划</span>}</td>
                                        <td className="whitespace-nowrap px-4 py-2.5 text-xs">
                                            {arrival.inspection_status ? (
                                                <div>
                                                    <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${
                                                        arrival.inspection_status === 'pass' ? 'bg-emerald-100 text-emerald-800'
                                                            : arrival.inspection_status === 'fail' ? 'bg-red-100 text-red-800'
                                                                : 'bg-amber-100 text-amber-800'
                                                    }`}>{INSPECTION_LABELS[arrival.inspection_status] ?? arrival.inspection_status}</span>
                                                    {arrival.disposition && (
                                                        <div className="mt-1 text-[10px] text-om-muted">处置：{DISPOSITION_LABELS[arrival.disposition] ?? arrival.disposition}</div>
                                                    )}
                                                </div>
                                            ) : arrival.can_dispatch ? (
                                                <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">待派工</span>
                                            ) : (
                                                <span className="rounded-full bg-om-chip px-2 py-0.5 text-[11px] font-semibold text-om-muted">跳过</span>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-2.5 text-xs">
                                            {arrival.lot_status ? (
                                                <div>
                                                    <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${
                                                        arrival.lot_status === 'released' ? 'bg-emerald-100 text-emerald-800'
                                                            : arrival.lot_status === 'quarantine' ? 'bg-amber-100 text-amber-800'
                                                                : arrival.lot_status === 'rejected' ? 'bg-red-100 text-red-800'
                                                                    : 'bg-om-chip text-om-muted'
                                                    }`}>{LOT_LABELS[arrival.lot_status] ?? arrival.lot_status}</span>
                                                    <div className="mt-1 font-mono text-[10px] text-om-faint">{arrival.lot_number_actual}</div>
                                                </div>
                                            ) : (
                                                <span className="text-om-faint">未建物料批</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="px-4 py-6 text-sm text-om-muted">最近 30 天没有已过账的原料到货；入库单过账后这里会出现待检批次。</div>
                )}
            </div>

            <div className="overflow-hidden rounded-om-md border border-om-line bg-om-card">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[860px] text-sm">
                        <thead>
                            <tr className="border-b border-om-line bg-om-bg text-left text-xs text-om-muted">
                                <th className="px-4 py-3 font-semibold">计划名称</th>
                                <th className="px-3 py-3 font-semibold">适用范围</th>
                                <th className="whitespace-nowrap px-3 py-3 font-semibold">版本</th>
                                <th className="whitespace-nowrap px-3 py-3 font-semibold">项目</th>
                                <th className="whitespace-nowrap px-3 py-3 font-semibold">状态</th>
                                <th className="whitespace-nowrap px-3 py-3 font-semibold">已检验</th>
                                <th className="whitespace-nowrap px-3 py-3 font-semibold">发布时间</th>
                                <th className="px-4 py-3 text-right font-semibold">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.id} className="border-b border-om-line2/60 align-top">
                                    <td className="px-4 py-3">
                                        <div className="font-semibold text-om-ink">{row.name}</div>
                                        {row.description && <div className="mt-1 line-clamp-2 text-xs text-om-muted">{row.description}</div>}
                                    </td>
                                    <td className="px-3 py-3 text-xs text-om-muted">{row.scope}</td>
                                    <td className="px-3 py-3 font-mono text-xs text-om-ink">V{row.version}</td>
                                    <td className="px-3 py-3 text-xs text-om-ink">{row.criteria_count} 项</td>
                                    <td className="px-3 py-3">
                                        {row.is_active ? (
                                            <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">生效</span>
                                        ) : row.is_draft ? (
                                            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">草稿</span>
                                        ) : (
                                            <span className="rounded-full bg-om-chip px-2 py-0.5 text-[11px] font-semibold text-om-muted">历史版本</span>
                                        )}
                                    </td>
                                    <td className="px-3 py-3 text-xs text-om-muted">{row.inspections_count} 次</td>
                                    <td className="px-3 py-3 text-xs text-om-muted">{row.published_at ? formatDateTime(row.published_at) : '—'}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-3 text-xs">
                                            <button type="button" onClick={() => drawer.edit(row)} className="font-bold text-om-accent hover:underline">编辑</button>
                                            {row.is_draft && <button type="button" onClick={() => publish(row)} className="font-bold text-om-accent hover:underline">发布</button>}
                                            <button type="button" onClick={() => remove(row)} className="font-bold text-om-blocked hover:underline">删除</button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <tr><td colSpan={8} className="px-4 py-10 text-center text-sm text-om-muted">还没有来料检验计划，新建一条草稿后发布。</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <ResourceFormDrawer
                {...drawer.props}
                action={basePath}
                fields={fields}
                initial={criteriaInitial}
                title={{ create: '新建来料检验计划', edit: '编辑来料检验计划' }}
                subtitle="草稿可反复编辑；已发布版本编辑会派生新草稿，发布后旧版本自动停用。"
                submitLabel={{ create: '保存草稿', edit: '保存' }}
                width={640}
            />
        </>
    );
}

InspectionPlansIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
